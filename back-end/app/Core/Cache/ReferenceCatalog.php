<?php

declare(strict_types=1);

namespace App\Core\Cache;

use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Departments\Presentation\Resources\DepartmentResource;
use Illuminate\Support\Facades\Cache;

/**
 * Departments and clinics change rarely, so the list is cached for an hour
 * and dropped on write. Clinical rows (visits, results, invoices) are not
 * cached: they change during the working day and need an explicit invalidation
 * policy before any cache is safe.
 *
 * The store is the default CACHE_STORE. Use redis only when a shared cache
 * or a queue actually needs it; this class does not require Redis.
 */
final class ReferenceCatalog
{
    private const TTL_SECONDS = 3600;

    /**
     * @return list<array<string, mixed>>
     */
    public function departments(int $hospitalId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            self::key($hospitalId),
            self::TTL_SECONDS,
            function () use ($hospitalId): array {
                $departments = DepartmentModel::query()
                    ->with(['clinics' => fn ($query) => $query->orderBy('name')])
                    ->where('hospital_id', $hospitalId)
                    ->orderBy('name')
                    ->get();

                return DepartmentResource::collection($departments)->resolve();
            },
        );

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function clinics(int $hospitalId): array
    {
        $clinics = [];

        foreach ($this->departments($hospitalId) as $department) {
            foreach ($department['clinics'] ?? [] as $clinic) {
                if (! is_array($clinic)) {
                    continue;
                }

                $clinics[] = $clinic + [
                    'department' => [
                        'id' => $department['id'],
                        'name' => $department['name'],
                    ],
                ];
            }
        }

        usort(
            $clinics,
            static fn (array $left, array $right): int => strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? '')),
        );

        return $clinics;
    }

    public static function forget(int $hospitalId): void
    {
        if ($hospitalId < 1) {
            return;
        }

        Cache::forget(self::key($hospitalId));
    }

    private static function key(int $hospitalId): string
    {
        return 'ref:departments:'.$hospitalId;
    }
}
