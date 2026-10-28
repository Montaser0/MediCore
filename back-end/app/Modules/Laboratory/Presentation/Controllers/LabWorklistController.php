<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Laboratory\Application\Actions\CancelLabOrderItemAction;
use App\Modules\Laboratory\Application\Queries\LabBarcodeQuery;
use App\Modules\Laboratory\Application\Queries\LabWorklistQuery;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use App\Modules\Laboratory\Presentation\Resources\LabOrderItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LabWorklistController extends ApiController
{
    public function index(Request $request, LabWorklistQuery $query): JsonResponse
    {
        $items = $query->execute(
            array_filter($request->only([
                'status', 'priority', 'patient_id', 'encounter_id',
                'lab_test_id', 'date', 'open_only',
            ])),
            $this->perPage(50),
        );

        return ApiResponse::paginated(LabOrderItemResource::collection($items), $items);
    }

    public function showByBarcode(string $barcode, LabBarcodeQuery $query): JsonResponse
    {
        $ticket = $query->find($barcode);

        if ($ticket === null) {
            return ApiResponse::message('لا يوجد طلب تحاليل بهذا الباركود.', 404);
        }

        return ApiResponse::data($ticket);
    }

    public function show(LabOrderItemModel $labItem): JsonResponse
    {
        return ApiResponse::resource(new LabOrderItemResource($labItem->load([
            'test.parameters',
            'samples.collectedBy',
            'result.values.parameter',
            'result.enteredBy',
            'result.verifiedBy',
            'labOrder.order.encounter.patient',
            'labOrder.order.orderedBy',
        ])));
    }

    public function cancel(Request $request, LabOrderItemModel $labItem, CancelLabOrderItemAction $action): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::resource(new LabOrderItemResource(
            $action->execute($labItem, $validated['reason'] ?? null)->load('test')
        ));
    }
}
