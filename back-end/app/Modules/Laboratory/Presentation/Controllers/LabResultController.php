<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Laboratory\Application\Actions\EnterLabResultAction;
use App\Modules\Laboratory\Application\Actions\VerifyLabResultAction;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabResultModel;
use App\Modules\Laboratory\Presentation\Requests\StoreLabResultRequest;
use App\Modules\Laboratory\Presentation\Resources\LabResultResource;
use Illuminate\Http\JsonResponse;

final class LabResultController extends ApiController
{
    public function store(StoreLabResultRequest $request, LabOrderItemModel $labItem, EnterLabResultAction $action): JsonResponse
    {
        return ApiResponse::created(new LabResultResource(
            $action->execute($labItem, $request->validated())
        ));
    }

    public function show(LabResultModel $labResult): JsonResponse
    {
        return ApiResponse::resource(new LabResultResource($labResult->load([
            'values.parameter', 'enteredBy', 'verifiedBy', 'orderItem.test',
        ])));
    }

    public function verify(LabResultModel $labResult, VerifyLabResultAction $action): JsonResponse
    {
        return ApiResponse::resource(new LabResultResource($action->execute($labResult)));
    }
}
