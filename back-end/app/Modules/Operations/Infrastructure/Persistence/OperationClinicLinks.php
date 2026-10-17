<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence;

use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTypeModel;

/**
 * Default operations, each one tied to the clinic that performs it.
 * Existing links are left alone so an administrator can retarget a clinic.
 */
final class OperationClinicLinks
{
    public static function ensureDefaults(): void
    {
        foreach (self::defaults() as [$name, $code, $specialty, $minutes, $risk, $price, $clinicCodes]) {
            $row = OperationTypeModel::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'specialty' => $specialty,
                    'estimated_duration_minutes' => $minutes,
                    'risk_level' => $risk,
                    'price' => $price,
                    'is_active' => true,
                ],
            );

            if ($row->clinics()->exists()) {
                continue;
            }

            $ids = ClinicModel::query()->whereIn('code', $clinicCodes)->pluck('id');
            if ($ids->isEmpty()) {
                continue;
            }

            $row->clinics()->syncWithoutDetaching($ids->all());
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: int, 4: string, 5: int, 6: list<string>}>
     */
    private static function defaults(): array
    {
        return [
            ['استئصال الزائدة الدودية', 'OP-APPX', 'الجراحة العامة', 60, 'moderate', 450000, ['SR-GEN']],
            ['استئصال المرارة بالتنظير', 'OP-LAPCHOLE', 'الجراحة العامة', 90, 'moderate', 850000, ['SR-GEN']],
            ['عملية قيصرية', 'OP-CS', 'النسائية والتوليد', 60, 'moderate', 700000, ['OB-GYN']],
            ['إصلاح فتق', 'OP-HERNIA', 'الجراحة العامة', 75, 'low', 500000, ['SR-GEN']],
            ['استئصال اللوزتين', 'OP-TONSIL', 'الأذن والأنف والحنجرة', 45, 'low', 380000, ['EN-GEN']],
            ['تثبيت كسر بالصفيحة والبراغي', 'OP-ORIF', 'جراحة العظام', 120, 'high', 1200000, ['SR-ORTHO']],
            ['استئصال الساد', 'OP-CATARACT', 'العينية', 40, 'low', 600000, ['OP-GEN']],
            ['مجازة الشريان التاجي', 'OP-CABG', 'جراحة القلب والصدر', 300, 'critical', 6500000, ['IM-CARD']],
        ];
    }
}
