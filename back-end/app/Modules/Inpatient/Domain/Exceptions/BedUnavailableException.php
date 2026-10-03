<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Domain\Exceptions;

use App\Core\Exceptions\DomainException;

/**
 * Two patients in one bed is the failure this system exists to prevent, so
 * it is a conflict rather than a validation error.
 */
final class BedUnavailableException extends DomainException
{
    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'inpatient.bed_unavailable';
    }

    public static function for(string $bedNumber, string $status): self
    {
        return new self(
            "السرير {$bedNumber} غير متاح للتعيين.",
            ['bed_number' => $bedNumber, 'status' => $status],
        );
    }
}
