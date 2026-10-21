<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Presentation\Resources\DoctorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class DoctorController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $departmentId = $request->filled('department_id') ? $request->integer('department_id') : null;
        $clinicId = $request->filled('clinic_id') ? $request->integer('clinic_id') : null;

        $with = ['staff.department'];
        if (Schema::hasColumn('doctors', 'clinic_id')) {
            $with[] = 'clinic';
        }
        if (Schema::hasTable('clinic_doctor')) {
            $with[] = 'clinics';
        }

        $doctors = DoctorModel::query()
            ->with($with)
            ->when($clinicId !== null, fn ($query) => $query->inClinic($clinicId))
            ->when($clinicId === null && $departmentId !== null, fn ($query) => $query->inDepartment($departmentId))
            ->when($request->filled('specialty'), fn ($query) => $query->where('specialty', 'like', '%'.$request->string('specialty').'%'))
            ->when(
                ! $request->boolean('include_inactive'),
                fn ($query) => $query->whereHas('staff', fn ($staff) => $staff->where('is_active', true))
            )
            ->orderBy('doctors.id')
            ->paginate($this->perPage());

        return ApiResponse::paginated(DoctorResource::collection($doctors), $doctors);
    }

    public function show(DoctorModel $doctor): JsonResponse
    {
        return ApiResponse::resource(new DoctorResource($doctor->load('staff.department')));
    }
}
