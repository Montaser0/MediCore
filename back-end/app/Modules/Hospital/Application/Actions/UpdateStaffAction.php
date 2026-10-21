<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Application\Actions;

use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class UpdateStaffAction
{
    public function __construct(private readonly SyncStaffClinic $clinics) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(StaffModel $staff, array $data): StaffModel
    {
        return DB::transaction(function () use ($staff, $data): StaffModel {
            $fields = [
                'first_name',
                'last_name',
                'phone',
                'job_title',
                'department_id',
                'clinic_id',
                'is_active',
            ];
            if (! Schema::hasColumn('staff', 'clinic_id')) {
                $fields = array_values(array_diff($fields, ['clinic_id']));
            }

            $explicitClinicId = $this->explicitClinicId($data);

            $staff->fill(array_intersect_key($data, array_flip($fields)))->save();

            $shouldBeDoctor = $data['is_doctor'] ?? $staff->doctor !== null;

            if ($shouldBeDoctor) {
                $doctorValues = array_intersect_key($data, array_flip(['license_number', 'specialty']));
                if ($explicitClinicId !== null && Schema::hasColumn('doctors', 'clinic_id')) {
                    $doctorValues['clinic_id'] = $explicitClinicId;
                }

                DoctorModel::query()->updateOrCreate(
                    ['staff_id' => $staff->getKey()],
                    $doctorValues,
                );
                $staff->unsetRelation('doctor');
            }

            $this->clinics->ensure($staff, $explicitClinicId);

            return $staff->fresh(['department', 'clinic', 'doctor']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function explicitClinicId(array $data): ?int
    {
        if (! array_key_exists('clinic_id', $data) || $data['clinic_id'] === null || $data['clinic_id'] === '') {
            return null;
        }

        $clinicId = (int) $data['clinic_id'];

        return $clinicId > 0 ? $clinicId : null;
    }
}
