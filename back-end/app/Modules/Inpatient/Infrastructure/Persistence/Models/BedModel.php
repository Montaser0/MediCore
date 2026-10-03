<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Inpatient\Domain\Enums\BedStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class BedModel extends BaseModel
{
    use Auditable;

    protected $table = 'beds';

    protected function casts(): array
    {
        return [
            'status' => BedStatus::class,
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(RoomModel::class, 'room_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BedAssignmentModel::class, 'bed_id');
    }

    /**
     * The assignment with no release date is the patient currently in the bed.
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(BedAssignmentModel::class, 'bed_id')
            ->whereNull('released_at')
            ->latestOfMany('assigned_at');
    }

    public function fullLabel(): string
    {
        $room = $this->room;

        return trim(($room?->ward?->name ?? '').' / '.($room?->room_number ?? '').' / '.$this->bed_number, ' /');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', BedStatus::Available->value);
    }

    public function scopeInWard(Builder $query, ?int $wardId): Builder
    {
        if ($wardId === null) {
            return $query;
        }

        return $query->whereHas('room', fn (Builder $room) => $room->where('ward_id', $wardId));
    }
}
