<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;

final class InpatientRecordContributor implements PatientRecordContributor
{
    public function section(): string
    {
        return 'admissions';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $admissions = AdmissionModel::query()
            ->with(['department', 'attendingDoctor', 'admittedBy', 'currentBedAssignment.bed.room.ward'])
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId))
            ->orderByDesc('admitted_at')
            ->limit(20)
            ->get();

        return [
            'total' => $admissions->count(),
            'currently_admitted' => $admissions->contains(fn (AdmissionModel $a) => $a->status->isActive()),
            'items' => $admissions->map(fn (AdmissionModel $admission) => [
                'id' => $admission->getKey(),
                'admission_number' => $admission->admission_number,
                'status' => $admission->status->value,
                'admitted_at' => $admission->admitted_at?->toIso8601String(),
                'discharged_at' => $admission->discharged_at?->toIso8601String(),
                'length_of_stay_days' => $admission->lengthOfStayDays(),
                'department' => $admission->department?->name,
                'attending_doctor' => $admission->attendingDoctor?->displayName(),
                'by' => $admission->admittedBy?->displayName(),
                'bed' => $admission->currentBedAssignment?->bed?->fullLabel(),
                'discharge_type' => $admission->discharge_type?->value,
            ])->all(),
        ];
    }
}
