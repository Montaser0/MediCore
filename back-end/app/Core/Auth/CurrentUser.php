<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Resolves the acting user and the hospital/department context that most
 * actions need, so modules do not reach for the auth facade directly.
 */
final class CurrentUser
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function model(): ?UserModel
    {
        $user = $this->auth->guard('sanctum')->user() ?? $this->auth->guard()->user();

        return $user instanceof UserModel ? $user : null;
    }

    public function id(): ?int
    {
        return $this->model()?->getKey();
    }

    public function hospitalId(): ?int
    {
        return $this->model()?->staff?->hospital_id;
    }

    public function departmentId(): ?int
    {
        return $this->model()?->staff?->department_id;
    }

    public function doctorId(): ?int
    {
        return $this->model()?->staff?->doctor?->getKey();
    }

    /**
     * A doctor account, and only that account, is limited to its own patients
     * and appointments. Administrators and every other role keep the full list.
     */
    public function ownDoctorId(): ?int
    {
        $user = $this->model();

        if ($user === null || $user->hasRole('admin')) {
            return null;
        }

        $roles = $user->getRoleNames();

        if ($roles->count() !== 1 || $roles->first() !== 'doctor') {
            return null;
        }

        return $this->doctorId() ?? 0;
    }

    /**
     * Doctor and clinic/wing data-entry accounts share the clinical desk:
     * they work inside visits, and do not register patients or open new ones.
     */
    public function isClinicalDesk(): bool
    {
        $user = $this->model();

        if ($user === null || $user->hasRole('admin')) {
            return false;
        }

        $roles = $user->getRoleNames();

        return $roles->count() === 1 && in_array($roles->first(), ['doctor', 'data_entry'], true);
    }

    public function isDataEntry(): bool
    {
        return $this->hasSingleRole('data_entry');
    }

    /**
     * The clinic whose referred patients this data-entry account may see.
     * Null when the account is not limited to a clinic.
     *
     * @return array<int, int>|null
     */
    public function dataEntryClinicIds(): ?array
    {
        if (! $this->isDataEntry()) {
            return null;
        }

        $clinicId = $this->model()?->staff?->clinic_id;

        return $clinicId ? [(int) $clinicId] : null;
    }

    /**
     * Wing (department) fallback when a data-entry account has no clinic.
     * A clinic assignment replaces this, so the clerk sees that clinic only.
     *
     * @return array<int, int>|null
     */
    public function dataEntryDepartmentIds(): ?array
    {
        if (! $this->isDataEntry() || $this->dataEntryClinicIds() !== null) {
            return null;
        }

        $user = $this->model();
        $ids = [];
        $home = $user?->staff?->department_id;

        if ($home) {
            $ids[] = (int) $home;
        }

        foreach ($user?->departments()->pluck('departments.id')->all() ?? [] as $id) {
            $ids[] = (int) $id;
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? [0] : $ids;
    }

    public function canAccessDepartment(?int $departmentId): bool
    {
        $ids = $this->dataEntryDepartmentIds();

        if ($ids === null) {
            return true;
        }

        return $departmentId !== null && in_array($departmentId, $ids, true);
    }

    /**
     * A clinic clerk may open any visit sent to their clinic, whichever doctor
     * it belongs to. A wing clerk stays inside the department.
     */
    public function canAccessVisit(?int $departmentId, ?int $clinicId): bool
    {
        $clinics = $this->dataEntryClinicIds();

        if ($clinics !== null) {
            return $clinicId !== null && in_array($clinicId, $clinics, true);
        }

        return $this->canAccessDepartment($departmentId);
    }

    private function hasSingleRole(string $role): bool
    {
        $user = $this->model();

        if ($user === null || $user->hasRole('admin')) {
            return false;
        }

        $roles = $user->getRoleNames();

        return $roles->count() === 1 && $roles->first() === $role;
    }

    public function isAuthenticated(): bool
    {
        return $this->model() !== null;
    }
}
