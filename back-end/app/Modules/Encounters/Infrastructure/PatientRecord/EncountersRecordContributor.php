<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;

final class EncountersRecordContributor implements PatientRecordContributor
{
    public function section(): string
    {
        return 'encounters';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $base = EncounterModel::query()->where('patient_id', $patientId);

        $current = (clone $base)->open()->with(['department', 'doctor.staff'])->latest()->first();

        $recent = (clone $base)
            ->with(['department', 'doctor.staff'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->open()->count(),
            'current' => $current === null ? null : $this->format($current),
            'recent' => $recent->map(fn (EncounterModel $encounter) => $this->format($encounter))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function format(EncounterModel $encounter): array
    {
        return [
            'id' => $encounter->getKey(),
            'encounter_number' => $encounter->encounter_number,
            'type' => $encounter->type->value,
            'status' => $encounter->status->value,
            'department' => $encounter->department?->name,
            'doctor' => $encounter->doctor?->staff?->full_name,
            'started_at' => $encounter->started_at?->toIso8601String(),
            'ended_at' => $encounter->ended_at?->toIso8601String(),
            'created_at' => $encounter->created_at?->toIso8601String(),
        ];
    }
}
