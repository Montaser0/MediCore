<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Nursing\Infrastructure\Persistence\Models\NursingNoteModel
 */
final class NursingNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note_type' => $this->note_type->value,
            'note_type_label' => $this->note_type->label(),
            'shift' => $this->shift?->value,
            'shift_label' => $this->shift?->label(),
            'content' => $this->content,
            'encounter_id' => $this->encounter_id,
            'admission_id' => $this->admission_id,
            'author' => $this->whenLoaded('author', fn () => $this->author?->displayName()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
