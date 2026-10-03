<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Infrastructure\Persistence\Models\PaymentModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\RefundModel;
use Illuminate\Support\Facades\DB;

/**
 * A payment is never deleted or edited; money going back out is recorded as a
 * refund against the original receipt.
 */
final class RefundPaymentAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly RecalculateInvoiceAction $recalculate,
        private readonly SyncInstallmentsAction $installments,
    ) {}

    public function execute(PaymentModel $payment, float $amount, string $reason): RefundModel
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw BusinessRuleException::make('مبلغ الاسترداد يجب أن يكون أكبر من صفر.');
        }

        $refundable = $payment->netAmount();

        if ($amount > $refundable + 0.009) {
            throw BusinessRuleException::make(
                "المتبقي القابل للاسترداد من هذا الإيصال هو {$refundable}.",
                ['refundable' => $refundable],
            );
        }

        return DB::transaction(function () use ($payment, $amount, $reason): RefundModel {
            $refund = RefundModel::query()->create([
                'payment_id' => $payment->getKey(),
                'amount' => $amount,
                'reason' => $reason,
                'refunded_by' => $this->currentUser->id(),
                'refunded_at' => now(),
            ]);

            $invoice = $payment->invoice;

            if ($invoice !== null) {
                $invoice->load('payments.refunds', 'items');
                $this->recalculate->execute($invoice);
                $this->installments->execute($invoice);

                // A refund reopens a settled invoice so the balance is visible
                // again to the cashier.
                if ($invoice->status === InvoiceStatus::Paid && ! $invoice->isFullyPaid()) {
                    $invoice->forceFill(['status' => InvoiceStatus::PartiallyPaid->value])->save();
                }
            }

            return $refund->load('payment');
        });
    }
}
