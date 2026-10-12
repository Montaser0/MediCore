<?php

declare(strict_types=1);

namespace App\Modules\Departments\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Departments\Presentation\Requests\StoreClinicRequest;
use App\Modules\Departments\Presentation\Resources\ClinicResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ClinicController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $clinics = ClinicModel::query()
            ->with('department')
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->orderBy('name')
            ->get();

        return ApiResponse::data(ClinicResource::collection($clinics)->resolve());
    }

    public function store(StoreClinicRequest $request): JsonResponse
    {
        $clinic = ClinicModel::query()->create($request->validated());

        return ApiResponse::created(new ClinicResource($clinic->load('department')));
    }

    public function update(Request $request, ClinicModel $clinic): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::DEPARTMENTS_MANAGE) === true, 403);

        $clinic->update($request->validate([
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('clinics', 'code')
                    ->where('department_id', $request->integer('department_id') ?: $clinic->department_id)
                    ->ignore($clinic->getKey()),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return ApiResponse::resource(new ClinicResource($clinic->load('department')));
    }

    public function destroy(Request $request, ClinicModel $clinic): JsonResponse
    {
        DeactivateRecord::run($request, $clinic, Permissions::DEPARTMENTS_MANAGE);

        return ApiResponse::noContent();
    }
}
