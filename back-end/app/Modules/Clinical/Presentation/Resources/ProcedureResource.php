<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Clinical\Infrastructure\Persistence\Models\ProcedureModel
 */
final class ProcedureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'name' => $this->name,
            'code' => $this->code,
            'service_id' => $this->service_id,
            'description' => $this->description,
            'outcome' => $this->outcome,
            'performed_at' => $this->performed_at?->toIso8601String(),
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor?->id,
                'name' => $this->doctor?->display_name,
            ]),
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy === null ? null : [
                'id' => $this->recordedBy->id,
                'name' => $this->recordedBy->actorLabel(),
            ]),
        ];
    }
}
