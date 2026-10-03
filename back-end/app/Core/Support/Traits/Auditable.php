<?php

declare(strict_types=1);

namespace App\Core\Support\Traits;

use App\Core\Support\Contracts\AuditRecorder;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create/update/delete on sensitive medical records. Applied to the
 * models the PRD calls out as auditable rather than to every table, so the
 * audit trail stays readable.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        if (! config('hms.audit.enabled')) {
            return;
        }

        static::created(function (Model $model): void {
            $model->writeAudit('created', null, $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            $model->writeAudit(
                'updated',
                $model->auditableAttributes($original),
                $model->auditableAttributes($changes),
            );
        });

        static::deleted(function (Model $model): void {
            $model->writeAudit('deleted', $model->auditableAttributes($model->getOriginal()), null);
        });
    }

    /**
     * Attributes that must never reach the audit trail.
     *
     * @return array<int, string>
     */
    protected function auditExcluded(): array
    {
        return ['password', 'remember_token'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditableAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip($this->auditExcluded()));
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function writeAudit(string $action, ?array $old, ?array $new): void
    {
        app(AuditRecorder::class)->record(
            action: class_basename(static::class).'.'.$action,
            entityType: static::class,
            entityId: $this->getKey(),
            oldValues: $old,
            newValues: $new,
        );
    }
}
