<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Builder;

final class RadiologyServiceModel extends BaseModel
{
    protected $table = 'radiology_services';

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'requires_contrast' => 'boolean',
            'is_active' => 'boolean',
        ];
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
                ->orWhere('modality', 'like', '%'.$term.'%');
        });
    }
}
