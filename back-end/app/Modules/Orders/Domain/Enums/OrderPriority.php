<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Enums;

enum OrderPriority: string
{
    case Routine = 'routine';
    case Urgent = 'urgent';
    case Stat = 'stat';

    /**
     * Lower sorts first, so a stat order jumps the department queue.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Stat => 0,
            self::Urgent => 1,
            self::Routine => 2,
        };
    }

    public function requiresAlert(): bool
    {
        return $this !== self::Routine;
    }

    public function label(): string
    {
        return match ($this) {
            self::Routine => 'عادي',
            self::Urgent => 'مستعجل',
            self::Stat => 'فوري',
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
