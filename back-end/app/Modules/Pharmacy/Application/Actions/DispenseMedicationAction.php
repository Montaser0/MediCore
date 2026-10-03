<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\StockBatch;
use App\Core\Support\Contracts\StockService;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Pharmacy\Domain\Enums\PrescriptionStatus;
use App\Modules\Pharmacy\Domain\Events\MedicationDispensed;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationDispenseModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionModel;
use Illuminate\Support\Facades\DB;

/**
 * Handing medicine to the patient, which the PRD singles out as the operation
 * that must be all-or-nothing. One transaction covers the whole sequence:
 *
 *   validate the prescription → check stock → pick the batch (nearest expiry,
 *   never an expired one) → record the dispense → move the stock → update the
 *   prescription → announce it so billing raises the charge.
 *
 * If any step fails, nothing happened: no stock left the shelf and no charge
 * was raised.
 */
final class DispenseMedicationAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly NumberGenerator $numbers,
        private readonly CurrentUser $currentUser,
        private readonly ChangePrescriptionStatusAction $changeStatus,
        private readonly TimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(PrescriptionItemModel $item, array $data): MedicationDispenseModel
    {
        $item->loadMissing(['prescription.encounter', 'medication']);

        $prescription = $item->prescription;
        $medication = $item->medication;

        if ($prescription === null || $medication === null) {
            throw BusinessRuleException::make('بند الوصفة غير مكتمل.');
        }

        if (! $prescription->status->isDispensable()) {
            throw BusinessRuleException::make(
                'لا يمكن الصرف والوصفة حالتها «'.$prescription->status->label().'».',
                ['status' => $prescription->status->value],
            );
        }

        $quantity = (float) $data['quantity'];

        if ($quantity <= 0) {
            throw BusinessRuleException::make('كمية الصرف يجب أن تكون أكبر من صفر.');
        }

        $this->guardAgainstOverDispensing($item, $quantity);

        if ($medication->inventory_item_id === null) {
            throw BusinessRuleException::make(
                "الدواء {$medication->displayName()} غير مربوط بصنف مخزون، ولا يمكن صرفه.",
                ['medication_id' => $medication->getKey()],
            );
        }

        $warehouseId = (int) $data['warehouse_id'];

        return DB::transaction(function () use ($item, $prescription, $medication, $quantity, $warehouseId, $data): MedicationDispenseModel {
            // Draws stock, refusing expired batches and an overdrawn balance.
            $drawn = $this->stock->issue(
                inventoryItemId: (int) $medication->inventory_item_id,
                warehouseId: $warehouseId,
                quantity: $quantity,
                movementType: 'dispense',
                referenceType: 'prescription_item',
                referenceId: (int) $item->getKey(),
                batchId: isset($data['batch_id']) ? (int) $data['batch_id'] : null,
                notes: $data['notes'] ?? null,
            );

            $primary = $drawn[0] ?? null;

            $dispense = MedicationDispenseModel::query()->create([
                'hospital_id' => $prescription->hospital_id,
                'dispense_number' => $this->numbers->dispenseNumber((int) $prescription->hospital_id),
                'prescription_id' => $prescription->getKey(),
                'prescription_item_id' => $item->getKey(),
                'encounter_id' => $prescription->encounter_id,
                'medication_id' => $medication->getKey(),
                'warehouse_id' => $warehouseId,
                'inventory_batch_id' => $primary?->id,
                'quantity' => $quantity,
                'unit_price' => $medication->price,
                'total_price' => round((float) $medication->price * $quantity, 2),
                'batch_number' => $this->describeBatches($drawn),
                'batch_expiry_date' => $primary?->expiryDate,
                'dispensed_by' => $this->currentUser->id(),
                'dispensed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $item->forceFill([
                'dispensed_quantity' => (float) $item->dispensed_quantity + $quantity,
            ])->save();

            $this->advancePrescription($prescription);

            // Billing listens for this and raises the charge in the same
            // transaction, so stock and money move together.
            MedicationDispensed::dispatch($dispense);

            $encounter = $prescription->encounter;

            if ($encounter !== null) {
                $this->timeline->record(
                    patientId: (int) $encounter->patient_id,
                    encounterId: (int) $encounter->getKey(),
                    eventType: 'pharmacy.medication_dispensed',
                    title: 'صُرف '.$medication->displayName(),
                    description: $quantity.' × '.$medication->displayName(),
                    departmentId: $encounter->department_id,
                    payload: [
                        'dispense_id' => $dispense->getKey(),
                        'prescription_id' => $prescription->getKey(),
                    ],
                );
            }

            return $dispense->load(['medication', 'warehouse', 'dispensedBy']);
        });
    }

    private function guardAgainstOverDispensing(PrescriptionItemModel $item, float $quantity): void
    {
        if ($item->quantity === null) {
            return;
        }

        $remaining = $item->remainingQuantity();

        if ($quantity > $remaining + 1e-6) {
            throw BusinessRuleException::make(
                "المتبقي للصرف من هذا البند هو {$remaining} فقط.",
                [
                    'prescribed' => $item->quantity,
                    'already_dispensed' => $item->dispensed_quantity,
                    'remaining' => $remaining,
                ],
            );
        }
    }

    private function advancePrescription(PrescriptionModel $prescription): void
    {
        $prescription->refresh();

        $target = $prescription->isFullyDispensed()
            ? PrescriptionStatus::Dispensed
            : PrescriptionStatus::PartiallyDispensed;

        if ($prescription->status->canTransitionTo($target)) {
            $this->changeStatus->execute($prescription, $target);
        }
    }

    /**
     * A single dispense can span batches when one runs out mid-quantity; the
     * label has to show all of them.
     *
     * @param  array<int, StockBatch>  $batches
     */
    private function describeBatches(array $batches): ?string
    {
        if ($batches === []) {
            return null;
        }

        return implode(', ', array_map(
            static fn (StockBatch $batch) => $batch->batchNumber.' ('.$batch->quantity.')',
            $batches,
        ));
    }
}
