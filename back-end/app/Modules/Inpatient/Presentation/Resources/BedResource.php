<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel
 */
final class BedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bed_number' => $this->bed_number,
            'label' => $this->fullLabel(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_assignable' => $this->status->isAssignable(),
            'notes' => $this->notes,
            'room' => $this->whenLoaded('room', fn () => [
                'id' => $this->room?->id,
                'room_number' => $this->room?->room_number,
                'room_type' => $this->room?->room_type,
                'ward_id' => $this->room?->ward_id,
                'ward' => $this->room?->ward?->name,
                'daily_rate' => $this->room?->effectiveDailyRate(),
            ]),
        ];
    }
}
