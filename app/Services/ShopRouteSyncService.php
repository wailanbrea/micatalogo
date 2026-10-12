<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopSyncRecord;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Routes are tenant-owned allocations of the same physical inventory, not new stock. */
class ShopRouteSyncService
{
    private array $readCache = [];
    public const TYPES = ['route_upsert', 'route_assign', 'route_unassign', 'route_reorder', 'route_load_create', 'route_load_status'];

    public function validate(array $input): array
    {
        $rules = ['type' => ['required', 'in:'.implode(',', self::TYPES)], 'client_operation_uuid' => ['required', 'uuid'],
            'record_id' => ['required', 'uuid'], 'notes' => ['nullable', 'string', 'max:1000'],
            'related_record_ids' => ['sometimes', 'array'], 'related_record_ids.*' => ['uuid']];
        $rules += match ($input['type']) {
            'route_upsert' => ['name' => ['required', 'string', 'max:100'], 'code' => ['required', 'string', 'max:40'],
                'description' => ['nullable', 'string', 'max:2000'], 'is_active' => ['required', 'boolean'], 'created_at' => ['required', 'date']],
            'route_assign', 'route_unassign' => ['customer_id' => ['required', 'string', 'max:36'], 'visit_order' => ['sometimes', 'integer', 'min:0']],
            'route_reorder' => ['customer_ids' => ['present', 'array'], 'customer_ids.*' => ['required', 'string', 'max:36', 'distinct']],
            'route_load_create' => ['route_id' => ['required', 'uuid'], 'date' => ['required', 'date'], 'created_at' => ['required', 'date'],
                'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*.id' => ['required', 'uuid', 'distinct'],
                'items.*.product_id' => ['required', 'ulid', 'distinct'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
                'items.*.unit_cost_cents' => ['required', 'integer', 'min:0'], 'items.*.expected_main_quantity' => ['required', 'integer', 'min:0']],
            'route_load_status' => ['status' => ['required', 'in:SETTLED,CANCELLED']],
        };

        return Validator::make($input, $rules)->validate();
    }

    public function apply(Shop $shop, array $input): array
    {
        unset($this->readCache[$shop->id]);
        $data = $this->validate($input);
        $id = $data['record_id'];
        if ($data['type'] === 'route_upsert') {
            $duplicates = $this->rows($shop, 'route')->where('record_uuid', '!=', $id)->get();
            if ($duplicates->contains(fn ($row) => mb_strtolower($row->payload['code']) === mb_strtolower(trim($data['code'])))) {
                throw ValidationException::withMessages(['code' => 'Ya existe una ruta con ese código.']);
            }
            $old = $this->rows($shop, 'route')->where('record_uuid', $id)->first();
            $payload = array_intersect_key($data, array_flip(['name', 'code', 'description', 'is_active', 'created_at']));
            $payload['name'] = trim($payload['name']); $payload['code'] = trim($payload['code']);
            if ($payload['name'] === '' || $payload['code'] === '') {
                throw ValidationException::withMessages(['name' => 'El nombre y código son obligatorios.']);
            }
            $payload['created_at'] = $old?->payload['created_at'] ?? $payload['created_at'];
            $row = $this->save($shop, 'route', $id, $payload);
        } elseif (in_array($data['type'], ['route_assign', 'route_unassign', 'route_reorder'], true)) {
            $route = $this->route($shop, $id);
            $members = $this->rows($shop, 'route_members')->where('record_uuid', $id)->first()?->payload['customers'] ?? [];
            if ($data['type'] === 'route_reorder') {
                $ids = array_map(fn ($key) => $this->customer($shop, $key)->public_id, $data['customer_ids']);
                if (count($ids) !== count($members) || array_diff($ids, array_column($members, 'customer_id'))) {
                    throw new InvalidArgumentException('La lista de clientes de la ruta cambió.', 409);
                }
                $members = array_map(fn ($customer, $order) => ['customer_id' => $customer, 'visit_order' => $order], $ids, array_keys($ids));
            } else {
                $customer = $this->customer($shop, $data['customer_id']);
                if ($data['type'] === 'route_unassign' && ! collect($members)->contains(fn ($member) => $member['customer_id'] === $customer->public_id)) {
                    throw new InvalidArgumentException('El cliente ya no está asignado a esta ruta.', 409);
                }
                if ($data['type'] === 'route_assign' && (! $route->payload['is_active'] || ! $customer->is_active || $customer->trashed())) {
                    throw ValidationException::withMessages(['customer_id' => 'La ruta y el cliente deben estar activos.']);
                }
                foreach ($this->rows($shop, 'route_members')->get() as $other) {
                    $filtered = array_values(array_filter($other->payload['customers'], fn ($member) => $member['customer_id'] !== $customer->public_id));
                    if (count($filtered) !== count($other->payload['customers'])) {
                        $this->saveMembers($shop, $other->record_uuid, $filtered);
                    }
                }
                $members = array_values(array_filter($members, fn ($member) => $member['customer_id'] !== $customer->public_id));
                if ($data['type'] === 'route_assign') {
                    $position = min((int) ($data['visit_order'] ?? count($members)), count($members));
                    array_splice($members, $position, 0, [['customer_id' => $customer->public_id, 'visit_order' => $position]]);
                }
            }
            $row = $this->saveMembers($shop, $id, $members);
        } elseif ($data['type'] === 'route_load_create') {
            if ($this->rows($shop, 'route_load')->where('record_uuid', $id)->exists()) {
                throw new InvalidArgumentException('La carga ya existe; usa su UUID de operación original.', 409);
            }
            $route = $this->route($shop, $data['route_id']);
            if (! $route->payload['is_active']) {
                throw ValidationException::withMessages(['route_id' => 'La ruta está inactiva.']);
            }
            $items = [];
            foreach ($data['items'] as $item) {
                $product = $shop->products()->where('public_id', $item['product_id'])->with('inventory')->lockForUpdate()->firstOrFail();
                if (($product->sale_unit ?: 'unit') !== 'unit' || $product->isCombo() || ! $product->inventory?->track_inventory) {
                    throw ValidationException::withMessages(['items' => 'Las cargas admiten productos físicos por unidad con inventario activo.']);
                }
                $main = $this->mainQuantity($product);
                if ($main !== (int) $item['expected_main_quantity'] || $main < (int) $item['quantity']) {
                    throw new InvalidArgumentException('El inventario disponible cambió; revisa la carga.', 409);
                }
                $items[] = $item + ['remaining' => (int) $item['quantity']];
            }
            $row = $this->save($shop, 'route_load', $id, ['route_id' => $data['route_id'], 'date' => $data['date'],
                'created_at' => $data['created_at'], 'notes' => $data['notes'] ?? null, 'status' => 'OPEN', 'items' => $items]);
        } else {
            $row = $this->rows($shop, 'route_load')->where('record_uuid', $id)->firstOrFail();
            if ($row->payload['status'] !== 'OPEN') {
                throw new InvalidArgumentException('La carga ya está cerrada.', 409);
            }
            $row->update(['payload' => array_replace($row->payload, ['status' => $data['status']])]);
        }

        unset($this->readCache[$shop->id]);
        return ['record_id' => $row->record_uuid, 'record' => $row->payload];
    }

    public function mainQuantity(Product $product): int
    {
        return max(0, (int) $product->inventory?->stock_quantity - array_sum(array_map(fn ($row) => $row['quantity'],
            $this->stock($product->shop)->filter(fn ($row) => $row['product_id'] === $product->public_id)->all())));
    }

    public function reserved(Product $product): int
    {
        return (int) $this->stock($product->shop)->where('product_id', $product->public_id)->sum('quantity');
    }

    public function stock(Shop $shop)
    {
        $stock = [];
        foreach ($this->readRows($shop)->where('kind', 'route_load') as $row) {
            foreach ($row->payload['items'] as $item) {
                $key = $row->payload['route_id'].':'.$item['product_id'];
                $stock[$key] ??= ['route_id' => $row->payload['route_id'], 'product_id' => $item['product_id'], 'quantity' => 0, 'unit_cost_cents' => $item['unit_cost_cents']];
                if ($row->payload['status'] === 'OPEN') $stock[$key]['quantity'] += $item['remaining'];
            }
        }

        return collect(array_values($stock));
    }

    public function consume(Shop $shop, string $routeId, array $items, string $saleUuid): void
    {
        $route = $this->route($shop, $routeId);
        if (! $route->payload['is_active']) throw new InvalidArgumentException('La ruta está inactiva.', 409);
        foreach ($items as $item) {
            $needed = (int) $item['quantity'];
            foreach ($this->rows($shop, 'route_load')->orderBy('id')->get() as $load) {
                $payload = $load->payload;
                if ($payload['status'] !== 'OPEN' || $payload['route_id'] !== $routeId) continue;
                foreach ($payload['items'] as &$line) {
                    if ($line['product_id'] !== $item['product_id']) continue;
                    $used = min($needed, $line['remaining']); $line['remaining'] -= $used; $needed -= $used;
                }
                unset($line);
                $load->update(['payload' => $payload]);
                if ($needed === 0) break;
            }
            if ($needed > 0) throw new InvalidArgumentException('Stock insuficiente en la ruta seleccionada.', 409);
        }
        $this->save($shop, 'route_sale', $saleUuid, ['route_id' => $routeId, 'items' => $items]);
        unset($this->readCache[$shop->id]);
    }

    public function snapshot(Shop $shop, bool $finance): array
    {
        return $this->readRows($shop)->whereIn('kind', ['route', 'route_members', 'route_load'])->map(function ($row) use ($finance) {
            $payload = $row->payload;
            if (! $finance && $row->kind === 'route_load') {
                $payload['items'] = array_map(fn ($item) => array_replace($item, ['unit_cost_cents' => 0]), $payload['items']);
            }
            return ['kind' => $row->kind, 'record_id' => $row->record_uuid, 'payload' => $payload, 'updated_at' => $row->updated_at->toISOString()];
        })->all();
    }

    private function route(Shop $shop, string $id): ShopSyncRecord { return $this->rows($shop, 'route')->where('record_uuid', $id)->firstOrFail(); }
    private function readRows(Shop $shop) { return $this->readCache[$shop->id] ??= $this->rows($shop)->orderBy('id')->get(); }
    private function customer(Shop $shop, string $id) { return $shop->customers()->withTrashed()->where(fn ($query) => $query->where('public_id', $id)->orWhere('client_customer_uuid', $id))->firstOrFail(); }
    private function rows(Shop $shop, ?string $kind = null) { return ShopSyncRecord::where('shop_id', $shop->id)->when($kind, fn ($query) => $query->where('kind', $kind)); }
    private function save(Shop $shop, string $kind, string $id, array $payload): ShopSyncRecord { return ShopSyncRecord::updateOrCreate(['shop_id' => $shop->id, 'kind' => $kind, 'record_uuid' => $id], ['payload' => $payload]); }
    private function saveMembers(Shop $shop, string $route, array $members): ShopSyncRecord {
        return $this->save($shop, 'route_members', $route, ['customers' => array_map(fn ($member, $order) => array_replace($member, ['visit_order' => $order]), array_values($members), array_keys(array_values($members)))]);
    }
}
