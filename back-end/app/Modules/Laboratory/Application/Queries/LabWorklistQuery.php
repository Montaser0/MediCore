<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Queries;

use App\Core\Persistence\QueryWindow;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * What the laboratory screen shows: every requested test with its patient,
 * sample and result state, urgent work first.
 */
final class LabWorklistQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 50));

        return LabOrderItemModel::query()
            ->with([
                'test',
                'samples',
                'result',
                'labOrder.order.encounter.patient',
                'labOrder.order.orderedBy',
            ])
            ->whereHas('labOrder.order', function ($query) use ($filters): void {
                $query->forCurrentHospital();

                if (! empty($filters['priority'])) {
                    $query->whereIn('priority', (array) $filters['priority']);
                }

                if (! empty($filters['patient_id'])) {
                    $query->whereHas('encounter', fn ($e) => $e->where('patient_id', $filters['patient_id']));
                }

                if (! empty($filters['encounter_id'])) {
                    $query->where('encounter_id', $filters['encounter_id']);
                }

                if (! empty($filters['date'])) {
                    QueryWindow::on($query, 'ordered_at', $filters['date']);
                }
            })
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->whereIn('status', (array) $value))
            ->when($filters['lab_test_id'] ?? null, fn ($q, $value) => $q->where('lab_test_id', $value))
            ->when(
                ($filters['open_only'] ?? null) !== null && filter_var($filters['open_only'], FILTER_VALIDATE_BOOLEAN),
                fn ($q) => $q->open(),
            )
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
