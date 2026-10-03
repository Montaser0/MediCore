<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Enums;

enum OrderType: string
{
    case Laboratory = 'laboratory';
    case Radiology = 'radiology';
    case Pharmacy = 'pharmacy';
    case Procedure = 'procedure';
    case Consultation = 'consultation';
    case Supply = 'supply';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Laboratory => 'مختبر',
            self::Radiology => 'أشعة',
            self::Pharmacy => 'صيدلية',
            self::Procedure => 'إجراء',
            self::Consultation => 'استشارة',
            self::Supply => 'مستلزم',
            self::Other => 'أخرى',
        };
    }

    /**
     * The catalogue an order item of this type is picked from. Used to
     * validate `service_type` on the item.
     */
    public function serviceType(): string
    {
        return match ($this) {
            self::Laboratory => 'lab_test',
            self::Radiology => 'radiology_service',
            self::Pharmacy => 'medication',
            self::Supply => 'inventory_item',
            self::Procedure, self::Consultation, self::Other => 'service',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
