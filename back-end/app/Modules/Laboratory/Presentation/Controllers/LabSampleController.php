<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Laboratory\Application\Actions\CollectSampleAction;
use App\Modules\Laboratory\Application\Actions\ReceiveSampleAction;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabSampleModel;
use App\Modules\Laboratory\Presentation\Requests\CollectSampleRequest;
use App\Modules\Laboratory\Presentation\Requests\RejectSampleRequest;
use App\Modules\Laboratory\Presentation\Resources\LabSampleResource;
use Illuminate\Http\JsonResponse;

final class LabSampleController extends ApiController
{
    public function store(CollectSampleRequest $request, LabOrderItemModel $labItem, CollectSampleAction $action): JsonResponse
    {
        return ApiResponse::created(new LabSampleResource(
            $action->execute($labItem, $request->validated())->load('collectedBy')
        ));
    }

    public function receive(LabSampleModel $sample, ReceiveSampleAction $action): JsonResponse
    {
        return ApiResponse::resource(new LabSampleResource($action->receive($sample)));
    }

    public function reject(RejectSampleRequest $request, LabSampleModel $sample, ReceiveSampleAction $action): JsonResponse
    {
        return ApiResponse::resource(new LabSampleResource(
            $action->reject($sample, $request->string('reason')->toString())
        ));
    }
}
