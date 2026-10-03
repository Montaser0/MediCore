<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Departments\Domain\Enums\DepartmentType;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use Illuminate\Database\Seeder;

/**
 * الهيكل التنظيمي للمستشفى: المستشفى نفسه، ثم الأقسام الطبية والخدمية
 * والإدارية والعيادات التابعة لها.
 *
 * الأسماء بالعربية لأنها ما يراه المستخدم، والرموز بالحروف اللاتينية لأنها
 * معرّفات داخلية تُستخدم في الباركود والتقارير.
 */
final class HospitalSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = HospitalModel::query()->firstOrCreate(
            ['code' => config('hms.default_hospital_code', 'MAIN')],
            [
                'name' => env('HMS_DEFAULT_HOSPITAL_NAME', 'المستشفى الرئيسي'),
                'phone' => '+963 11 000 0000',
                'email' => 'info@hospital.local',
                'address' => 'شارع المستشفى',
                'city' => 'دمشق',
                'country' => 'سوريا',
                'timezone' => 'Asia/Damascus',
                'currency' => 'USD',
                'is_active' => true,
            ],
        );

        if ($hospital->currency !== 'USD') {
            $hospital->forceFill(['currency' => 'USD'])->save();
        }

        foreach ($this->departments() as [$name, $code, $type, $clinics]) {
            $department = DepartmentModel::query()->firstOrCreate(
                ['hospital_id' => $hospital->getKey(), 'code' => $code],
                ['name' => $name, 'type' => $type->value, 'is_active' => true],
            );

            foreach ($clinics as [$clinicName, $clinicCode]) {
                ClinicModel::query()->firstOrCreate(
                    ['department_id' => $department->getKey(), 'code' => $clinicCode],
                    ['name' => $clinicName, 'is_active' => true],
                );
            }
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: DepartmentType, 3: array<int, array{0: string, 1: string}>}>
     */
    private function departments(): array
    {
        return [
            ['قسم الطوارئ', 'ER', DepartmentType::Emergency, [
                ['غرفة الإسعاف', 'ER-TRIAGE'],
            ]],
            ['قسم الأمراض الباطنة', 'IM', DepartmentType::Medical, [
                ['عيادة الطب العام', 'IM-GEN'],
                ['عيادة القلبية', 'IM-CARD'],
                ['عيادة الجهاز الهضمي', 'IM-GAST'],
                ['عيادة الغدد الصم', 'IM-ENDO'],
            ]],
            ['قسم الجراحة', 'SURG', DepartmentType::Surgery, [
                ['عيادة الجراحة العامة', 'SR-GEN'],
                ['عيادة جراحة العظام', 'SR-ORTHO'],
                ['عيادة المسالك البولية', 'SR-URO'],
            ]],
            ['قسم الأطفال', 'PED', DepartmentType::Medical, [
                ['عيادة الأطفال', 'PD-GEN'],
                ['عيادة حديثي الولادة', 'PD-NEO'],
            ]],
            ['قسم النسائية والتوليد', 'OBG', DepartmentType::Medical, [
                ['عيادة متابعة الحمل', 'OB-ANC'],
                ['عيادة الأمراض النسائية', 'OB-GYN'],
            ]],
            ['قسم الجلدية', 'DERM', DepartmentType::Medical, [
                ['عيادة الجلدية', 'DR-GEN'],
            ]],
            ['قسم العينية', 'OPH', DepartmentType::Medical, [
                ['عيادة العيون', 'OP-GEN'],
            ]],
            ['قسم الأذن والأنف والحنجرة', 'ENT', DepartmentType::Medical, [
                ['عيادة الأذن والأنف والحنجرة', 'EN-GEN'],
            ]],
            ['قسم الأسنان', 'DENT', DepartmentType::Medical, [
                ['عيادة الأسنان', 'DN-GEN'],
            ]],
            ['المخبر', 'LAB', DepartmentType::Laboratory, []],
            ['قسم الأشعة', 'RAD', DepartmentType::Radiology, []],
            ['الصيدلية', 'PHAR', DepartmentType::Pharmacy, []],
            ['قسم التمريض', 'NURS', DepartmentType::Nursing, []],
            ['غرف العمليات', 'OT', DepartmentType::Surgery, []],
            ['أقسام التنويم', 'WARD', DepartmentType::Inpatient, []],
            ['الاستقبال', 'RCPT', DepartmentType::Administration, []],
            ['المحاسبة', 'BILL', DepartmentType::Administration, []],
            ['المستودع', 'WH', DepartmentType::Warehouse, []],
        ];
    }
}
