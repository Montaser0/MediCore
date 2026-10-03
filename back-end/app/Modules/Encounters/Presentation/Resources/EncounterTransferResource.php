<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Presentation\Resources;

use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterTransferModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EncounterTransferModel
 */
final class EncounterTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'transferred_at' => $this->transferred_at?->toIso8601String(),
            'from_department' => $this->whenLoaded('fromDepartment', fn () => $this->fromDepartment === null ? null : [
                'id' => $this->fromDepartment->id,
                'name' => $this->fromDepartment->name,
            ]),
            'to_department' => $this->whenLoaded('toDepartment', fn () => [
                'id' => $this->toDepartment->id,
                'name' => $this->toDepartment->name,
            ]),
        ];
    }
}
