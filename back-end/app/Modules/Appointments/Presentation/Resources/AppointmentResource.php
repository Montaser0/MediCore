<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel
 */
final class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_number' => $this->appointment_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'ends_at' => $this->endsAt()?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'encounter_id' => $this->encounter_id,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
                'phone' => $this->patient->phone,
            ]),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'clinic' => $this->whenLoaded('clinic', fn () => [
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
            ]),
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor->id,
                'name' => $this->doctor->display_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
