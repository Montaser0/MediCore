<?php

declare(strict_types=1);

namespace App\Core\Excel;

use App\Core\Auth\CurrentUser;
use App\Core\Authorization\Permissions;
use App\Core\Exceptions\DomainException;
use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisCodeModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Appointments\Application\Actions\CreateAppointmentAction;
use App\Modules\Encounters\Application\Actions\CreateEncounterAction;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Hospital\Application\Actions\CreateStaffAction;
use App\Modules\Hospital\Application\Actions\EnsureStaffClinicSchema;
use App\Modules\Hospital\Application\Actions\UpdateStaffAction;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use App\Modules\Identity\Application\Actions\CreateUserAction;
use App\Modules\Identity\Application\DTOs\UserData;
use App\Modules\Inpatient\Application\Actions\AdmitPatientAction;
use App\Modules\Inpatient\Application\Actions\CreateRoomWithBedsAction;
use App\Modules\Inpatient\Application\Actions\UpdateRoomAction;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\RoomModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\WardModel;
use App\Modules\Inventory\Application\Actions\ReceiveStockAction;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Laboratory\Domain\Enums\LabTestCategory;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use App\Modules\Laboratory\Application\Actions\CreateBloodRequestAction;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabTestModel;
use App\Modules\Operations\Application\Actions\ScheduleOperationAction;
use App\Modules\Orders\Application\Actions\CreateOrderAction;
use App\Modules\Patients\Application\Actions\RegisterPatientAction;
use App\Modules\Patients\Application\Actions\UpdatePatientAction;
use App\Modules\Patients\Application\DTOs\PatientData;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Pharmacy\Application\Actions\CreatePrescriptionAction;
use App\Modules\Pharmacy\Application\Actions\PharmacyPurchaseAction;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Settings\Application\Services\SettingsRepository;
use App\Modules\Radiology\Domain\Enums\Modality;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyServiceModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Spreadsheet exchange for the catalogues and the lists the desks keep.
 * Rows match a natural key (code, barcode, national id, name). The database
 * assigns the identifier. Rows missing from the file are left in place.
 */
final class ExcelExchange
{
    use FillsExcelSheets;

    public function __construct(
        protected readonly CurrentUser $currentUser,
        protected readonly RegisterPatientAction $registerPatient,
        protected readonly UpdatePatientAction $updatePatient,
        protected readonly CreateStaffAction $createStaff,
        protected readonly UpdateStaffAction $updateStaff,
        protected readonly CreateUserAction $createUser,
        protected readonly CreateEncounterAction $createEncounter,
        protected readonly CreateOrderAction $createOrder,
        protected readonly CreateRoomWithBedsAction $createRoom,
        protected readonly UpdateRoomAction $updateRoom,
        protected readonly CreateAppointmentAction $createAppointment,
        protected readonly AdmitPatientAction $admitPatient,
        protected readonly CreateBloodRequestAction $createBloodRequest,
        protected readonly ReceiveStockAction $receiveStock,
        protected readonly ScheduleOperationAction $scheduleOperation,
        protected readonly CreatePrescriptionAction $createPrescription,
        protected readonly PharmacyPurchaseAction $recordPurchase,
        protected readonly SettingsRepository $settings,
    ) {}

    public function find(string $key): ?ExcelBook
    {
        return $this->books()[$key] ?? null;
    }

    public function spreadsheet(ExcelBook $book, bool $template): string
    {
        $columns = $book->columns;
        $export = $book->export;

        return Xlsx::write((function () use ($columns, $export, $template) {
            yield array_map(static fn (array $column): string => $column['header'], $columns);

            if ($template) {
                return;
            }

            foreach ($export() as $record) {
                $line = [];
                foreach ($columns as $column) {
                    $value = $record[$column['key']] ?? '';
                    $line[] = $value === null ? '' : (string) $value;
                }
                yield $line;
            }
        })(), $book->title);
    }

