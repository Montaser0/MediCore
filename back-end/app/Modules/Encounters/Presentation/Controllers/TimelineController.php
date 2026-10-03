<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Encounters\Application\Queries\GetPatientTimelineQuery;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Encounters\Presentation\Resources\TimelineEventResource;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The chronological view of everything that happened to a patient.
 */
final class TimelineController extends ApiController
{
    public function forPatient(Request $request, PatientModel $patient, GetPatientTimelineQuery $query): JsonResponse
    {
        $events = $query->execute(
            patientId: $patient->getKey(),
            encounterId: $request->integer('encounter_id') ?: null,
            eventType: $request->string('event_type')->toString() ?: null,
            perPage: $this->perPage(100),
        );

        $collection = TimelineEventResource::collection($events);

        return ApiResponse::data([
            'events' => $collection->resolve(),
            'grouped_by_day' => collect($collection->resolve())
                ->groupBy('occurred_on')
                ->map(fn ($group, $date) => ['date' => $date, 'events' => $group->values()->all()])
                ->values()
                ->all(),
        ], [
            'current_page' => $events->currentPage(),
            'per_page' => $events->perPage(),
            'total' => $events->total(),
            'last_page' => $events->lastPage(),
        ]);
    }

    public function forEncounter(EncounterModel $encounter, GetPatientTimelineQuery $query): JsonResponse
    {
        $events = $query->execute(
            patientId: (int) $encounter->patient_id,
            encounterId: $encounter->getKey(),
            perPage: 200,
        );

        return ApiResponse::data(TimelineEventResource::collection($events)->resolve());
    }
}
