<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyServiceModel
 */
final class RadiologyServiceResource extends JsonResource
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
            'modality' => $this->modality,
            'description' => $this->description,
            'preparation_instructions' => $this->preparation_instructions,
            'price' => $this->price,
            'requires_contrast' => $this->requires_contrast,
            'is_active' => $this->is_active,
        ];
    }
}
