<?php

declare(strict_types=1);

namespace App\Core\Authorization;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\UnauthorizedActionException;

/**
 * Used inside actions for checks that cannot be expressed in a FormRequest,
 * for example verifying a lab result the acting user also entered.
 */
final class PermissionChecker
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    public function allows(string $permission): bool
    {
        return $this->currentUser->model()?->can($permission) === true;
    }

    public function denies(string $permission): bool
    {
        return ! $this->allows($permission);
    }

    public function authorize(string $permission): void
    {
        if ($this->denies($permission)) {
            throw UnauthorizedActionException::missingPermission($permission);
        }
    }
}
