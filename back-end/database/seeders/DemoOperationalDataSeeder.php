<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\ChargeModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InsuranceCompanyModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceItemModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\PatientInsuranceModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\PaymentModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\ServiceModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\ClinicalNoteModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisCodeModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\ProcedureModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\VitalSignModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\TimelineEventModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedAssignmentModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockLevelModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabResultModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabResultValueModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabSampleModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabTestModel;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationModel;
use App\Modules\Nursing\Infrastructure\Persistence\Models\CarePlanModel;
use App\Modules\Nursing\Infrastructure\Persistence\Models\MedicationAdministrationModel;
use App\Modules\Nursing\Infrastructure\Persistence\Models\NursingNoteModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationNoteModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTeamModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTheatreModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTypeModel;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItemModel;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationDispenseModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyReportModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyServiceModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyStudyModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * يملأ كل الوحدات التشغيلية ببيانات عربية وهمية: مواعيد، زيارات، مختبر،
 * أشعة، صيدلية، تنويم، عمليات، تمريض، فوترة، مخزون، وإشعارات.
 *
 * يُستدعى من DemoDataSeeder بعد إنشاء الكادر والمرضى.
 */
final class DemoOperationalDataSeeder extends Seeder
{
    private int $hospitalId;

    private UserModel $admin;

    private UserModel $doctorUser;

    private UserModel $reception;

    private UserModel $nurse;

    private UserModel $labUser;

    private UserModel $labBoss;

    private UserModel $radTech;

    private UserModel $radiologist;

    private UserModel $pharmacist;

    private UserModel $cashier;

    private UserModel $storeKeeper;

    private ?DoctorModel $doctor = null;

    private int $seq = 1;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('البيانات التشغيلية التجريبية لا تُزرع في الإنتاج.');

