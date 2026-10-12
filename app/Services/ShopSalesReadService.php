<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Http\Request;

/** One filter contract for the on-screen read model and its complete CSV export. */
class ShopSalesReadService
{
    public function query(Shop $shop, Request $request)
    {
        $data = $request->validate(['period' => ['sometimes', 'in:today,month,last_7,all'],
            'status' => ['sometimes', 'in:all,paid,credit,partial,void'], 'q' => ['nullable', 'string', 'max:255'],
            'origin' => ['sometimes', 'in:all,warehouse,route'], 'min_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'max_amount' => ['nullable', 'decimal:0,2', 'min:0']]);
        $query = $shop->invoices()->with(['customer', 'user', 'items']);
        $status = $data['status'] ?? 'all';
        if ($status === 'all') $query->where('status', '!=', 'void');
        elseif ($status === 'credit') $query->whereIn('status', ['credit', 'pending']);
        else $query->where('status', $status);
        match ($data['period'] ?? 'today') {
            'today' => $query->whereDate('issued_at', today()),
            'month' => $query->whereBetween('issued_at', [now()->startOfMonth(), now()->endOfDay()]),
            'last_7' => $query->whereBetween('issued_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()]),
            default => null,
        };
        if (($search = trim($data['q'] ?? '')) !== '') $query->where(fn ($q) => $q->where('invoice_number', 'like', "%$search%")
            ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%$search%"))
            ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%$search%")));
        if (isset($data['min_amount'])) $query->where('total', '>=', $data['min_amount']);
        if (isset($data['max_amount'])) $query->where('total', '<=', $data['max_amount']);
        $origin = $data['origin'] ?? 'all';
        if ($origin !== 'all') {
            $method = $origin === 'route' ? 'whereExists' : 'whereNotExists';
            $query->$method(fn ($q) => $q->selectRaw('1')->from('pos_sale_uploads')->join('shop_sync_records', function ($join) use ($shop) {
                $join->on('shop_sync_records.record_uuid', '=', 'pos_sale_uploads.client_sale_uuid')
                    ->where('shop_sync_records.kind', 'route_sale')->where('shop_sync_records.shop_id', $shop->id);
            })->whereColumn('pos_sale_uploads.invoice_id', 'invoices.id')->where('pos_sale_uploads.shop_id', $shop->id));
        }
        return $query;
    }
}
