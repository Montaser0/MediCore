<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Builder;

final class DiagnosisCodeModel extends BaseModel
{
    protected $table = 'diagnosis_codes';

    protected function casts(): array
    {
        return [
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
            $inner->where('code', 'like', $term.'%')
                ->orWhere('title', 'like', '%'.$term.'%');
        });
    }
}
