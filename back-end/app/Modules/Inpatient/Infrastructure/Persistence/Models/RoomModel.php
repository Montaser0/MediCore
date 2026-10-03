<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RoomModel extends BaseModel
{
    protected $table = 'rooms';

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'daily_rate' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(WardModel::class, 'ward_id');
    }

    public function beds(): HasMany
    {
        return $this->hasMany(BedModel::class, 'room_id');
    }

    /**
     * Rooms may price differently from their ward; falling back keeps the
     * price list short.
     */
    public function effectiveDailyRate(): float
    {
        return (float) ($this->daily_rate ?? $this->ward?->daily_rate ?? 0);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
