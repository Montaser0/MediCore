<?php

declare(strict_types=1);

namespace App\Core\Support\Traits;

use App\Core\Auth\CurrentUser;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tables that carry `hospital_id` use this to keep multi-hospital data
 * separated without every query having to remember the filter.
 */
trait BelongsToHospital
{
    public function scopeForCurrentHospital(Builder $query): Builder
    {
        $hospitalId = app(CurrentUser::class)->hospitalId();

        return $hospitalId === null
            ? $query
            : $query->where($query->getModel()->getTable().'.hospital_id', $hospitalId);
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where($query->getModel()->getTable().'.hospital_id', $hospitalId);
    }
}
