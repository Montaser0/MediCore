<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Inventory\Presentation\Requests\StoreWarehouseRequest;
use App\Modules\Inventory\Presentation\Resources\WarehouseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WarehouseController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $warehouses = WarehouseModel::query()
            ->with('department')
            ->forCurrentHospital()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->when($request->boolean('pharmacy_only'), fn ($query) => $query->pharmacy())
            ->orderBy('name')
            ->get();

        return ApiResponse::data(WarehouseResource::collection($warehouses)->resolve());
    }

    public function store(StoreWarehouseRequest $request, CurrentUser $currentUser): JsonResponse
    {
        $warehouse = WarehouseModel::query()->create(
            $request->validated() + ['hospital_id' => $currentUser->hospitalId()]
        );

        return ApiResponse::created(new WarehouseResource($warehouse));
    }

    public function update(StoreWarehouseRequest $request, WarehouseModel $warehouse): JsonResponse
    {
        $warehouse->fill($request->validated())->save();

        return ApiResponse::resource(new WarehouseResource($warehouse->load('department')));
    }

    public function destroy(Request $request, WarehouseModel $warehouse): JsonResponse
    {
        DeactivateRecord::run($request, $warehouse, Permissions::INVENTORY_ITEMS_MANAGE);

        return ApiResponse::noContent();
    }
}
