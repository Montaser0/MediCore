<?php

declare(strict_types=1);

namespace App\Modules\Operations\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel
 */
final class OperationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operation_number' => $this->operation_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'actual_duration_minutes' => $this->actualDurationMinutes(),
            'anesthesia_type' => $this->anesthesia_type?->value,
            'anesthesia_type_label' => $this->anesthesia_type?->label(),
            'consent_obtained' => $this->consent_obtained,
            'consent_obtained_at' => $this->consent_obtained_at?->toIso8601String(),
            'pre_op_diagnosis' => $this->pre_op_diagnosis,
            'pre_op_notes' => $this->pre_op_notes,
            'post_op_diagnosis' => $this->post_op_diagnosis,
            'post_op_instructions' => $this->post_op_instructions,
            'complications' => $this->complications,
            'outcome' => $this->outcome,
            'follow_up_date' => $this->follow_up_date?->toDateString(),
            'cancellation_reason' => $this->cancellation_reason,
            'encounter_id' => $this->encounter_id,
            'type' => $this->whenLoaded('type', fn () => [
                'id' => $this->type?->id,
                'name' => $this->type?->name,
                'code' => $this->type?->code,
                'specialty' => $this->type?->specialty,
                'risk_level' => $this->type?->risk_level,
            ]),
            'theatre' => $this->whenLoaded('theatre', fn () => [
                'id' => $this->theatre?->id,
                'name' => $this->theatre?->name,
            ]),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'mrn' => $this->patient?->mrn,
                'full_name' => $this->patient?->full_name,
                'age' => $this->patient?->age,
                'gender' => $this->patient?->gender?->value,
            ]),
            'team' => $this->whenLoaded('team', fn () => $this->team->map(fn ($member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->displayName(),
                'role' => $member->role->value,
                'role_label' => $member->role->label(),
                'notes' => $member->notes,
            ])->all()),
            'notes' => $this->whenLoaded('notes', fn () => $this->notes->map(fn ($note) => [
                'id' => $note->id,
                'note_type' => $note->note_type->value,
                'note_type_label' => $note->note_type->label(),
                'content' => $note->content,
                'author' => $note->author?->displayName(),
                'created_at' => $note->created_at?->toIso8601String(),
            ])->all()),
            'materials' => $this->whenLoaded('materials', fn () => $this->materials->map(fn ($material) => [
                'id' => $material->id,
                'item' => $material->item?->name,
                'quantity' => $material->quantity,
                'unit_cost' => $material->unit_cost,
            ])->all()),
        ];
    }
}
