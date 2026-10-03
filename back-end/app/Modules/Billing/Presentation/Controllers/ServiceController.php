<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Billing\Infrastructure\Persistence\Models\ServiceModel;
use App\Modules\Billing\Presentation\Requests\StoreServiceRequest;
use App\Modules\Billing\Presentation\Resources\ServiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ServiceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $services = ServiceModel::query()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->search($request->string('search')->toString())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->orderBy('name')
            ->paginate($this->perPage(100));

        return ApiResponse::paginated(ServiceResource::collection($services), $services);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        return ApiResponse::created(new ServiceResource(
            ServiceModel::query()->create($request->validated())
        ));
    }

    public function update(StoreServiceRequest $request, ServiceModel $service): JsonResponse
    {
        $service->fill($request->validated())->save();

        return ApiResponse::resource(new ServiceResource($service));
    }

    public function destroy(Request $request, ServiceModel $service): JsonResponse
    {
        DeactivateRecord::run($request, $service, Permissions::BILLING_SERVICES_MANAGE);

        return ApiResponse::noContent();
    }
}
