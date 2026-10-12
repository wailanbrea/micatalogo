<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\Money;

/** Shared read/write allocation conserves discounted cents and invoice-level tax. */
class InvoiceRefundAmounts
{
    public function chargedLines(Invoice $invoice): array
    {
        $rows = $invoice->items;
        $weights = $rows->mapWithKeys(fn ($item) => [$item->id => max(0, Money::toCents($item->line_total) - (int) $item->general_discount_cents)]);
        if ($weights->sum() === 0) {
            $weights = $rows->mapWithKeys(fn ($item) => [$item->id => Money::toCents($item->line_total)]);
            if ($weights->sum() === 0) $weights = $rows->mapWithKeys(fn ($item) => [$item->id => (int) $item->quantity]);
        }
        $total = $weights->sum(); $cumulative = $allocated = 0; $charged = [];
        foreach ($rows->sortBy('id') as $row) {
            $cumulative += $weights[$row->id];
            $next = $total > 0 ? intdiv(Money::toCents($invoice->tax) * $cumulative, $total) : 0;
            $charged[$row->id] = max(0, Money::toCents($row->line_total) - (int) $row->general_discount_cents) + $next - $allocated;
            $allocated = $next;
        }
        return $charged;
    }
}
