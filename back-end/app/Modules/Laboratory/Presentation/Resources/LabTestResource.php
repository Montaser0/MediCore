<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Laboratory\Infrastructure\Persistence\Models\LabTestModel
 */
final class LabTestResource extends JsonResource
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
            'category' => $this->category,
            'sample_type' => $this->sample_type,
            'unit' => $this->unit,
            'reference_range' => $this->reference_range,
            'price' => $this->price,
            'turnaround_hours' => $this->turnaround_hours,
            'is_panel' => $this->is_panel,
            'is_active' => $this->is_active,
            'parameters' => $this->whenLoaded('parameters', fn () => $this->parameters->map(fn ($parameter) => [
                'id' => $parameter->id,
                'name' => $parameter->name,
                'code' => $parameter->code,
                'unit' => $parameter->unit,
                'reference_range' => $parameter->reference_range,
                'normal_low' => $parameter->normal_low,
                'normal_high' => $parameter->normal_high,
            ])->all()),
        ];
    }
}
