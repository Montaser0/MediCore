<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Application\Actions;

use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;

/**
 * Keeps the employee clinic, the doctor clinic, and the clinic roster aligned.
 * A department with a single clinic fills a missing choice so the assignment
 * is still there the next time the form opens.
 */
final class SyncStaffClinic
{
    public function ensure(StaffModel $staff): StaffModel
    {
        $staff->loadMissing('doctor');

        $departmentId = $staff->department_id !== null ? (int) $staff->department_id : null;
        $clinicId = $staff->clinic_id !== null ? (int) $staff->clinic_id : null;

        if ($clinicId === null && $staff->doctor?->clinic_id !== null) {
            $clinicId = (int) $staff->doctor->clinic_id;
        }

        if ($departmentId !== null && ! $this->belongsToDepartment($clinicId, $departmentId)) {
            $clinicId = $this->soleClinicId($departmentId);
        }

        return $this->persist($staff, $clinicId);
    }

    private function belongsToDepartment(?int $clinicId, int $departmentId): bool
    {
        if ($clinicId === null) {
            return false;
        }

        return ClinicModel::query()
            ->whereKey($clinicId)
            ->where('department_id', $departmentId)
            ->exists();
    }

    private function soleClinicId(int $departmentId): ?int
    {
        $ids = ClinicModel::query()
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(2)
            ->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    private function persist(StaffModel $staff, ?int $clinicId): StaffModel
    {
        if ((int) ($staff->clinic_id ?? 0) !== (int) ($clinicId ?? 0)) {
            $staff->clinic_id = $clinicId;
            $staff->save();
            $staff->unsetRelation('clinic');
            $staff->load('clinic');
        }

        $doctor = $staff->doctor;
        if ($doctor === null || (int) ($doctor->clinic_id ?? 0) === (int) ($clinicId ?? 0)) {
            return $staff;
        }

        $doctor->clinic_id = $clinicId;
        $doctor->save();

        if ($clinicId === null) {
            $doctor->clinics()->detach();
        } else {
            $doctor->clinics()->sync([$clinicId]);
        }

        return $staff;
    }
}
