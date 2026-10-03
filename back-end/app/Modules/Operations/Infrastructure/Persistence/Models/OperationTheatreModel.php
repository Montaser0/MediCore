<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OperationTheatreModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'operation_theatres';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function operations(): HasMany
    {
        return $this->hasMany(OperationModel::class, 'theatre_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
