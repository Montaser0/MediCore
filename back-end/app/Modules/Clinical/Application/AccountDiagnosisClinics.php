<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Application;

use App\Core\Auth\CurrentUser;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;

/**
 * The clinics whose diagnosis names an account is allowed to pick.
 * A doctor sees the clinic they belong to. A data-entry clerk sees the clinic
 * they are assigned to. Other accounts are not limited here.
 */
final class AccountDiagnosisClinics
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    /**
     * @return list<int>|null Null when the account may use the requested filter.
     */
    public function ids(): ?array
    {
        $ownDoctorId = $this->currentUser->ownDoctorId();

        if ($ownDoctorId !== null) {
            if ($ownDoctorId === 0) {
                return [];
            }

            $doctor = DoctorModel::query()->with(['clinics:clinics.id', 'staff'])->find($ownDoctorId);

            if ($doctor === null) {
                return [];
            }

            $ids = $doctor->diagnosisClinicIds();
            $staffClinicId = $doctor->staff?->clinic_id;

            if ($staffClinicId !== null) {
                $ids[] = (int) $staffClinicId;
            }

            return $ids === []
                ? $this->clinicsInDepartment($doctor->staff?->department_id)
                : array_values(array_unique($ids));
        }

        if ($this->currentUser->isDataEntry()) {
            $ids = $this->currentUser->dataEntryClinicIds();

            if ($ids !== null) {
                return $ids;
            }

            return $this->clinicsInDepartment($this->currentUser->departmentId());
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function clinicsInDepartment(?int $departmentId): array
    {
        if ($departmentId === null) {
            return [];
        }

        return ClinicModel::query()
            ->where('department_id', $departmentId)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
