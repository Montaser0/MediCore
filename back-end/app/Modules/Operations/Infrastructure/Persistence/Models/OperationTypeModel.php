<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Builder;

final class OperationTypeModel extends BaseModel
{
    protected $table = 'operation_types';

    protected function casts(): array
    {
        return [
            'estimated_duration_minutes' => 'integer',
            'price' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(fn (Builder $inner) => $inner
            ->where('name', 'like', "%{$term}%")
            ->orWhere('code', 'like', "%{$term}%")
            ->orWhere('specialty', 'like', "%{$term}%"));
    }
}
