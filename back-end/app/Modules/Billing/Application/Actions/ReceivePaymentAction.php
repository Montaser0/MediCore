<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Enums\PaymentMethod;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Support\Facades\DB;

/**
 * Takes money against an invoice. The invoice is locked so two cashiers
 * cannot both believe they collected the last of the balance.
 */
final class ReceivePaymentAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly CurrentUser $currentUser,
        private readonly RecalculateInvoiceAction $recalculate,
        private readonly SyncInstallmentsAction $installments,
        private readonly TimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(InvoiceModel $invoice, array $data): PaymentModel
    {
        $method = PaymentMethod::from((string) $data['method']);
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw BusinessRuleException::make('مبلغ الدفع يجب أن يكون أكبر من صفر.');
        }

        if ($method->requiresReference() && empty($data['reference'])) {
            throw BusinessRuleException::make('الدفع بطريقة '.$method->label().' يحتاج رقماً مرجعياً.');
        }

        return DB::transaction(function () use ($invoice, $data, $method, $amount): PaymentModel {
            $locked = InvoiceModel::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if (! $locked->status->acceptsPayment()) {
                throw BusinessRuleException::make(
                    match ($locked->status) {
                        InvoiceStatus::Paid => "الفاتورة {$locked->invoice_number} مدفوعة بالكامل.",
                        InvoiceStatus::Cancelled => "الفاتورة {$locked->invoice_number} ملغاة، ولا يُقبض عليها.",
                        InvoiceStatus::Draft => "أصدر الفاتورة {$locked->invoice_number} قبل تسجيل الدفعة.",
                        default => "لا يمكن قبض دفعة على الفاتورة {$locked->invoice_number}.",
                    },
                    ['status' => $locked->status->value],
                );
            }

            if ($amount > (float) $locked->balance + 0.009) {
                throw BusinessRuleException::make(
                    "المبلغ أكبر من الرصيد المتبقي {$locked->balance}.",
                    ['balance' => $locked->balance, 'amount' => $amount],
                );
            }

            $payment = PaymentModel::query()->create([
                'hospital_id' => $locked->hospital_id,
                'invoice_id' => $locked->getKey(),
                'receipt_number' => $this->numbers->receiptNumber((int) $locked->hospital_id),
                'amount' => $amount,
                'method' => $method->value,
                'reference' => $data['reference'] ?? null,
                'received_by' => $this->currentUser->id(),
                'paid_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $locked->load('payments.refunds');
            $this->recalculate->execute($locked);
            $this->installments->execute($locked);

            $target = $locked->isFullyPaid()
                ? InvoiceStatus::Paid
                : InvoiceStatus::PartiallyPaid;

            if ($locked->status->canTransitionTo($target)) {
                $locked->forceFill(['status' => $target->value])->save();
            }

            if ($locked->encounter_id !== null) {
                $this->timeline->record(
                    patientId: (int) $locked->patient_id,
                    encounterId: (int) $locked->encounter_id,
                    eventType: 'billing.payment_received',
                    title: 'استُلم مبلغ '.$amount,
                    description: $method->label(),
                    payload: [
                        'invoice_id' => $locked->getKey(),
                        'payment_id' => $payment->getKey(),
                        'balance' => $locked->balance,
                    ],
                );
            }

            return $payment->load(['invoice', 'receivedBy']);
        });
    }
}
