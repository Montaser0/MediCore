<?php

declare(strict_types=1);

namespace App\Modules\Departments\Domain\Enums;

/**
 * Distinguishes departments patients are seen in from departments that
 * fulfil orders, which is what lets the order engine route work without
 * hard-coding department names.
 */
enum DepartmentType: string
{
    case Medical = 'medical';
    case Laboratory = 'laboratory';
    case Radiology = 'radiology';
    case Pharmacy = 'pharmacy';
    case Warehouse = 'warehouse';
    case Emergency = 'emergency';
    case Surgery = 'surgery';
    case Inpatient = 'inpatient';
    case Nursing = 'nursing';
    case Administration = 'administration';
    case Support = 'support';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Departments where an encounter can be opened and a doctor can examine
     * a patient.
     */
    public function isClinical(): bool
    {
        return in_array($this, [
            self::Medical,
            self::Emergency,
            self::Surgery,
            self::Inpatient,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Medical => 'طبي',
            self::Laboratory => 'مختبر',
            self::Radiology => 'أشعة',
            self::Pharmacy => 'صيدلية',
            self::Warehouse => 'مستودع',
            self::Emergency => 'طوارئ',
            self::Surgery => 'جراحة',
            self::Inpatient => 'داخلي',
            self::Nursing => 'تمريض',
            self::Administration => 'إدارة',
            self::Support => 'مساندة',
        };
    }
}
