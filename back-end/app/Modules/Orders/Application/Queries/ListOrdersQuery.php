<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Queries;

use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListOrdersQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return OrderModel::query()
            ->with(['items', 'department', 'orderedBy', 'encounter.patient'])
            ->forCurrentHospital()
            ->when($filters['encounter_id'] ?? null, fn ($q, $value) => $q->where('encounter_id', $value))
            ->when($filters['type'] ?? null, fn ($q, $value) => $q->whereIn('type', (array) $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->whereIn('status', (array) $value))
            ->when($filters['priority'] ?? null, fn ($q, $value) => $q->whereIn('priority', (array) $value))
            ->when($filters['department_id'] ?? null, fn ($q, $value) => $q->where('department_id', $value))
            ->when($filters['ordered_by'] ?? null, fn ($q, $value) => $q->where('ordered_by', $value))
            ->when($filters['patient_id'] ?? null, fn ($q, $value) => $q->whereHas(
                'encounter',
                fn ($encounter) => $encounter->where('patient_id', $value),
            ))
            ->when($filters['date'] ?? null, fn ($q, $value) => $q->whereDate('ordered_at', $value))
            ->when(
                ($filters['open_only'] ?? null) !== null && filter_var($filters['open_only'], FILTER_VALIDATE_BOOLEAN),
                fn ($q) => $q->open(),
            )
            ->when(
                ($filters['worklist'] ?? null) !== null && filter_var($filters['worklist'], FILTER_VALIDATE_BOOLEAN),
                fn ($q) => $q->worklist(),
                fn ($q) => $q->orderByDesc('ordered_at'),
            )
            ->paginate($perPage);
    }
}
