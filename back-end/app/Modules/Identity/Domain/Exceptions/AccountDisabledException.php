<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use App\Core\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

final class AccountDisabledException extends DomainException
{
    protected int $status = Response::HTTP_FORBIDDEN;

    protected string $errorCode = 'account_disabled';

    public static function make(): self
    {
        return new self('هذا الحساب معطّل. راجع مدير النظام.');
    }
}
