<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Application\Queries;

use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use App\Modules\Nursing\Infrastructure\Persistence\Models\MedicationAdministrationModel;
use Illuminate\Support\Carbon;

/**
 * What the nurse on shift has to do, with overdue doses first because they
 * are the ones that matter.
 */
final class NursingWorklistQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(?int $wardId = null, ?Carbon $until = null): array
    {
        $until ??= now()->addHours(4);

        $doses = MedicationAdministrationModel::query()
            ->with(['medication', 'patient', 'encounter'])
            ->forCurrentHospital()
            ->due()
            ->where('scheduled_at', '<=', $until)
            ->when($wardId !== null, fn ($query) => $query->whereIn(
                'encounter_id',
                $this->encounterIdsInWard($wardId),
            ))
            ->orderBy('scheduled_at')
            ->get();

        return [
            'counts' => [
                'overdue' => $doses->filter(fn (MedicationAdministrationModel $dose) => $dose->isOverdue())->count(),
                'upcoming' => $doses->reject(fn (MedicationAdministrationModel $dose) => $dose->isOverdue())->count(),
            ],
            'doses' => $doses->map(fn (MedicationAdministrationModel $dose) => [
                'id' => $dose->getKey(),
                'patient_id' => $dose->patient_id,
                'patient_name' => $dose->patient?->full_name,
                'mrn' => $dose->patient?->mrn,
                'encounter_id' => $dose->encounter_id,
                'medication' => $dose->medication?->displayName(),
                'dose' => $dose->dose,
                'route' => $dose->route,
                'scheduled_at' => $dose->scheduled_at?->toIso8601String(),
                'is_overdue' => $dose->isOverdue(),
            ])->values()->all(),
        ];
    }

    /**
     * Visits whose patient currently occupies a bed in the given ward.
     *
     * @return array<int, int>
     */
    private function encounterIdsInWard(int $wardId): array
    {
        return AdmissionModel::query()
            ->active()
            ->whereHas(
                'currentBedAssignment.bed.room',
                fn ($room) => $room->where('ward_id', $wardId),
            )
            ->pluck('encounter_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
