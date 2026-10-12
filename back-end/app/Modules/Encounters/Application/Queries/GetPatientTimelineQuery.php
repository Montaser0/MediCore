<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Application\Queries;

use App\Core\Authorization\RecordVisibility;
use App\Modules\Encounters\Infrastructure\Persistence\Models\TimelineEventModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Reads the timeline the whole system writes into.
 */
final class GetPatientTimelineQuery
{
    /**
     * @param  array<int, int>|null  $departmentIds
     * @param  array<int, int>|null  $clinicIds
     * @return LengthAwarePaginator<int, TimelineEventModel>
     */
    public function execute(int $patientId, ?int $encounterId = null, ?string $eventType = null, int $perPage = 100, ?array $departmentIds = null, ?array $clinicIds = null): LengthAwarePaginator
    {
        $query = TimelineEventModel::query()
            ->with(['actor.staff', 'actor.roles', 'department:id,name'])
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($inner) => $inner->where('encounter_id', $encounterId))
            ->when($eventType !== null, fn ($inner) => $inner->where('event_type', 'like', $eventType.'%'))
            ->when($clinicIds !== null, fn ($inner) => $inner->whereHas(
                'encounter',
                fn ($encounter) => $encounter->whereIn('clinic_id', $clinicIds),
            ))
            ->when($departmentIds !== null, fn ($inner) => $inner->whereIn('department_id', $departmentIds));

        foreach (RecordVisibility::hiddenEventPrefixes() as $prefix) {
            $query->where('event_type', 'not like', $prefix.'%');
        }

        return $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
