<?php

declare(strict_types=1);

namespace App\Modules\Operations\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTheatreModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTypeModel;
use App\Modules\Operations\Presentation\Requests\StoreOperationTypeRequest;
use App\Modules\Operations\Presentation\Resources\OperationTypeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OperationTypeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $types = OperationTypeModel::query()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->search($request->string('search')->toString())
            ->orderBy('name')
            ->get();

        return ApiResponse::data(OperationTypeResource::collection($types)->resolve());
    }

    public function store(StoreOperationTypeRequest $request): JsonResponse
    {
        return ApiResponse::created(new OperationTypeResource(
            OperationTypeModel::query()->create($request->validated())
        ));
    }

    public function update(StoreOperationTypeRequest $request, OperationTypeModel $type): JsonResponse
    {
        $type->fill($request->validated())->save();

        return ApiResponse::resource(new OperationTypeResource($type));
    }

    public function destroy(Request $request, OperationTypeModel $type): JsonResponse
    {
        DeactivateRecord::run($request, $type, Permissions::SETTINGS_MANAGE);

        return ApiResponse::noContent();
    }

    public function theatres(Request $request, CurrentUser $currentUser): JsonResponse
    {
        $theatres = OperationTheatreModel::query()
            ->forCurrentHospital()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active']);

        return ApiResponse::data($theatres->all());
    }

    public function storeTheatre(Request $request, CurrentUser $currentUser): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50'],
        ]);

        $theatre = OperationTheatreModel::query()->create($validated + [
            'hospital_id' => $currentUser->hospitalId(),
        ]);

        return ApiResponse::data([
            'id' => $theatre->getKey(),
            'name' => $theatre->name,
            'code' => $theatre->code,
        ], status: 201);
    }
}
