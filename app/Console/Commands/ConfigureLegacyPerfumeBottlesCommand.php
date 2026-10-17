<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\LegacyBottleCapacityResolver;
use App\Services\MobileOperationService;
use App\Services\ProductPresentationChangeGuard;
use App\Services\ShopRecordSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConfigureLegacyPerfumeBottlesCommand extends Command
{
    protected $signature = 'catalog:configure-legacy-perfume-bottles
                            {shop : Slug of the shop to update}
                            {owner-email : Expected shop owner email; must match exactly}
                            {--apply : Apply eligible changes; without this option the command is read-only}';

    protected $description = 'Configures legacy individual perfumes as full bottles through the mobile operation flow';

    public function handle(LegacyBottleCapacityResolver $capacityResolver): int
    {
        $shop = Shop::query()->where('slug', (string) $this->argument('shop'))->first();
        if (! $shop) {
            $this->error('No existe esa tienda.');

            return self::FAILURE;
        }

        $owner = User::query()->find($shop->user_id);
        if (! $owner || ! hash_equals(mb_strtolower((string) $owner->email), mb_strtolower((string) $this->argument('owner-email')))) {
            $this->error('El correo esperado no coincide con el propietario de la tienda; no se modificó nada.');

            return self::FAILURE;
        }

        $shop->load('user');
        $unitUuid = app(ShopRecordSyncService::class)->localId($shop, 'unit', 'bottle');
        $candidates = $shop->products()->with(['inventory', 'shopCategory'])->get()
            ->filter(fn (Product $product) => $this->isIndividualLegacyPerfume($product));

        $eligible = [];
        $blocked = [];
        foreach ($candidates as $product) {
            $capacity = $capacityResolver->resolve((string) $product->name);
            if ($capacity['error']) {
                $blocked[] = [$product, $capacity['error']];

                continue;
            }

            try {
                app(ProductPresentationChangeGuard::class)->assertMayChange(
                    $shop,
                    $product,
                    $product->inventory,
                    ['sale_unit' => 'bottle', 'volume_ml' => $capacity['volume_ml'], 'inventory_source_product_id' => null],
                    $unitUuid,
                    false,
                );
                $eligible[] = [$product, $capacity['volume_ml'], $capacity['source']];
            } catch (Throwable $exception) {
                $message = $exception instanceof ValidationException
                    ? ($exception->validator->errors()->first('sale_unit') ?: 'Presentación protegida por validación.')
                    : $exception->getMessage();
                $blocked[] = [$product, $message];
            }
        }

        $this->info(sprintf('Tienda: %s · propietario verificado · candidatos: %d', $shop->slug, $candidates->count()));
        foreach ($eligible as [$product, $volume, $source]) {
            $this->line(sprintf('LISTO  %s | %d ml (%s) | stock %d | lotes %d',
                $product->name, $volume, $source, (int) ($product->inventory?->stock_quantity ?? 0), $product->inventoryLots()->count()));
        }
        foreach ($blocked as [$product, $reason]) {
            $this->warn(sprintf('OMITIDO  %s | %s', $product->name, $reason));
        }

        if (! $this->option('apply')) {
            $this->comment(sprintf('Simulación: %d elegibles, %d omitidos. No se escribió ningún dato. Ejecuta con --apply para aplicar.', count($eligible), count($blocked)));

            return $blocked === [] ? self::SUCCESS : self::FAILURE;
        }

        $applied = 0;
        $failed = [];
        foreach ($eligible as [$product, $volume]) {
            try {
                app(MobileOperationService::class)->apply($shop, $owner, [
                    'type' => 'product_upsert',
                    'client_operation_uuid' => (string) Str::uuid(),
                    'product_id' => $product->public_id,
                    'sale_unit' => 'bottle',
                    'volume_ml' => $volume,
                    'unit_uuid' => $unitUuid,
                ], 'maintenance');

                $verified = $product->fresh(['inventory']);
                if ($verified->sale_unit !== 'bottle'
                    || (int) $verified->volume_ml !== $volume
                    || (int) ($verified->inventory?->stock_quantity ?? 0) !== (int) ($product->inventory?->stock_quantity ?? 0)
                    || (int) ($verified->inventory?->available_ml ?? 0) !== (int) ($verified->inventory?->stock_quantity ?? 0) * $volume
                    || $verified->inventoryLots()->where('quantity_unit', '!=', 'ml')->exists()) {
                    throw new \RuntimeException('La verificación posterior no coincidió con la presentación esperada.');
                }

                $applied++;
                $this->line(sprintf('APLICADO  %s · %d ml', $verified->name, $volume));
            } catch (Throwable $exception) {
                $failed[] = [$product, $exception->getMessage()];
                $this->error(sprintf('FALLÓ  %s | %s', $product->name, $exception->getMessage()));
            }
        }

        $this->info(sprintf('Resultado: %d aplicados, %d bloqueados en preflight, %d fallidos.', $applied, count($blocked), count($failed)));

        return $blocked === [] && $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    private function isIndividualLegacyPerfume(Product $product): bool
    {
        $category = mb_strtolower((string) ($product->shopCategory?->name ?? ''));
        $name = mb_strtolower(trim((string) $product->name));

        return str_contains($category, 'perfume')
            && ! str_starts_with($category, 'set')
            && ! preg_match('/\b(?:set|kit|combo|pack|coffret)\b/u', $name)
            && ($product->sale_unit === null || $product->sale_unit === 'unit')
            && $product->volume_ml === null
            && $product->inventory_source_product_id === null
            && ! $product->isCombo();
    }
}
