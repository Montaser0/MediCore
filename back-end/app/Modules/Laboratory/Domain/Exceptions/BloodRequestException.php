<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Exceptions;

use App\Core\Exceptions\DomainException;

final class BloodRequestException extends DomainException
{
    protected string $errorCode = 'blood.request_invalid';

    public static function encounterClosed(): self
    {
        return new self('افتح زيارة للمريض قبل طلب الدم.');
    }

    public static function wrongPatient(): self
    {
        return new self('الزيارة لا تخص هذا المريض.');
    }

    public static function productUnavailable(): self
    {
        return new self('منتج الدم هذا غير متاح للطلب.');
    }
}
