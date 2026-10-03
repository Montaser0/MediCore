<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The history of who lay in which bed and when, which is what a transfer
 * trail is made of.
 */
final class BedAssignmentModel extends BaseModel
{
    protected $table = 'bed_assignments';

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(AdmissionModel::class, 'admission_id');
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(BedModel::class, 'bed_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'assigned_by');
    }

    public function nights(): int
    {
        $end = $this->released_at ?? now();

        return max(1, (int) $this->assigned_at->diffInDays($end));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }
}
