<?php

declare(strict_types=1);

namespace App\Modules\Departments\Presentation\Resources;

use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DepartmentModel
 */
final class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'is_clinical' => $this->type->isClinical(),
            'description' => $this->description,
            'is_active' => $this->is_active,
            'clinics' => $this->whenLoaded('clinics', fn () => $this->clinics->map(fn ($clinic) => [
                'id' => $clinic->id,
                'department_id' => $clinic->department_id,
                'name' => $clinic->name,
            ])->values()),
        ];
    }
}
