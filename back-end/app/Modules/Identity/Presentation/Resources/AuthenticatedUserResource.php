<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Resources;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The payload the Next.js app stores after login. It carries the flattened
 * permission list so the `<Can permission="..."/>` guards can work without an
 * extra round trip.
 *
 * @mixin UserModel
 */
final class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $staff = $this->staff;

        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'display_name' => $this->displayName(),
            'is_active' => $this->is_active,
            'hospital_id' => $staff?->hospital_id,
            'department_id' => $staff?->department_id,
            'department' => $staff?->department !== null
                ? [
                    'id' => $staff->department->id,
                    'name' => $staff->department->name,
                    'type' => $staff->department->type->value,
                ]
                : null,
            'staff_id' => $staff?->id,
            'doctor_id' => $staff?->doctor?->id,
            'job_title' => $staff?->job_title,
            'roles' => $this->getRoleNames()->all(),
            'permissions' => $this->permissionNames(),
            'accessible_department_ids' => $this->relationLoaded('departments')
                ? $this->departments->pluck('id')->all()
                : [],
        ];
    }
}
