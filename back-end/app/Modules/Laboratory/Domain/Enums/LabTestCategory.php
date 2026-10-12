<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Enums;

enum LabTestCategory: string
{
    case Hematology = 'hematology';
    case Chemistry = 'chemistry';
    case Hormones = 'hormones';
    case Tumors = 'tumors';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
