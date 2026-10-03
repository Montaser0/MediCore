<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class UnauthorizedActionException extends DomainException
{
    protected int $status = Response::HTTP_FORBIDDEN;

    protected string $errorCode = 'unauthorized_action';

    public static function missingPermission(string $permission): self
    {
        return new self('ليست لديك صلاحية لهذا الإجراء.', [
            'required_permission' => $permission,
        ]);
    }
}
