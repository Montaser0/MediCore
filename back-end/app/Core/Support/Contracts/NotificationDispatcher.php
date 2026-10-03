<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

/**
 * Lets any module raise an in-app notification without knowing how it is
 * stored or broadcast.
 */
interface NotificationDispatcher
{
    /**
     * @param  array<int, int>|int  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function toUsers(
        array|int $userIds,
        string $type,
        string $title,
        ?string $message = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $payload = [],
    ): void;

    /**
     * Fans out to everyone who holds a permission, which is how a department
     * queue gets told that new work arrived.
     *
     * @param  array<string, mixed>  $payload
     */
    public function toPermissionHolders(
        string $permission,
        string $type,
        string $title,
        ?string $message = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $payload = [],
    ): void;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toDepartment(
        int $departmentId,
        string $type,
        string $title,
        ?string $message = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $payload = [],
    ): void;
}
