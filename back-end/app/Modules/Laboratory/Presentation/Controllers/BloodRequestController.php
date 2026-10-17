<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Laboratory\Application\Actions\CreateBloodRequestAction;
use App\Modules\Laboratory\Application\Actions\FulfillBloodRequestAction;
use App\Modules\Laboratory\Application\Queries\BloodRequestQuery;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodRequestModel;
use App\Modules\Laboratory\Presentation\Requests\FulfillBloodRequestRequest;
use App\Modules\Laboratory\Presentation\Requests\StoreBloodRequestRequest;
use App\Modules\Laboratory\Presentation\Resources\BloodRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BloodRequestController extends ApiController
{
    public function index(Request $request, BloodRequestQuery $query): JsonResponse
    {
        $items = $query->execute(
            array_filter($request->only(['status', 'patient_id', 'search'])),
            $this->perPage(50),
        );

        return ApiResponse::paginated(BloodRequestResource::collection($items), $items);
    }

    public function store(StoreBloodRequestRequest $request, CreateBloodRequestAction $action): JsonResponse
    {
        return ApiResponse::created(new BloodRequestResource(
            $action->execute($request->validated())
        ));
    }

    public function fulfill(
        FulfillBloodRequestRequest $request,
        BloodRequestModel $bloodRequest,
        FulfillBloodRequestAction $action,
    ): JsonResponse {
        return ApiResponse::resource(new BloodRequestResource(
            $action->execute($bloodRequest, $request->validated())
        ));
    }
}
