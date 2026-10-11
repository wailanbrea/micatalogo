<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Read-only attribution, capped by the authoritative customer balance. */
class CustomerDebtReadService
{
    public function read(Shop $shop, Customer $customer): array
    {
        abort_unless($customer->shop_id === $shop->id, 404);
        $invoices = $customer->invoices()->where('shop_id', $shop->id)
            ->whereIn('status', ['pending', 'partial'])
            ->withSum('payments', 'amount_cents')
            ->orderByRaw('COALESCE(due_date, issued_at) asc')->orderBy('id')->get();
        $returns = DB::table('invoice_returns')->where('shop_id', $shop->id)
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->selectRaw('invoice_id, SUM(total) AS total')->groupBy('invoice_id')->pluck('total', 'invoice_id');
        $orders = Order::where('shop_id', $shop->id)->whereIn('invoice_id', $invoices->pluck('id'))
            ->pluck('order_number', 'invoice_id');
        $remaining = max(0, Money::toCents($customer->balance));
        $origins = [];
        foreach ($invoices as $invoice) {
            $paid = (int) ($invoice->payments_sum_amount_cents ?? 0);
            $returned = Money::toCents($returns->get($invoice->id) ?? '0.00');
            $outstanding = min($remaining, max(0, Money::toCents($invoice->total) - $paid - $returned));
            if ($outstanding <= 0) {
                continue;
            }
            $remaining -= $outstanding;
            $origins[] = [
                'invoice_number' => $invoice->invoice_number,
                'order_number' => $orders->get($invoice->id),
                'channel' => $invoice->channel,
                'issued_at' => $invoice->issued_at?->toISOString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'total' => $invoice->total,
                'paid_amount' => Money::toDecimal($paid),
                'returned_amount' => Money::toDecimal($returned),
                'outstanding_amount' => Money::toDecimal($outstanding),
            ];
        }

        return ['debt_origins' => $origins, 'unallocated_balance' => Money::toDecimal($remaining)];
    }
}
