<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

final class WardModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'wards';

    protected function casts(): array
    {
        return [
            'floor' => 'integer',
            'daily_rate' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(RoomModel::class, 'ward_id');
    }

    public function beds(): HasManyThrough
    {
        return $this->hasManyThrough(BedModel::class, RoomModel::class, 'ward_id', 'room_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
