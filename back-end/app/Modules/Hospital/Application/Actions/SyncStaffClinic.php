<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Application\Actions;

use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the employee clinic, the doctor clinic, and the clinic roster aligned.
 * A department with a single clinic fills a missing choice so the assignment
 * is still there the next time the form opens.
 */
final class SyncStaffClinic
{
    public function ensure(StaffModel $staff, ?int $explicitClinicId = null): StaffModel
    {
        $staff->loadMissing('doctor');

        $departmentId = $staff->department_id !== null ? (int) $staff->department_id : null;

        // The clinic picked in the form wins, even when the department has
        // several clinics. A missing choice can still be filled later.
        if ($explicitClinicId !== null && $explicitClinicId > 0) {
            $clinicDepartment = ClinicModel::query()->whereKey($explicitClinicId)->value('department_id');
            if ($clinicDepartment !== null && (int) $clinicDepartment !== (int) ($departmentId ?? 0)) {
                $staff->department_id = (int) $clinicDepartment;
                $staff->save();
            }

            return $this->persist($staff, $explicitClinicId);
        }

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
        if ($doctor === null) {
            return $staff;
        }

        if ((int) ($doctor->clinic_id ?? 0) !== (int) ($clinicId ?? 0)) {
            $doctor->clinic_id = $clinicId;
            $doctor->save();
        }

        $this->syncRoster($doctor, $clinicId);

        return $staff;
    }

    private function syncRoster(DoctorModel $doctor, ?int $clinicId): void
    {
        if (! Schema::hasTable('clinic_doctor')) {
            return;
        }

        $linked = $doctor->clinics()->pluck('clinics.id')->map(fn ($id) => (int) $id)->all();
        $desired = $clinicId === null ? [] : [$clinicId];
        if ($linked !== $desired) {
            $doctor->clinics()->sync($desired);
        }
    }
}
