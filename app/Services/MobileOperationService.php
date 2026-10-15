<?php

namespace App\Services;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductImageProcessingStatus;
use App\Enums\ProductModerationStatus;
use App\Models\CustomerAccountEntry;
use App\Models\Customer;
use App\Models\InventoryLot;
use App\Models\Invoice;
use App\Models\MobileOperation;
use App\Models\PosSaleUpload;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MobileOperationService
{
    public function apply(Shop $shop, User $user, array $data, string $source = 'android'): array
    {
        abort_unless($user->canSellAtShop($shop), 404);
        if (str_starts_with($data['type'], 'decant_') || ($data['type'] === 'open_bottle' && ($data['track_decants'] ?? false))) {
            abort_unless(in_array('decants', app(SellerMenuService::class)->visibleForUser($shop, $user), true), 403);
        }
        if ($data['type'] === 'customer_upsert') {
            abort_unless(in_array('customers', app(SellerMenuService::class)->visibleForUser($shop, $user), true), 403);
        } elseif (in_array($data['type'], array_merge(FinancialMobileOperationService::TYPES, OnlineMobileOperationService::TYPES), true)) {
            // The financial controllers enforce their own menu/plan/session boundaries.
        } elseif (in_array($data['type'], ['product_upsert', 'product_archive'], true)) {
            $product = $shop->products()->withTrashed()->where('public_id', $data['product_id'])->first();
            app(CatalogAccessService::class)->assertWrite($shop, $user, $product, $data);
        } elseif ($data['type'] === 'product_photo_update') {
            $product = $shop->products()->where('public_id', $data['product_id'])->firstOrFail();
            abort_unless(app(CatalogAccessService::class)->canManagePhotos($shop, $user, $product), 403);
        } elseif (in_array($data['type'], ['category_upsert', 'unit_upsert'], true)) {
            app(CatalogTaxonomyService::class)->authorize($shop, $user, $data);
        } elseif ($data['type'] !== 'return') {
            Gate::forUser($user)->authorize('update', $shop);
            if (in_array($data['type'], ['restock', 'adjustment', 'open_bottle'], true)) {
                $key = match ($data['type']) {
                    'restock' => 'inventory', 'adjustment' => 'inventory_adjustments', 'open_bottle' => 'decants',
                };
                abort_unless(in_array($key, app(SellerMenuService::class)->visibleForUser($shop, $user), true), 403);
            }
        } else {
            abort_unless(in_array('returns', app(SellerMenuService::class)->visibleForUser($shop, $user), true), 403);
        }
        // A previous successful receipt does not bypass current permission checks.
        // Perform these before looking up the UUID, including administrative envelopes.
        if (in_array($data['type'], FinancialMobileOperationService::TYPES, true)) {
            app(FinancialMobileOperationService::class)->authorize($shop, $user, $data);
        } elseif (in_array($data['type'], OnlineMobileOperationService::TYPES, true)) {
            app(OnlineMobileOperationService::class)->authorize($shop, $user, $data);
        } elseif (in_array($data['type'], ShopAdministrationMobileOperationService::TYPES, true)) {
            app(ShopAdministrationMobileOperationService::class)->authorize($shop, $user, $data);
        } elseif (in_array($data['type'], array_diff(array_merge(ShopRecordSyncService::TYPES, ShopRouteSyncService::TYPES), ['category_upsert', 'unit_upsert']), true)) {
            $menu = in_array($data['type'], ShopRouteSyncService::TYPES, true) ? 'routes'
                : ($data['type'] === 'adjustment_reason_upsert' ? 'inventory_adjustments' : 'products');
            abort_unless(in_array($menu, app(SellerMenuService::class)->visibleForUser($shop, $user), true), 403);
        }
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($shop, $user, $data, $hash, $source) {
            // Serializes deduplication, quota checks and document writes in one tenant.
            $shop = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $old = MobileOperation::where('shop_id', $shop->id)->where('client_operation_uuid', $data['client_operation_uuid'])->first();
            if ($old) {
                if (! hash_equals($old->payload_sha256, $hash)) {
                    throw new InvalidArgumentException('El identificador ya fue utilizado con otro contenido.', 409);
                }
                if (in_array($data['type'], FinancialMobileOperationService::TYPES, true)) {
                    app(FinancialMobileOperationService::class)->authorizeReceipt($shop, $user, $data, $old->result);
                }

                return $old->result;
            }
            $operation = MobileOperation::create(['shop_id' => $shop->id, 'client_operation_uuid' => $data['client_operation_uuid'],
                'type' => $data['type'], 'payload_sha256' => $hash, 'result' => []]);
            $result = match ($data['type']) {
                'product_photo_update' => $this->photoOperation($shop, $data),
                'quote_create', 'quote_convert', 'order_confirm', 'purchase_create', 'purchase_receive', 'supplier_create', 'inventory_import_confirm', 'day_close', 'partner_create', 'partner_transaction' => app(OnlineMobileOperationService::class)->apply($shop, $user, $data),
                'shop_settings_update', 'seller_menu_permissions_update', 'shop_menu_visibility_update', 'seller_create', 'authorization_approve', 'authorization_reject' => app(ShopAdministrationMobileOperationService::class)->apply($shop, $user, $data),
                'cash_open', 'cash_close', 'cash_movement', 'expense_create', 'expense_payment' => app(FinancialMobileOperationService::class)->apply($shop, $user, $data),
                'route_upsert', 'route_assign', 'route_unassign', 'route_reorder', 'route_load_create', 'route_load_status' => app(ShopRouteSyncService::class)->apply($shop, $data),
                'category_upsert', 'unit_upsert', 'adjustment_reason_upsert' => app(ShopRecordSyncService::class)->apply($shop, $data, $user, $source),
                'return' => $this->refund($shop, $user, $data, $operation),
                'customer_upsert' => $this->customerOperation($shop, $data),
                'decant_setup', 'decant_vial_receive', 'decant_vial_update', 'decant_prepare', 'decant_presentation_update', 'decant_discard', 'decant_undo_open', 'decant_sell_remainder', 'decant_reconcile_opening' => app(DecantInventoryService::class)->command($shop, $data, $user->id),
                default => $this->productOperation($shop, $user, $data),
            };
            $result['client_operation_uuid'] = $data['client_operation_uuid'];
            $operation->update(['result' => $result]);

            return $result;
        });
    }

    private function customerOperation(Shop $shop, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
        }
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'El nombre del cliente es obligatorio.']);
        }

        $customer = ! empty($data['customer_id'])
            ? $shop->customers()->withTrashed()->where('public_id', $data['customer_id'])->lockForUpdate()->firstOrFail()
            : $shop->customers()->withTrashed()->where('client_customer_uuid', $data['client_customer_uuid'])->lockForUpdate()->first();
        // Older mobile clients used is_active=false as their delete command.
        // The explicit flag separates deactivation from deletion for current clients.
        $deleted = (bool) ($data['is_deleted'] ?? ! ($data['is_active'] ?? true));
        if ($deleted && $customer && Money::toCents($customer->balance) > 0) {
            throw ValidationException::withMessages(['customer_id' => 'No se puede eliminar un cliente con saldo pendiente.']);
        }
        $identity = null;
        if (! empty($data['document_type']) && ! empty($data['document_number'])) {
            $identity = $shop->customers()->withTrashed()
                ->where('document_type', $data['document_type'])
                ->where('document_number', $data['document_number'])
                ->when($customer, fn ($query) => $query->where('id', '!=', $customer->id))
                ->exists();
        }
        if ($identity) {
            throw ValidationException::withMessages(['document_number' => 'Ya existe un cliente con ese documento.']);
        }

        $values = [
            'name' => $name,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'document_type' => $data['document_type'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'credit_limit' => $data['credit_limit'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
        if ($customer) {
            $customer->fill($values);
            if (! $deleted) {
                $customer->restore();
                $customer->save();
            } else {
                $customer->is_active = false;
                $customer->save();
                $customer->delete();
            }
        } else {
            $customer = $shop->customers()->create(array_merge($values, [
                'client_customer_uuid' => $data['client_customer_uuid'],
            ]));
        }

        return [
            'customer_id' => $customer->public_id,
            'client_customer_uuid' => $customer->client_customer_uuid,
            'is_active' => $customer->is_active,
            'balance' => $customer->balance,
        ];
    }

    private function productOperation(Shop $shop, User $user, array $data): array
    {
        $product = $shop->products()->withTrashed()->where('public_id', $data['product_id'])->lockForUpdate()->first();
        if (in_array($data['type'], ['product_upsert', 'product_archive'], true)) {
            app(CatalogAccessService::class)->assertWrite($shop, $user, $product, $data);
        }
        if ($data['type'] === 'product_upsert') {
            $hasPresentationInput = array_key_exists('sale_unit', $data)
                || array_key_exists('volume_ml', $data)
                || array_key_exists('inventory_source_product_id', $data);
            $isCombo = (bool) ($data['is_combo'] ?? (array_key_exists('combo_items', $data) ? true : ($product?->isCombo() ?? false)));
            if ($isCombo) {
                abort_if(($data['sale_unit'] ?? $product?->sale_unit ?? 'unit') !== 'unit', 422, 'Un combo se vende como una unidad agrupada.');
            }
            $presentation = ! $product || $hasPresentationInput
                ? $this->mobilePresentation($shop, $data, $product)
                : [
                    'sale_unit' => $product->sale_unit ?: 'unit',
                    'volume_ml' => $product->volume_ml,
                    'inventory_source_product_id' => $product->inventory_source_product_id,
                ];
            if ($product && ($presentation['sale_unit'] === 'service') !== $product->isService()
                && ((int) ($product->inventory?->stock_quantity ?? 0) > 0
                    || InventoryLot::where('product_id', $product->id)->exists()
                    || \App\Models\InvoiceItem::where('product_id', $product->id)->exists())) {
                throw ValidationException::withMessages(['sale_unit' => 'Crea una oferta nueva para cambiar entre producto y servicio cuando existen stock, lotes o ventas.']);
            }
            if (! $product) {
                abort_if(Product::withTrashed()->where('public_id', $data['product_id'])->exists(), 409);
                app(PlanLimitsService::class)->assertCanAddProducts($shop, 1);
                $name = $data['name'] ?? throw ValidationException::withMessages(['name' => 'Indica el nombre.']);
                $price = $data['price'] ?? throw ValidationException::withMessages(['price' => 'Indica el precio.']);
                $isService = $presentation['sale_unit'] === 'service';
                $published = (bool) ($data['published'] ?? true);
                $product = $shop->products()->make(['name' => $name, 'price' => $price,
                    'slug' => (Str::slug($name) ?: 'producto').'-'.strtolower($data['product_id']),
                    'currency' => 'DOP', 'sale_unit' => $presentation['sale_unit'], 'is_combo' => $isCombo, 'volume_ml' => $presentation['volume_ml'],
                    'inventory_source_product_id' => $presentation['inventory_source_product_id'],
                    'moderation_status' => $published ? ProductModerationStatus::Active : ProductModerationStatus::Draft,
                    'availability_status' => $isService ? ProductAvailabilityStatus::Available : ProductAvailabilityStatus::OutOfStock,
                    'published_at' => $published ? now() : null]);
                $product->forceFill(['public_id' => $data['product_id']])->save();
                $initialStock = 0;
                $initialAvailableMl = null;
                if ($presentation['sale_unit'] === 'decant') {
                    $source = $shop->products()->with('inventory')->find($presentation['inventory_source_product_id']);
                    $initialAvailableMl = $source?->inventory?->available_ml
                        ?? (($source?->inventory?->stock_quantity ?? 0) * ($source?->volume_ml ?? 0));
                    $initialStock = $presentation['volume_ml'] > 0
                        ? intdiv((int) $initialAvailableMl, (int) $presentation['volume_ml'])
                        : 0;
                    $product->forceFill([
                        'availability_status' => $initialStock > 0
                            ? ProductAvailabilityStatus::Available
                            : ProductAvailabilityStatus::OutOfStock,
                    ])->save();
                }
                $product->inventory()->create(['track_inventory' => ! $isService && ! $isCombo, 'stock_quantity' => $isService || $isCombo ? 0 : $initialStock, 'available_ml' => null,
                    'cost_price' => $isCombo ? null : ($data['cost_price'] ?? null), 'sold_quantity' => 0, 'low_stock_threshold' => $data['minimum_stock'] ?? 3]);
                if (! $isService && ! $isCombo) {
                    app(InventoryService::class)->synchronizeDecantStock($product);
                }
            } else {
                abort_if($product->trashed(), 409, 'El producto está en la papelera.');
                $currentPrice = $product->isOnSale()
                    ? ($product->getRawOriginal('sale_price') ?? $product->sale_price)
                    : ($product->getRawOriginal('price') ?? $product->price);
                if (isset($data['expected_price'])
                    && Money::toCents($currentPrice) !== Money::toCents($data['expected_price'])) {
                    throw new InvalidArgumentException('El precio remoto cambió; revisa el conflicto antes de editar.', 409);
                }
            if ($hasPresentationInput) {
                    if (app(ShopRouteSyncService::class)->reserved($product) > 0 &&
                        ($presentation['sale_unit'] !== ($product->sale_unit ?: 'unit') || $isCombo !== $product->isCombo())) {
                        throw new InvalidArgumentException('Cierra las cargas de ruta antes de cambiar la presentación del producto.', 409);
                    }
                    $hadLots = InventoryLot::where('product_id', $product->id)->exists()
                        || (app(DecantInventoryService::class)->available() && DB::table('decant_batches')->where('product_id', $product->id)->exists());
                    $presentationChanged = $presentation['sale_unit'] !== ($product->sale_unit ?: 'unit')
                        || $presentation['volume_ml'] !== $product->volume_ml
                        || $presentation['inventory_source_product_id'] !== $product->inventory_source_product_id;
                    if ($hadLots && $presentationChanged && ! $this->canConfigureUnitAsBottle($product, $presentation)) {
                        throw new InvalidArgumentException('No cambies la presentación de un producto con lotes; crea una presentación vinculada.', 422);
                    }
                    $product->fill([
                        'sale_unit' => $presentation['sale_unit'],
                        'volume_ml' => $presentation['volume_ml'],
                        'inventory_source_product_id' => $presentation['inventory_source_product_id'],
                    ]);
                }
                if (array_key_exists('is_combo', $data)) {
                    $product->is_combo = $isCombo;
                    if ($isCombo) {
                        $product->sale_unit = 'unit';
                    }
                }
            }
            // AUTO-* is reserved for codes generated by the mobile client when
            // the user leaves Código interno empty. Older clients derived that
            // value from the product name, so two products with the same name
            // could get the same code and remain blocked in the mobile outbox.
            // Re-key only this generated namespace; explicit user SKUs remain
            // strict conflicts and are still rejected.
            $requestedCode = trim((string) ($data['internal_code'] ?? $product?->product_code ?? ''));
            if ($this->isGeneratedMobileCode($requestedCode) && $this->productCodeExists($shop, $requestedCode, $product)) {
                $data['internal_code'] = $this->nextGeneratedProductCode($shop);
            }

            app(ProductIdentityService::class)->assertUnique($shop, [
                'product_code' => $data['internal_code'] ?? $product?->product_code,
                'barcode' => $data['barcode'] ?? $product?->barcode,
            ], $product);
            foreach (['name' => 'name', 'internal_code' => 'product_code', 'barcode' => 'barcode', 'description' => 'description'] as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $product->$column = $data[$input];
                }
            }
            if (array_key_exists('price', $data)) {
                $product->{$product->isOnSale() ? 'sale_price' : 'price'} = $data['price'];
            }
            if (array_key_exists('category_uuid', $data)) {
                $category = $data['category_uuid'] ? app(ShopRecordSyncService::class)->category($shop, $data['category_uuid']) : null;
                if ($data['category_uuid'] && ! $category) {
                    throw ValidationException::withMessages(['category_uuid' => 'La categoría no pertenece a la tienda.']);
                }
                if ($category) app(CatalogTaxonomyService::class)->assertCategoryAssignment($shop, $user, $category, $product->sale_unit, $product->shop_category_id);
                $product->shop_category_id = $category?->id;
            } elseif (! empty($data['category_name'])) {
                $category = app(CatalogTaxonomyService::class)->categoryByName($shop, $user, $data['category_name'], $product->sale_unit);
                $product->shop_category_id = $category->id;
            }
            if (isset($data['unit_uuid'])) {
                app(ShopRecordSyncService::class)->saveProductUnit($shop, $product->public_id, $data['unit_uuid'], $user);
            }
            $product->save();
            if (array_key_exists('combo_items', $data) || ($product->isCombo() && ! $product->comboItems()->exists())) {
                $this->syncMobileComboItems($shop, $product, $data['combo_items'] ?? []);
            }
            app(InventoryService::class)->synchronizeDecantStock($product);
            if (isset($data['minimum_stock'])) {
                $product->inventory?->update(['low_stock_threshold' => $data['minimum_stock']]);
            }
            if (array_key_exists('published', $data)) {
                $published = (bool) $data['published'];
                $product->forceFill([
                    'moderation_status' => $published ? ProductModerationStatus::Active : ProductModerationStatus::Draft,
                    'published_at' => $published ? ($product->published_at ?: now()) : null,
                ])->save();
            }
            if (isset($data['image_base64'])) {
                $this->productImage($shop, $product, $data);
            }
        } else {
            abort_unless($product && ! $product->trashed(), 404);
            if ($data['type'] === 'product_archive') {
                $product->delete(); // Always reversible; ledger and images survive.
            } elseif ($data['type'] === 'restock') {
                app(InventoryService::class)->recordRestock($product, $data['quantity'], $data['notes'] ?? null, $user->id,
                    $data['unit_cost'] ?? null);
            } elseif ($data['type'] === 'open_bottle') {
                app(BusinessCapabilityService::class)->assert($shop, 'decants');
                $inventory = $product->inventory()->lockForUpdate()->firstOrFail();
                if ($inventory->stock_quantity !== $data['expected_stock']) {
                    throw new InvalidArgumentException('La cantidad de botellas cambió; vuelve a sincronizar antes de abrirla.', 409);
                }
                if (array_key_exists('expected_available_ml', $data) && $inventory->available_ml !== $data['expected_available_ml']) {
                    throw new InvalidArgumentException('Los mililitros disponibles cambiaron; vuelve a sincronizar antes de abrirla.', 409);
                }
                app(InventoryService::class)->openBottle($product, $data['quantity'], $data['notes'] ?? null, $user->id, (bool) ($data['track_decants'] ?? false));
            } elseif ($data['type'] === 'adjustment') {
                $inventory = $product->inventory()->lockForUpdate()->firstOrFail();
                $reserved = app(ShopRouteSyncService::class)->reserved($product);
                if ($inventory->stock_quantity - $reserved !== $data['expected_stock']) {
                    throw new InvalidArgumentException('El stock cambió; vuelve a contar antes de ajustar.', 409);
                }
                if (isset($data['expected_available_ml']) && $inventory->available_ml !== $data['expected_available_ml']) {
                    throw new InvalidArgumentException('Los mililitros disponibles cambiaron; vuelve a contar antes de ajustar.', 409);
                }
                app(InventoryService::class)->adjustStock($product, $data['stock'] + $reserved, $data['notes'], $user->id);
            }
        }

        $fresh = $product->fresh(['inventory', 'comboItems.component.inventory']);

        return ['product_id' => $fresh->public_id, 'internal_code' => $fresh->product_code,
            'price' => number_format($fresh->currentPrice(), 2, '.', ''),
            'stock' => $fresh->isCombo() ? $fresh->comboAvailableQuantity() : $fresh->inventory?->stock_quantity,
            'opened_bottles' => $fresh->inventory?->opened_bottles,
            'available_ml' => $fresh->inventory?->available_ml,
            'reserved_decant_ml' => $fresh->inventory?->reserved_decant_ml];
    }

    private function photoOperation(Shop $shop, array $data): array
    {
        $product = $shop->products()->where('public_id', $data['product_id'])->lockForUpdate()->firstOrFail();
        $this->productImage($shop, $product, $data);

        return ['product_id' => $product->public_id, 'image_sha256' => $data['image_sha256']];
    }

    private function isGeneratedMobileCode(string $code): bool
    {
        return $code !== '' && preg_match('/^AUTO-[A-Z0-9-]+$/i', $code) === 1;
    }

    private function canConfigureUnitAsBottle(Product $product, array $presentation): bool
    {
        return ($product->sale_unit ?: 'unit') === 'unit'
            && $presentation['sale_unit'] === 'bottle'
            && (int) ($presentation['volume_ml'] ?? 0) > 0
            && $presentation['inventory_source_product_id'] === null
            && ! (app(DecantInventoryService::class)->available()
                && DB::table('decant_batches')->where('product_id', $product->id)->exists());
    }

    private function productCodeExists(Shop $shop, string $code, ?Product $except = null): bool
    {
        return $shop->products()->whereNotNull('product_code')
            ->whereRaw('LOWER(product_code) = ?', [Str::lower($code)])
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->exists();
    }

    private function nextGeneratedProductCode(Shop $shop): string
    {
        do {
            $candidate = 'AUTO-'.Str::upper(Str::substr(str_replace('-', '', (string) Str::ulid()), 0, 12));
        } while ($this->productCodeExists($shop, $candidate));

        return $candidate;
    }

    /**
     * Normalize mobile product presentation data to the same invariants as the
     * web product form. A decant never owns stock: it consumes the source
     * bottle/ml product through InventoryService when sold.
     */
    private function mobilePresentation(Shop $shop, array $data, ?Product $product): array
    {
        if ((bool) ($data['is_combo'] ?? $product?->isCombo() ?? false)) {
            return ['sale_unit' => 'unit', 'volume_ml' => null, 'inventory_source_product_id' => null];
        }

        $saleUnit = (string) ($data['sale_unit'] ?? $product?->sale_unit ?? 'unit');
        $volume = array_key_exists('volume_ml', $data) ? $data['volume_ml'] : $product?->volume_ml;
        $sourcePublicId = array_key_exists('inventory_source_product_id', $data)
            ? $data['inventory_source_product_id']
            : $product?->sourceProduct?->public_id;

        if ($saleUnit === 'unit') {
            return ['sale_unit' => 'unit', 'volume_ml' => null, 'inventory_source_product_id' => null];
        }

        if ($saleUnit === 'service') {
            app(BusinessCapabilityService::class)->assert($shop, 'services');

            return ['sale_unit' => 'service', 'volume_ml' => null, 'inventory_source_product_id' => null];
        }

        if (! in_array($saleUnit, ['bottle', 'ml', 'decant'], true)) {
            throw ValidationException::withMessages(['sale_unit' => 'La unidad de venta no es válida.']);
        }
        if (! $volume || (int) $volume < 1) {
            throw ValidationException::withMessages(['volume_ml' => 'Indica el volumen en mililitros.']);
        }

        if ($saleUnit !== 'decant') {
            $inputCostCents = is_numeric($data['cost_price'] ?? null)
                ? Money::toCents((string) $data['cost_price'])
                : 0;
            $existingCostCents = $product?->inventory?->cost_price === null
                ? 0
                : Money::toCents($product->inventory->getRawOriginal('cost_price') ?? $product->inventory->cost_price);
            if ($saleUnit === 'bottle' && $inputCostCents <= 0 && $existingCostCents <= 0) {
                throw ValidationException::withMessages(['cost_price' => 'Indica el costo de compra de la botella para calcular ganancias y recuperación de inversión.']);
            }

            return ['sale_unit' => $saleUnit, 'volume_ml' => (int) $volume, 'inventory_source_product_id' => null];
        }

        app(BusinessCapabilityService::class)->assert($shop, 'decants');
        if (! $sourcePublicId) {
            throw ValidationException::withMessages(['inventory_source_product_id' => 'Elige una botella completa del inventario que abastece el decant.']);
        }
        $source = $shop->products()->with('inventory')->where('public_id', $sourcePublicId)->lockForUpdate()->first();
        if (! $source || ($product && $source->id === $product->id)) {
            throw ValidationException::withMessages(['inventory_source_product_id' => 'La fuente debe ser un producto de esta tienda distinto al decant.']);
        }
        if ($source->sale_unit !== 'bottle' || ! $source->volume_ml || ! $source->inventory?->track_inventory) {
            throw ValidationException::withMessages(['inventory_source_product_id' => 'La fuente debe ser un perfume completo configurado como botella, con volumen y control de inventario.']);
        }
        if ($source->inventory->cost_price === null
            || Money::toCents($source->inventory->getRawOriginal('cost_price') ?? $source->inventory->cost_price) <= 0) {
            throw ValidationException::withMessages(['inventory_source_product_id' => 'La botella fuente no tiene costo de compra. Regístralo antes de crear el decant.']);
        }
        if ((int) $volume > (int) $source->volume_ml) {
            throw ValidationException::withMessages(['volume_ml' => 'El decant no puede superar el volumen de su fuente.']);
        }

        return ['sale_unit' => 'decant', 'volume_ml' => (int) $volume, 'inventory_source_product_id' => $source->id];
    }

    private function syncMobileComboItems(Shop $shop, Product $product, array $items): void
    {
        if (! $product->isCombo()) {
            $product->comboItems()->delete();

            return;
        }

        $resolved = [];
        foreach ($items as $item) {
            $component = $shop->products()->with('inventory')->where('public_id', $item['product_id'] ?? null)->first();
            if (! $component || $component->id === $product->id || $component->isCombo() || $component->isService() || ! $component->inventory?->track_inventory) {
                throw ValidationException::withMessages(['combo_items' => 'Cada componente debe ser un producto con inventario activo y no puede ser otro combo.']);
            }
            $resolved[$component->id] = ($resolved[$component->id] ?? 0) + (int) $item['quantity'];
        }
        if ($resolved === []) {
            throw ValidationException::withMessages(['combo_items' => 'Agrega al menos un producto al combo.']);
        }

        $product->comboItems()->delete();
        foreach ($resolved as $componentId => $quantity) {
            $product->comboItems()->create(['component_product_id' => $componentId, 'quantity' => $quantity]);
        }
        $product->forceFill([
            'availability_status' => $product->fresh(['comboItems.component.inventory'])->comboAvailableQuantity() > 0
                ? ProductAvailabilityStatus::Available
                : ProductAvailabilityStatus::OutOfStock,
        ])->saveQuietly();
    }

    private function productImage(Shop $shop, Product $product, array $data): void
    {
        $bytes = base64_decode($data['image_base64'], true);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        if ($bytes === false || strlen($bytes) > 512 * 1024 || ! $info ||
            ! in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true) ||
            (int) config('catalog.uploads.max_input_pixels', 60_000_000) < $info[0] * $info[1] ||
            ! hash_equals($data['image_sha256'], hash('sha256', $bytes))) {
            throw ValidationException::withMessages(['image_base64' => 'La foto no es válida o cambió durante el envío.']);
        }
        $derivatives = app(ImageDerivativeService::class);
        try {
            $rendered = $derivatives->render($bytes);
        } catch (\Throwable $error) {
            throw ValidationException::withMessages(['image_base64' => 'No se pudo procesar la foto.']);
        }
        $existing = $product->images()->where('checksum_sha256', $rendered['checksum'])->first();
        if (! $existing) {
            abort_if($product->images()->count() >= app(PlanLimitsService::class)->imageLimit($shop), 422,
                'Tu plan alcanzó el límite de fotos. Gestiona las anteriores en el panel; no se borran automáticamente.');
            $media = app(MediaStorageService::class);
            $main = $media->buildProductObjectKey($product->public_id, 'main', $rendered['checksum']);
            $thumb = $media->buildProductObjectKey($product->public_id, 'thumb', $rendered['checksum']);
            $derivatives->store($rendered, $main, $thumb, $media);
            $existing = $product->images()->create(['object_key' => $main, 'thumbnail_object_key' => $thumb,
                'checksum_sha256' => $rendered['checksum'], 'mime_type' => 'image/webp',
                'width' => $rendered['width'], 'height' => $rendered['height'], 'size_bytes' => $rendered['size_bytes'],
                'sort_order' => 0, 'processing_status' => ProductImageProcessingStatus::Ready]);
        }
        // Promote the chosen photo without deleting or replacing an older object.
        $product->images()->whereKeyNot($existing->id)->increment('sort_order');
        $existing->update(['sort_order' => 0]);
    }

    private function refund(Shop $shop, User $user, array $data, MobileOperation $operation): array
    {
        $invoice = ! empty($data['invoice_id'])
            ? $shop->invoices()->whereKey((int) $data['invoice_id'])->lockForUpdate()->firstOrFail()
            : optional(PosSaleUpload::where('shop_id', $shop->id)->where('client_sale_uuid', $data['client_sale_uuid'])->first())->invoice;
        if (! $invoice) {
            throw new InvalidArgumentException('Sincroniza primero la venta original.', 409);
        }
        $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        if ($invoice->status !== 'paid' || CustomerAccountEntry::where('invoice_id', $invoice->id)->exists()) {
            throw new InvalidArgumentException('Solo se devuelven ventas de contado pagadas, sin deuda.', 422);
        }
        $rows = $invoice->items()->get();
        $invoiceItems = $rows->keyBy(fn ($item) => (string) $item->product_id);
        if ($rows->count() !== $invoiceItems->count()) {
            throw new InvalidArgumentException('La venta contiene líneas repetidas; requiere conciliación antes de devolver.', 422);
        }
        // Allocate invoice-level tax deterministically in cents, conserving the
        // full amount across all lines and subsequent partial returns.
        $chargedLines = app(InvoiceRefundAmounts::class)->chargedLines($invoice->setRelation('items', $rows));
        $taxShares = [];
        foreach ($rows->sortBy('id') as $row) {
            $taxShares[$row->id] = $chargedLines[$row->id] - max(0, Money::toCents($row->line_total) - (int) $row->general_discount_cents);
        }
        $returnId = DB::table('invoice_returns')->insertGetId(['invoice_id' => $invoice->id, 'mobile_operation_id' => $operation->id,
            'total' => 0, 'notes' => $data['notes'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        $totalCents = 0;
        foreach ($data['items'] as $line) {
            $product = $shop->products()->withTrashed()->where('public_id', $line['product_id'])->firstOrFail();
            $item = $invoiceItems->get((string) $product->id);
            if (! $item) {
                throw new InvalidArgumentException('La línea no pertenece a la venta.', 422);
            }
            $remainder = app(DecantInventoryService::class)->available()
                ? DB::table('decant_remainder_sales')->join('inventory_movements', 'inventory_movements.id', '=', 'decant_remainder_sales.movement_id')
                    ->where('inventory_movements.invoice_id', $invoice->id)->where('inventory_movements.product_id', $product->id)->select('decant_remainder_sales.*')->first()
                : null;
            if ($line['restock'] && ! $remainder && ($item->sale_unit !== $product->sale_unit ||
                in_array($item->sale_unit, ['bottle', 'ml', 'decant'], true) && (
                    $item->volume_ml !== $product->volume_ml ||
                    (string) $item->inventory_source_product_id !== (string) $product->inventory_source_product_id ||
                    $item->sale_unit === 'decant' && $item->inventory_source_product_id === null
                ))) {
                throw new InvalidArgumentException('La presentación o fuente cambió desde la venta; concilia el inventario antes de reintegrar.', 422);
            }
            $oldQty = (int) DB::table('invoice_return_items')->where('invoice_item_id', $item->id)->sum('quantity');
            $qty = $line['quantity'];
            if ($oldQty + $qty > $item->quantity) {
                throw new InvalidArgumentException('La devolución supera las unidades vendidas.', 422);
            }
            $refundCents = isset($line['refund_total']) ? Money::toCents($line['refund_total'])
                : Money::toCents($line['refund_price']) * $qty;
            $chargedCents = $chargedLines[$item->id];
            $maxRefund = intdiv($chargedCents * ($oldQty + $qty), $item->quantity) - intdiv($chargedCents * $oldQty, $item->quantity);
            if ($refundCents > $maxRefund) {
                throw new InvalidArgumentException('El reembolso supera lo cobrado por esas unidades.', 422);
            }
            $cost = $item->total_cost_cents === null ? null
                : intdiv($item->total_cost_cents * ($oldQty + $qty), $item->quantity) - intdiv($item->total_cost_cents * $oldQty, $item->quantity);
            $itemTaxCents = Money::toCents($item->tax) + $taxShares[$item->id];
            $taxCents = min($refundCents, intdiv($itemTaxCents * ($oldQty + $qty), $item->quantity) - intdiv($itemTaxCents * $oldQty, $item->quantity));
            DB::table('invoice_return_items')->insert(['invoice_return_id' => $returnId, 'invoice_item_id' => $item->id,
                'quantity' => $qty, 'refund' => Money::toDecimal($refundCents), 'tax_refund' => Money::toDecimal($taxCents),
                'total_cost_cents' => $cost, 'restock' => $line['restock'], 'created_at' => now(), 'updated_at' => now()]);
            if ($line['restock']) {
                if ($remainder) app(DecantInventoryService::class)->returnRemainder($product, $remainder, $cost, $user->id);
                else app(InventoryService::class)->recordReturn($product, $qty, $cost, $user->id, $data['notes'] ?? null);
            }
            $totalCents += $refundCents;
        }
        DB::table('invoice_returns')->where('id', $returnId)->update(['total' => Money::toDecimal($totalCents)]);

        return ['return_id' => $returnId, 'total' => number_format($totalCents / 100, 2, '.', '')];
    }
}
