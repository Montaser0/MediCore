<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Resources;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserModel
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'display_name' => $this->displayName(),
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'staff' => $this->whenLoaded('staff', fn () => [
                'id' => $this->staff->id,
                'employee_number' => $this->staff->employee_number,
                'full_name' => $this->staff->full_name,
                'job_title' => $this->staff->job_title,
                'hospital_id' => $this->staff->hospital_id,
                'department_id' => $this->staff->department_id,
                'department' => $this->staff->relationLoaded('department') && $this->staff->department !== null
                    ? ['id' => $this->staff->department->id, 'name' => $this->staff->department->name]
                    : null,
                'doctor_id' => $this->staff->relationLoaded('doctor') ? $this->staff->doctor?->id : null,
            ]),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->all()),
            'departments' => $this->whenLoaded('departments', fn () => $this->departments->map(fn ($department) => [
                'id' => $department->id,
                'name' => $department->name,
                'is_primary' => (bool) $department->pivot->is_primary,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
