<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Resources;

use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetMovementModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ItAssetMovementModel
 */
final class AssetMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'note' => $this->note,
            'occurred_at' => $this->occurred_at?->toDateTimeString(),
            'actor' => $this->actor?->staff?->full_name ?? $this->actor?->username,
            'from_department' => $this->fromDepartment?->name,
            'to_department' => $this->toDepartment?->name,
            'from_holder' => $this->from_holder,
            'to_holder' => $this->to_holder,
        ];
    }
}
