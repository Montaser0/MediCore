<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Departments\Domain\Enums\DepartmentType;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Encounters\Application\Actions\CloseEncounterAction;
use App\Modules\Encounters\Application\Actions\CreateEncounterAction;
use App\Modules\Encounters\Application\Actions\StartEncounterAction;
use App\Modules\Encounters\Application\Actions\TransferEncounterAction;
use App\Modules\Orders\Application\Actions\CreateOrderAction;
use App\Modules\Pharmacy\Application\Actions\CreatePrescriptionAction;
use App\Modules\Encounters\Application\Queries\ListEncountersQuery;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Encounters\Presentation\Requests\CloseEncounterRequest;
use App\Modules\Encounters\Presentation\Requests\StoreEncounterRequest;
use App\Modules\Encounters\Presentation\Requests\TransferEncounterRequest;
use App\Modules\Encounters\Presentation\Resources\EncounterResource;
use App\Modules\Encounters\Presentation\Resources\EncounterTransferResource;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EncounterController extends ApiController
{
    public function index(Request $request, ListEncountersQuery $query, CurrentUser $currentUser): JsonResponse
    {
        $filters = array_filter($request->only([
            'patient_id', 'department_id', 'clinic_id', 'doctor_id',
            'status', 'type', 'open_only', 'date', 'from', 'to', 'search',
        ]));

        $ownDoctorId = $currentUser->ownDoctorId();
        if ($ownDoctorId !== null) {
            $filters['doctor_id'] = $ownDoctorId;
            $filters['shared_clinic_ids'] = $currentUser->doctorClinicIds();
        }

        $clinicIds = $currentUser->dataEntryClinicIds();
        $departmentIds = $currentUser->dataEntryDepartmentIds();

        if ($clinicIds !== null) {
            $filters['clinic_ids'] = $clinicIds;
        } elseif ($departmentIds !== null) {
            $filters['department_ids'] = $departmentIds;
        }

        $encounters = $query->execute($filters, $this->perPage());

        return ApiResponse::paginated(EncounterResource::collection($encounters), $encounters);
    }

    public function show(EncounterModel $encounter, CurrentUser $currentUser): JsonResponse
    {
        abort_unless(
            $currentUser->canAccessVisit(
                $encounter->department_id === null ? null : (int) $encounter->department_id,
                $encounter->clinic_id === null ? null : (int) $encounter->clinic_id,
            ),
            403,
            'هذه الزيارة ليست من المرضى المحوّلين إلى عيادتك.',
        );

        return ApiResponse::resource(new EncounterResource(
            $encounter->load(['patient.allergies', 'department', 'clinic', 'doctor.staff'])
        ));
    }

    /**
     * The clinic waiting list (PRD §22): who is queued for this department,
     * clinic or doctor, oldest first.
     */
    public function queue(Request $request, CurrentUser $currentUser): JsonResponse
    {
        $ownDoctorId = $currentUser->ownDoctorId();
        $doctorClinics = $ownDoctorId !== null ? $currentUser->doctorClinicIds() : [];
        $clinicIds = $currentUser->dataEntryClinicIds();
        $departmentIds = $currentUser->dataEntryDepartmentIds();
        $lockedClinic = $clinicIds !== null ? ($clinicIds[0] ?? null) : null;

        $encounters = EncounterModel::query()
            ->with(['patient', 'department', 'clinic', 'doctor.staff'])
            ->forCurrentHospital()
            ->queueFor(
                $request->integer('department_id') ?: null,
                $lockedClinic ?? ($request->integer('clinic_id') ?: null),
                $ownDoctorId !== null ? null : ($request->integer('doctor_id') ?: null),
            )
            ->when($ownDoctorId !== null, function ($query) use ($ownDoctorId, $doctorClinics): void {
                $query->where(function ($inner) use ($ownDoctorId, $doctorClinics): void {
                    $inner->where('doctor_id', $ownDoctorId);
                    if ($doctorClinics !== []) {
                        $inner->orWhere(function ($shared) use ($doctorClinics): void {
                            $shared->whereNull('doctor_id')->whereIn('clinic_id', $doctorClinics);
                        });
                    }
                });
            })
            ->when($clinicIds !== null, fn ($query) => $query->whereIn('clinic_id', $clinicIds))
            ->when($departmentIds !== null, fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('created_at', $request->string('date')->toString()))
            ->paginate($this->perPage(100));

        return ApiResponse::paginated(EncounterResource::collection($encounters), $encounters);
    }

    public function forPatient(Request $request, PatientModel $patient, ListEncountersQuery $query, CurrentUser $currentUser): JsonResponse
    {
        $filters = ['patient_id' => $patient->getKey()] + array_filter($request->only(['status', 'type', 'open_only']));
        $clinicIds = $currentUser->dataEntryClinicIds();
        $departmentIds = $currentUser->dataEntryDepartmentIds();

        if ($clinicIds !== null) {
            $filters['clinic_ids'] = $clinicIds;
        } elseif ($departmentIds !== null) {
            $filters['department_ids'] = $departmentIds;
        }

        $encounters = $query->execute($filters, $this->perPage());

        return ApiResponse::paginated(EncounterResource::collection($encounters), $encounters);
    }

    public function store(
        StoreEncounterRequest $request,
        CreateEncounterAction $action,
        CreatePrescriptionAction $prescriptions,
        CreateOrderAction $orders,
        CurrentUser $currentUser,
    ): JsonResponse {
        abort_if($currentUser->isClinicalDesk(), 403, 'لا يمكنك فتح زيارة جديدة.');

        $patient = PatientModel::query()->findOrFail($request->integer('patient_id'));
        $payload = $request->validated();
        /** @var array<int, array<string, mixed>> $items */
        $items = $payload['items'] ?? [];
        unset($payload['items']);

        $department = isset($payload['department_id'])
            ? DepartmentModel::query()->find($payload['department_id'])
            : null;
        $desk = match ($department?->type) {
            DepartmentType::Pharmacy => 'pharmacy',
            DepartmentType::Laboratory => 'laboratory',
            DepartmentType::Radiology => 'radiology',
            default => null,
        };

        if ($desk !== null && $items === []) {
            throw BusinessRuleException::make(match ($desk) {
                'laboratory' => 'حدد تحليلاً واحداً على الأقل قبل تحويل المريض إلى المخبر.',
                'radiology' => 'حدد صورة واحدة على الأقل قبل تحويل المريض إلى الأشعة.',
                default => 'أضف دواءً إلى الوصفة قبل تحويل المريض إلى الصيدلية.',
            });
        }

        if ($desk === null && $items !== []) {
            throw BusinessRuleException::make('البنود تُرفق فقط عند اختيار قسم الصيدلية أو المخبر أو الأشعة.');
        }

        $created = DB::transaction(function () use ($patient, $payload, $items, $desk, $department, $action, $prescriptions, $orders) {
            if ($desk !== null) {
                $payload['allow_parallel'] = true;
                $payload['clinic_id'] = null;
                $payload['doctor_id'] = null;
                $payload['reason'] = $payload['reason'] ?? match ($desk) {
                    'laboratory' => 'تحاليل مخبرية فقط',
                    'radiology' => 'صور أشعة فقط',
                    default => 'صرف أدوية فقط',
                };
                $payload['chief_complaint'] = $payload['chief_complaint'] ?? match ($desk) {
                    'laboratory' => 'تحويل من الاستقبال إلى المخبر',
                    'radiology' => 'تحويل من الاستقبال إلى الأشعة',
                    default => 'صرف أدوية من صيدلية المشفى فقط',
                };
            }

            $encounter = $action->execute($patient, $payload);
            $prescription = null;
            $order = null;

            if ($desk === 'pharmacy') {
                $prescription = $prescriptions->execute([
                    'encounter_id' => $encounter->getKey(),
                    'fulfillment' => 'hospital',
                    'allow_without_doctor' => true,
                    'notes' => 'تحويل من الاستقبال إلى صيدلية المشفى',
                    'items' => $items,
                ]);
            }

            if ($desk === 'laboratory' || $desk === 'radiology') {
                $order = $orders->execute([
                    'encounter_id' => $encounter->getKey(),
                    'type' => $desk,
                    'priority' => 'routine',
                    'department_id' => $department?->getKey(),
                    'clinical_notes' => $desk === 'laboratory'
                        ? 'تحويل من الاستقبال إلى المخبر'
                        : 'تحويل من الاستقبال إلى الأشعة',
                    'items' => array_map(static function (array $item) use ($desk): array {
                        return [
                            'service_type' => $item['service_type'] ?? ($desk === 'laboratory' ? 'lab_test' : 'radiology_service'),
                            'service_id' => $item['service_id'] ?? null,
                            'name' => (string) ($item['name'] ?? ''),
                            'quantity' => $item['quantity'] ?? 1,
                            'with_injection' => $item['with_injection'] ?? false,
                        ];
                    }, $items),
                ]);
            }

            return [$encounter, $prescription, $order];
        });

        [$encounter, $prescription, $order] = $created;
        $body = (new EncounterResource($encounter))->resolve();
        if ($prescription !== null) {
            $body['prescription_number'] = $prescription->prescription_number;
        }
        if ($order !== null) {
            $body['order_number'] = $order->order_number;
            if ($desk === 'laboratory') {
                $slip = DB::table('lab_orders')->where('order_id', $order->getKey())->first();
                $body['barcode'] = $slip?->barcode;
                $body['clinic_name'] = $slip?->clinic_name;
                $body['doctor_name'] = $slip?->doctor_name;
            }
        }

        return ApiResponse::data($body, [], 201);
    }

    public function start(Request $request, EncounterModel $encounter, StartEncounterAction $action): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
        ]);

        return ApiResponse::resource(new EncounterResource(
            $action->execute($encounter, $validated['doctor_id'] ?? null)
        ));
    }

    public function transfer(TransferEncounterRequest $request, EncounterModel $encounter, TransferEncounterAction $action): JsonResponse
    {
        return ApiResponse::created(new EncounterTransferResource($action->execute($encounter, $request->validated())));
    }

    public function transfers(EncounterModel $encounter): JsonResponse
    {
        return ApiResponse::data(EncounterTransferResource::collection(
            $encounter->transfers()->with(['fromDepartment', 'toDepartment'])->latest()->get()
        )->resolve());
    }

    public function close(CloseEncounterRequest $request, EncounterModel $encounter, CloseEncounterAction $action): JsonResponse
    {
        return ApiResponse::resource(new EncounterResource($action->execute($encounter, $request->validated())));
    }

    public function statusHistory(EncounterModel $encounter): JsonResponse
    {
        return ApiResponse::data(
            $encounter->statusHistory()->orderBy('created_at')->get()->map(fn ($entry) => [
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'note' => $entry->note,
                'changed_by' => $entry->changed_by,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->all()
        );
    }
}
