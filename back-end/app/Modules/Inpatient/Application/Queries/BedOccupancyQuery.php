<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Application\Queries;

use App\Modules\Inpatient\Domain\Enums\BedStatus;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\WardModel;
use Illuminate\Support\Collection;

/**
 * The ward board: every bed, who is in it, and how full each ward is.
 */
final class BedOccupancyQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(?int $wardId = null): array
    {
        $wards = WardModel::query()
            ->active()
            ->forCurrentHospital()
            ->when($wardId !== null, fn ($query) => $query->whereKey($wardId))
            ->with([
                'rooms' => fn ($query) => $query->active()->orderBy('room_number'),
                'rooms.beds.currentAssignment.admission.patient',
                'department',
            ])
            ->orderBy('name')
            ->get();

        $board = $wards->map(function (WardModel $ward): array {
            $beds = $ward->rooms->flatMap(fn ($room) => $room->beds);

            return [
                'ward' => [
                    'id' => $ward->getKey(),
                    'name' => $ward->name,
                    'code' => $ward->code,
                    'type' => $ward->type,
                    'department' => $ward->department?->name,
                    'daily_rate' => $ward->daily_rate,
                ],
                'counts' => $this->countByStatus($beds),
                'occupancy_rate' => $this->occupancyRate($beds),
                'rooms' => $ward->rooms->map(fn ($room) => [
                    'id' => $room->getKey(),
                    'room_number' => $room->room_number,
                    'room_type' => $room->room_type,
                    'capacity' => $room->capacity,
                    'daily_rate' => $room->daily_rate,
                    'beds' => $room->beds->map(fn (BedModel $bed) => [
                        'id' => $bed->getKey(),
                        'bed_number' => $bed->bed_number,
                        'status' => $bed->status->value,
                        'status_label' => $bed->status->label(),
                        'occupant' => $bed->currentAssignment === null ? null : [
                            'admission_id' => $bed->currentAssignment->admission_id,
                            'admission_number' => $bed->currentAssignment->admission?->admission_number,
                            'patient_id' => $bed->currentAssignment->admission?->patient_id,
                            'patient_name' => $bed->currentAssignment->admission?->patient?->full_name,
                            'mrn' => $bed->currentAssignment->admission?->patient?->mrn,
                            'assigned_at' => $bed->currentAssignment->assigned_at?->toIso8601String(),
                            'nights' => $bed->currentAssignment->nights(),
                        ],
                    ])->values()->all(),
                ])->values()->all(),
            ];
        });

        $allBeds = $wards->flatMap(fn (WardModel $ward) => $ward->rooms->flatMap(fn ($room) => $room->beds));

        return [
            'summary' => [
                'total_beds' => $allBeds->count(),
                'counts' => $this->countByStatus($allBeds),
                'occupancy_rate' => $this->occupancyRate($allBeds),
            ],
            'wards' => $board->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, BedModel>  $beds
     * @return array<string, int>
     */
    private function countByStatus(Collection $beds): array
    {
        $counts = [];

        foreach (BedStatus::cases() as $status) {
            $counts[$status->value] = $beds->where('status', $status)->count();
        }

        return $counts;
    }

    /**
     * @param  Collection<int, BedModel>  $beds
     */
    private function occupancyRate(Collection $beds): float
    {
        if ($beds->isEmpty()) {
            return 0.0;
        }

        return round($beds->where('status', BedStatus::Occupied)->count() / $beds->count() * 100, 1);
    }
}
