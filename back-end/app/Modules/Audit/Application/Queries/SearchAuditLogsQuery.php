<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Queries;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLogModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchAuditLogsQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLogModel>
     */
    public function execute(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return AuditLogModel::query()
            ->with('user:id,username')
            ->when(isset($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['action']), fn ($query) => $query->where('action', 'like', '%'.$filters['action'].'%'))
            ->when(isset($filters['entity_type']), fn ($query) => $query->where('entity_type', 'like', '%'.$filters['entity_type'].'%'))
            ->when(isset($filters['entity_id']), fn ($query) => $query->where('entity_id', $filters['entity_id']))
            ->when(isset($filters['from']), fn ($query) => $query->where('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->where('created_at', '<=', $filters['to']))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
