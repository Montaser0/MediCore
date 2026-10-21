<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Application\Actions;

use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the employee record and, when the job title is clinical, the
 * matching doctor row in the same transaction.
 */
final class CreateStaffAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly SyncStaffClinic $clinics,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): StaffModel
    {
        return DB::transaction(function () use ($data): StaffModel {
            $hospitalId = (int) $data['hospital_id'];
            $clinicId = isset($data['clinic_id']) ? (int) $data['clinic_id'] : null;
            $departmentId = $data['department_id'] ?? null;

            if ($clinicId) {
                $departmentId = ClinicModel::query()->whereKey($clinicId)->value('department_id') ?? $departmentId;
            }

            $staffAttributes = [
                'hospital_id' => $hospitalId,
                'employee_number' => $data['employee_number'] ?? $this->numbers->employeeNumber($hospitalId),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'department_id' => $departmentId,
                'is_active' => $data['is_active'] ?? true,
            ];
            if (Schema::hasColumn('staff', 'clinic_id')) {
                $staffAttributes['clinic_id'] = $clinicId ?: null;
            }

            $staff = StaffModel::query()->create($staffAttributes);

            if (($data['is_doctor'] ?? false) === true) {
                $doctorAttributes = [
                    'staff_id' => $staff->getKey(),
                    'license_number' => $data['license_number'] ?? null,
                    'specialty' => $data['specialty'] ?? null,
                ];
                if (Schema::hasColumn('doctors', 'clinic_id')) {
                    $doctorAttributes['clinic_id'] = $clinicId ?: null;
                }

                DoctorModel::query()->create($doctorAttributes);
            }

            $this->clinics->ensure($staff);

            return $staff->fresh(['department', 'clinic', 'doctor']);
        });
    }
}
