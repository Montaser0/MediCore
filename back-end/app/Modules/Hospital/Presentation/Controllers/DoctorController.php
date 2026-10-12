<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Presentation\Resources\DoctorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DoctorController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $doctors = DoctorModel::query()
            ->with('staff.department')
            ->when($request->filled('department_id'), fn ($query) => $query->inDepartment($request->integer('department_id')))
            ->when($request->filled('clinic_id'), fn ($query) => $query->inClinic($request->integer('clinic_id')))
            ->when($request->filled('specialty'), fn ($query) => $query->where('specialty', 'like', '%'.$request->string('specialty').'%'))
            ->when(
                ! $request->boolean('include_inactive'),
                fn ($query) => $query->whereHas('staff', fn ($staff) => $staff->where('is_active', true))
            )
            ->paginate($this->perPage());

        return ApiResponse::paginated(DoctorResource::collection($doctors), $doctors);
    }

    public function show(DoctorModel $doctor): JsonResponse
    {
        return ApiResponse::resource(new DoctorResource($doctor->load('staff.department')));
    }
}
