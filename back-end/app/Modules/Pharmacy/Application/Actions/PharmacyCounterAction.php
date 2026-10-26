<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Core\Support\Contracts\StockService;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Encounters\Application\Actions\ChangeEncounterStatusAction;
use App\Modules\Encounters\Application\Actions\CloseEncounterAction;
use App\Modules\Encounters\Application\Actions\CreateEncounterAction;
use App\Modules\Encounters\Domain\Enums\EncounterStatus;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyCounterTicketModel;
use Illuminate\Support\Facades\DB;

/**
 * Reception sends a patient who came only to buy medicine.
 * The hospital pharmacy sells from stock and the bill lands on the patient account.
 */
final class PharmacyCounterAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly CreatePharmacySaleAction $sales,
        private readonly StockService $stock,
        private readonly CreateEncounterAction $encounters,
        private readonly ChangeEncounterStatusAction $changeEncounter,
        private readonly CloseEncounterAction $closeEncounter,
        private readonly CreateChargeAction $charges,
        private readonly TimelineRecorder $timeline,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function send(PatientModel $patient): PharmacyCounterTicketModel
    {
        $hospitalId = $this->sales->hospitalId();
        if ((int) $patient->hospital_id !== $hospitalId) {
            throw BusinessRuleException::make('المريض لا يتبع هذه المنشأة.');
        }

        $existing = PharmacyCounterTicketModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('patient_id', $patient->getKey())
            ->where('status', 'waiting')
            ->first();

        if ($existing instanceof PharmacyCounterTicketModel) {
            return $existing->load('patient');
        }

        $ticket = PharmacyCounterTicketModel::query()->create([
            'hospital_id' => $hospitalId,
            'patient_id' => $patient->getKey(),
            'status' => 'waiting',
            'sent_by' => $this->currentUser->id(),
            'sent_at' => now(),
        ]);

        $this->timeline->record(
            patientId: (int) $patient->getKey(),
            encounterId: null,
            eventType: 'pharmacy.counter_sent',
            title: 'تحويل إلى صيدلية المشفى',
            description: 'صرف أدوية فقط',
        );

        $this->notifications->toPermissionHolders(
            permission: 'pharmacy.dispensing.create',
            type: 'pharmacy.counter_sent',
            title: 'مريض للصرف من الصيدلية',
            message: $patient->full_name,
            entityType: 'pharmacy_counter_ticket',
            entityId: (int) $ticket->getKey(),
            payload: ['patient_id' => $patient->getKey()],
        );

        return $ticket->load('patient');
    }

    /**
     * @param  array<int, array{medication_id: int, quantity: float|int|string}>  $lines
     */
    public function sell(PharmacyCounterTicketModel $ticket, array $lines): PharmacyCounterTicketModel
    {
        if ($ticket->status !== 'waiting') {
            throw BusinessRuleException::make('هذا التحويل لم يعد بانتظار الصرف.');
        }

        if ((int) $ticket->hospital_id !== $this->sales->hospitalId()) {
            throw BusinessRuleException::make('هذا التحويل لا يتبع هذه المنشأة.');
        }

        if ($lines === []) {
            throw BusinessRuleException::make('أضف دواءً قبل إتمام البيع.');
        }

        return DB::transaction(function () use ($ticket, $lines): PharmacyCounterTicketModel {
            $locked = PharmacyCounterTicketModel::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $patient = PatientModel::query()->findOrFail($locked->patient_id);
            $hospitalId = (int) $locked->hospital_id;

            $priced = [];
            foreach ($lines as $line) {
                $quantity = round((float) $line['quantity'], 2);
                if ($quantity <= 0) {
                    throw BusinessRuleException::make('الكمية يجب أن تكون أكبر من صفر.');
                }

                $medication = MedicationModel::query()
                    ->whereKey((int) $line['medication_id'])
                    ->where('hospital_id', $hospitalId)
                    ->lockForUpdate()
                    ->first();

                if ($medication === null || ! $medication->is_active) {
                    throw BusinessRuleException::make('الدواء غير متاح في صيدلية المشفى.');
                }

                $priced[] = ['medication' => $medication, 'quantity' => $quantity];
            }

            $encounter = $this->encounters->execute($patient, [
                'type' => 'outpatient',
                'allow_parallel' => true,
                'reason' => 'صرف أدوية فقط',
                'chief_complaint' => 'صرف أدوية من صيدلية المشفى فقط',
            ]);
            $encounter = $this->changeEncounter->execute($encounter, EncounterStatus::InProgress, 'بيع من نقطة صيدلية المشفى');

            foreach ($priced as $row) {
                /** @var MedicationModel $medication */
                $medication = $row['medication'];
                $quantity = $row['quantity'];
                $this->takeStock($medication, $hospitalId, $quantity, (int) $locked->getKey());

                $this->charges->execute([
                    'encounter_id' => $encounter->getKey(),
                    'description' => $medication->name,
                    'quantity' => $quantity,
                    'unit_price' => $medication->price,
                    'source_type' => ChargeSource::PharmacyCounter->value,
                ]);
            }

            $this->closeEncounter->execute($encounter, [
                'note' => 'أُنجز بيع صيدلية المشفى',
                'discharge_instructions' => 'صرف أدوية فقط',
            ]);

            $locked->forceFill([
                'status' => 'completed',
                'encounter_id' => $encounter->getKey(),
                'completed_at' => now(),
            ])->save();

            return $locked->load('patient');
        });
    }

    public function cancel(PharmacyCounterTicketModel $ticket): PharmacyCounterTicketModel
    {
        if ($ticket->status !== 'waiting') {
            throw BusinessRuleException::make('لا يمكن إلغاء تحويل اكتمل صرفه.');
        }

        $ticket->forceFill([
            'status' => 'cancelled',
            'completed_at' => now(),
        ])->save();

        return $ticket->load('patient');
    }

    private function takeStock(MedicationModel $medication, int $hospitalId, float $quantity, int $ticketId): void
    {
        if ($medication->inventory_item_id !== null) {
            $warehouseId = $this->sales->pharmacyWarehouseId($hospitalId, (int) $medication->inventory_item_id);
            $onHand = $this->stock->availableQuantity((int) $medication->inventory_item_id, $warehouseId);
            if ($onHand + 1e-6 < $quantity) {
                throw BusinessRuleException::make('الكمية غير كافية: '.$medication->name);
            }

            $this->stock->issue(
                inventoryItemId: (int) $medication->inventory_item_id,
                warehouseId: $warehouseId,
                quantity: $quantity,
                movementType: 'sale',
                referenceType: 'pharmacy_counter_ticket',
                referenceId: $ticketId,
            );

            return;
        }

        $onHand = round((float) $medication->quantity, 2);
        if ($onHand < $quantity) {
            throw BusinessRuleException::make('الكمية غير كافية: '.$medication->name);
        }

        $medication->quantity = round($onHand - $quantity, 2);
        $medication->save();
    }
}
