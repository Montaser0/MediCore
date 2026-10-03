<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\AuditRecorder;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use Illuminate\Support\Facades\DB;

/**
 * Discounts are a separate, permissioned operation rather than an editable
 * field, and every one is audited with its reason.
 */
final class ApplyDiscountAction
{
    public function __construct(
        private readonly RecalculateInvoiceAction $recalculate,
        private readonly SyncInstallmentsAction $installments,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(InvoiceModel $invoice, float $discount, string $reason): InvoiceModel
    {
        if ($invoice->status->isSettled()) {
            throw BusinessRuleException::make(
                "لا يمكن تطبيق خصم على الفاتورة {$invoice->invoice_number} لأنها «{$invoice->status->label()}».",
            );
        }

        if ($discount < 0) {
            throw BusinessRuleException::make('الخصم لا يمكن أن يكون سالباً.');
        }

        if ($discount > (float) $invoice->subtotal) {
            throw BusinessRuleException::make('الخصم لا يمكن أن يتجاوز مجموع الفاتورة.');
        }

        return DB::transaction(function () use ($invoice, $discount, $reason): InvoiceModel {
            $previous = (float) $invoice->discount;

            $invoice->loadMissing(['items', 'payments.refunds']);

            $taxRate = (float) $invoice->subtotal > 0 && (float) $invoice->tax > 0
                ? round((float) $invoice->tax / max(0.01, (float) $invoice->subtotal - $previous) * 100, 4)
                : (float) config('hms.billing.tax_rate', 0);

            $invoice = $this->recalculate->execute($invoice, $discount, $taxRate);

            if ((float) $invoice->paid_amount > (float) $invoice->total + 0.009) {
                throw BusinessRuleException::make(
                    'هذا الخصم يجعل المدفوع أكبر من الفاتورة. استرد الفرق أولاً.',
                    ['paid_amount' => $invoice->paid_amount, 'total' => $invoice->total],
                );
            }

            $this->installments->execute($invoice);

            $this->audit->record(
                action: 'billing.discount_applied',
                entityType: 'invoice',
                entityId: (int) $invoice->getKey(),
                oldValues: ['discount' => $previous],
                newValues: ['discount' => $discount, 'reason' => $reason],
            );

            return $invoice;
        });
    }
}
