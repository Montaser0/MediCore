<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Encounters\Infrastructure\Persistence\Models\TimelineEventModel;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecordTimelineEventAction implements TimelineRecorder
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        int $patientId,
        ?int $encounterId,
        string $eventType,
        string $title,
        ?string $description = null,
        ?int $departmentId = null,
        array $payload = [],
    ): void {
        try {
            TimelineEventModel::query()->create([
                'patient_id' => $patientId,
                'encounter_id' => $encounterId,
                'department_id' => $departmentId ?? $this->currentUser->departmentId(),
                'actor_id' => $this->currentUser->id(),
                'event_type' => $eventType,
                'title' => $title,
                'description' => $description,
                'payload' => $payload === [] ? null : $payload,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $e) {
            // The timeline is a read model: losing an entry must not roll back
            // the clinical work that produced it.
            Log::error('Failed to append timeline event', [
                'patient_id' => $patientId,
                'event_type' => $eventType,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
