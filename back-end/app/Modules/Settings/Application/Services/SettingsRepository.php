<?php

declare(strict_types=1);

namespace App\Modules\Settings\Application\Services;

use App\Modules\Settings\Infrastructure\Persistence\Models\SettingModel;
use Illuminate\Support\Facades\Cache;

/**
 * Reads are cached because settings are consulted on nearly every request;
 * writes clear the affected cache key.
 */
final class SettingsRepository
{
    private const CACHE_TTL = 3600;

    public function get(string $group, string $key, mixed $default = null, ?int $hospitalId = null): mixed
    {
        $all = $this->group($group, $hospitalId);

        return $all[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group, ?int $hospitalId = null): array
    {
        return Cache::remember(
            $this->cacheKey($group, $hospitalId),
            self::CACHE_TTL,
            fn () => SettingModel::query()
                ->where('group', $group)
                ->where(fn ($query) => $query->whereNull('hospital_id')->orWhere('hospital_id', $hospitalId))
                // Hospital-specific rows come last so they overwrite the
                // system-wide defaults when both exist.
                ->orderByRaw('hospital_id IS NULL DESC')
                ->get()
                ->mapWithKeys(fn (SettingModel $setting) => [$setting->key => $setting->value])
                ->all(),
        );
    }

    public function set(string $group, string $key, mixed $value, ?int $hospitalId = null, string $type = 'string'): SettingModel
    {
        $setting = SettingModel::query()->updateOrCreate(
            ['hospital_id' => $hospitalId, 'group' => $group, 'key' => $key],
            ['value' => $value, 'type' => $type],
        );

        Cache::forget($this->cacheKey($group, $hospitalId));

        return $setting;
    }

    public function forget(string $group, string $key, ?int $hospitalId = null): void
    {
        SettingModel::query()
            ->where('group', $group)
            ->where('key', $key)
            ->when(
                $hospitalId === null,
                fn ($query) => $query->whereNull('hospital_id'),
                fn ($query) => $query->where('hospital_id', $hospitalId),
            )
            ->delete();

        Cache::forget($this->cacheKey($group, $hospitalId));
    }

    private function cacheKey(string $group, ?int $hospitalId): string
    {
        return "hms.settings.{$group}.".($hospitalId ?? 'global');
    }
}
