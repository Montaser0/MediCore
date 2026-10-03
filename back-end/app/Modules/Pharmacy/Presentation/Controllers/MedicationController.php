<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationCategoryModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Pharmacy\Presentation\Requests\StoreMedicationRequest;
use App\Modules\Pharmacy\Presentation\Resources\MedicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MedicationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $medications = MedicationModel::query()
            ->with('inventoryItem')
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->search($request->string('search')->toString())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->orderBy('name')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(MedicationResource::collection($medications), $medications);
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::data(
            MedicationCategoryModel::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_controlled'])
                ->all()
        );
    }

    public function show(MedicationModel $medication): JsonResponse
    {
        return ApiResponse::resource(new MedicationResource($medication->load('inventoryItem.stockLevels.warehouse')));
    }

    public function store(StoreMedicationRequest $request): JsonResponse
    {
        return ApiResponse::created(new MedicationResource(
            MedicationModel::query()->create($request->validated())
        ));
    }

    public function update(StoreMedicationRequest $request, MedicationModel $medication): JsonResponse
    {
        $medication->fill($request->validated())->save();

        return ApiResponse::resource(new MedicationResource($medication->load('inventoryItem')));
    }

    public function destroy(Request $request, MedicationModel $medication): JsonResponse
    {
        DeactivateRecord::run($request, $medication, Permissions::PHARMACY_MEDICATIONS_MANAGE);

        return ApiResponse::noContent();
    }
}
