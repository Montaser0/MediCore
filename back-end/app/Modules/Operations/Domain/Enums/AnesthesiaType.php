<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\Enums;

enum AnesthesiaType: string
{
    case General = 'general';
    case Spinal = 'spinal';
    case Epidural = 'epidural';
    case Regional = 'regional';
    case Local = 'local';
    case Sedation = 'sedation';

    public function label(): string
    {
        return match ($this) {
            self::General => 'عام',
            self::Spinal => 'نخاعي',
            self::Epidural => 'فوق الجافية',
            self::Regional => 'ناحي',
            self::Local => 'موضعي',
            self::Sedation => 'تسكين',
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
