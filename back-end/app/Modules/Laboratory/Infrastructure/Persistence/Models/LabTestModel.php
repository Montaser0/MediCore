<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LabTestModel extends BaseModel
{
    protected $table = 'lab_tests';

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_panel' => 'boolean',
            'is_active' => 'boolean',
            'turnaround_hours' => 'integer',
        ];
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(LabTestParameterModel::class, 'lab_test_id')->orderBy('display_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', '%'.$term.'%')
                ->orWhere('code', 'like', $term.'%')
                ->orWhere('category', 'like', '%'.$term.'%');
        });
    }
}
