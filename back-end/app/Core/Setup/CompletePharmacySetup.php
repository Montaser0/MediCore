<?php

declare(strict_types=1);

namespace App\Core\Setup;

use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Support\Facades\DB;

/**
 * First launch of a new pharmacy database. Permissions and the facility
 * record already exist. This step only names the pharmacy and creates the
 * administrator.
 */
final class CompletePharmacySetup
{
    /**
     * @return array{required: bool}
     */
    public function status(): array
    {
        return ['required' => ! UserModel::query()->exists()];
    }

    /**
     * @param  array{pharmacy_name: string, admin_name: string, admin_email: string, password: string}  $input
     */
    public function complete(array $input): void
    {
        if (UserModel::query()->exists()) {
            throw new SetupException('تم إعداد النظام مسبقاً.');
        }

        $hospital = HospitalModel::query()
            ->where('code', config('hms.default_hospital_code', 'MAIN'))
            ->first();

        if ($hospital === null) {
            throw new SetupException('قاعدة البيانات ليست جاهزة. أعد تشغيل التثبيت.');
        }

        $name = trim($input['admin_name']);
        $parts = preg_split('/\s+/u', $name, 2) ?: [];
        $firstName = $parts[0] !== '' ? $parts[0] : 'مدير';
        $lastName = ($parts[1] ?? '') !== '' ? $parts[1] : 'النظام';
        $email = mb_strtolower(trim($input['admin_email']));

        DB::transaction(function () use ($hospital, $input, $firstName, $lastName, $email): void {
            if (UserModel::query()->lockForUpdate()->exists()) {
                throw new SetupException('تم إعداد النظام مسبقاً.');
            }

            $hospital->forceFill(['name' => trim($input['pharmacy_name'])])->save();

            $department = DepartmentModel::query()
                ->where('hospital_id', $hospital->getKey())
                ->where('code', 'PHAR')
                ->first();

            $staff = StaffModel::query()->create([
                'hospital_id' => $hospital->getKey(),
                'department_id' => $department?->getKey(),
                'employee_number' => 'EMP-0001',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'job_title' => 'مدير النظام',
                'is_active' => true,
            ]);

            $user = UserModel::query()->create([
                'staff_id' => $staff->getKey(),
                'username' => $email,
                'email' => $email,
                'password' => $input['password'],
                'is_active' => true,
            ]);

            $user->syncRoles(['admin']);
        });
    }
}
