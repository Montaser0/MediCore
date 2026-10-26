<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Departments\Domain\Enums\DepartmentType;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Pharmacy\Application\Actions\ChangePrescriptionStatusAction;
use App\Modules\Pharmacy\Application\Actions\CreatePrescriptionAction;
use App\Modules\Pharmacy\Domain\Enums\PrescriptionStatus;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionModel;
use App\Modules\Pharmacy\Presentation\Requests\StorePrescriptionRequest;
use App\Modules\Pharmacy\Presentation\Resources\PrescriptionResource;
use App\Core\Authorization\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PrescriptionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $prescriptions = PrescriptionModel::query()
            ->with(['items.medication', 'doctor.staff', 'encounter.patient'])
            ->forCurrentHospital()
            ->when($request->filled('status'), fn ($query) => $query->whereIn('status', (array) $request->input('status')))
            ->when($request->boolean('dispensable_only'), fn ($query) => $query->dispensable())
            ->when(
                ! $request->filled('patient_id') && ! $request->filled('encounter_id'),
                fn ($query) => $query->where('fulfillment', 'hospital'),
            )
            ->when(
                in_array($request->string('fulfillment')->toString(), ['hospital', 'external'], true),
                fn ($query) => $query->where('fulfillment', $request->string('fulfillment')->toString()),
            )
            ->when($request->filled('encounter_id'), fn ($query) => $query->where('encounter_id', $request->integer('encounter_id')))
            ->when($request->filled('patient_id'), fn ($query) => $query->whereHas(
                'encounter',
                fn ($encounter) => $encounter->where('patient_id', $request->integer('patient_id')),
            ))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('prescribed_at', $request->date('date')))
            ->orderByDesc('prescribed_at')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(PrescriptionResource::collection($prescriptions), $prescriptions);
    }

    public function show(PrescriptionModel $prescription): JsonResponse
    {
        return ApiResponse::resource(new PrescriptionResource($prescription->load([
            'items.medication.inventoryItem',
            'doctor.staff',
            'encounter.patient.allergies',
        ])));
    }

    public function forEncounter(EncounterModel $encounter): JsonResponse
    {
        $prescriptions = PrescriptionModel::query()
            ->with(['items.medication', 'doctor.staff'])
            ->where('encounter_id', $encounter->getKey())
            ->orderByDesc('prescribed_at')
            ->get();

        return ApiResponse::data(PrescriptionResource::collection($prescriptions)->resolve());
    }

    public function store(StorePrescriptionRequest $request, CreatePrescriptionAction $action): JsonResponse
    {
        $data = $request->validated();
        $prescriber = $request->user()?->can(Permissions::PHARMACY_PRESCRIPTIONS_CREATE) === true;

        if (! $prescriber) {
            $encounter = EncounterModel::query()->with('department')->find($data['encounter_id'] ?? null);
            if ($encounter?->department?->type !== DepartmentType::Pharmacy) {
                throw BusinessRuleException::make('إنشاء الوصفة من الاستقبال متاح لزيارة قسم الصيدلية فقط.');
            }
            $data['allow_without_doctor'] = true;
        }

        return ApiResponse::created(new PrescriptionResource(
            $action->execute($data)->load(['items.medication', 'doctor.staff'])
        ));
    }

    /**
     * Issues a draft prescription to the pharmacy.
     */
    public function activate(PrescriptionModel $prescription, ChangePrescriptionStatusAction $action): JsonResponse
    {
        return ApiResponse::resource(new PrescriptionResource(
            $action->execute($prescription, PrescriptionStatus::Active)->load('items.medication')
        ));
    }

    public function cancel(Request $request, PrescriptionModel $prescription, ChangePrescriptionStatusAction $action): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return ApiResponse::resource(new PrescriptionResource(
            $action->execute($prescription, PrescriptionStatus::Cancelled, $validated['reason'])->load('items.medication')
        ));
    }
}
