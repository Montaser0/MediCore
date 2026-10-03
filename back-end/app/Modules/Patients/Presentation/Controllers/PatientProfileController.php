<?php

declare(strict_types=1);

namespace App\Modules\Patients\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientAllergyModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientConditionModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientContactModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Patients\Presentation\Requests\StorePatientAllergyRequest;
use App\Modules\Patients\Presentation\Requests\StorePatientConditionRequest;
use App\Modules\Patients\Presentation\Requests\StorePatientContactRequest;
use App\Modules\Patients\Presentation\Resources\PatientAllergyResource;
use App\Modules\Patients\Presentation\Resources\PatientConditionResource;
use App\Modules\Patients\Presentation\Resources\PatientContactResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The lifelong facts attached to a patient file rather than to one visit.
 */
final class PatientProfileController extends ApiController
{
    public function allergies(PatientModel $patient): JsonResponse
    {
        return ApiResponse::data(
            PatientAllergyResource::collection($patient->allergies()->latest()->get())->resolve()
        );
    }

    public function storeAllergy(StorePatientAllergyRequest $request, PatientModel $patient): JsonResponse
    {
        $allergy = $patient->allergies()->create($request->validated());

        return ApiResponse::created(new PatientAllergyResource($allergy));
    }

    public function updateAllergy(StorePatientAllergyRequest $request, PatientAllergyModel $allergy): JsonResponse
    {
        $allergy->update($request->validated());

        return ApiResponse::resource(new PatientAllergyResource($allergy));
    }

    public function destroyAllergy(Request $request, PatientAllergyModel $allergy): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::PATIENTS_UPDATE) === true, 403);

        $allergy->delete();

        return ApiResponse::noContent();
    }

    public function conditions(PatientModel $patient): JsonResponse
    {
        return ApiResponse::data(
            PatientConditionResource::collection($patient->conditions()->latest()->get())->resolve()
        );
    }

    public function storeCondition(StorePatientConditionRequest $request, PatientModel $patient): JsonResponse
    {
        $condition = $patient->conditions()->create($request->validated());

        return ApiResponse::created(new PatientConditionResource($condition));
    }

    public function updateCondition(StorePatientConditionRequest $request, PatientConditionModel $condition): JsonResponse
    {
        $condition->update($request->validated());

        return ApiResponse::resource(new PatientConditionResource($condition));
    }

    public function destroyCondition(Request $request, PatientConditionModel $condition): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::PATIENTS_UPDATE) === true, 403);

        $condition->delete();

        return ApiResponse::noContent();
    }

    public function contacts(PatientModel $patient): JsonResponse
    {
        return ApiResponse::data(
            PatientContactResource::collection($patient->contacts()->orderByDesc('is_emergency_contact')->get())->resolve()
        );
    }

    public function storeContact(StorePatientContactRequest $request, PatientModel $patient): JsonResponse
    {
        $contact = $patient->contacts()->create($request->validated());

        return ApiResponse::created(new PatientContactResource($contact));
    }

    public function destroyContact(Request $request, PatientContactModel $contact): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::PATIENTS_UPDATE) === true, 403);

        $contact->delete();

        return ApiResponse::noContent();
    }
}