    /**
     * @return array{created: int, updated: int, skipped: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    public function import(ExcelBook $book, string $path): array
    {
        $table = Xlsx::read($path);
        $headerIndex = $this->headerIndex($table);

        if ($headerIndex === null) {
            throw new InvalidArgumentException('الملف فارغ.');
        }

        if (! $book->importable) {
            throw new InvalidArgumentException('هذا الجدول للتصدير فقط، والاستيراد لا يغيّر السجلات الموجودة.');
        }

        $indexes = $this->columnIndexes($book, $table[$headerIndex]['cells']);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $dataRows = 0;

        foreach (array_slice($table, $headerIndex + 1) as $entry) {
            $record = $this->record($indexes, $entry['cells']);

            if ($this->blank($record)) {
                continue;
            }

            $dataRows++;

            if ($dataRows > 5000) {
                throw new InvalidArgumentException('الحد الأقصى للاستيراد 5000 سطر.');
            }

            try {
                $outcome = ($book->import)($record);
                if ($outcome === 'updated') {
                    $updated++;
                } elseif ($outcome === 'skipped') {
                    $skipped++;
                } else {
                    $created++;
                }
            } catch (InvalidArgumentException|DomainException $exception) {
                $failed++;
                $this->remember($errors, (int) $entry['row'], $exception->getMessage());
            } catch (QueryException $exception) {
                report($exception);
                $failed++;
                $this->remember($errors, (int) $entry['row'], 'تعذر حفظ السطر. قد يكون الرمز أو الرقم أو اسم المستخدم مكرراً.');
            } catch (\Throwable $exception) {
                report($exception);
                $failed++;
                $this->remember($errors, (int) $entry['row'], 'تعذر حفظ هذا السطر.');
            }
        }

        if ($dataRows === 0) {
            throw new InvalidArgumentException('لا توجد صفوف بيانات تحت العناوين.');
        }

        return compact('created', 'updated', 'skipped', 'failed', 'errors');
    }

    /**
     * @return array<string, ExcelBook>
     */
    protected function books(): array
    {
        $books = [
            $this->book('lab-tests', 'التحاليل', Permissions::ORDERS_VIEW, Permissions::LABORATORY_TESTS_MANAGE, [
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'code', 'header' => 'الرمز'],
                ['key' => 'category', 'header' => 'الفئة', 'required' => true],
                ['key' => 'sample_type', 'header' => 'نوع العينة'],
                ['key' => 'unit', 'header' => 'الوحدة'],
                ['key' => 'reference_range', 'header' => 'المجال المرجعي'],
                ['key' => 'price', 'header' => 'السعر'],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportLabTests(...), $this->importLabTest(...)),
            $this->book('lab-orders', 'المخبر', Permissions::LABORATORY_ORDERS_VIEW, Permissions::ORDERS_CREATE, [
                ['key' => 'mrn', 'header' => 'رقم المريض'],
                ['key' => 'national_id', 'header' => 'الرقم الوطني'],
                ['key' => 'patient_name', 'header' => 'اسم المريض'],
                ['key' => 'test_code', 'header' => 'رمز التحليل'],
                ['key' => 'test_name', 'header' => 'اسم التحليل'],
                ['key' => 'notes', 'header' => 'ملاحظات'],
                ['key' => 'status', 'header' => 'الحالة'],
                ['key' => 'ordered_at', 'header' => 'التاريخ'],
            ], $this->exportLabOrders(...), $this->importLabOrder(...)),
            $this->book('medications', 'الأدوية', Permissions::ORDERS_VIEW, Permissions::PHARMACY_MEDICATIONS_MANAGE, [
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'generic_name', 'header' => 'الاسم العلمي'],
                ['key' => 'strength', 'header' => 'التركيز'],
                ['key' => 'dosage_form', 'header' => 'الشكل'],
                ['key' => 'route', 'header' => 'طريقة الإعطاء'],
                ['key' => 'barcode', 'header' => 'الباركود'],
                ['key' => 'price', 'header' => 'السعر'],
                ['key' => 'requires_prescription', 'header' => 'يحتاج وصفة'],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportMedications(...), $this->importMedication(...)),
            $this->book('pharmacy', 'الصيدلية', Permissions::PHARMACY_MEDICATIONS_MANAGE, Permissions::PHARMACY_MEDICATIONS_MANAGE, [
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'barcode', 'header' => 'الباركود', 'required' => true],
                ['key' => 'quantity', 'header' => 'الكمية', 'required' => true],
                ['key' => 'price', 'header' => 'السعر', 'required' => true],
            ], $this->exportPharmacy(...), $this->importPharmacy(...)),
            $this->book('diagnoses', 'التشخيصات', Permissions::CLINICAL_DIAGNOSIS_VIEW, Permissions::DEPARTMENTS_MANAGE, [
                ['key' => 'title', 'header' => 'التشخيص', 'required' => true],
                ['key' => 'clinic', 'header' => 'العيادة', 'required' => true],
            ], $this->exportDiagnoses(...), $this->importDiagnosis(...)),
            $this->book('warehouses', 'المستودعات', Permissions::INVENTORY_STOCK_VIEW, Permissions::INVENTORY_ITEMS_MANAGE, [
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'code', 'header' => 'الرمز', 'required' => true],
                ['key' => 'type', 'header' => 'النوع'],
                ['key' => 'department', 'header' => 'القسم'],
                ['key' => 'is_pharmacy', 'header' => 'صيدلية'],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportWarehouses(...), $this->importWarehouse(...)),
            $this->book('patients', 'المرضى', Permissions::PATIENTS_VIEW, Permissions::PATIENTS_CREATE, [
                ['key' => 'mrn', 'header' => 'الرقم'],
                ['key' => 'first_name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'father_name', 'header' => 'اسم الأب'],
                ['key' => 'last_name', 'header' => 'الكنية', 'required' => true],
                ['key' => 'mother_name', 'header' => 'اسم الأم'],
                ['key' => 'gender', 'header' => 'الجنس', 'required' => true],
                ['key' => 'date_of_birth', 'header' => 'تاريخ الميلاد'],
                ['key' => 'national_id', 'header' => 'الرقم الوطني'],
                ['key' => 'phone', 'header' => 'الهاتف'],
                ['key' => 'city', 'header' => 'المدينة'],
                ['key' => 'address', 'header' => 'العنوان'],
                ['key' => 'blood_type', 'header' => 'فصيلة الدم'],
                ['key' => 'notes', 'header' => 'ملاحظات'],
            ], $this->exportPatients(...), $this->importPatient(...)),
            $this->book('clinics', 'العيادات', Permissions::DEPARTMENTS_MANAGE, Permissions::DEPARTMENTS_MANAGE, [
                ['key' => 'department', 'header' => 'القسم', 'required' => true],
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'code', 'header' => 'الرمز', 'required' => true],
                ['key' => 'description', 'header' => 'الوصف'],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportClinics(...), $this->importClinic(...)),
            $this->book('staff', 'الكوادر', Permissions::STAFF_MANAGE, Permissions::STAFF_MANAGE, [
                ['key' => 'employee_number', 'header' => 'الرقم الوظيفي'],
                ['key' => 'first_name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'last_name', 'header' => 'الكنية', 'required' => true],
                ['key' => 'phone', 'header' => 'الهاتف'],
                ['key' => 'job_title', 'header' => 'المسمى'],
                ['key' => 'department', 'header' => 'القسم'],
                ['key' => 'clinic', 'header' => 'العيادة'],
                ['key' => 'specialty', 'header' => 'الاختصاص'],
                ['key' => 'license_number', 'header' => 'رقم الترخيص'],
                ['key' => 'is_doctor', 'header' => 'طبيب'],
                ['key' => 'is_active', 'header' => 'فعال'],
                ['key' => 'role', 'header' => 'نوع الحساب'],
                ['key' => 'username', 'header' => 'اسم المستخدم'],
                ['key' => 'password', 'header' => 'كلمة المرور'],
                ['key' => 'email', 'header' => 'البريد'],
            ], $this->exportStaff(...), $this->importStaff(...)),
            $this->book('radiology-orders', 'طلبات الأشعة', Permissions::RADIOLOGY_ORDERS_VIEW, Permissions::ORDERS_CREATE, [
                ['key' => 'mrn', 'header' => 'رقم المريض'],
                ['key' => 'national_id', 'header' => 'الرقم الوطني'],
                ['key' => 'patient_name', 'header' => 'اسم المريض'],
                ['key' => 'service_code', 'header' => 'رمز الفحص'],
                ['key' => 'service_name', 'header' => 'اسم الفحص'],
                ['key' => 'notes', 'header' => 'ملاحظات'],
                ['key' => 'status', 'header' => 'الحالة'],
                ['key' => 'ordered_at', 'header' => 'التاريخ'],
            ], $this->exportRadiologyOrders(...), $this->importRadiologyOrder(...)),
            $this->book('radiology-services', 'أنواع الأشعة', Permissions::ORDERS_VIEW, Permissions::RADIOLOGY_SERVICES_MANAGE, [
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'code', 'header' => 'الرمز'],
                ['key' => 'modality', 'header' => 'النوع', 'required' => true],
                ['key' => 'price', 'header' => 'السعر'],
                ['key' => 'description', 'header' => 'الوصف'],
                ['key' => 'requires_contrast', 'header' => 'حقنة'],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportRadiologyServices(...), $this->importRadiologyService(...)),
            $this->book('rooms', 'الغرف', Permissions::INPATIENT_VIEW, Permissions::INPATIENT_BEDS_MANAGE, [
                ['key' => 'ward', 'header' => 'الجناح', 'required' => true],
                ['key' => 'room_number', 'header' => 'رقم الغرفة', 'required' => true],
                ['key' => 'room_type', 'header' => 'نوع الغرفة'],
                ['key' => 'bed_count', 'header' => 'عدد الأسرة', 'required' => true],
                ['key' => 'daily_rate', 'header' => 'السعر'],
            ], $this->exportRooms(...), $this->importRoom(...)),
            $this->book('wards', 'الأجنحة', Permissions::INPATIENT_VIEW, Permissions::INPATIENT_BEDS_MANAGE, [
                ['key' => 'department', 'header' => 'القسم', 'required' => true],
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'code', 'header' => 'الرمز', 'required' => true],
                ['key' => 'type', 'header' => 'النوع'],
                ['key' => 'floor', 'header' => 'الطابق'],
                ['key' => 'daily_rate', 'header' => 'السعر'],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportWards(...), $this->importWard(...)),
            ...$this->extraBooks(),
        ];

        $indexed = [];
        foreach ($books as $book) {
            $indexed[$book->key] = $book;
        }

        return $indexed;
    }

    /**
     * @param  list<array{key: string, header: string, required?: bool}>  $columns
     * @param  \Closure(): iterable<int, array<string, scalar|null>>  $export
     * @param  \Closure(array<string, string>): string  $import
     */
    protected function book(
        string $key,
        string $title,
        string $view,
        string $manage,
        array $columns,
        \Closure $export,
        \Closure $import,
        bool $importable = true,
    ): ExcelBook {
        return new ExcelBook($key, $title, $view, $manage, $columns, $export, $import, $importable);
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportLabTests(): iterable
    {
        $query = LabTestModel::query()->orderBy('name');
        $this->guardCount($query);

        foreach ($query->cursor() as $test) {
            yield [
                'id' => $test->getKey(),
                'name' => $test->name,
                'code' => $test->code,
                'category' => $this->labCategoryLabel((string) $test->category),
                'sample_type' => $test->sample_type,
                'unit' => $test->unit,
                'reference_range' => $test->reference_range,
                'price' => $this->money($test->price),
                'is_active' => $this->yesNo($test->is_active),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importLabTest(array $row): string
    {
        $category = $this->labCategory($row['category'] ?? '');
        $name = $this->required($row, 'name', 'اسم التحليل');
        $existing = $this->model(LabTestModel::class, $row);

        if ($existing === null && trim($row['code'] ?? '') !== '') {
            $existing = LabTestModel::query()->where('code', trim($row['code']))->first();
        }

        if ($existing === null && trim($row['code'] ?? '') === '') {
            $existing = LabTestModel::query()->where('name', $name)->where('category', $category)->first();
        }

        $attributes = [
            'name' => $name,
            'category' => $category,
            'sample_type' => $this->nullable($row, 'sample_type'),
            'unit' => $this->nullable($row, 'unit'),
            'reference_range' => $this->nullable($row, 'reference_range'),
            'price' => $this->optionalNumber($row, 'price', $existing instanceof LabTestModel ? $existing->price : null, 0),
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof LabTestModel ? (bool) $existing->is_active : null, true),
        ];
        $code = trim($row['code'] ?? '');

        if ($code !== '') {
            $this->assertCode(LabTestModel::query(), $code, $existing?->getKey(), 'رمز التحليل مستخدم: '.$code);
            $attributes['code'] = $code;
        } elseif ($existing === null) {
            $attributes['code'] = $this->nextCode(LabTestModel::class, 'T-');
        }

        if ($existing === null) {
            LabTestModel::query()->create($attributes);

            return 'created';
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportLabOrders(): iterable
    {
        $query = LabOrderItemModel::query()
            ->with(['test', 'labOrder.order.encounter.patient'])
            ->whereHas('labOrder.order', fn ($order) => $order->forCurrentHospital())
            ->orderBy('id');
        $this->guardCount($query);

        foreach ($query->get() as $item) {
            $patient = $item->labOrder?->order?->encounter?->patient;
            $order = $item->labOrder?->order;
            yield [
                'id' => $item->getKey(),
                'mrn' => $patient?->mrn,
                'national_id' => $patient?->national_id,
                'patient_name' => $patient?->full_name,
                'test_code' => $item->test?->code,
                'test_name' => $item->test?->name,
                'notes' => $order?->clinical_notes,
                'status' => $item->status->label(),
                'ordered_at' => $order?->ordered_at?->format('Y-m-d H:i'),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importLabOrder(array $row): string
    {
        $patient = $this->patientFromRow($row);
        $test = null;
        $code = trim($row['test_code'] ?? '');
        $name = trim($row['test_name'] ?? '');

        if ($code !== '') {
            $test = LabTestModel::query()->where('code', $code)->first();
        }

        if ($test === null && $name !== '') {
            $test = LabTestModel::query()->where('name', $name)->first();
        }

        if ($test === null) {
            throw new InvalidArgumentException('التحليل غير موجود في دليل التحاليل.');
        }

        if ($this->sameDayItem(LabOrderItemModel::class, 'lab_test_id', (int) $test->getKey(), 'labOrder.order', $patient, $row)) {
            return 'skipped';
        }

        $this->orderFor($patient, 'laboratory', 'lab_test', (int) $test->getKey(), (string) $test->name, $row['notes'] ?? '');

        return 'created';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportMedications(): iterable
    {
        $query = MedicationModel::query()->orderBy('name');
        $this->guardCount($query);

        foreach ($query->cursor() as $medication) {
            yield $this->medicationRow($medication);
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importMedication(array $row): string
    {
        $name = $this->required($row, 'name', 'اسم الدواء');
        $strength = $this->nullable($row, 'strength');
        $barcode = $this->nullable($row, 'barcode');
        $existing = $this->model(MedicationModel::class, $row);

        if (! $existing instanceof MedicationModel && $barcode !== null) {
            $existing = MedicationModel::query()->where('barcode', $barcode)->first();
        }

        if (! $existing instanceof MedicationModel) {
            $match = MedicationModel::query()->where('name', $name);
            $strength === null ? $match->whereNull('strength') : $match->where('strength', $strength);
            $existing = $match->first();
        }

        $attributes = [
            'name' => $name,
            'generic_name' => $this->nullable($row, 'generic_name'),
            'strength' => $strength,
            'dosage_form' => $this->nullable($row, 'dosage_form'),
            'route' => $this->nullable($row, 'route'),
            'price' => $this->optionalNumber($row, 'price', $existing instanceof MedicationModel ? $existing->price : null, 0),
            'requires_prescription' => $this->optionalBool($row, 'requires_prescription', $existing instanceof MedicationModel ? (bool) $existing->requires_prescription : null, true),
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof MedicationModel ? (bool) $existing->is_active : null, true),
        ];

        if ($barcode !== null) {
            $attributes['barcode'] = $barcode;
        }

        if ($existing === null) {
            MedicationModel::query()->create($attributes);

            return 'created';
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportPharmacy(): iterable
    {
        $query = MedicationModel::query()->where('hospital_id', $this->hospitalId())->orderBy('name');
        $this->guardCount($query);

        foreach ($query->cursor() as $medication) {
            yield [
                'id' => $medication->getKey(),
                'name' => $medication->name,
                'barcode' => $medication->barcode,
                'quantity' => $this->money($medication->quantity),
                'price' => $this->money($medication->price),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importPharmacy(array $row): string
    {
        $hospitalId = $this->hospitalId();
        $name = $this->required($row, 'name', 'اسم الدواء');
        $barcode = $this->required($row, 'barcode', 'الباركود');
        $existing = $this->model(MedicationModel::class, $row);

        if ($existing instanceof MedicationModel && (int) $existing->hospital_id !== $hospitalId) {
            throw new InvalidArgumentException('هذا الدواء لا يتبع صيدلية هذا المستشفى.');
        }

        if (! $existing instanceof MedicationModel) {
            $existing = MedicationModel::query()
                ->where('hospital_id', $hospitalId)
                ->where('barcode', $barcode)
                ->first();
        }

        $attributes = [
            'name' => $name,
            'barcode' => $barcode,
            'quantity' => $this->requiredNumber($row, 'quantity', 'الكمية', $existing instanceof MedicationModel ? (float) $existing->quantity : null),
            'price' => $this->requiredNumber($row, 'price', 'السعر', $existing instanceof MedicationModel ? (float) $existing->price : null),
            'hospital_id' => $hospitalId,
            'requires_prescription' => false,
            'is_active' => true,
        ];

        if ($existing === null) {
            MedicationModel::query()->create($attributes);

            return 'created';
        }

        $taken = MedicationModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('barcode', $barcode)
            ->whereKeyNot($existing->getKey())
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException('الباركود مستخدم لدواء آخر: '.$barcode);
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportDiagnoses(): iterable
    {
        $query = DiagnosisCodeModel::query()->with('clinics')->orderBy('title');
        $this->guardCount($query);

        foreach ($query->get() as $code) {
            yield [
                'id' => $code->getKey(),
                'title' => $code->title,
                'clinic' => $code->clinics->first()?->name,
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importDiagnosis(array $row): string
    {
        $title = $this->required($row, 'title', 'التشخيص');
        $clinicId = $this->clinic($row['clinic'] ?? '')->getKey();
        $existing = $this->model(DiagnosisCodeModel::class, $row);

        if (! $existing instanceof DiagnosisCodeModel) {
            $existing = DiagnosisCodeModel::query()
                ->where('title', $title)
                ->whereHas('clinics', fn ($clinics) => $clinics->where('clinics.id', $clinicId))
                ->first();
        }

        if ($existing === null) {
            $code = DiagnosisCodeModel::query()->create([
                'system' => 'local',
                'code' => DiagnosisCodeModel::nextLocalCode(),
                'title' => $title,
                'is_active' => true,
            ]);
            $code->clinics()->sync([$clinicId]);

            return 'created';
        }

        $existing->update(['title' => $title, 'is_active' => true]);
        $existing->clinics()->sync([(int) $clinicId]);

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportWarehouses(): iterable
    {
        $query = WarehouseModel::query()->with('department')->forCurrentHospital()->orderBy('name');
        $this->guardCount($query);

        foreach ($query->get() as $warehouse) {
            yield [
                'id' => $warehouse->getKey(),
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'type' => $warehouse->type,
                'department' => $warehouse->department?->name,
                'is_pharmacy' => $this->yesNo($warehouse->is_pharmacy),
                'is_active' => $this->yesNo($warehouse->is_active),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importWarehouse(array $row): string
    {
        $code = $this->required($row, 'code', 'رمز المستودع');
        $existing = $this->model(WarehouseModel::class, $row);
        $hospitalId = $this->hospitalId();

        if (! $existing instanceof WarehouseModel) {
            $existing = WarehouseModel::query()->where('hospital_id', $hospitalId)->where('code', $code)->first();
        }

        $this->assertScopedCode(WarehouseModel::query()->where('hospital_id', $hospitalId), $code, $existing?->getKey(), 'رمز المستودع مستخدم: '.$code);
        $department = trim($row['department'] ?? '');
        $attributes = [
            'hospital_id' => $hospitalId,
            'name' => $this->required($row, 'name', 'اسم المستودع'),
            'code' => $code,
            'type' => $this->nullable($row, 'type'),
            'department_id' => $department === '' ? null : $this->department($department)->getKey(),
            'is_pharmacy' => $this->optionalBool($row, 'is_pharmacy', $existing instanceof WarehouseModel ? (bool) $existing->is_pharmacy : null, false),
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof WarehouseModel ? (bool) $existing->is_active : null, true),
        ];

        if ($existing === null) {
            WarehouseModel::query()->create($attributes);

            return 'created';
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportPatients(): iterable
    {
        $query = PatientModel::query()->forCurrentHospital()->orderBy('id');
        $this->guardCount($query);

        foreach ($query->cursor() as $patient) {
            yield [
                'id' => $patient->getKey(),
                'mrn' => $patient->mrn,
                'first_name' => $patient->first_name,
                'father_name' => $patient->father_name,
                'last_name' => $patient->last_name,
                'mother_name' => $patient->mother_name,
                'gender' => $this->genderLabel($patient->gender->value),
                'date_of_birth' => $patient->date_of_birth?->toDateString(),
                'national_id' => $patient->national_id,
                'phone' => $patient->phone,
                'city' => $patient->city,
                'address' => $patient->address,
                'blood_type' => $patient->blood_type,
                'notes' => $patient->notes,
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importPatient(array $row): string
    {
        $hospitalId = $this->hospitalId();
        $existing = $this->model(PatientModel::class, $row);

        if (! $existing instanceof PatientModel) {
            $existing = $this->findPatient($row, false);
        }

        $gender = trim($row['gender'] ?? '');
        $payload = [
            'first_name' => $this->required($row, 'first_name', 'الاسم'),
            'father_name' => $this->nullable($row, 'father_name'),
            'last_name' => $this->required($row, 'last_name', 'الكنية'),
            'mother_name' => $this->nullable($row, 'mother_name'),
            'gender' => $gender === '' && $existing instanceof PatientModel
                ? $existing->gender->value
                : $this->gender($gender),
            'date_of_birth' => trim($row['date_of_birth'] ?? '') === ''
                ? $existing?->date_of_birth?->toDateString()
                : $this->dateValue($row, 'date_of_birth'),
            'national_id' => trim($row['national_id'] ?? '') === '' && $existing instanceof PatientModel
                ? $existing->national_id
                : $this->nullable($row, 'national_id'),
            'phone' => $this->nullable($row, 'phone'),
            'city' => $this->nullable($row, 'city'),
            'address' => $this->nullable($row, 'address'),
            'blood_type' => $this->nullable($row, 'blood_type'),
            'notes' => $this->nullable($row, 'notes'),
        ];

        if ($existing instanceof PatientModel) {
            $this->updatePatient->execute($existing, $payload);

            return 'updated';
        }

        try {
            $this->registerPatient->execute(PatientData::fromArray($payload, $hospitalId));
        } catch (DomainException $exception) {
            $id = (int) ($exception->context()['patient_id'] ?? 0);
            $patient = $id > 0 ? PatientModel::query()->find($id) : null;

            if (! $patient instanceof PatientModel) {
                throw $exception;
            }

            $this->updatePatient->execute($patient, $payload);

            return 'updated';
        }

        return 'created';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportClinics(): iterable
    {
        $query = ClinicModel::query()
            ->with('department')
            ->whereHas('department', fn ($department) => $department->where('hospital_id', $this->hospitalId()))
            ->orderBy('name');
        $this->guardCount($query);

        foreach ($query->get() as $clinic) {
            yield [
                'id' => $clinic->getKey(),
                'department' => $clinic->department?->name,
                'name' => $clinic->name,
                'code' => $clinic->code,
                'description' => $clinic->description,
                'is_active' => $this->yesNo($clinic->is_active),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importClinic(array $row): string
    {
        $department = $this->department($row['department'] ?? '');
        $code = $this->required($row, 'code', 'رمز العيادة');
        $existing = $this->model(ClinicModel::class, $row);

        if (! $existing instanceof ClinicModel) {
            $existing = ClinicModel::query()
                ->where('department_id', $department->getKey())
                ->where('code', $code)
                ->first();
        }

        $this->assertScopedCode(
            ClinicModel::query()->where('department_id', $department->getKey()),
            $code,
            $existing?->getKey(),
            'رمز العيادة مستخدم في هذا القسم: '.$code,
        );
        $attributes = [
            'department_id' => $department->getKey(),
            'name' => $this->required($row, 'name', 'اسم العيادة'),
            'code' => $code,
            'description' => $this->nullable($row, 'description'),
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof ClinicModel ? (bool) $existing->is_active : null, true),
        ];

        if ($existing === null) {
            ClinicModel::query()->create($attributes);

            return 'created';
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportStaff(): iterable
    {
        app(EnsureStaffClinicSchema::class)->ensure();
        $query = StaffModel::query()
            ->with(['department', 'clinic', 'doctor', 'user.roles'])
            ->forCurrentHospital()
            ->orderBy('id');
        $this->guardCount($query);

        foreach ($query->get() as $staff) {
            $role = $staff->user?->roles->first()?->name;
            yield [
                'id' => $staff->getKey(),
                'employee_number' => $staff->employee_number,
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'phone' => $staff->phone,
                'job_title' => $staff->job_title,
                'department' => $staff->department?->name,
                'clinic' => $staff->clinic?->name,
                'specialty' => $staff->doctor?->specialty,
                'license_number' => $staff->doctor?->license_number,
                'is_doctor' => $this->yesNo($staff->doctor !== null),
                'is_active' => $this->yesNo($staff->is_active),
                'role' => Permissions::staffRoles()[$role] ?? ($role ?? ''),
                'username' => $staff->user?->username,
                'password' => '',
                'email' => $staff->user?->email,
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importStaff(array $row): string
    {
        $hospitalId = $this->hospitalId();
        $number = trim($row['employee_number'] ?? '');
        $existing = $this->model(StaffModel::class, $row);

        if ($existing instanceof StaffModel && (int) $existing->hospital_id !== $hospitalId) {
            throw new InvalidArgumentException('هذا الموظّف لا يتبع المستشفى الحالي.');
        }

        if (! $existing instanceof StaffModel && $number !== '') {
            $existing = StaffModel::query()->where('hospital_id', $hospitalId)->where('employee_number', $number)->first();
        }

        $role = $this->role($row['role'] ?? '');
        $departmentLabel = trim($row['department'] ?? '');
        $departmentId = $departmentLabel === ''
            ? ($existing instanceof StaffModel ? $existing->department_id : null)
            : $this->department($departmentLabel)->getKey();
        $clinicLabel = trim($row['clinic'] ?? '');
        $clinicId = $clinicLabel === ''
            ? ($existing instanceof StaffModel ? $existing->clinic_id : null)
            : $this->clinic($clinicLabel, $departmentId ? (int) $departmentId : null)->getKey();
        $isDoctor = $this->optionalBool($row, 'is_doctor', $existing instanceof StaffModel ? $existing->doctor !== null : null, false)
            || $role === 'doctor';

        if ($isDoctor && $clinicId === null && $departmentId) {
            $clinics = ClinicModel::query()->where('department_id', $departmentId)->where('is_active', true)->get();
            if ($clinics->count() > 1) {
                throw new InvalidArgumentException('اختر العيادة التي يعمل فيها الطبيب.');
            }
            $clinicId = $clinics->first()?->getKey();
        }

        $account = $this->account($row, $role);
        $job = $this->nullable($row, 'job_title');

        if ($job === null && $role !== null && $existing === null) {
            $job = Permissions::staffRoles()[$role];
        }

        $data = [
            'hospital_id' => $hospitalId,
            'employee_number' => $number !== '' ? $number : ($existing instanceof StaffModel ? $existing->employee_number : null),
            'first_name' => $this->required($row, 'first_name', 'الاسم'),
            'last_name' => $this->required($row, 'last_name', 'الكنية'),
            'phone' => $this->nullable($row, 'phone'),
            'job_title' => $job,
            'department_id' => $departmentId,
            'clinic_id' => $clinicId,
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof StaffModel ? (bool) $existing->is_active : null, true),
            'is_doctor' => $isDoctor,
            'specialty' => $this->nullable($row, 'specialty'),
            'license_number' => $this->nullable($row, 'license_number'),
        ];

        $created = $existing === null;

        return DB::transaction(function () use ($created, $data, $existing, $account): string {
            $staff = $created
                ? $this->createStaff->execute($data)
                : $this->updateStaff->execute($existing, $data);

            if ($account !== null && $staff->user === null) {
                $this->createUser->execute(UserData::fromArray([
                    'username' => $account['username'],
                    'email' => $account['email'],
                    'password' => $account['password'],
                    'staff_id' => $staff->getKey(),
                    'roles' => [$account['role']],
                    'department_ids' => array_values(array_filter([(int) $staff->department_id])),
                ]));
            }

            return $created ? 'created' : 'updated';
        });
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportRadiologyOrders(): iterable
    {
        $query = RadiologyOrderItemModel::query()
            ->with(['service', 'radiologyOrder.order.encounter.patient'])
            ->whereHas('radiologyOrder.order', fn ($order) => $order->forCurrentHospital())
            ->orderBy('id');
        $this->guardCount($query);

        foreach ($query->get() as $item) {
            $patient = $item->radiologyOrder?->order?->encounter?->patient;
            $order = $item->radiologyOrder?->order;
            yield [
                'id' => $item->getKey(),
                'mrn' => $patient?->mrn,
                'national_id' => $patient?->national_id,
                'patient_name' => $patient?->full_name,
                'service_code' => $item->service?->code,
                'service_name' => $item->service?->name,
                'notes' => $order?->clinical_notes ?: $item->radiologyOrder?->clinical_indication,
                'status' => $item->status->label(),
                'ordered_at' => $order?->ordered_at?->format('Y-m-d H:i'),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importRadiologyOrder(array $row): string
    {
        $patient = $this->patientFromRow($row);
        $code = trim($row['service_code'] ?? '');
        $name = trim($row['service_name'] ?? '');
        $service = null;

        if ($code !== '') {
            $service = RadiologyServiceModel::query()->where('code', $code)->first();
        }

        if ($service === null && $name !== '') {
            $service = RadiologyServiceModel::query()->where('name', $name)->first();
        }

        if ($service === null) {
            throw new InvalidArgumentException('فحص الأشعة غير موجود في الأنواع.');
        }

        if ($this->sameDayItem(RadiologyOrderItemModel::class, 'radiology_service_id', (int) $service->getKey(), 'radiologyOrder.order', $patient, $row)) {
            return 'skipped';
        }

        $this->orderFor($patient, 'radiology', 'radiology_service', (int) $service->getKey(), (string) $service->name, $row['notes'] ?? '');

        return 'created';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportRadiologyServices(): iterable
    {
        $query = RadiologyServiceModel::query()->orderBy('name');
        $this->guardCount($query);

        foreach ($query->cursor() as $service) {
            $modality = Modality::tryFrom((string) $service->modality);
            yield [
                'id' => $service->getKey(),
                'name' => $service->name,
                'code' => $service->code,
                'modality' => $modality?->label() ?? $service->modality,
                'price' => $this->money($service->price),
                'description' => $service->description,
                'requires_contrast' => $this->yesNo($service->requires_contrast),
                'is_active' => $this->yesNo($service->is_active),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importRadiologyService(array $row): string
    {
        $name = $this->required($row, 'name', 'اسم الفحص');
        $modality = $this->modality($row['modality'] ?? '');
        $existing = $this->model(RadiologyServiceModel::class, $row);

        if ($existing === null && trim($row['code'] ?? '') !== '') {
            $existing = RadiologyServiceModel::query()->where('code', trim($row['code']))->first();
        }

        if ($existing === null && trim($row['code'] ?? '') === '') {
            $existing = RadiologyServiceModel::query()->where('name', $name)->where('modality', $modality)->first();
        }

        $attributes = [
            'name' => $name,
            'modality' => $modality,
            'description' => $this->nullable($row, 'description'),
            'price' => $this->optionalNumber($row, 'price', $existing instanceof RadiologyServiceModel ? $existing->price : null, 0),
            'requires_contrast' => $this->optionalBool($row, 'requires_contrast', $existing instanceof RadiologyServiceModel ? (bool) $existing->requires_contrast : null, false),
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof RadiologyServiceModel ? (bool) $existing->is_active : null, true),
        ];
        $code = trim($row['code'] ?? '');

        if ($code !== '') {
            $this->assertCode(RadiologyServiceModel::query(), $code, $existing?->getKey(), 'رمز الفحص مستخدم: '.$code);
            $attributes['code'] = $code;
        } elseif ($existing === null) {
            $attributes['code'] = $this->nextCode(RadiologyServiceModel::class, 'R-');
        }

        if ($existing === null) {
            RadiologyServiceModel::query()->create($attributes);

            return 'created';
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportRooms(): iterable
    {
        $query = RoomModel::query()
            ->with('ward')
            ->whereHas('ward', fn ($ward) => $ward->where('hospital_id', $this->hospitalId()))
            ->orderBy('id');
        $this->guardCount($query);

        foreach ($query->get() as $room) {
            yield [
                'id' => $room->getKey(),
                'ward' => $room->ward?->name,
                'room_number' => $room->room_number,
                'room_type' => $room->room_type,
                'bed_count' => $room->capacity,
                'daily_rate' => $room->daily_rate === null ? '' : $this->money($room->daily_rate),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importRoom(array $row): string
    {
        $ward = $this->ward($row['ward'] ?? '');
        $number = $this->required($row, 'room_number', 'رقم الغرفة');
        $existing = $this->model(RoomModel::class, $row);

        if ($existing instanceof RoomModel) {
            $existing->loadMissing('ward');

            if ($existing->ward !== null && (int) $existing->ward->hospital_id !== $this->hospitalId()) {
                throw new InvalidArgumentException('هذه الغرفة لا تتبع المستشفى الحالي.');
            }
        }

        if (! $existing instanceof RoomModel) {
            $existing = RoomModel::query()->where('ward_id', $ward->getKey())->where('room_number', $number)->first();
        }

        $beds = trim($row['bed_count'] ?? '');
        $bedCount = $beds === '' ? (int) ($existing?->capacity ?? 0) : (int) $beds;

        if ($bedCount < 1) {
            throw new InvalidArgumentException('عدد الأسرة مطلوب.');
        }

        $payload = [
            'ward_id' => $ward->getKey(),
            'room_number' => $number,
            'room_type' => $this->nullable($row, 'room_type'),
            'bed_count' => $bedCount,
            'daily_rate' => $this->nullableNumber($row, 'daily_rate'),
        ];

        if ($existing === null) {
            $this->createRoom->execute($ward, $payload);

            return 'created';
        }

        $this->updateRoom->execute($existing, $payload);

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    protected function exportWards(): iterable
    {
        $query = WardModel::query()->with('department')->forCurrentHospital()->orderBy('name');
        $this->guardCount($query);

        foreach ($query->get() as $ward) {
            yield [
                'id' => $ward->getKey(),
                'department' => $ward->department?->name,
                'name' => $ward->name,
                'code' => $ward->code,
                'type' => $ward->type,
                'floor' => $ward->floor,
                'daily_rate' => $this->money($ward->daily_rate),
                'is_active' => $this->yesNo($ward->is_active),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importWard(array $row): string
    {
        $code = $this->required($row, 'code', 'رمز الجناح');
        $hospitalId = $this->hospitalId();
        $existing = $this->model(WardModel::class, $row);

        if ($existing instanceof WardModel && (int) $existing->hospital_id !== $hospitalId) {
            throw new InvalidArgumentException('هذا الجناح لا يتبع المستشفى الحالي.');
        }

        if (! $existing instanceof WardModel) {
            $existing = WardModel::query()->where('hospital_id', $hospitalId)->where('code', $code)->first();
        }

        $this->assertScopedCode(
            WardModel::query()->where('hospital_id', $hospitalId),
            $code,
            $existing?->getKey(),
            'رمز الجناح مستخدم: '.$code,
        );
        $floor = trim($row['floor'] ?? '');
        $attributes = [
            'hospital_id' => $hospitalId,
            'department_id' => $this->department($row['department'] ?? '')->getKey(),
            'name' => $this->required($row, 'name', 'اسم الجناح'),
            'code' => $code,
            'type' => $this->nullable($row, 'type'),
            'floor' => $floor === '' ? null : (int) $floor,
            'daily_rate' => $this->nullableNumber($row, 'daily_rate') ?? 0,
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof WardModel ? (bool) $existing->is_active : null, true),
        ];

        if ($existing === null) {
            WardModel::query()->create($attributes);

            return 'created';
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function orderFor(PatientModel $patient, string $type, string $serviceType, int $serviceId, string $name, string $notes): void
    {
        $encounter = EncounterModel::query()->open()->where('patient_id', $patient->getKey())->latest('id')->first();

        if ($encounter === null) {
            try {
                $encounter = $this->createEncounter->execute($patient, [
                    'type' => 'outpatient',
                    'department_id' => $this->currentUser->departmentId(),
                    'reason' => 'طلب من ملف إكسل',
                ]);
            } catch (DomainException $exception) {
                $id = (int) ($exception->context()['encounter_id'] ?? 0);
                $encounter = $id > 0 ? EncounterModel::query()->find($id) : null;

                if ($encounter === null) {
                    throw $exception;
                }
            }
        }

        $this->createOrder->execute([
            'encounter_id' => $encounter->getKey(),
            'type' => $type,
            'department_id' => $encounter->department_id,
            'clinical_notes' => trim($notes) === '' ? null : trim($notes),
            'items' => [[
                'service_type' => $serviceType,
                'service_id' => $serviceId,
                'name' => $name,
                'quantity' => 1,
            ]],
        ]);
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function patientFromRow(array $row): PatientModel
    {
        $patient = $this->findPatient($row, true);

        if ($patient instanceof PatientModel) {
            return $patient;
        }

        throw new InvalidArgumentException('لم يُعثر على المريض. اكتب رقمه أو رقمه الوطني.');
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function findPatient(array $row, bool $required): ?PatientModel
    {
        $hospitalId = $this->hospitalId();
        $national = trim($row['national_id'] ?? '');
        $mrn = trim($row['mrn'] ?? '');

        if ($national !== '') {
            $patient = PatientModel::query()->where('hospital_id', $hospitalId)->where('national_id', $national)->first();
            if ($patient instanceof PatientModel) {
                return $patient;
            }
        }

        if ($mrn !== '') {
            $patient = PatientModel::query()->where('hospital_id', $hospitalId)->where('mrn', $mrn)->first();
            if ($patient instanceof PatientModel) {
                return $patient;
            }
        }

        if ($required && ($national !== '' || $mrn !== '')) {
            throw new InvalidArgumentException('لم يُعثر على المريض. اكتب رقمه أو رقمه الوطني.');
        }

        return null;
    }

    /**
     * The same patient and the same test on the same day is already on file,
     * so a second import fills nothing and removes nothing.
     *
     * @param  class-string<Model>  $class
     * @param  array<string, string>  $row
     */
    protected function sameDayItem(string $class, string $column, int $itemId, string $orderPath, PatientModel $patient, array $row): bool
    {
        $day = $this->sheetDay($row, 'ordered_at');

        return $class::query()
            ->where($column, $itemId)
            ->whereHas($orderPath, function ($order) use ($patient, $day): void {
                $order->whereDate('ordered_at', $day)
                    ->whereHas('encounter', fn ($encounter) => $encounter->where('patient_id', $patient->getKey()));
            })
            ->exists();
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function sheetDay(array $row, string $key): string
    {
        $raw = trim($row[$key] ?? '');

        if ($raw === '') {
            return now()->toDateString();
        }

        $date = explode(' ', str_replace('T', ' ', $raw))[0];

        return (string) $this->dateValue(['day' => $date], 'day');
    }

    /**
     * @param  array<string, string>  $row
     * @return array{username: string, password: string, email: ?string, role: string}|null
     */
    protected function account(array $row, ?string $role): ?array
    {
        $username = trim($row['username'] ?? '');
        $password = trim($row['password'] ?? '');
        $roleFilled = trim($row['role'] ?? '') !== '';

        if ($username === '' && $password === '' && ! $roleFilled) {
            return null;
        }

        if ($username === '' || $password === '' || $role === null) {
            throw new InvalidArgumentException('لإنشاء حساب املأ نوع الحساب واسم المستخدم وكلمة المرور معاً.');
        }

        if (mb_strlen($password) < 6) {
            throw new InvalidArgumentException('كلمة المرور يجب أن تكون 6 أحرف على الأقل.');
        }

        return [
            'username' => $username,
            'password' => $password,
            'email' => $this->nullable($row, 'email'),
            'role' => $role,
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function medicationRow(MedicationModel $medication): array
    {
        return [
            'id' => $medication->getKey(),
            'name' => $medication->name,
            'generic_name' => $medication->generic_name,
            'strength' => $medication->strength,
            'dosage_form' => $medication->dosage_form,
            'route' => $medication->route,
            'barcode' => $medication->barcode,
            'price' => $this->money($medication->price),
            'requires_prescription' => $this->yesNo($medication->requires_prescription),
            'is_active' => $this->yesNo($medication->is_active),
        ];
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, string>  $row
     */
    protected function model(string $class, array $row): ?Model
    {
        return null;
    }

    protected function department(string $label): DepartmentModel
    {
        $label = trim($label);

        if ($label === '') {
            throw new InvalidArgumentException('القسم مطلوب.');
        }

        $base = DepartmentModel::query()->where('hospital_id', $this->hospitalId());
        $department = (clone $base)->where('code', $label)->first()
            ?? (clone $base)->where('name', $label)->first();

        if (! $department instanceof DepartmentModel) {
            throw new InvalidArgumentException('القسم غير موجود: '.$label);
        }

        return $department;
    }

    protected function clinic(string $label, ?int $departmentId = null): ClinicModel
    {
        $label = trim($label);

        if ($label === '') {
            throw new InvalidArgumentException('العيادة مطلوبة.');
        }

        $base = ClinicModel::query()
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->whereHas('department', fn ($department) => $department->where('hospital_id', $this->hospitalId()));
        $clinic = (clone $base)->where('code', $label)->first()
            ?? (clone $base)->where('name', $label)->first();

        if (! $clinic instanceof ClinicModel) {
            throw new InvalidArgumentException('العيادة غير موجودة: '.$label);
        }

        return $clinic;
    }

    protected function ward(string $label): WardModel
    {
        $label = trim($label);

        if ($label === '') {
            throw new InvalidArgumentException('الجناح مطلوب.');
        }

        $base = WardModel::query()->where('hospital_id', $this->hospitalId());
        $ward = (clone $base)->where('code', $label)->first()
            ?? (clone $base)->where('name', $label)->first();

        if (! $ward instanceof WardModel) {
            throw new InvalidArgumentException('الجناح غير موجود: '.$label);
        }

        return $ward;
    }

    protected function hospitalId(): int
    {
        $id = $this->currentUser->hospitalId();

        if ($id) {
            return $id;
        }

        $fallback = HospitalModel::query()->orderBy('id')->value('id');

        if (! $fallback) {
            throw new InvalidArgumentException('لا يوجد مستشفى مرتبط بهذا الحساب.');
        }

        return (int) $fallback;
    }

    /**
     * @param  class-string<Model>  $class
     */
    protected function nextCode(string $class, string $prefix): string
    {
        $number = ((int) $class::query()->max('id')) + 1;

        do {
            $code = $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while ($class::query()->where('code', $code)->exists());

        return $code;
    }

    protected function assertCode(Builder $query, string $code, mixed $ignore, string $message): void
    {
        $check = (clone $query)->where('code', $code);

        if ($ignore) {
            $check->whereKeyNot($ignore);
        }

        if ($check->exists()) {
            throw new InvalidArgumentException($message);
        }
    }

    protected function assertScopedCode(Builder $query, string $code, mixed $ignore, string $message): void
    {
        $this->assertCode($query, $code, $ignore, $message);
    }

    protected function guardCount(Builder $query): void
    {
        if ((clone $query)->count() > 20000) {
            throw new InvalidArgumentException('عدد السجلات أكبر من 20000.');
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function required(array $row, string $key, string $label): string
    {
        $value = trim($row[$key] ?? '');

        if ($value === '') {
            throw new InvalidArgumentException($label.' مطلوب.');
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function nullable(array $row, string $key): ?string
    {
        $value = trim($row[$key] ?? '');

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function optionalNumber(array $row, string $key, ?float $current, float $default): float
    {
        $raw = trim(str_replace(',', '.', $row[$key] ?? ''));

        if ($raw === '') {
            return $current ?? $default;
        }

        if (! is_numeric($raw) || (float) $raw < 0) {
            throw new InvalidArgumentException('القيمة الرقمية غير صالحة.');
        }

        return round((float) $raw, 2);
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function requiredNumber(array $row, string $key, string $label, ?float $current): float
    {
        $raw = trim($row[$key] ?? '');

        if ($raw === '') {
            if ($current !== null) {
                return $current;
            }

            throw new InvalidArgumentException($label.' مطلوب.');
        }

        return $this->optionalNumber($row, $key, null, 0);
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function nullableNumber(array $row, string $key): ?float
    {
        if (trim($row[$key] ?? '') === '') {
            return null;
        }

        return $this->optionalNumber($row, $key, null, 0);
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function optionalBool(array $row, string $key, ?bool $current, bool $default): bool
    {
        $raw = trim($row[$key] ?? '');

        if ($raw === '') {
            return $current ?? $default;
        }

        return $this->truthy($raw);
    }

    protected function truthy(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'نعم', 'صح', 'فعال'], true);
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function dateValue(array $row, string $key): ?string
    {
        $value = trim($row[$key] ?? '');

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (int) floor((float) $value);

            if ($serial > 20000 && $serial < 80000) {
                return (new \DateTimeImmutable('1899-12-30'))->modify('+'.$serial.' days')->format('Y-m-d');
            }
        }

        $value = str_replace('.', '/', $value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($parsed instanceof \DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed->format('Y-m-d');
            }
        }

        throw new InvalidArgumentException('التاريخ غير مفهوم: '.$value);
    }

    protected function gender(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return match ($value) {
            'male', 'm', 'ذكر' => 'male',
            'female', 'f', 'أنثى', 'انثى' => 'female',
            'other', 'أخرى', 'اخرى' => 'other',
            default => throw new InvalidArgumentException('الجنس يجب أن يكون ذكر أو أنثى.'),
        };
    }

    protected function genderLabel(string $value): string
    {
        return match ($value) {
            'male' => 'ذكر',
            'female' => 'أنثى',
            default => 'أخرى',
        };
    }

    protected function labCategory(string $value): string
    {
        $raw = trim($value);
        $map = [
            'hematology' => LabTestCategory::Hematology->value,
            'أمراض الدم' => LabTestCategory::Hematology->value,
            'chemistry' => LabTestCategory::Chemistry->value,
            'كيمياء' => LabTestCategory::Chemistry->value,
            'hormones' => LabTestCategory::Hormones->value,
            'هرمونات' => LabTestCategory::Hormones->value,
            'tumors' => LabTestCategory::Tumors->value,
            'أورام' => LabTestCategory::Tumors->value,
            'اورام' => LabTestCategory::Tumors->value,
        ];
        $found = $map[mb_strtolower($raw)] ?? $map[$raw] ?? null;

        if ($found === null) {
            throw new InvalidArgumentException('الفئة غير معروفة. استخدم: أمراض الدم، كيمياء، هرمونات، أورام.');
        }

        return $found;
    }

    protected function labCategoryLabel(string $value): string
    {
        return match ($value) {
            'hematology' => 'أمراض الدم',
            'chemistry' => 'كيمياء',
            'hormones' => 'هرمونات',
            'tumors' => 'أورام',
            default => $value,
        };
    }

    protected function modality(string $value): string
    {
        $raw = trim($value);
        $lower = mb_strtolower($raw);

        foreach (Modality::cases() as $case) {
            if ($raw === $case->value || $lower === $case->value || $raw === $case->label()) {
                return $case->value;
            }
        }

        throw new InvalidArgumentException('نوع الأشعة غير معروف. استخدم: طبقي، إيكو، بانوراما، رنين، أشعة بسيطة.');
    }

    protected function role(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach (Permissions::staffRoles() as $key => $label) {
            if ($value === $key || $value === $label) {
                return $key;
            }
        }

        throw new InvalidArgumentException('نوع الحساب غير معروف: '.$value);
    }

    protected function yesNo(mixed $value): string
    {
        return $value ? 'نعم' : 'لا';
    }

    protected function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;

        if (floor($number) == $number) {
            return (string) (int) $number;
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    /**
     * @param  list<array{row: int, cells: list<string>}>  $table
     */
    protected function headerIndex(array $table): ?int
    {
        foreach ($table as $index => $entry) {
            if (! $this->blank($entry['cells'])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    protected function columnIndexes(ExcelBook $book, array $headers): array
    {
        $map = [];

        foreach ($book->columns as $column) {
            $map[$this->norm($column['header'])] = $column['key'];
            $map[$this->norm($column['key'])] = $column['key'];
        }

        $indexes = [];

        foreach ($headers as $index => $header) {
            $key = $map[$this->norm($header)] ?? null;

            if ($key !== null) {
                $indexes[$key] = $index;
            }
        }

        $missing = [];

        foreach ($book->columns as $column) {
            if (($column['required'] ?? false) && ! isset($indexes[$column['key']])) {
                $missing[] = $column['header'];
            }
        }

        if ($missing !== []) {
            throw new InvalidArgumentException('الملف ينقصه الأعمدة: '.implode('، ', $missing));
        }

        return $indexes;
    }

    /**
     * @param  array<string, int>  $indexes
     * @param  list<string>  $cells
     * @return array<string, string>
     */
    protected function record(array $indexes, array $cells): array
    {
        $record = [];

        foreach ($indexes as $key => $index) {
            $record[$key] = trim((string) ($cells[$index] ?? ''));
        }

        return $record;
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    protected function blank(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function norm(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return trim(str_replace("\u{00A0}", ' ', $value));
    }

    /**
     * @param  list<array{row: int, message: string}>  $errors
     */
    protected function remember(array &$errors, int $row, string $message): void
    {
        if (count($errors) < 30) {
            $errors[] = ['row' => $row, 'message' => $message];
        }
    }
}
