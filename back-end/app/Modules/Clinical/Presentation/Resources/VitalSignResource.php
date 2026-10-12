<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Clinical\Infrastructure\Persistence\Models\VitalSignModel
 */
final class VitalSignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'temperature' => $this->temperature,
            'systolic_bp' => $this->systolic_bp,
            'diastolic_bp' => $this->diastolic_bp,
            'blood_pressure' => $this->bloodPressure(),
            'heart_rate' => $this->heart_rate,
            'respiratory_rate' => $this->respiratory_rate,
            'oxygen_saturation' => $this->oxygen_saturation,
            'weight' => $this->weight,
            'height' => $this->height,
            'bmi' => $this->bmi(),
            'abnormalities' => $this->abnormalities(),
            'notes' => $this->notes,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => [
                'id' => $this->recordedBy->id,
                'name' => $this->recordedBy->actorLabel(),
            ]),
        ];
    }
}
