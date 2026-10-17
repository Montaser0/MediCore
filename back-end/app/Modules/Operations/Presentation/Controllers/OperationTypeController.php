<?php

declare(strict_types=1);

namespace App\Modules\Operations\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Clinical\Application\AccountDiagnosisClinics;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTheatreModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTypeModel;
use App\Modules\Operations\Presentation\Requests\StoreOperationTypeRequest;
use App\Modules\Operations\Presentation\Resources\OperationTypeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OperationTypeController extends ApiController
{
    public function index(Request $request, AccountDiagnosisClinics $accountClinics): JsonResponse
    {
        $limited = $accountClinics->ids();

        $types = OperationTypeModel::query()
            ->with('clinics')
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->search($request->string('search')->toString())
            ->when($limited !== null, function ($query) use ($limited): void {
                if ($limited === []) {
                    $query->whereRaw('0 = 1');

                    return;
                }

                $query->whereHas(
                    'clinics',
                    fn ($clinics) => $clinics->whereIn('clinics.id', $limited),
                );
            })
            ->when(
                $limited === null && $request->filled('clinic_id'),
                fn ($query) => $query->forClinic($request->integer('clinic_id')),
            )
            ->orderBy('name')
            ->get();

        return ApiResponse::data(OperationTypeResource::collection($types)->resolve());
    }

    public function store(StoreOperationTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $clinicId = (int) $data['clinic_id'];
        unset($data['clinic_id']);

        if (blank($data['code'] ?? null)) {
            $data['code'] = OperationTypeModel::nextCode();
        }

        $type = OperationTypeModel::query()->create($data);
        $type->clinics()->sync([$clinicId]);

        return ApiResponse::created(new OperationTypeResource($type->load('clinics')));
    }

    public function update(StoreOperationTypeRequest $request, OperationTypeModel $type): JsonResponse
    {
        $data = $request->validated();
        $clinicId = (int) $data['clinic_id'];
        unset($data['clinic_id']);

        if (blank($data['code'] ?? null)) {
            unset($data['code']);
        }

        $type->fill($data)->save();
        $type->clinics()->sync([$clinicId]);

        return ApiResponse::resource(new OperationTypeResource($type->load('clinics')));
    }

    public function destroy(Request $request, OperationTypeModel $type): JsonResponse
    {
        DeactivateRecord::run($request, $type, Permissions::DEPARTMENTS_MANAGE);

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
