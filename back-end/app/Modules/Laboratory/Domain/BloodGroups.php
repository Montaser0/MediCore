<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain;

final class BloodGroups
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    }
}
