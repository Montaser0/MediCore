<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use App\Core\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

final class InvalidCredentialsException extends DomainException
{
    protected int $status = Response::HTTP_UNAUTHORIZED;

    protected string $errorCode = 'invalid_credentials';

    public static function make(): self
    {
        return new self('بيانات الدخول غير صحيحة.');
    }
}
