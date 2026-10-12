<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientAllergyModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientConditionModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientContactModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Seeder;

/**
 * بيانات تجريبية عربية لملء النظام أثناء التطوير: كادر بأدواره، مرضى،
 * ثم بيانات تشغيلية لكل الأقسام (مواعيد، زيارات، مختبر، أشعة...).
 *
 *     php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder
 */
final class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('البيانات التجريبية لا تُزرع في بيئة الإنتاج.');

            return;
        }

        $hospital = HospitalModel::query()
            ->where('code', config('hms.default_hospital_code', 'MAIN'))
            ->firstOrFail();

        $hospitalId = (int) $hospital->getKey();

        $this->staff($hospitalId);
        $this->departmentDoctors($hospitalId);
        $this->patients($hospitalId);

        $this->call(DemoOperationalDataSeeder::class);
    }

    private function staff(int $hospitalId): void
    {
        $accounts = [
            ['doctor', 'doctor', 'IM', 'طبيب اختصاصي', true],
            ['reception', 'reception', 'RCPT', 'موظف استقبال', false],
            ['nurse', 'nurse', 'NURS', 'ممرض', false],
            ['lab', 'lab_technician', 'LAB', 'فني مخبر', false],
            ['lab_boss', 'lab_supervisor', 'LAB', 'رئيس قسم المخبر', false],
            ['radtech', 'radiology_technician', 'RAD', 'فني أشعة', false],
            ['radiologist', 'radiologist', 'RAD', 'طبيب أشعة', true],
            ['pharmacist', 'pharmacist', 'PHAR', 'صيدلي', false],
            ['store', 'warehouse', 'WH', 'أمين مستودع', false],
            ['cashier', 'cashier', 'BILL', 'محاسب', false],
        ];

        foreach ($accounts as [$username, $role, $departmentCode, $jobTitle, $isDoctor]) {
            if (UserModel::query()->where('username', $username)->exists()) {
                continue;
            }

            $department = DepartmentModel::query()
                ->where('hospital_id', $hospitalId)
                ->where('code', $departmentCode)
                ->first();

            $staff = StaffModel::factory()->create([
                'hospital_id' => $hospitalId,
                'department_id' => $department?->getKey(),
                'job_title' => $jobTitle,
            ]);

            if ($isDoctor) {
                DoctorModel::factory()->create([
                    'staff_id' => $staff->getKey(),
                    'clinic_id' => $department === null
                        ? null
                        : ClinicModel::query()->where('department_id', $department->getKey())->orderBy('id')->value('id'),
                ]);
            }

            UserModel::factory()->withRole($role)->create([
                'staff_id' => $staff->getKey(),
                'username' => $username,
                'email' => $username.'@hospital.local',
            ]);
        }
    }

    /**
     * طبيب إضافي لكل قسم طبي/جراحي حتى تمتلئ قوائم العيادات.
     */
    private function departmentDoctors(int $hospitalId): void
    {
        $medicalCodes = ['IM', 'SURG', 'PED', 'OBG', 'DERM', 'OPH', 'ENT', 'DENT', 'ER'];

        foreach ($medicalCodes as $code) {
            $department = DepartmentModel::query()
                ->where('hospital_id', $hospitalId)
                ->where('code', $code)
                ->first();

            if ($department === null) {
                continue;
            }

            $existingCount = StaffModel::query()
                ->where('department_id', $department->getKey())
                ->whereHas('doctor')
                ->count();

            if ($existingCount >= 2) {
                $this->coverClinics($department);

                continue;
            }

            $clinics = ClinicModel::query()
                ->where('department_id', $department->getKey())
                ->orderBy('id')
                ->get();
            $needed = 2 - $existingCount;
            for ($i = 0; $i < $needed; $i++) {
                $staff = StaffModel::factory()->doctor()->create([
                    'hospital_id' => $hospitalId,
                    'department_id' => $department->getKey(),
                ]);
                DoctorModel::factory()->create([
                    'staff_id' => $staff->getKey(),
                    'clinic_id' => $clinics->isEmpty() ? null : $clinics[($existingCount + $i) % $clinics->count()]->getKey(),
                ]);
            }

            $this->coverClinics($department);
        }
    }

    private function coverClinics(DepartmentModel $department): void
    {
        $clinicIds = ClinicModel::query()
            ->where('department_id', $department->getKey())
            ->orderBy('id')
            ->pluck('id');
        $doctors = DoctorModel::query()->inDepartment((int) $department->getKey())->orderBy('id')->get();

        if ($clinicIds->isEmpty() || $doctors->isEmpty()) {
            return;
        }

        foreach ($clinicIds as $index => $clinicId) {
            $doctors[$index % $doctors->count()]->clinics()->syncWithoutDetaching([$clinicId]);
        }
    }

    private function patients(int $hospitalId): void
    {
        $existing = PatientModel::query()->where('hospital_id', $hospitalId)->count();
        $target = 50;

        if ($existing >= $target) {
            return;
        }

        $patients = PatientModel::factory()
            ->count($target - $existing)
            ->create(['hospital_id' => $hospitalId]);

        foreach ($patients as $index => $patient) {
            PatientContactModel::factory()->create(['patient_id' => $patient->getKey()]);

            if ($index % 4 === 0) {
                PatientAllergyModel::factory()->create(['patient_id' => $patient->getKey()]);
            }

            if ($index % 3 === 0) {
                PatientConditionModel::factory()->create(['patient_id' => $patient->getKey()]);
            }
        }
    }
}
