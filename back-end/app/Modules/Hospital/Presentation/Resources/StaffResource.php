<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Presentation\Resources;

use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffModel
 */
final class StaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'is_active' => $this->is_active,
            'department' => $this->whenLoaded('department', fn () => $this->department === null ? null : [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'type' => $this->department->type->value,
            ]),
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor === null ? null : [
                'id' => $this->doctor->id,
                'license_number' => $this->doctor->license_number,
                'specialty' => $this->doctor->specialty,
            ]),
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'roles' => $this->user->relationLoaded('roles')
                    ? $this->user->roles->pluck('name')->values()->all()
                    : [],
            ]),
        ];
    }
}
