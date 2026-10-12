<?php

declare(strict_types=1);

namespace App\Modules\Departments\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Auth\CurrentUser;
use App\Core\Cache\ReferenceCatalog;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Departments\Domain\Enums\DepartmentType;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Departments\Presentation\Requests\StoreDepartmentRequest;
use App\Modules\Departments\Presentation\Requests\UpdateDepartmentRequest;
use App\Modules\Departments\Presentation\Resources\DepartmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DepartmentController extends ApiController
{
    public function index(Request $request, CurrentUser $currentUser, ReferenceCatalog $catalog): JsonResponse
    {
        $hospitalId = $currentUser->hospitalId();

        if ($hospitalId === null) {
            $departments = DepartmentModel::query()
                ->with('clinics')
                ->forCurrentHospital()
                ->when($request->filled('type'), fn ($query) => $query->where('type', (string) $request->string('type')))
                ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
                ->orderBy('name')
                ->get();

            return ApiResponse::data(DepartmentResource::collection($departments)->resolve());
        }

        $rows = collect($catalog->departments($hospitalId));

        if (! $request->boolean('include_inactive')) {
            $rows = $rows->where('is_active', true);
        }

        if ($request->filled('type')) {
            $rows = $rows->where('type', (string) $request->string('type'));
        }

        return ApiResponse::data($rows->values()->all());
    }

    public function types(): JsonResponse
    {
        return ApiResponse::data(array_map(
            fn (DepartmentType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'is_clinical' => $type->isClinical(),
            ],
            DepartmentType::cases(),
        ));
    }

    public function show(DepartmentModel $department): JsonResponse
    {
        return ApiResponse::resource(new DepartmentResource($department->load('clinics')));
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = DepartmentModel::query()->create($request->validated());

        return ApiResponse::created(new DepartmentResource($department));
    }

    public function update(UpdateDepartmentRequest $request, DepartmentModel $department): JsonResponse
    {
        $department->update($request->validated());

        return ApiResponse::resource(new DepartmentResource($department->load('clinics')));
    }

    public function destroy(Request $request, DepartmentModel $department): JsonResponse
    {
        DeactivateRecord::run($request, $department, Permissions::DEPARTMENTS_MANAGE);

        return ApiResponse::noContent();
    }
}
