<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Support\Contracts\AuditRecorder;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLogModel;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only writer of audit rows. Bound to AuditRecorder so other modules
 * depend on the contract rather than on this module.
 */
final class RecordAuditAction implements AuditRecorder
{
    public function __construct(private readonly CurrentUser $currentUser) {}

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
    ): void {
        if (! config('hms.audit.enabled')) {
            return;
        }

        $request = request();

        try {
            AuditLogModel::query()->create([
                'user_id' => $this->currentUser->id(),
                'hospital_id' => $this->currentUser->hospitalId(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => is_numeric($entityId) ? (int) $entityId : null,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (Throwable $e) {
            // A failure to audit must never take down the clinical operation
            // that triggered it, but it has to be visible in the logs.
            Log::error('Failed to write audit log entry', [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
