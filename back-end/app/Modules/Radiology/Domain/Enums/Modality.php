<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Domain\Enums;

/**
 * Imaging modalities, kept as a soft list: `radiology_services.modality` is a
 * string so a hospital can add its own without a migration.
 */
enum Modality: string
{
    case XRay = 'xray';
    case CT = 'ct';
    case MRI = 'mri';
    case Ultrasound = 'ultrasound';
    case Mammography = 'mammography';
    case Fluoroscopy = 'fluoroscopy';
    case NuclearMedicine = 'nuclear_medicine';

    public function label(): string
    {
        return match ($this) {
            self::XRay => 'أشعة سينية',
            self::CT => 'طبقي محوري',
            self::MRI => 'رنين مغناطيسي',
            self::Ultrasound => 'إيكو',
            self::Mammography => 'تصوير الثدي',
            self::Fluoroscopy => 'تنظير شعاعي',
            self::NuclearMedicine => 'طب نووي',
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
