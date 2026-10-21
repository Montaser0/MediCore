<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum AssetMovementType: string
{
    case Registered = 'registered';
    case Assigned = 'assigned';
    case Transferred = 'transferred';
    case Returned = 'returned';
    case Maintenance = 'maintenance';
    case Repaired = 'repaired';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'تسجيل',
            self::Assigned => 'تسليم',
            self::Transferred => 'نقل العهدة',
            self::Returned => 'إرجاع للمستودع',
            self::Maintenance => 'إرسال للصيانة',
            self::Repaired => 'عودة من الصيانة',
            self::Retired => 'إخراج من الخدمة',
        };
    }
}
