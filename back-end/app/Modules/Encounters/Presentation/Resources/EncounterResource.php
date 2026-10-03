<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Presentation\Resources;

use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Patients\Presentation\Resources\PatientHeaderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EncounterModel
 */
final class EncounterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_number' => $this->encounter_number,
            'patient_id' => $this->patient_id,
            'type' => $this->type->value,
            'requires_admission' => $this->type->requiresAdmission(),
            'status' => $this->status->value,
            'is_open' => $this->status->isOpen(),
            'allowed_transitions' => array_map(
                static fn ($status) => $status->value,
                $this->status->allowedTransitions(),
            ),

            'reason' => $this->reason,
            'chief_complaint' => $this->chief_complaint,

            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'duration_minutes' => $this->durationInMinutes(),

            'discharge_instructions' => $this->discharge_instructions,
            'discharge_disposition' => $this->discharge_disposition,
            'follow_up_date' => $this->follow_up_date?->toDateString(),

            'department' => $this->whenLoaded('department', fn () => $this->department === null ? null : [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'type' => $this->department->type->value,
            ]),
            'clinic' => $this->whenLoaded('clinic', fn () => $this->clinic === null ? null : [
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
            ]),
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor === null ? null : [
                'id' => $this->doctor->id,
                'name' => $this->doctor->staff?->full_name,
                'specialty' => $this->doctor->specialty,
            ]),
            'patient' => $this->whenLoaded('patient', fn () => new PatientHeaderResource($this->patient)),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
