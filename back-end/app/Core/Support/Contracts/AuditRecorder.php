<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

/**
 * Implemented by the Audit module and consumed by every other module, so no
 * module needs to know how audit rows are persisted.
 */
interface AuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $action,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void;
}
