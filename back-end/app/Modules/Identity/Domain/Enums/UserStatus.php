<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    public static function fromFlag(bool $isActive): self
    {
        return $isActive ? self::Active : self::Disabled;
    }
}
