<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisModel
 */
final class DiagnosisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'diagnosis_name' => $this->diagnosis_name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'notes' => $this->notes,
            'diagnosed_at' => $this->diagnosed_at?->toIso8601String(),
            'code' => $this->whenLoaded('code', fn () => [
                'id' => $this->code?->id,
                'system' => $this->code?->system,
                'code' => $this->code?->code,
                'title' => $this->code?->title,
            ]),
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor?->id,
                'name' => $this->doctor?->display_name,
            ]),
        ];
    }
}
