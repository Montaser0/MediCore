<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Domain\Enums;

enum ReportStatus: string
{
    case Draft = 'draft';
    case Final = 'final';

    public function isReleasable(): bool
    {
        return $this === self::Final;
    }
}
