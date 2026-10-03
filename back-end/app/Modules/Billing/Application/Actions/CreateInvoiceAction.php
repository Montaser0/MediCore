<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Infrastructure\Persistence\Models\ChargeModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\PatientInsuranceModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns the outstanding charges into an invoice. Charges are locked and
 * stamped with the invoice id so a second cashier cannot bill them again.
 */
final class CreateInvoiceAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly CurrentUser $currentUser,
        private readonly RecalculateInvoiceAction $recalculate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): InvoiceModel
    {
        return DB::transaction(function () use ($data): InvoiceModel {
            $charges = $this->lockCharges($data);

            if ($charges->isEmpty()) {
                throw BusinessRuleException::make('لا توجد بنود غير مفوترة.');
            }

            $first = $charges->first();

            $invoice = InvoiceModel::query()->create([
                'hospital_id' => $first->hospital_id,
                'patient_id' => $first->patient_id,
                'encounter_id' => $data['encounter_id'] ?? $first->encounter_id,
                'invoice_number' => $this->numbers->invoiceNumber((int) $first->hospital_id),
                'status' => InvoiceStatus::Draft->value,
                'notes' => $data['notes'] ?? null,
                'created_by' => $this->currentUser->id(),
            ]);

            foreach ($charges as $charge) {
                $invoice->items()->create([
                    'charge_id' => $charge->getKey(),
                    'description' => $charge->description,
                    'quantity' => $charge->quantity,
                    'unit_price' => $charge->unit_price,
                    'discount' => $charge->discount,
                    'total' => $charge->total,
                ]);

                $charge->forceFill(['invoice_id' => $invoice->getKey()])->save();
            }

            $invoiceDiscount = (float) ($data['discount'] ?? 0);
            $taxRate = $this->resolveTaxRate($data);

            $invoice = $this->recalculate->execute($invoice, $invoiceDiscount, $taxRate);

            if (($data['issue'] ?? true) === true) {
                $invoice->forceFill([
                    'status' => InvoiceStatus::Issued->value,
                    'issued_at' => now(),
                ])->save();
            }

            return $invoice->load(['items', 'patient', 'encounter']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, ChargeModel>
     */
    private function lockCharges(array $data): Collection
    {
        return ChargeModel::query()
            ->uninvoiced()
            ->when(
                ! empty($data['charge_ids']),
                fn ($query) => $query->whereIn('id', (array) $data['charge_ids']),
                fn ($query) => $query
                    ->where('patient_id', $data['patient_id'] ?? 0)
                    ->when($data['encounter_id'] ?? null, fn ($inner, $value) => $inner->where('encounter_id', $value)),
            )
            ->lockForUpdate()
            ->get();
    }

    /**
     * An in-date insurance policy replaces the standard tax rate handling
     * with its coverage percentage applied as a discount instead.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveTaxRate(array $data): float
    {
        if (isset($data['tax_rate'])) {
            return (float) $data['tax_rate'];
        }

        return (float) config('hms.billing.tax_rate', 0);
    }

    /**
     * Coverage available to a patient right now, used by the cashier screen.
     */
    public function insuranceCoverageFor(int $patientId): ?PatientInsuranceModel
    {
        return PatientInsuranceModel::query()
            ->with('company')
            ->where('patient_id', $patientId)
            ->validNow()
            ->orderByDesc('is_primary')
            ->first();
    }
}
