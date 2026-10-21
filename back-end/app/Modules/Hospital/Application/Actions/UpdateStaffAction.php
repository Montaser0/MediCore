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

            $staff->fill(array_intersect_key($data, array_flip($fields)))->save();

            $shouldBeDoctor = $data['is_doctor'] ?? $staff->doctor !== null;

            if ($shouldBeDoctor) {
                $doctorValues = array_intersect_key($data, array_flip(['license_number', 'specialty']));

                DoctorModel::query()->updateOrCreate(
                    ['staff_id' => $staff->getKey()],
                    $doctorValues,
                );
                $staff->unsetRelation('doctor');
            }

            $this->clinics->ensure($staff);

            return $staff->fresh(['department', 'clinic', 'doctor']);
        });
    }
}
