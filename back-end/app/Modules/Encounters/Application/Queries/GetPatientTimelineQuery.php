<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Application\Queries;

use App\Modules\Encounters\Infrastructure\Persistence\Models\TimelineEventModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Reads the timeline the whole system writes into.
 */
final class GetPatientTimelineQuery
{
    /**
     * @return LengthAwarePaginator<int, TimelineEventModel>
     */
    public function execute(int $patientId, ?int $encounterId = null, ?string $eventType = null, int $perPage = 100): LengthAwarePaginator
    {
        return TimelineEventModel::query()
            ->with(['actor.staff', 'department:id,name'])
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId))
            ->when($eventType !== null, fn ($query) => $query->where('event_type', 'like', $eventType.'%'))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
