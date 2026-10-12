<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Domain\Enums;

/**
 * The five radiology desks. `radiology_services.modality` stores one of these.
 */
enum Modality: string
{
    case CT = 'ct';
    case Echo = 'echo';
    case Panorama = 'panorama';
    case MRI = 'mri';
    case XRay = 'xray';

    public function label(): string
    {
        return match ($this) {
            self::CT => 'طبقي',
            self::Echo => 'إيكو',
            self::Panorama => 'بانوراما',
            self::MRI => 'رنين',
            self::XRay => 'أشعة بسيطة',
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
