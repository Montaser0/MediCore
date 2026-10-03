<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class ResourceNotFoundException extends DomainException
{
    protected int $status = Response::HTTP_NOT_FOUND;

    protected string $errorCode = 'resource_not_found';

    public static function for(string $resource, int|string $id): self
    {
        return new self('السجل المطلوب غير موجود.', [
            'resource' => $resource,
            'id' => $id,
        ]);
    }
}
