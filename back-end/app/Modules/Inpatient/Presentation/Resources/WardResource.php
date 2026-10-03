<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inpatient\Infrastructure\Persistence\Models\WardModel
 */
final class WardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'floor' => $this->floor,
            'daily_rate' => $this->daily_rate,
            'is_active' => $this->is_active,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department?->id,
                'name' => $this->department?->name,
            ]),
            'rooms' => $this->whenLoaded('rooms', fn () => $this->rooms->map(fn ($room) => [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'room_type' => $room->room_type,
                'capacity' => $room->capacity,
                'daily_rate' => $room->effectiveDailyRate(),
            ])->all()),
            'bed_count' => $this->whenCounted('beds'),
        ];
    }
}
