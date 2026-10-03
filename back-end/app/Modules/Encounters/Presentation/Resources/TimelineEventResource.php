<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Presentation\Resources;

use App\Modules\Encounters\Infrastructure\Persistence\Models\TimelineEventModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimelineEventModel
 */
final class TimelineEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'event_type' => $this->event_type,
            'title' => $this->title,
            'description' => $this->description,
            'payload' => $this->payload,
            'department' => $this->whenLoaded('department', fn () => $this->department === null ? null : [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'actor' => $this->whenLoaded('actor', fn () => $this->actor === null ? null : [
                'id' => $this->actor->id,
                'name' => $this->actor->displayName(),
            ]),
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'occurred_on' => $this->occurred_at->toDateString(),
            'occurred_time' => $this->occurred_at->format('H:i'),
        ];
    }
}
