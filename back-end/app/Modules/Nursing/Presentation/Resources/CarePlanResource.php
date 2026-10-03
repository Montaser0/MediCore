<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Nursing\Infrastructure\Persistence\Models\CarePlanModel
 */
final class CarePlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'problem' => $this->problem,
            'goal' => $this->goal,
            'interventions' => $this->interventions,
            'evaluation' => $this->evaluation,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'start_date' => $this->start_date?->toDateString(),
            'target_date' => $this->target_date?->toDateString(),
            'resolved_date' => $this->resolved_date?->toDateString(),
            'encounter_id' => $this->encounter_id,
            'author' => $this->whenLoaded('author', fn () => $this->author?->displayName()),
        ];
    }
}
