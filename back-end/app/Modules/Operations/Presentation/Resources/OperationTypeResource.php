<?php

declare(strict_types=1);

namespace App\Modules\Operations\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Operations\Infrastructure\Persistence\Models\OperationTypeModel
 */
final class OperationTypeResource extends JsonResource
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
            'specialty' => $this->specialty,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'risk_level' => $this->risk_level,
            'price' => $this->price,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ];
    }
}
