<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

final class BloodProductModel extends BaseModel
{
    protected $table = 'blood_products';

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function storeCatalog(array $attributes): self
    {
        if (! array_key_exists('is_active', $attributes)) {
            $attributes['is_active'] = true;
        }

        try {
            return static::query()->create($attributes);
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'product_type')) {
                throw $exception;
            }

            $attributes['product_type'] = mb_substr((string) ($attributes['code'] ?? 'other'), 0, 30);
            $model = new static;
            $model->forceFill($attributes)->save();

            return $model;
        }
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
                ->orWhere('code', 'like', $term.'%');
        });
    }
}
