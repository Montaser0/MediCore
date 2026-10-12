<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Presentation\Resources;

use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DoctorModel
 */
final class DoctorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'staff_id' => $this->staff_id,
            'name' => $this->staff?->full_name,
            'display_name' => $this->display_name,
            'license_number' => $this->license_number,
            'specialty' => $this->specialty,
            'clinic_id' => $this->clinic_id,
            'department' => $this->whenLoaded('staff', fn () => $this->staff->department === null ? null : [
                'id' => $this->staff->department->id,
                'name' => $this->staff->department->name,
            ]),
            'is_active' => (bool) ($this->staff?->is_active ?? false),
        ];
    }
}
