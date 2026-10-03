<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Application\Actions;

use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use Illuminate\Support\Facades\DB;

final class UpdateStaffAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(StaffModel $staff, array $data): StaffModel
    {
        return DB::transaction(function () use ($staff, $data): StaffModel {
            $staff->fill(array_intersect_key($data, array_flip([
                'first_name',
                'last_name',
                'phone',
                'job_title',
                'department_id',
                'is_active',
            ])))->save();

            $shouldBeDoctor = $data['is_doctor'] ?? $staff->doctor !== null;

            if ($shouldBeDoctor) {
                DoctorModel::query()->updateOrCreate(
                    ['staff_id' => $staff->getKey()],
                    array_intersect_key($data, array_flip(['license_number', 'specialty'])),
                );
            }

            return $staff->fresh(['department', 'doctor']);
        });
    }
}
