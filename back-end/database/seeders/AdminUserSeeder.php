<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * الحساب الوحيد الموجود قبل أن يسجّل أحد دخوله. كلمة المرور تأتي من متغيرات
 * البيئة حتى لا يُنشر النظام بكلمة مرور معروفة.
 */
final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = HospitalModel::query()
            ->where('code', config('hms.default_hospital_code', 'MAIN'))
            ->firstOrFail();

        DB::transaction(function () use ($hospital): void {
            $staff = StaffModel::query()->firstOrCreate(
                ['hospital_id' => $hospital->getKey(), 'employee_number' => 'EMP-0001'],
                [
                    'first_name' => 'مدير',
                    'last_name' => 'النظام',
                    'job_title' => 'مدير النظام',
                    'is_active' => true,
                ],
            );

            $user = UserModel::query()->firstOrCreate(
                ['username' => env('HMS_ADMIN_USERNAME', 'admin')],
                [
                    'staff_id' => $staff->getKey(),
                    'email' => env('HMS_ADMIN_EMAIL', 'admin@hospital.local'),
                    'password' => env('HMS_ADMIN_PASSWORD', 'ChangeMe!2026'),
                    'is_active' => true,
                ],
            );

            $user->syncRoles(['admin']);
        });
    }
}
