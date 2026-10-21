<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Presentation\Resources;

use App\Core\Authorization\Permissions;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
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
            'clinic_id' => $this->visibleClinicId(),
            'clinic' => $this->visibleClinic(),
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
            'screens' => $this->when($this->relationLoaded('user'), fn () => $this->resolvedScreens()),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function visibleClinic(): ?array
    {
        $clinic = null;

        if ($this->relationLoaded('clinic') && $this->clinic !== null) {
            $clinic = $this->clinic;
        } elseif ($this->relationLoaded('doctor') && $this->doctor?->relationLoaded('clinic') && $this->doctor->clinic !== null) {
            $clinic = $this->doctor->clinic;
        } elseif ($this->relationLoaded('doctor') && $this->doctor?->relationLoaded('clinics')) {
            $clinic = $this->doctor->clinics->first();
        }

        if ($clinic === null) {
            $id = $this->getAttribute('clinic_id');
            if ($id === null && $this->relationLoaded('doctor')) {
                $id = $this->doctor?->getAttribute('clinic_id');
            }
            if ($id !== null) {
                $clinic = ClinicModel::query()->find($id);
            }
        }

        return $clinic === null ? null : [
            'id' => $clinic->id,
            'name' => $clinic->name,
        ];
    }

    private function visibleClinicId(): ?int
    {
        return $this->visibleClinic()['id'] ?? ($this->clinic_id !== null ? (int) $this->clinic_id : null);
    }

    /**
     * @return array<int, string>
     */
    private function resolvedScreens(): array
    {
        $user = $this->user;

        if ($user === null) {
            return [];
        }

        if (is_array($user->ui_screens)) {
            return array_values($user->ui_screens);
        }

        $role = $user->relationLoaded('roles')
            ? $user->roles->pluck('name')->first()
            : $user->getRoleNames()->first();

        return Permissions::screensForRole((string) $role);
    }
}
