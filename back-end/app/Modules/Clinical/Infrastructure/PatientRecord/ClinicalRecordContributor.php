<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\ProcedureModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\VitalSignModel;
use Illuminate\Database\Eloquent\Builder;

final class ClinicalRecordContributor implements PatientRecordContributor
{
    public function section(): string
    {
        return 'clinical';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $diagnoses = DiagnosisModel::query()
            ->with(['code', 'doctor.staff', 'enteredBy.staff', 'enteredBy.roles'])
            ->whereHas('encounter', $this->encounterFilter($patientId, $encounterId))
            ->orderByDesc('diagnosed_at')
            ->limit(20)
            ->get();

        $latestVitals = VitalSignModel::query()
            ->with(['recordedBy.staff', 'recordedBy.roles'])
            ->whereHas('encounter', $this->encounterFilter($patientId, $encounterId))
            ->orderByDesc('recorded_at')
            ->first();

        $procedures = ProcedureModel::query()
            ->with(['doctor.staff', 'recordedBy.staff', 'recordedBy.roles'])
            ->whereHas('encounter', $this->encounterFilter($patientId, $encounterId))
            ->orderByDesc('performed_at')
            ->limit(20)
            ->get();

        return [
            'diagnoses' => $diagnoses->map(fn (DiagnosisModel $diagnosis) => [
                'id' => $diagnosis->getKey(),
                'name' => $diagnosis->diagnosis_name,
                'code' => $diagnosis->code?->code,
                'type' => $diagnosis->type->value,
                'doctor' => $diagnosis->doctor?->staff?->full_name,
                'by' => $diagnosis->enteredBy?->actorLabel() ?? $diagnosis->doctor?->staff?->full_name,
                'diagnosed_at' => $diagnosis->diagnosed_at?->toIso8601String(),
            ])->all(),
            'latest_vitals' => $latestVitals === null ? null : [
                'temperature' => $latestVitals->temperature,
                'blood_pressure' => $latestVitals->bloodPressure(),
                'heart_rate' => $latestVitals->heart_rate,
                'respiratory_rate' => $latestVitals->respiratory_rate,
                'oxygen_saturation' => $latestVitals->oxygen_saturation,
                'weight' => $latestVitals->weight,
                'height' => $latestVitals->height,
                'bmi' => $latestVitals->bmi(),
                'abnormalities' => $latestVitals->abnormalities(),
                'recorded_at' => $latestVitals->recorded_at?->toIso8601String(),
                'by' => $latestVitals->recordedBy?->actorLabel(),
            ],
            'procedures' => $procedures->map(fn (ProcedureModel $procedure) => [
                'id' => $procedure->getKey(),
                'name' => $procedure->name,
                'doctor' => $procedure->doctor?->staff?->full_name,
                'by' => $procedure->recordedBy?->actorLabel() ?? $procedure->doctor?->staff?->full_name,
                'performed_at' => $procedure->performed_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Clinical rows hang off encounters, so the patient filter is applied to
     * the parent visit; a specific visit narrows it further.
     */
    private function encounterFilter(int $patientId, ?int $encounterId): callable
    {
        return function (Builder $query) use ($patientId, $encounterId): void {
            $query->where('patient_id', $patientId);

            if ($encounterId !== null) {
                $query->whereKey($encounterId);
            }
        };
    }
}
