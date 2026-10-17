<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /**
     * @return BelongsToMany<ClinicModel, $this>
     */
    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(
            ClinicModel::class,
            'clinic_operation_type',
            'operation_type_id',
            'clinic_id',
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<OperationTypeModel>  $query
     * @return Builder<OperationTypeModel>
     */
    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->whereHas(
            'clinics',
            fn (Builder $clinics) => $clinics->where('clinics.id', $clinicId),
        );
    }

    public static function nextCode(): string
    {
        $latest = static::query()->orderByDesc('id')->value('code');
        $number = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $number = ((int) $matches[1]) + 1;
        }

        return 'OP-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
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
