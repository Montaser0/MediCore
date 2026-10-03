<?php

declare(strict_types=1);

namespace App\Modules\Audit\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Audit\Application\Queries\SearchAuditLogsQuery;
use App\Modules\Audit\Presentation\Resources\AuditLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuditLogController extends ApiController
{
    public function index(Request $request, SearchAuditLogsQuery $query): JsonResponse
    {
        $logs = $query->execute(
            array_filter($request->only(['user_id', 'action', 'entity_type', 'entity_id', 'from', 'to'])),
            $this->perPage(50),
        );

        return ApiResponse::paginated(AuditLogResource::collection($logs), $logs);
    }

    /**
     * Full change history for one record, which is what the PRD asks for when
     * a diagnosis or a lab result is questioned.
     */
    public function forEntity(Request $request, string $entityType, int $entityId, SearchAuditLogsQuery $query): JsonResponse
    {
        $logs = $query->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ], $this->perPage(50));

        return ApiResponse::paginated(AuditLogResource::collection($logs), $logs);
    }
}
