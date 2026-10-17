<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enums;

/**
 * Where a charge originated. Used to keep automatic charges idempotent: the
 * same source row can only ever produce one charge.
 */
enum ChargeSource: string
{
    case Manual = 'manual';
    case Consultation = 'consultation';
    case LabOrderItem = 'lab_order_item';
    case RadiologyOrderItem = 'radiology_order_item';
    case RadiologyInjection = 'radiology_injection';
    case MedicationDispense = 'medication_dispense';
    case Procedure = 'procedure';
    case Operation = 'operation';
    case BedAssignment = 'bed_assignment';
    case Supply = 'supply';
    case OrderItem = 'order_item';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'إدخال يدوي',
            self::Consultation => 'استشارة',
            self::LabOrderItem => 'تحليل مخبري',
            self::RadiologyOrderItem => 'صورة أشعة',
            self::RadiologyInjection => 'حقن',
            self::MedicationDispense => 'دواء',
            self::Procedure => 'إجراء',
            self::Operation => 'عملية',
            self::BedAssignment => 'أجر السرير',
            self::Supply => 'مستلزمات',
            self::OrderItem => 'بند طلب',
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
