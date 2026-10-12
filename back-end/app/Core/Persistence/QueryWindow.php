<?php

declare(strict_types=1);

namespace App\Core\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Day bounds that leave the indexed column untouched.
 * whereDate() wraps the column in a function, so MySQL cannot use the index.
 */
final class QueryWindow
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function on(Builder $query, string $column, mixed $day): void
    {
        [$start, $end] = self::bounds($day);
        $query->where($column, '>=', $start)->where($column, '<', $end);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function from(Builder $query, string $column, mixed $day): void
    {
        $query->where($column, '>=', self::bounds($day)[0]);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function until(Builder $query, string $column, mixed $day): void
    {
        $query->where($column, '<', self::bounds($day)[1]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function bounds(mixed $day): array
    {
        $start = Carbon::parse($day, (string) config('app.timezone'))->startOfDay();

        return [$start, $start->copy()->addDay()];
    }
}
