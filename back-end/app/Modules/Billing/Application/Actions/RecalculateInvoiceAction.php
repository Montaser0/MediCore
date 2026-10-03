<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;

/**
 * Recomputes the money on an invoice from its lines and its payments, so the
 * totals are always derived rather than edited by hand.
 */
final class RecalculateInvoiceAction
{
    public function execute(InvoiceModel $invoice, ?float $discount = null, ?float $taxRate = null): InvoiceModel
    {
        $invoice->loadMissing(['items', 'payments.refunds']);

        $subtotal = round((float) $invoice->items->sum('total'), 2);
        $discount = round($discount ?? (float) $invoice->discount, 2);
        $taxable = max(0.0, $subtotal - $discount);

        $tax = $taxRate !== null
            ? round($taxable * ($taxRate / 100), 2)
            : (float) $invoice->tax;

        $total = round($taxable + $tax, 2);

        $paid = round($invoice->payments->sum(fn ($payment) => $payment->netAmount()), 2);

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'paid_amount' => $paid,
            'balance' => round($total - $paid, 2),
        ])->save();

        return $invoice;
    }
}
