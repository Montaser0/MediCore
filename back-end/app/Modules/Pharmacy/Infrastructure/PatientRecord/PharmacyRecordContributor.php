<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationDispenseModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionModel;
use Illuminate\Database\Eloquent\Builder;

final class PharmacyRecordContributor implements PatientRecordContributor
{
    public function section(): string
    {
        return 'pharmacy';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $filter = function (Builder $query) use ($patientId, $encounterId): void {
            $query->where('patient_id', $patientId);

            if ($encounterId !== null) {
                $query->whereKey($encounterId);
            }
        };

        $prescriptions = PrescriptionModel::query()
            ->with(['items.medication', 'doctor.staff', 'enteredBy.staff', 'enteredBy.roles'])
            ->whereHas('encounter', $filter)
            ->orderByDesc('prescribed_at')
            ->limit(20)
            ->get();

        $dispenses = MedicationDispenseModel::query()
            ->with(['medication', 'dispensedBy'])
            ->whereHas('encounter', $filter)
            ->orderByDesc('dispensed_at')
            ->limit(20)
            ->get();

        return [
            'prescriptions' => $prescriptions->map(fn (PrescriptionModel $prescription) => [
                'id' => $prescription->getKey(),
                'prescription_number' => $prescription->prescription_number,
                'status' => $prescription->status->value,
                'fulfillment' => $prescription->fulfillment ?? 'hospital',
                'doctor' => $prescription->doctor?->staff?->full_name,
                'by' => $prescription->enteredBy?->actorLabel() ?? $prescription->doctor?->staff?->full_name,
                'prescribed_at' => $prescription->prescribed_at?->toIso8601String(),
                'medications' => $prescription->items->map(fn ($item) => [
                    'name' => $item->medication?->name ?? $item->medication_name,
                    'sig' => $item->sig(),
                    'price' => $item->medication?->price,
                    'quantity' => $item->quantity,
                    'dispensed_quantity' => $item->dispensed_quantity,
                ])->all(),
            ])->all(),
            'dispenses' => $dispenses->map(fn (MedicationDispenseModel $dispense) => [
                'id' => $dispense->getKey(),
                'dispense_number' => $dispense->dispense_number,
                'medication' => $dispense->medication?->name,
                'quantity' => $dispense->quantity,
                'total_price' => $dispense->total_price,
                'batch_number' => $dispense->batch_number,
                'by' => $dispense->dispensedBy?->displayName(),
                'dispensed_at' => $dispense->dispensed_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
