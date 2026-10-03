<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Enums;

enum ResultStatus: string
{
    case Draft = 'draft';
    case Final = 'final';

    public function isReleasable(): bool
    {
        return $this === self::Final;
    }
}
