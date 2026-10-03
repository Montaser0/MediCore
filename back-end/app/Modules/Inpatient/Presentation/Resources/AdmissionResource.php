<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel
 */
final class AdmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_number' => $this->admission_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'admitted_at' => $this->admitted_at?->toIso8601String(),
            'expected_discharge_at' => $this->expected_discharge_at?->toIso8601String(),
            'discharged_at' => $this->discharged_at?->toIso8601String(),
            'discharge_type' => $this->discharge_type?->value,
            'discharge_type_label' => $this->discharge_type?->label(),
            'discharge_summary' => $this->discharge_summary,
            'admission_reason' => $this->admission_reason,
            'length_of_stay_days' => $this->lengthOfStayDays(),
            'encounter_id' => $this->encounter_id,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'mrn' => $this->patient?->mrn,
                'full_name' => $this->patient?->full_name,
                'gender' => $this->patient?->gender?->value,
                'age' => $this->patient?->age,
            ]),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department?->id,
                'name' => $this->department?->name,
            ]),
            'attending_doctor' => $this->whenLoaded('attendingDoctor', fn () => [
                'id' => $this->attendingDoctor?->id,
                'name' => $this->attendingDoctor?->displayName(),
            ]),
            'current_bed' => $this->whenLoaded('currentBedAssignment', fn () => $this->currentBedAssignment === null ? null : [
                'assignment_id' => $this->currentBedAssignment->id,
                'bed_id' => $this->currentBedAssignment->bed_id,
                'label' => $this->currentBedAssignment->bed?->fullLabel(),
                'assigned_at' => $this->currentBedAssignment->assigned_at?->toIso8601String(),
                'nights' => $this->currentBedAssignment->nights(),
            ]),
            'bed_history' => $this->whenLoaded('bedAssignments', fn () => $this->bedAssignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'bed' => $assignment->bed?->fullLabel(),
                'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                'released_at' => $assignment->released_at?->toIso8601String(),
                'nights' => $assignment->nights(),
                'reason' => $assignment->reason,
            ])->all()),
        ];
    }
}
