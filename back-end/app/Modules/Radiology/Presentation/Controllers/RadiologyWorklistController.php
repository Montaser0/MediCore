<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Radiology\Application\Actions\CancelRadiologyOrderItemAction;
use App\Modules\Radiology\Application\Queries\RadiologyWorklistQuery;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;
use App\Modules\Radiology\Presentation\Resources\RadiologyOrderItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RadiologyWorklistController extends ApiController
{
    public function index(Request $request, RadiologyWorklistQuery $query): JsonResponse
    {
        $items = $query->execute(
            array_filter($request->only([
                'status', 'priority', 'patient_id', 'encounter_id',
                'radiology_service_id', 'modality', 'date', 'open_only',
            ])),
            $this->perPage(50),
        );

        return ApiResponse::paginated(RadiologyOrderItemResource::collection($items), $items);
    }

    public function show(RadiologyOrderItemModel $radiologyItem): JsonResponse
    {
        return ApiResponse::resource(new RadiologyOrderItemResource($radiologyItem->load([
            'service',
            'studies.report.reportedBy',
            'studies.performedBy',
            'radiologyOrder.order.encounter.patient',
            'radiologyOrder.order.orderedBy',
        ])));
    }

    public function cancel(Request $request, RadiologyOrderItemModel $radiologyItem, CancelRadiologyOrderItemAction $action): JsonResponse
    {
        abort_unless(
            $request->user()?->can(Permissions::ORDERS_CANCEL) === true
                || $request->user()?->can(Permissions::RADIOLOGY_ORDERS_VIEW) === true,
            403,
        );

        return ApiResponse::resource(new RadiologyOrderItemResource($action->execute($radiologyItem)->load('service')));
    }
}