            return;
        }

        $hospital = HospitalModel::query()
            ->where('code', config('hms.default_hospital_code', 'MAIN'))
            ->firstOrFail();

        $this->hospitalId = (int) $hospital->getKey();

        if (AppointmentModel::query()->where('hospital_id', $this->hospitalId)->exists()) {
            $this->command?->info('البيانات التشغيلية موجودة مسبقاً — تم التخطي.');

            return;
        }

        $this->resolveUsers();

        DB::transaction(function (): void {
            $this->seedInventoryStock();
            $this->seedInsurance();

            $patients = PatientModel::query()
                ->where('hospital_id', $this->hospitalId)
                ->orderBy('id')
                ->limit(50)
                ->get();

            if ($patients->isEmpty()) {
                $this->command?->warn('لا يوجد مرضى — شغّل DemoDataSeeder أولاً.');

                return;
            }

            $departments = DepartmentModel::query()
                ->where('hospital_id', $this->hospitalId)
                ->where('is_active', true)
                ->get()
                ->keyBy('code');

            $clinics = ClinicModel::query()
                ->whereIn('department_id', $departments->pluck('id'))
                ->where('is_active', true)
                ->get();

            $outpatientEncounters = collect();
            $inpatientEncounters = collect();
            $emergencyEncounters = collect();

            foreach ($patients->take(25) as $index => $patient) {
                $clinic = $clinics->isNotEmpty()
                    ? $clinics[$index % $clinics->count()]
                    : null;
                $department = $clinic
                    ? $departments->firstWhere('id', $clinic->department_id)
                    : ($departments->get('IM') ?? $departments->first());

                $encounter = $this->createEncounter(
                    patient: $patient,
                    type: 'outpatient',
                    status: $index < 8 ? 'in_progress' : ($index < 18 ? 'completed' : 'waiting'),
                    departmentId: $department?->id,
                    clinicId: $clinic?->id,
                    hoursAgo: 2 + $index,
                );
                $outpatientEncounters->push($encounter);

                $this->createAppointment(
                    patient: $patient,
                    departmentId: $department?->id,
                    clinicId: $clinic?->id,
                    encounter: $index < 12 ? $encounter : null,
                    daysOffset: $index < 12 ? 0 : ($index - 10),
                    status: $index < 5 ? 'checked_in' : ($index < 12 ? 'completed' : 'scheduled'),
                );

                $this->seedClinical($encounter, $patient);
                $this->timeline($patient, $encounter, 'encounter.opened', 'فتح زيارة عيادة', 'تم تسجيل زيارة خارجية');
            }

            foreach ($patients->slice(25, 10)->values() as $index => $patient) {
                $er = $departments->get('ER');
                $encounter = $this->createEncounter(
                    patient: $patient,
                    type: 'emergency',
                    status: $index < 4 ? 'in_progress' : 'completed',
                    departmentId: $er?->id,
                    clinicId: null,
                    hoursAgo: 1 + $index,
                    reason: 'حالة إسعافية',
                    complaint: fake()->randomElement(['ألم صدري', 'ضيق تنفس', 'إصابة', 'حمى عالية', 'ألم بطني حاد']),
                );
                $emergencyEncounters->push($encounter);
                $this->seedClinical($encounter, $patient);
                $this->timeline($patient, $encounter, 'encounter.emergency', 'دخول طوارئ', $encounter->chief_complaint);
            }

            foreach ($patients->slice(35, 10)->values() as $index => $patient) {
                $wardDept = $departments->get('WARD') ?? $departments->get('IM');
                $encounter = $this->createEncounter(
                    patient: $patient,
                    type: 'inpatient',
                    status: $index < 7 ? 'in_progress' : 'completed',
                    departmentId: $wardDept?->id,
                    clinicId: null,
                    hoursAgo: 24 * (1 + $index),
                    reason: 'تنويم',
                    complaint: fake()->randomElement(['التهاب رئوي', 'بعد عملية', 'مراقبة قلبية', 'سيطرة على السكر']),
                );
                $inpatientEncounters->push($encounter);
                $this->seedClinical($encounter, $patient);
                $this->seedAdmission($encounter, $patient, $wardDept?->id, $index < 7);
            }

            $allOpen = $outpatientEncounters->merge($emergencyEncounters)->merge($inpatientEncounters);

            foreach ($allOpen->take(20) as $i => $encounter) {
                $this->seedLabOrder($encounter, $i);
            }

            foreach ($allOpen->take(15) as $i => $encounter) {
                $this->seedRadiologyOrder($encounter, $i);
            }

            foreach ($allOpen->take(18) as $i => $encounter) {
                $this->seedPrescription($encounter, $i);
            }

            foreach ($inpatientEncounters->take(8) as $encounter) {
                $this->seedNursing($encounter);
            }

            foreach ($patients->slice(20, 6)->values() as $i => $patient) {
                $this->seedOperation($patient, $departments->get('OT') ?? $departments->get('SURG'), $i);
            }

            foreach ($allOpen->take(22) as $i => $encounter) {
                $this->seedBilling($encounter, $i);
            }

            $this->seedNotifications();
        });

        $this->command?->info('تم زراعة البيانات التشغيلية الوهمية لكل الأقسام.');
    }

    private function resolveUsers(): void
    {
        $this->admin = UserModel::query()->where('username', 'admin')->firstOrFail();
        $this->doctorUser = UserModel::query()->where('username', 'doctor')->firstOrFail();
        $this->reception = UserModel::query()->where('username', 'reception')->firstOrFail();
        $this->nurse = UserModel::query()->where('username', 'nurse')->firstOrFail();
        $this->labUser = UserModel::query()->where('username', 'lab')->firstOrFail();
        $this->labBoss = UserModel::query()->where('username', 'lab_boss')->firstOrFail();
        $this->radTech = UserModel::query()->where('username', 'radtech')->firstOrFail();
        $this->radiologist = UserModel::query()->where('username', 'radiologist')->firstOrFail();
        $this->pharmacist = UserModel::query()->where('username', 'pharmacist')->firstOrFail();
        $this->cashier = UserModel::query()->where('username', 'cashier')->firstOrFail();
        $this->storeKeeper = UserModel::query()->where('username', 'store')->firstOrFail();

        $this->doctor = DoctorModel::query()
            ->where('staff_id', $this->doctorUser->staff_id)
            ->first()
            ?? DoctorModel::query()->first();
    }

    private function next(string $prefix): string
    {
        return sprintf('%s-%s-%04d', $prefix, now()->format('ymd'), $this->seq++);
    }

    private function createEncounter(
        PatientModel $patient,
        string $type,
        string $status,
        ?int $departmentId,
        ?int $clinicId,
        int $hoursAgo,
        ?string $reason = null,
        ?string $complaint = null,
    ): EncounterModel {
        $started = now()->subHours($hoursAgo);

        return EncounterModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'patient_id' => $patient->id,
            'encounter_number' => $this->next('ENC'),
            'type' => $type,
            'status' => $status,
            'department_id' => $departmentId,
            'clinic_id' => $clinicId,
            'doctor_id' => $this->doctor?->id,
            'started_at' => in_array($status, ['in_progress', 'completed'], true) ? $started : null,
            'ended_at' => $status === 'completed' ? $started->copy()->addHours(2) : null,
            'reason' => $reason ?? 'معاينة طبية',
            'chief_complaint' => $complaint ?? fake()->randomElement([
                'صداع', 'ألم بطني', 'ارتفاع حرارة', 'سعال', 'متابعة مزمنة', 'دوخة',
            ]),
            'created_by' => $this->reception->id,
        ]);
    }

    private function createAppointment(
        PatientModel $patient,
        ?int $departmentId,
        ?int $clinicId,
        ?EncounterModel $encounter,
        int $daysOffset,
        string $status,
    ): void {
        $scheduled = now()->startOfHour()->addDays($daysOffset)->setHour(9 + ($this->seq % 7));

        AppointmentModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'patient_id' => $patient->id,
            'appointment_number' => $this->next('APT'),
            'department_id' => $departmentId,
            'clinic_id' => $clinicId,
            'doctor_id' => $this->doctor?->id,
            'scheduled_at' => $scheduled,
            'duration_minutes' => 15,
            'status' => $status,
            'reason' => 'موعد عيادة',
            'encounter_id' => $encounter?->id,
            'created_by' => $this->reception->id,
        ]);
    }

    private function seedClinical(EncounterModel $encounter, PatientModel $patient): void
    {
        VitalSignModel::query()->create([
            'encounter_id' => $encounter->id,
            'temperature' => fake()->randomFloat(1, 36.2, 39.5),
            'systolic_bp' => fake()->numberBetween(100, 160),
            'diastolic_bp' => fake()->numberBetween(60, 100),
            'heart_rate' => fake()->numberBetween(60, 110),
            'respiratory_rate' => fake()->numberBetween(14, 24),
            'oxygen_saturation' => fake()->randomFloat(1, 92, 100),
            'weight' => fake()->randomFloat(1, 45, 110),
            'height' => fake()->randomFloat(1, 145, 190),
            'recorded_by' => $this->nurse->id,
            'recorded_at' => now()->subHours(1),
        ]);

        ClinicalNoteModel::query()->create([
            'encounter_id' => $encounter->id,
            'author_id' => $this->doctorUser->id,
            'note_type' => fake()->randomElement(['history', 'examination', 'progress', 'general']),
            'content' => 'ملاحظة سريرية تجريبية: المريض يشكو من '.$encounter->chief_complaint.'، والعلامات الحيوية مستقرة نسبياً.',
        ]);

        $code = DiagnosisCodeModel::query()->inRandomOrder()->first();
        if ($this->doctor && $code) {
            DiagnosisModel::query()->create([
                'encounter_id' => $encounter->id,
                'doctor_id' => $this->doctor->id,
                'diagnosis_code_id' => $code->id,
                'diagnosis_name' => $code->title,
                'type' => 'primary',
                'diagnosed_at' => now()->subMinutes(30),
            ]);
        }

        if ($this->doctor && fake()->boolean(40)) {
            ProcedureModel::query()->create([
                'encounter_id' => $encounter->id,
                'doctor_id' => $this->doctor->id,
                'name' => fake()->randomElement(['تضميد جرح', 'حقنة عضلية', 'تخطيط قلب', 'تركيب قثطرة وريدية']),
                'code' => fake()->randomElement(['PROC-DRESS', 'PROC-IM', 'PROC-ECG', 'PROC-IV']),
                'description' => 'إجراء سريري تجريبي',
                'outcome' => 'تم بنجاح',
                'performed_at' => now()->subMinutes(20),
                'recorded_by' => $this->doctorUser->id,
            ]);
        }
    }

    private function seedLabOrder(EncounterModel $encounter, int $index): void
    {
        $labDept = DepartmentModel::query()
            ->where('hospital_id', $this->hospitalId)
            ->where('code', 'LAB')
            ->first();

        $tests = LabTestModel::query()->with('parameters')->where('is_active', true)->inRandomOrder()->limit(2)->get();
        if ($tests->isEmpty()) {
            return;
        }

        $completed = $index % 3 !== 0;

        $order = OrderModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'encounter_id' => $encounter->id,
            'order_number' => $this->next('ORD-LAB'),
            'type' => 'laboratory',
            'status' => $completed ? 'completed' : 'in_progress',
            'priority' => $index % 5 === 0 ? 'urgent' : 'routine',
            'ordered_by' => $this->doctorUser->id,
            'department_id' => $labDept?->id,
            'clinical_notes' => 'طلب مخبري تجريبي',
            'ordered_at' => now()->subHours(3 + $index),
            'completed_at' => $completed ? now()->subHour() : null,
        ]);

        $labOrder = LabOrderModel::query()->create(['order_id' => $order->id]);

        foreach ($tests as $ti => $test) {
            $orderItem = OrderItemModel::query()->create([
                'order_id' => $order->id,
                'service_type' => 'lab_test',
                'service_id' => $test->id,
                'name' => $test->name,
                'quantity' => 1,
                'status' => $completed ? 'completed' : 'in_progress',
            ]);

            $labItem = LabOrderItemModel::query()->create([
                'lab_order_id' => $labOrder->id,
                'lab_test_id' => $test->id,
                'order_item_id' => $orderItem->id,
                'status' => $completed ? 'verified' : ($ti === 0 ? 'processing' : 'pending'),
            ]);

            if ($labItem->status !== 'pending') {
                LabSampleModel::query()->create([
                    'lab_order_item_id' => $labItem->id,
                    'sample_number' => $this->next('SMP'),
                    'sample_type' => $test->sample_type,
                    'collected_by' => $this->labUser->id,
                    'collected_at' => now()->subHours(2),
                    'received_at' => now()->subHours(1),
                    'status' => 'received',
                ]);
            }

            if ($completed) {
                $result = LabResultModel::query()->create([
                    'lab_order_item_id' => $labItem->id,
                    'result_value' => $test->is_panel ? null : (string) fake()->randomFloat(1, 3, 15),
                    'unit' => $test->unit,
                    'reference_range' => $test->reference_range,
                    'flag' => fake()->randomElement(['normal', 'normal', 'high', 'low']),
                    'entered_by' => $this->labUser->id,
                    'verified_by' => $this->labBoss->id,
                    'entered_at' => now()->subMinutes(40),
                    'verified_at' => now()->subMinutes(20),
                    'status' => 'final',
                ]);

                foreach ($test->parameters ?? [] as $param) {
                    LabResultValueModel::query()->create([
                        'lab_result_id' => $result->id,
                        'lab_test_parameter_id' => $param->id,
                        'value' => (string) fake()->randomFloat(1, (float) ($param->normal_low ?? 4), (float) ($param->normal_high ?? 12)),
                        'unit' => $param->unit,
                        'reference_range' => $param->reference_range,
                        'flag' => 'normal',
                    ]);
                }
            }
        }

        $this->timeline(
            PatientModel::query()->find($encounter->patient_id),
            $encounter,
            'lab.order.created',
            'طلب مخبري',
            'تم إنشاء طلب مخبر',
        );
    }

    private function seedRadiologyOrder(EncounterModel $encounter, int $index): void
    {
        $radDept = DepartmentModel::query()
            ->where('hospital_id', $this->hospitalId)
            ->where('code', 'RAD')
            ->first();

        $service = RadiologyServiceModel::query()->where('is_active', true)->inRandomOrder()->first();
        if (! $service) {
            return;
        }

        $reported = $index % 2 === 0;

        $order = OrderModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'encounter_id' => $encounter->id,
            'order_number' => $this->next('ORD-RAD'),
            'type' => 'radiology',
            'status' => $reported ? 'completed' : 'accepted',
            'priority' => 'routine',
            'ordered_by' => $this->doctorUser->id,
            'department_id' => $radDept?->id,
            'clinical_notes' => 'استطباب سريري للتصوير',
            'ordered_at' => now()->subHours(4 + $index),
            'completed_at' => $reported ? now()->subHour() : null,
        ]);

        $orderItem = OrderItemModel::query()->create([
            'order_id' => $order->id,
            'service_type' => 'radiology_service',
            'service_id' => $service->id,
            'name' => $service->name,
            'quantity' => 1,
            'status' => $reported ? 'completed' : 'in_progress',
        ]);

        $radOrder = RadiologyOrderModel::query()->create([
            'order_id' => $order->id,
            'clinical_indication' => $encounter->chief_complaint,
        ]);

        $radItem = RadiologyOrderItemModel::query()->create([
            'radiology_order_id' => $radOrder->id,
            'radiology_service_id' => $service->id,
            'order_item_id' => $orderItem->id,
            'status' => $reported ? 'reported' : 'in_progress',
        ]);

        $study = RadiologyStudyModel::query()->create([
            'radiology_order_id' => $radOrder->id,
            'radiology_order_item_id' => $radItem->id,
            'study_number' => $this->next('STD'),
            'accession_number' => $this->next('ACC'),
            'performed_by' => $this->radTech->id,
            'performed_at' => now()->subHours(2),
            'image_count' => fake()->numberBetween(2, 40),
            'technician_notes' => 'دراسة تجريبية',
            'status' => $reported ? 'completed' : 'in_progress',
        ]);

        if ($reported) {
            RadiologyReportModel::query()->create([
                'study_id' => $study->id,
                'findings' => 'موجودات شعاعية ضمن الحدود الطبيعية في هذه الدراسة التجريبية.',
                'impression' => 'لا توجد آفات واضحة.',
                'is_critical' => false,
                'reported_by' => $this->radiologist->id,
                'verified_by' => $this->radiologist->id,
                'reported_at' => now()->subHour(),
                'verified_at' => now()->subMinutes(50),
                'status' => 'final',
            ]);
        }
    }

    private function seedPrescription(EncounterModel $encounter, int $index): void
    {
        if (! $this->doctor) {
            return;
        }

        $meds = MedicationModel::query()->where('is_active', true)->inRandomOrder()->limit(2)->get();
        if ($meds->isEmpty()) {
            return;
        }

        $pharmacy = WarehouseModel::query()
            ->where('hospital_id', $this->hospitalId)
            ->where('is_pharmacy', true)
            ->first();

        $dispensed = $index % 3 !== 1;

        $order = OrderModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'encounter_id' => $encounter->id,
            'order_number' => $this->next('ORD-RX'),
            'type' => 'pharmacy',
            'status' => $dispensed ? 'completed' : 'accepted',
            'priority' => 'routine',
            'ordered_by' => $this->doctorUser->id,
            'department_id' => DepartmentModel::query()->where('code', 'PHAR')->value('id'),
            'ordered_at' => now()->subHours(2),
            'completed_at' => $dispensed ? now()->subMinutes(30) : null,
        ]);

        $rx = PrescriptionModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'encounter_id' => $encounter->id,
            'prescription_number' => $this->next('RX'),
            'doctor_id' => $this->doctor->id,
            'order_id' => $order->id,
            'status' => $dispensed ? 'dispensed' : 'active',
            'notes' => 'وصفة تجريبية',
            'prescribed_at' => now()->subHours(2),
        ]);

        foreach ($meds as $med) {
            OrderItemModel::query()->create([
                'order_id' => $order->id,
                'service_type' => 'medication',
                'service_id' => $med->id,
                'name' => $med->name,
                'quantity' => 10,
                'status' => $dispensed ? 'completed' : 'pending',
            ]);

            $item = PrescriptionItemModel::query()->create([
                'prescription_id' => $rx->id,
                'medication_id' => $med->id,
                'dose' => $med->strength ?? '1',
                'frequency' => 'مرتين يومياً',
                'duration' => '5 أيام',
                'route' => $med->route,
                'quantity' => 10,
                'dispensed_quantity' => $dispensed ? 10 : 0,
                'instructions' => 'بعد الطعام',
            ]);

            if ($dispensed && $pharmacy) {
                $batch = InventoryBatchModel::query()
                    ->where('inventory_item_id', $med->inventory_item_id)
                    ->where('warehouse_id', $pharmacy->id)
                    ->first();

                MedicationDispenseModel::query()->create([
                    'hospital_id' => $this->hospitalId,
                    'dispense_number' => $this->next('DSP'),
                    'prescription_id' => $rx->id,
                    'prescription_item_id' => $item->id,
                    'encounter_id' => $encounter->id,
                    'medication_id' => $med->id,
                    'warehouse_id' => $pharmacy->id,
                    'inventory_batch_id' => $batch?->id,
                    'quantity' => 10,
                    'unit_price' => $med->price,
                    'total_price' => $med->price * 10,
                    'batch_number' => $batch?->batch_number,
                    'batch_expiry_date' => $batch?->expiry_date,
                    'dispensed_by' => $this->pharmacist->id,
                    'dispensed_at' => now()->subMinutes(25),
                ]);
            }
        }
    }

    private function seedAdmission(EncounterModel $encounter, PatientModel $patient, ?int $departmentId, bool $active): void
    {
        $bed = BedModel::query()
            ->where('status', 'available')
            ->inRandomOrder()
            ->first();

        $admission = AdmissionModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'admission_number' => $this->next('ADM'),
            'department_id' => $departmentId,
            'attending_doctor_id' => $this->doctorUser->id,
            'admitted_by' => $this->reception->id,
            'admitted_at' => $encounter->started_at ?? now()->subDays(1),
            'expected_discharge_at' => now()->addDays(3),
            'discharged_at' => $active ? null : now()->subHours(2),
            'status' => $active ? 'admitted' : 'discharged',
            'discharge_type' => $active ? null : 'routine',
            'admission_reason' => $encounter->chief_complaint,
            'discharge_summary' => $active ? null : 'خرج بحالة جيدة.',
            'discharged_by' => $active ? null : $this->doctorUser->id,
        ]);

        if ($bed) {
            BedAssignmentModel::query()->create([
                'admission_id' => $admission->id,
                'bed_id' => $bed->id,
                'assigned_at' => $admission->admitted_at,
                'released_at' => $active ? null : $admission->discharged_at,
                'assigned_by' => $this->nurse->id,
                'reason' => 'تنويم تجريبي',
            ]);

            $bed->update(['status' => $active ? 'occupied' : 'available']);
        }

        $this->timeline($patient, $encounter, 'inpatient.admitted', 'تنويم مريض', 'تم تنويم المريض');
    }

    private function seedNursing(EncounterModel $encounter): void
    {
        $admissionId = AdmissionModel::query()->where('encounter_id', $encounter->id)->value('id');

        NursingNoteModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'admission_id' => $admissionId,
            'note_type' => 'progress',
            'shift' => fake()->randomElement(['morning', 'evening', 'night']),
            'content' => 'ملاحظة تمريضية: المريض مستقر، تم قياس العلامات الحيوية وإعطاء العلاج حسب الوصفة.',
            'created_by' => $this->nurse->id,
        ]);

        CarePlanModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'admission_id' => $admissionId,
            'title' => 'خطة رعاية تجريبية',
            'problem' => 'ألم / حمى',
            'goal' => 'تخفيف الأعراض خلال 48 ساعة',
            'interventions' => 'مراقبة العلامات الحيوية كل 4 ساعات، إعطاء المسكنات حسب الوصفة',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'target_date' => now()->addDays(3)->toDateString(),
            'created_by' => $this->nurse->id,
        ]);

        $med = MedicationModel::query()->where('is_active', true)->inRandomOrder()->first();
        if ($med) {
            MedicationAdministrationModel::query()->create([
                'hospital_id' => $this->hospitalId,
                'patient_id' => $encounter->patient_id,
                'encounter_id' => $encounter->id,
                'medication_id' => $med->id,
                'scheduled_at' => now()->subHour(),
                'administered_at' => now()->subMinutes(50),
                'dose' => $med->strength,
                'route' => $med->route,
                'status' => 'given',
                'notes' => 'أُعطي حسب الوصفة',
                'administered_by' => $this->nurse->id,
            ]);
        }
    }

    private function seedOperation(PatientModel $patient, ?DepartmentModel $otDept, int $index): void
    {
        $type = OperationTypeModel::query()->where('is_active', true)->inRandomOrder()->first();
        $theatre = OperationTheatreModel::query()
            ->where('hospital_id', $this->hospitalId)
            ->inRandomOrder()
            ->first();

        if (! $type) {
            return;
        }

        $encounter = $this->createEncounter(
            patient: $patient,
            type: 'operation',
            status: $index < 3 ? 'in_progress' : 'completed',
            departmentId: $otDept?->id,
            clinicId: null,
            hoursAgo: 6 + $index,
            reason: 'عملية جراحية',
            complaint: $type->name,
        );

        $completed = $index >= 3;

        $operation = OperationModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'operation_type_id' => $type->id,
            'theatre_id' => $theatre?->id,
            'operation_number' => $this->next('OP'),
            'status' => $completed ? 'completed' : ($index === 0 ? 'in_progress' : 'scheduled'),
            'scheduled_at' => now()->addHours($completed ? -5 : 6 + $index),
            'estimated_duration_minutes' => $type->estimated_duration_minutes,
            'started_at' => $completed || $index === 0 ? now()->subHours(3) : null,
            'ended_at' => $completed ? now()->subHour() : null,
            'pre_op_diagnosis' => $type->name,
            'anesthesia_type' => fake()->randomElement(['general', 'spinal', 'local']),
            'consent_obtained' => true,
            'consent_obtained_at' => now()->subDays(1),
            'post_op_diagnosis' => $completed ? $type->name : null,
            'post_op_instructions' => $completed ? 'راحة ومراقبة، مراجعة بعد أسبوع' : null,
            'outcome' => $completed ? 'successful' : null,
            'created_by' => $this->doctorUser->id,
        ]);

        OperationTeamModel::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $this->doctorUser->id,
            'role' => 'surgeon',
        ]);
        OperationTeamModel::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $this->nurse->id,
            'role' => 'scrub_nurse',
        ]);

        if ($completed) {
            OperationNoteModel::query()->create([
                'operation_id' => $operation->id,
                'note_type' => 'operative',
                'content' => 'تقرير عملية تجريبي: سارت العملية دون مضاعفات.',
                'created_by' => $this->doctorUser->id,
            ]);
        }
    }

    private function seedBilling(EncounterModel $encounter, int $index): void
    {
        $service = ServiceModel::query()->where('is_active', true)->inRandomOrder()->first();
        if (! $service) {
            return;
        }

        $qty = 1;
        $total = (float) $service->price * $qty;

        $charge = ChargeModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'service_id' => $service->id,
            'description' => $service->name,
            'quantity' => $qty,
            'unit_price' => $service->price,
            'discount' => 0,
            'total' => $total,
            'source_type' => 'manual',
            'created_by' => $this->cashier->id,
        ]);

        $paid = $index % 3 === 0;
        $partial = $index % 3 === 1;
        $status = $paid ? 'paid' : ($partial ? 'partially_paid' : 'issued');
        $paidAmount = $paid ? $total : ($partial ? round($total / 2, 2) : 0);

        $invoice = InvoiceModel::query()->create([
            'hospital_id' => $this->hospitalId,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'invoice_number' => $this->next('INV'),
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'paid_amount' => $paidAmount,
            'balance' => $total - $paidAmount,
            'status' => $status,
            'notes' => 'فاتورة تجريبية',
            'issued_at' => now()->subHours(1),
            'created_by' => $this->cashier->id,
        ]);

        $charge->update(['invoice_id' => $invoice->id]);

        InvoiceItemModel::query()->create([
            'invoice_id' => $invoice->id,
            'charge_id' => $charge->id,
            'description' => $service->name,
            'quantity' => $qty,
            'unit_price' => $service->price,
            'discount' => 0,
            'total' => $total,
        ]);

        if ($paidAmount > 0) {
            PaymentModel::query()->create([
                'hospital_id' => $this->hospitalId,
                'invoice_id' => $invoice->id,
                'receipt_number' => $this->next('RCP'),
                'amount' => $paidAmount,
                'method' => fake()->randomElement(['cash', 'card']),
                'received_by' => $this->cashier->id,
                'paid_at' => now()->subMinutes(40),
            ]);
        }
    }

    private function seedInventoryStock(): void
    {
        $warehouses = WarehouseModel::query()
            ->where('hospital_id', $this->hospitalId)
            ->get();

        if ($warehouses->isEmpty()) {
            return;
        }

        $items = InventoryItemModel::query()->where('is_active', true)->get();

        foreach ($items as $i => $item) {
            $warehouse = $item->item_type === 'medicine'
                ? ($warehouses->firstWhere('is_pharmacy', true) ?? $warehouses->first())
                : ($warehouses->firstWhere('is_pharmacy', false) ?? $warehouses->first());

            if (! $warehouse) {
                continue;
            }

            $qty = $item->item_type === 'medicine'
                ? fake()->numberBetween(80, 800)
                : fake()->numberBetween(50, 1500);

            $batch = InventoryBatchModel::query()->firstOrCreate(
                [
                    'inventory_item_id' => $item->id,
                    'warehouse_id' => $warehouse->id,
                    'batch_number' => 'BATCH-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                ],
                [
                    'expiry_date' => now()->addMonths(fake()->numberBetween(3, 24))->toDateString(),
                    'quantity' => $qty,
                    'unit_cost' => fake()->randomFloat(2, 50, 5000),
                ],
            );

            StockLevelModel::query()->updateOrCreate(
                [
                    'inventory_item_id' => $item->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'quantity' => $batch->quantity,
                    'reserved_quantity' => 0,
                ],
            );

            if (! StockMovementModel::query()
                ->where('inventory_item_id', $item->id)
                ->where('warehouse_id', $warehouse->id)
                ->where('type', 'purchase')
                ->exists()) {
                StockMovementModel::query()->create([
                    'inventory_item_id' => $item->id,
                    'warehouse_id' => $warehouse->id,
                    'batch_id' => $batch->id,
                    'type' => 'purchase',
                    'quantity' => $qty,
                    'balance_after' => $qty,
                    'performed_by' => $this->storeKeeper->id,
                    'notes' => 'توريد تجريبي أولي',
                    'created_at' => now()->subDays(10),
                ]);
            }
        }
    }

    private function seedInsurance(): void
    {
        $companies = [
            ['شركة التأمين الوطنية', 'NIC', 70],
            ['التأمين الصحي التعاوني', 'CHI', 80],
            ['تأمين الموظفين الحكوميين', 'GOV', 90],
        ];

        foreach ($companies as [$name, $code, $coverage]) {
            InsuranceCompanyModel::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'phone' => '+963 11 '.fake()->numerify('### ####'),
                    'email' => strtolower($code).'@insurance.local',
                    'default_coverage_percentage' => $coverage,
                    'is_active' => true,
                ],
            );
        }

        $company = InsuranceCompanyModel::query()->first();
        if (! $company) {
            return;
        }

        $patients = PatientModel::query()
            ->where('hospital_id', $this->hospitalId)
            ->orderBy('id')
            ->limit(15)
            ->get();

        foreach ($patients as $i => $patient) {
            PatientInsuranceModel::query()->firstOrCreate(
                [
                    'patient_id' => $patient->id,
                    'insurance_company_id' => $company->id,
                    'policy_number' => 'POL-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                ],
                [
                    'member_number' => 'MBR-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                    'coverage_percentage' => $company->default_coverage_percentage,
                    'valid_from' => now()->subYear()->toDateString(),
                    'valid_to' => now()->addYear()->toDateString(),
                    'is_primary' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedNotifications(): void
    {
        $targets = [
            [$this->doctorUser, 'lab.result.ready', 'نتيجة مخبر جاهزة', 'نتيجة تحليل جاهزة للمراجعة'],
            [$this->doctorUser, 'radiology.report.ready', 'تقرير أشعة جاهز', 'تم اعتماد تقرير شعاعي'],
            [$this->labUser, 'lab.order.created', 'طلب مخبر جديد', 'وصل طلب مخبري جديد لقائمة العمل'],
            [$this->pharmacist, 'pharmacy.prescription.created', 'وصفة جديدة', 'وصفة بانتظار الصرف'],
            [$this->storeKeeper, 'inventory.stock.low', 'تنبيه مخزون', 'بعض الأصناف اقتربت من الحد الأدنى'],
            [$this->cashier, 'billing.invoice.issued', 'فاتورة صادرة', 'تم إصدار فاتورة جديدة'],
            [$this->nurse, 'inpatient.admitted', 'تنويم جديد', 'تم تنويم مريض جديد'],
            [$this->reception, 'encounter.transferred', 'تحويل مريض', 'تم تحويل مريض بين الأقسام'],
        ];

        foreach ($targets as [$user, $type, $title, $message]) {
            NotificationModel::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'entity_type' => null,
                'entity_id' => null,
                'payload' => ['demo' => true],
                'read_at' => fake()->boolean(30) ? now()->subMinutes(10) : null,
                'created_at' => now()->subMinutes(fake()->numberBetween(5, 200)),
            ]);
        }
    }

    private function timeline(
        ?PatientModel $patient,
        EncounterModel $encounter,
        string $type,
        string $title,
        ?string $description,
    ): void {
        if ($patient === null) {
            return;
        }

        TimelineEventModel::query()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'department_id' => $encounter->department_id,
            'actor_id' => $this->doctorUser->id,
            'event_type' => $type,
            'title' => $title,
            'description' => $description,
            'occurred_at' => Carbon::now()->subMinutes($this->seq),
        ]);
    }
}
