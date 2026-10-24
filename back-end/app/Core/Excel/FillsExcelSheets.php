<?php

declare(strict_types=1);

namespace App\Core\Excel;

use App\Core\Authorization\Permissions;
use App\Core\Exceptions\DomainException;
use App\Modules\Assets\Application\Actions\RegisterAssetAction;
use App\Modules\Assets\Domain\Enums\AssetCategory;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetModel;
use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLogModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\ServiceModel;
use App\Modules\Departments\Domain\Enums\DepartmentType;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use App\Modules\Identity\Application\DTOs\UserData;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;
use App\Modules\Inventory\Domain\Enums\ItemType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockLevelModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Laboratory\Domain\Enums\BloodRequestStatus;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodProductModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodRequestModel;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationModel;
use App\Modules\Nursing\Infrastructure\Persistence\Models\MedicationAdministrationModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTypeModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyPurchaseModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionModel;
use App\Modules\Settings\Infrastructure\Persistence\Models\SettingModel;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The remaining list sheets. Matching uses a business key. The table assigns
 * its own identifier, and rows that are absent from the file stay where they are.
 *
 * @mixin ExcelExchange
 */
trait FillsExcelSheets
{
    /**
     * @return list<ExcelBook>
     */
    private function extraBooks(): array
    {
        return [
            $this->book('departments', 'الأقسام', Permissions::DEPARTMENTS_MANAGE, Permissions::DEPARTMENTS_MANAGE, [
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'code', 'header' => 'الرمز', 'required' => true],
                ['key' => 'type', 'header' => 'النوع', 'required' => true],
                ['key' => 'description', 'header' => 'الوصف'],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportDepartments(...), $this->importDepartment(...)),
            $this->book('assets', 'أصول القسم التقني', Permissions::ASSETS_VIEW, Permissions::ASSETS_MANAGE, [
                ['key' => 'asset_code', 'header' => 'رقم الأصل'],
                ['key' => 'barcode', 'header' => 'الباركود'],
                ['key' => 'category', 'header' => 'النوع', 'required' => true],
                ['key' => 'brand', 'header' => 'الشركة'],
                ['key' => 'model_name', 'header' => 'الموديل'],
                ['key' => 'received_by', 'header' => 'العهدة لدى'],
                ['key' => 'department', 'header' => 'القسم'],
                ['key' => 'location', 'header' => 'المكان'],
                ['key' => 'delivered_on', 'header' => 'تاريخ التسليم', 'required' => true],
                ['key' => 'notes', 'header' => 'ملاحظات'],
                ['key' => 'status', 'header' => 'الحالة'],
            ], $this->exportAssets(...), $this->importAsset(...)),
            $this->book('inventory-items', 'الأصناف', Permissions::INVENTORY_STOCK_VIEW, Permissions::INVENTORY_ITEMS_MANAGE, [
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'sku', 'header' => 'الرمز', 'required' => true],
                ['key' => 'barcode', 'header' => 'الباركود'],
                ['key' => 'category', 'header' => 'الفئة'],
                ['key' => 'unit', 'header' => 'الوحدة', 'required' => true],
                ['key' => 'item_type', 'header' => 'النوع', 'required' => true],
                ['key' => 'minimum_stock', 'header' => 'الحد الأدنى'],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportItems(...), $this->importItem(...)),
            $this->book('stock', 'المخزون', Permissions::INVENTORY_STOCK_VIEW, Permissions::INVENTORY_ITEMS_MANAGE, [
                ['key' => 'sku', 'header' => 'رمز الصنف', 'required' => true],
                ['key' => 'item', 'header' => 'الصنف'],
                ['key' => 'warehouse', 'header' => 'المستودع', 'required' => true],
                ['key' => 'quantity', 'header' => 'الكمية', 'required' => true],
                ['key' => 'expiry_date', 'header' => 'تاريخ الصلاحية'],
            ], $this->exportStock(...), $this->importStock(...)),
            $this->book('pharmacy-stock', 'مخزون الصيدلية', Permissions::PHARMACY_MEDICATIONS_MANAGE, Permissions::PHARMACY_MEDICATIONS_MANAGE, [
                ['key' => 'sku', 'header' => 'رمز الصنف', 'required' => true],
                ['key' => 'item', 'header' => 'الصنف'],
                ['key' => 'warehouse', 'header' => 'المستودع', 'required' => true],
                ['key' => 'quantity', 'header' => 'الكمية', 'required' => true],
                ['key' => 'expiry_date', 'header' => 'تاريخ الصلاحية'],
            ], $this->exportPharmacyStock(...), $this->importStock(...)),
            $this->book('blood-products', 'منتجات الدم', Permissions::ORDERS_VIEW, Permissions::LABORATORY_TESTS_MANAGE, [
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'code', 'header' => 'الرمز'],
                ['key' => 'price', 'header' => 'السعر', 'required' => true],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportBloodProducts(...), $this->importBloodProduct(...)),
            $this->book('blood-requests', 'طلبات الدم', Permissions::LABORATORY_ORDERS_VIEW, Permissions::ORDERS_CREATE, [
                ['key' => 'mrn', 'header' => 'رقم المريض'],
                ['key' => 'national_id', 'header' => 'الرقم الوطني'],
                ['key' => 'patient_name', 'header' => 'اسم المريض'],
                ['key' => 'product_code', 'header' => 'رمز المنتج'],
                ['key' => 'product_name', 'header' => 'اسم المنتج'],
                ['key' => 'blood_group', 'header' => 'فصيلة الدم', 'required' => true],
                ['key' => 'units', 'header' => 'الوحدات', 'required' => true],
                ['key' => 'status', 'header' => 'الحالة'],
                ['key' => 'ordered_at', 'header' => 'التاريخ'],
            ], $this->exportBloodRequests(...), $this->importBloodRequest(...)),
            $this->book('billing-services', 'خدمات المحاسبة', Permissions::BILLING_INVOICES_VIEW, Permissions::BILLING_SERVICES_MANAGE, [
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'code', 'header' => 'الرمز', 'required' => true],
                ['key' => 'category', 'header' => 'الفئة'],
                ['key' => 'price', 'header' => 'السعر', 'required' => true],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportServices(...), $this->importService(...)),
            $this->book('invoices', 'الفواتير', Permissions::BILLING_INVOICES_VIEW, Permissions::BILLING_INVOICES_VIEW, [
                ['key' => 'number', 'header' => 'الرقم'],
                ['key' => 'mrn', 'header' => 'رقم المريض'],
                ['key' => 'patient_name', 'header' => 'اسم المريض'],
                ['key' => 'total', 'header' => 'الإجمالي'],
                ['key' => 'paid', 'header' => 'المدفوع'],
                ['key' => 'balance', 'header' => 'المتبقي'],
                ['key' => 'status', 'header' => 'الحالة'],
                ['key' => 'issued_at', 'header' => 'التاريخ'],
            ], $this->exportInvoices(...), $this->refuseImport(...), false),
            $this->book('operation-types', 'أنواع العمليات', Permissions::OPERATIONS_VIEW, Permissions::DEPARTMENTS_MANAGE, [
                ['key' => 'name', 'header' => 'الاسم', 'required' => true],
                ['key' => 'code', 'header' => 'الرمز', 'required' => true],
                ['key' => 'clinic', 'header' => 'العيادة', 'required' => true],
                ['key' => 'specialty', 'header' => 'الاختصاص'],
                ['key' => 'duration', 'header' => 'المدة'],
                ['key' => 'risk', 'header' => 'الخطورة'],
                ['key' => 'price', 'header' => 'السعر', 'required' => true],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportOperationTypes(...), $this->importOperationType(...)),
            $this->book('operations', 'العمليات', Permissions::OPERATIONS_VIEW, Permissions::OPERATIONS_SCHEDULE, [
                ['key' => 'mrn', 'header' => 'رقم المريض'],
                ['key' => 'national_id', 'header' => 'الرقم الوطني'],
                ['key' => 'patient_name', 'header' => 'اسم المريض'],
                ['key' => 'type_code', 'header' => 'رمز العملية'],
                ['key' => 'type_name', 'header' => 'اسم العملية'],
                ['key' => 'scheduled_at', 'header' => 'التاريخ', 'required' => true],
                ['key' => 'notes', 'header' => 'ملاحظات'],
                ['key' => 'status', 'header' => 'الحالة'],
            ], $this->exportOperations(...), $this->importOperation(...)),
            $this->book('admissions', 'الإقامات', Permissions::INPATIENT_VIEW, Permissions::INPATIENT_ADMIT, [
                ['key' => 'mrn', 'header' => 'رقم المريض'],
                ['key' => 'national_id', 'header' => 'الرقم الوطني'],
                ['key' => 'patient_name', 'header' => 'اسم المريض'],
                ['key' => 'ward', 'header' => 'الجناح', 'required' => true],
                ['key' => 'room_number', 'header' => 'رقم الغرفة'],
                ['key' => 'bed_number', 'header' => 'رقم السرير'],
                ['key' => 'reason', 'header' => 'السبب'],
                ['key' => 'admitted_at', 'header' => 'التاريخ'],
                ['key' => 'status', 'header' => 'الحالة'],
            ], $this->exportAdmissions(...), $this->importAdmission(...)),
            $this->book('appointments', 'المواعيد', Permissions::APPOINTMENTS_VIEW, Permissions::APPOINTMENTS_CREATE, [
                ['key' => 'mrn', 'header' => 'رقم المريض'],
                ['key' => 'national_id', 'header' => 'الرقم الوطني'],
                ['key' => 'patient_name', 'header' => 'اسم المريض'],
                ['key' => 'clinic', 'header' => 'العيادة'],
                ['key' => 'doctor', 'header' => 'الطبيب'],
                ['key' => 'scheduled_at', 'header' => 'التاريخ', 'required' => true],
                ['key' => 'duration', 'header' => 'المدة'],
                ['key' => 'reason', 'header' => 'السبب'],
                ['key' => 'notes', 'header' => 'ملاحظات'],
                ['key' => 'status', 'header' => 'الحالة'],
            ], $this->exportAppointments(...), $this->importAppointment(...)),
            $this->book('encounters', 'الزيارات', Permissions::ENCOUNTERS_VIEW, Permissions::ENCOUNTERS_CREATE, [
                ['key' => 'mrn', 'header' => 'رقم المريض'],
                ['key' => 'national_id', 'header' => 'الرقم الوطني'],
                ['key' => 'patient_name', 'header' => 'اسم المريض'],
                ['key' => 'department', 'header' => 'القسم'],
                ['key' => 'clinic', 'header' => 'العيادة'],
                ['key' => 'doctor', 'header' => 'الطبيب'],
                ['key' => 'type', 'header' => 'النوع'],
                ['key' => 'reason', 'header' => 'السبب'],
                ['key' => 'status', 'header' => 'الحالة'],
                ['key' => 'opened_at', 'header' => 'التاريخ'],
            ], $this->exportEncounters(...), $this->importEncounter(...)),
            $this->book('prescriptions', 'الوصفات', Permissions::PHARMACY_PRESCRIPTIONS_VIEW, Permissions::PHARMACY_PRESCRIPTIONS_CREATE, [
                ['key' => 'mrn', 'header' => 'رقم المريض'],
                ['key' => 'national_id', 'header' => 'الرقم الوطني'],
                ['key' => 'patient_name', 'header' => 'اسم المريض'],
                ['key' => 'medication', 'header' => 'الدواء', 'required' => true],
                ['key' => 'barcode', 'header' => 'الباركود'],
                ['key' => 'dose', 'header' => 'الجرعة'],
                ['key' => 'frequency', 'header' => 'التكرار'],
                ['key' => 'duration', 'header' => 'المدة'],
                ['key' => 'quantity', 'header' => 'الكمية'],
                ['key' => 'notes', 'header' => 'ملاحظات'],
                ['key' => 'status', 'header' => 'الحالة'],
                ['key' => 'prescribed_at', 'header' => 'التاريخ'],
            ], $this->exportPrescriptions(...), $this->importPrescription(...)),
            $this->book('purchases', 'المصاريف', Permissions::PHARMACY_MEDICATIONS_MANAGE, Permissions::PHARMACY_MEDICATIONS_MANAGE, [
                ['key' => 'supplier', 'header' => 'المورد', 'required' => true],
                ['key' => 'phone', 'header' => 'الهاتف'],
                ['key' => 'invoice_number', 'header' => 'رقم الفاتورة', 'required' => true],
                ['key' => 'total', 'header' => 'الإجمالي', 'required' => true],
                ['key' => 'cash', 'header' => 'المدفوع'],
                ['key' => 'due', 'header' => 'المتبقي'],
                ['key' => 'received_at', 'header' => 'التاريخ'],
            ], $this->exportPurchases(...), $this->importPurchase(...)),
            $this->book('debts', 'الديون', Permissions::PHARMACY_MEDICATIONS_MANAGE, Permissions::PHARMACY_MEDICATIONS_MANAGE, [
                ['key' => 'customer', 'header' => 'الزبون'],
                ['key' => 'phone', 'header' => 'الهاتف'],
                ['key' => 'sale_number', 'header' => 'رقم البيع'],
                ['key' => 'total', 'header' => 'الإجمالي'],
                ['key' => 'due', 'header' => 'المتبقي'],
                ['key' => 'sold_at', 'header' => 'التاريخ'],
            ], $this->exportDebts(...), $this->refuseImport(...), false),
            $this->book('settings', 'الإعدادات', Permissions::SETTINGS_MANAGE, Permissions::SETTINGS_MANAGE, [
                ['key' => 'group', 'header' => 'المجموعة'],
                ['key' => 'key', 'header' => 'المفتاح', 'required' => true],
                ['key' => 'value', 'header' => 'القيمة', 'required' => true],
            ], $this->exportSettings(...), $this->importSetting(...)),
            $this->book('users', 'المستخدمون', Permissions::USERS_MANAGE, Permissions::USERS_MANAGE, [
                ['key' => 'username', 'header' => 'اسم المستخدم', 'required' => true],
                ['key' => 'email', 'header' => 'البريد'],
                ['key' => 'password', 'header' => 'كلمة المرور'],
                ['key' => 'is_active', 'header' => 'فعال'],
            ], $this->exportUsers(...), $this->importUser(...)),
            $this->book('notifications', 'الإشعارات', '', '', [
                ['key' => 'title', 'header' => 'العنوان'],
                ['key' => 'message', 'header' => 'النص'],
                ['key' => 'type', 'header' => 'النوع'],
                ['key' => 'created_at', 'header' => 'التاريخ'],
            ], $this->exportNotifications(...), $this->refuseImport(...), false),
            $this->book('audit', 'سجل التدقيق', Permissions::AUDIT_VIEW, Permissions::AUDIT_VIEW, [
                ['key' => 'action', 'header' => 'الإجراء'],
                ['key' => 'user', 'header' => 'المستخدم'],
                ['key' => 'entity', 'header' => 'السجل'],
                ['key' => 'created_at', 'header' => 'التاريخ'],
            ], $this->exportAudit(...), $this->refuseImport(...), false),
            $this->book('nursing', 'التمريض', Permissions::NURSING_NOTES_VIEW, Permissions::NURSING_NOTES_VIEW, [
                ['key' => 'mrn', 'header' => 'رقم المريض'],
                ['key' => 'patient_name', 'header' => 'اسم المريض'],
                ['key' => 'medication', 'header' => 'الدواء'],
                ['key' => 'dose', 'header' => 'الجرعة'],
                ['key' => 'scheduled_at', 'header' => 'الوقت'],
                ['key' => 'status', 'header' => 'الحالة'],
            ], $this->exportNursing(...), $this->refuseImport(...), false),
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function refuseImport(array $row): string
    {
        throw new InvalidArgumentException('هذا الجدول للتصدير فقط، والاستيراد لا يغيّر السجلات الموجودة.');
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportDepartments(): iterable
    {
        $query = DepartmentModel::query()->where('hospital_id', $this->hospitalId())->orderBy('name');
        $this->guardCount($query);

        foreach ($query->get() as $department) {
            $type = $department->type;

            yield [
                'name' => $department->name,
                'code' => $department->code,
                'type' => $type instanceof DepartmentType ? $type->label() : (string) $type,
                'description' => $department->description,
                'is_active' => $this->yesNo($department->is_active),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importDepartment(array $row): string
    {
        $hospitalId = $this->hospitalId();
        $code = $this->required($row, 'code', 'رمز القسم');
        $existing = DepartmentModel::query()->where('hospital_id', $hospitalId)->where('code', $code)->first();
        $this->assertCode(
            DepartmentModel::query()->where('hospital_id', $hospitalId),
            $code,
            $existing?->getKey(),
            'رمز القسم مستخدم: '.$code,
        );

        $attributes = [
            'hospital_id' => $hospitalId,
            'name' => $this->required($row, 'name', 'اسم القسم'),
            'code' => $code,
            'type' => $this->departmentKind($row['type'] ?? ''),
            'description' => $this->nullable($row, 'description'),
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof DepartmentModel ? (bool) $existing->is_active : null, true),
        ];

        if ($existing === null) {
            DepartmentModel::query()->create($attributes);

            return 'created';
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportItems(): iterable
    {
        $query = InventoryItemModel::query()->orderBy('name');
        $this->guardCount($query);

        foreach ($query->get() as $item) {
            $type = $item->item_type;

            yield [
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'category' => $item->category,
                'unit' => $item->unit,
                'item_type' => $type instanceof ItemType ? $type->label() : (string) $type,
                'minimum_stock' => $item->minimum_stock,
                'is_active' => $this->yesNo($item->is_active),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importItem(array $row): string
    {
        $sku = $this->required($row, 'sku', 'رمز الصنف');
        $barcode = $this->nullable($row, 'barcode');
        $existing = InventoryItemModel::query()->where('sku', $sku)->first();

        if (! $existing instanceof InventoryItemModel && $barcode !== null) {
            $existing = InventoryItemModel::query()->where('barcode', $barcode)->first();
        }

        $attributes = [
            'name' => $this->required($row, 'name', 'اسم الصنف'),
            'sku' => $sku,
            'category' => $this->nullable($row, 'category'),
            'unit' => $this->required($row, 'unit', 'الوحدة'),
            'item_type' => $this->itemKind($row['item_type'] ?? ''),
            'minimum_stock' => $this->optionalNumber($row, 'minimum_stock', $existing instanceof InventoryItemModel ? $existing->minimum_stock : null, 0),
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof InventoryItemModel ? (bool) $existing->is_active : null, true),
        ];

        if ($barcode !== null) {
            $attributes['barcode'] = $barcode;
        }

        if ($existing === null) {
            InventoryItemModel::query()->create($attributes);

            return 'created';
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportStock(): iterable
    {
        return $this->stockRows(false);
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportPharmacyStock(): iterable
    {
        return $this->stockRows(true);
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function stockRows(bool $pharmacyOnly): iterable
    {
        $query = StockLevelModel::query()->with(['item', 'warehouse'])->orderBy('id');

        if ($pharmacyOnly) {
            $query->whereHas('warehouse', fn ($warehouse) => $warehouse->where('is_pharmacy', true));
        }

        $this->guardCount($query);

        foreach ($query->get() as $level) {
            yield [
                'sku' => $level->item?->sku,
                'item' => $level->item?->name,
                'warehouse' => $level->warehouse?->code ?: $level->warehouse?->name,
                'quantity' => $level->quantity,
                'expiry_date' => '',
            ];
        }
    }

    /**
     * Raises the on-hand quantity up to the sheet. A smaller number is left
     * alone, and other stock rows are not removed.
     *
     * @param  array<string, string>  $row
     */
    private function importStock(array $row): string
    {
        $item = $this->stockItem($row);
        $warehouse = $this->warehouseFromLabel($this->required($row, 'warehouse', 'المستودع'));
        $target = (float) str_replace(',', '', $this->required($row, 'quantity', 'الكمية'));
        $level = StockLevelModel::query()
            ->where('inventory_item_id', $item->getKey())
            ->where('warehouse_id', $warehouse->getKey())
            ->first();
        $current = (float) ($level->quantity ?? 0);

        if ($target <= $current + 0.0001) {
            return 'skipped';
        }

        $expiry = trim($row['expiry_date'] ?? '');
        $this->receiveStock->execute([
            'inventory_item_id' => $item->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'quantity' => round($target - $current, 3),
            'expiry_date' => $expiry === '' ? null : $this->dateValue(['day' => $expiry], 'day'),
        ]);

        return $level === null ? 'created' : 'updated';
    }

    /**
     * @param  array<string, string>  $row
     */
    private function stockItem(array $row): InventoryItemModel
    {
        $sku = trim($row['sku'] ?? '');
        $name = trim($row['item'] ?? '');
        $item = $sku !== '' ? InventoryItemModel::query()->where('sku', $sku)->first() : null;

        if (! $item instanceof InventoryItemModel && $name !== '') {
            $item = InventoryItemModel::query()->where('name', $name)->first();
        }

        if (! $item instanceof InventoryItemModel) {
            throw new InvalidArgumentException('الصنف غير موجود في دليل الأصناف.');
        }

        return $item;
    }

    private function warehouseFromLabel(string $label): WarehouseModel
    {
        $base = WarehouseModel::query()->where('hospital_id', $this->hospitalId());
        $warehouse = (clone $base)->where('code', $label)->first()
            ?? (clone $base)->where('name', $label)->first();

        if (! $warehouse instanceof WarehouseModel) {
            throw new InvalidArgumentException('المستودع غير موجود: '.$label);
        }

        return $warehouse;
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportBloodProducts(): iterable
    {
        BloodProductModel::ensureCatalog();
        $query = BloodProductModel::query()->orderBy('name');
        $this->guardCount($query);

        foreach ($query->get() as $product) {
            yield [
                'name' => $product->name,
                'code' => $product->code,
                'price' => $this->money($product->price),
                'is_active' => $this->yesNo($product->is_active),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importBloodProduct(array $row): string
    {
        BloodProductModel::ensureCatalog();
        $name = $this->required($row, 'name', 'اسم منتج الدم');
        $code = trim($row['code'] ?? '');
        $existing = $code !== '' ? BloodProductModel::query()->where('code', $code)->first() : null;

        if (! $existing instanceof BloodProductModel) {
            $existing = BloodProductModel::query()->where('name', $name)->first();
        }

        $attributes = [
            'name' => $name,
            'price' => $this->optionalNumber($row, 'price', $existing instanceof BloodProductModel ? $existing->price : null, 0),
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof BloodProductModel ? (bool) $existing->is_active : null, true),
        ];

        if ($code !== '') {
            $this->assertCode(BloodProductModel::query(), $code, $existing?->getKey(), 'رمز منتج الدم مستخدم: '.$code);
            $attributes['code'] = $code;
        } elseif ($existing === null) {
            $attributes['code'] = $this->nextCode(BloodProductModel::class, 'BLD-');
        }

        if ($existing === null) {
            BloodProductModel::storeCatalog($attributes);

            return 'created';
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportBloodRequests(): iterable
    {
        $query = BloodRequestModel::query()->with(['patient', 'product'])->where('hospital_id', $this->hospitalId())->orderBy('id');
        $this->guardCount($query);

        foreach ($query->get() as $request) {
            $status = $request->status;

            yield [
                'mrn' => $request->patient?->mrn,
                'national_id' => $request->patient?->national_id,
                'patient_name' => $request->patient?->full_name,
                'product_code' => $request->product?->code,
                'product_name' => $request->product?->name ?? $request->product_name,
                'blood_group' => $request->blood_group,
                'units' => $request->units,
                'status' => $status instanceof BloodRequestStatus ? $status->label() : (string) $status,
                'ordered_at' => $request->created_at?->format('Y-m-d'),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importBloodRequest(array $row): string
    {
        $patient = $this->patientFromRow($row);
        $code = trim($row['product_code'] ?? '');
        $name = trim($row['product_name'] ?? '');
        $product = $code !== '' ? BloodProductModel::query()->where('code', $code)->first() : null;

        if (! $product instanceof BloodProductModel && $name !== '') {
            $product = BloodProductModel::query()->where('name', $name)->first();
        }

        if (! $product instanceof BloodProductModel) {
            throw new InvalidArgumentException('منتج الدم غير موجود.');
        }

        $open = BloodRequestModel::query()
            ->where('patient_id', $patient->getKey())
            ->where('blood_product_id', $product->getKey())
            ->where('status', BloodRequestStatus::Requested->value)
            ->exists();

        if ($open) {
            return 'skipped';
        }

        $encounter = $this->openVisit($patient, [
            'type' => 'outpatient',
            'department_id' => $this->currentUser->departmentId(),
            'reason' => 'طلب دم من ملف إكسل',
        ]);

        $this->createBloodRequest->execute([
            'patient_id' => $patient->getKey(),
            'encounter_id' => $encounter->getKey(),
            'blood_product_id' => $product->getKey(),
            'blood_group' => $this->required($row, 'blood_group', 'فصيلة الدم'),
            'units' => (int) $this->required($row, 'units', 'الوحدات'),
        ]);

        return 'created';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportServices(): iterable
    {
        $query = ServiceModel::query()->orderBy('name');
        $this->guardCount($query);

        foreach ($query->get() as $service) {
            yield [
                'name' => $service->name,
                'code' => $service->code,
                'category' => $service->category,
                'price' => $this->money($service->price),
                'is_active' => $this->yesNo($service->is_active),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importService(array $row): string
    {
        $code = $this->required($row, 'code', 'رمز الخدمة');
        $existing = ServiceModel::query()->where('code', $code)->first();
        $this->assertCode(ServiceModel::query(), $code, $existing?->getKey(), 'رمز الخدمة مستخدم: '.$code);

        $attributes = [
            'name' => $this->required($row, 'name', 'اسم الخدمة'),
            'code' => $code,
            'category' => $this->nullable($row, 'category'),
            'price' => $this->optionalNumber($row, 'price', $existing instanceof ServiceModel ? $existing->price : null, 0),
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof ServiceModel ? (bool) $existing->is_active : null, true),
        ];

        if ($existing === null) {
            ServiceModel::query()->create($attributes);

            return 'created';
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportInvoices(): iterable
    {
        $query = InvoiceModel::query()->with('patient')->where('hospital_id', $this->hospitalId())->orderByDesc('id');
        $this->guardCount($query);

        foreach ($query->get() as $invoice) {
            yield [
                'number' => $invoice->invoice_number,
                'mrn' => $invoice->patient?->mrn,
                'patient_name' => $invoice->patient?->full_name,
                'total' => $this->money($invoice->total),
                'paid' => $this->money($invoice->paid_amount),
                'balance' => $this->money($invoice->balance),
                'status' => $invoice->status->label(),
                'issued_at' => $invoice->issued_at?->format('Y-m-d H:i'),
            ];
        }
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportOperationTypes(): iterable
    {
        $query = OperationTypeModel::query()->with('clinics')->orderBy('name');
        $this->guardCount($query);

        foreach ($query->get() as $type) {
            yield [
                'name' => $type->name,
                'code' => $type->code,
                'clinic' => $type->clinics->first()?->name,
                'specialty' => $type->specialty,
                'duration' => $type->estimated_duration_minutes,
                'risk' => $type->risk_level,
                'price' => $this->money($type->price),
                'is_active' => $this->yesNo($type->is_active),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importOperationType(array $row): string
    {
        $code = $this->required($row, 'code', 'رمز العملية');
        $clinic = $this->clinic($this->required($row, 'clinic', 'العيادة'));
        $existing = OperationTypeModel::query()->where('code', $code)->first();
        $this->assertCode(OperationTypeModel::query(), $code, $existing?->getKey(), 'رمز العملية مستخدم: '.$code);
        $risk = trim($row['risk'] ?? '');

        $attributes = [
            'name' => $this->required($row, 'name', 'اسم العملية'),
            'code' => $code,
            'specialty' => $this->nullable($row, 'specialty'),
            'risk_level' => $risk === '' ? ($existing instanceof OperationTypeModel ? $existing->risk_level : 'moderate') : $this->riskLevel($risk),
            'price' => $this->optionalNumber($row, 'price', $existing instanceof OperationTypeModel ? $existing->price : null, 0),
            'is_active' => $this->optionalBool($row, 'is_active', $existing instanceof OperationTypeModel ? (bool) $existing->is_active : null, true),
        ];
        $duration = $this->nullableNumber($row, 'duration');

        if ($duration !== null) {
            $attributes['estimated_duration_minutes'] = (int) $duration;
        }

        if ($existing === null) {
            $existing = OperationTypeModel::query()->create($attributes);
            $existing->clinics()->syncWithoutDetaching([$clinic->getKey()]);

            return 'created';
        }

        $existing->fill($attributes)->save();
        $existing->clinics()->syncWithoutDetaching([$clinic->getKey()]);

        return 'updated';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportOperations(): iterable
    {
        $query = OperationModel::query()->with(['patient', 'type'])->where('hospital_id', $this->hospitalId())->orderByDesc('id');
        $this->guardCount($query);

        foreach ($query->get() as $operation) {
            yield [
                'mrn' => $operation->patient?->mrn,
                'national_id' => $operation->patient?->national_id,
                'patient_name' => $operation->patient?->full_name,
                'type_code' => $operation->type?->code,
                'type_name' => $operation->type?->name,
                'scheduled_at' => $operation->scheduled_at?->format('Y-m-d H:i'),
                'notes' => $operation->pre_op_notes,
                'status' => $operation->status->label(),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importOperation(array $row): string
    {
        $patient = $this->patientFromRow($row);
        $type = $this->operationTypeFromRow($row);
        $when = $this->sheetMoment($row, 'scheduled_at');
        $exists = OperationModel::query()
            ->where('patient_id', $patient->getKey())
            ->where('operation_type_id', $type->getKey())
            ->where('scheduled_at', $when)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($exists) {
            return 'skipped';
        }

        $type->loadMissing('clinics');
        $clinic = $type->clinics->first();

        if ($clinic === null) {
            throw new InvalidArgumentException('نوع العملية غير مربوط بعيادة.');
        }

        $encounter = EncounterModel::query()->open()
            ->where('patient_id', $patient->getKey())
            ->where('clinic_id', $clinic->getKey())
            ->latest('id')
            ->first();

        if (! $encounter instanceof EncounterModel) {
            $encounter = $this->openVisit($patient, [
                'type' => 'operation',
                'department_id' => $clinic->department_id,
                'clinic_id' => $clinic->getKey(),
                'reason' => 'عملية من ملف إكسل',
                'allow_parallel' => true,
            ]);
        }

        $this->scheduleOperation->execute([
            'encounter_id' => $encounter->getKey(),
            'operation_type_id' => $type->getKey(),
            'scheduled_at' => $when->toDateTimeString(),
            'pre_op_notes' => $this->nullable($row, 'notes'),
        ]);

        return 'created';
    }

    /**
     * @param  array<string, string>  $row
     */
    private function operationTypeFromRow(array $row): OperationTypeModel
    {
        $code = trim($row['type_code'] ?? '');
        $name = trim($row['type_name'] ?? '');
        $type = $code !== '' ? OperationTypeModel::query()->where('code', $code)->first() : null;

        if (! $type instanceof OperationTypeModel && $name !== '') {
            $type = OperationTypeModel::query()->where('name', $name)->first();
        }

        if (! $type instanceof OperationTypeModel) {
            throw new InvalidArgumentException('نوع العملية غير موجود.');
        }

        return $type;
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportAdmissions(): iterable
    {
        $query = AdmissionModel::query()
            ->with(['patient', 'currentBedAssignment.bed.room.ward'])
            ->where('hospital_id', $this->hospitalId())
            ->orderByDesc('id');
        $this->guardCount($query);

        foreach ($query->get() as $admission) {
            $bed = $admission->currentBedAssignment?->bed;

            yield [
                'mrn' => $admission->patient?->mrn,
                'national_id' => $admission->patient?->national_id,
                'patient_name' => $admission->patient?->full_name,
                'ward' => $bed?->room?->ward?->name,
                'room_number' => $bed?->room?->room_number,
                'bed_number' => $bed?->bed_number,
                'reason' => $admission->admission_reason,
                'admitted_at' => $admission->admitted_at?->format('Y-m-d H:i'),
                'status' => $admission->status->label(),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importAdmission(array $row): string
    {
        $patient = $this->patientFromRow($row);

        if (AdmissionModel::query()->active()->where('patient_id', $patient->getKey())->exists()) {
            return 'skipped';
        }

        $ward = $this->ward($this->required($row, 'ward', 'الجناح'));
        $roomNumber = trim($row['room_number'] ?? '');
        $bedNumber = trim($row['bed_number'] ?? '');
        $data = [
            'department_id' => $ward->department_id,
            'admission_reason' => $this->nullable($row, 'reason'),
            'admitted_at' => trim($row['admitted_at'] ?? '') === '' ? now() : $this->sheetMoment($row, 'admitted_at'),
        ];

        if ($roomNumber !== '' || $bedNumber !== '') {
            if ($roomNumber === '' || $bedNumber === '') {
                throw new InvalidArgumentException('اكتب رقم الغرفة ورقم السرير معاً، أو اتركهما فارغين.');
            }

            $bed = BedModel::query()
                ->where('bed_number', $bedNumber)
                ->whereHas('room', fn ($room) => $room->where('ward_id', $ward->getKey())->where('room_number', $roomNumber))
                ->first();

            if (! $bed instanceof BedModel) {
                throw new InvalidArgumentException('السرير غير موجود في هذا الجناح.');
            }

            $data['bed_id'] = $bed->getKey();
        }

        $this->admitPatient->execute($patient, $data);

        return 'created';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportAppointments(): iterable
    {
        $query = AppointmentModel::query()->with(['patient', 'clinic', 'doctor.staff'])->where('hospital_id', $this->hospitalId())->orderByDesc('scheduled_at');
        $this->guardCount($query);

        foreach ($query->get() as $appointment) {
            $staff = $appointment->doctor?->staff;

            yield [
                'mrn' => $appointment->patient?->mrn,
                'national_id' => $appointment->patient?->national_id,
                'patient_name' => $appointment->patient?->full_name,
                'clinic' => $appointment->clinic?->name,
                'doctor' => $staff === null ? '' : trim($staff->first_name.' '.$staff->last_name),
                'scheduled_at' => $appointment->scheduled_at?->format('Y-m-d H:i'),
                'duration' => $appointment->duration_minutes,
                'reason' => $appointment->reason,
                'notes' => $appointment->notes,
                'status' => $appointment->status->label(),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importAppointment(array $row): string
    {
        $patient = $this->patientFromRow($row);
        $when = $this->sheetMoment($row, 'scheduled_at');
        $clinicLabel = trim($row['clinic'] ?? '');
        $clinic = $clinicLabel === '' ? null : $this->clinic($clinicLabel);
        $doctor = $this->doctorFromRow($row);
        $existing = AppointmentModel::query()
            ->where('hospital_id', $this->hospitalId())
            ->where('patient_id', $patient->getKey())
            ->where('scheduled_at', $when)
            ->when($clinic !== null, fn ($query) => $query->where('clinic_id', $clinic->getKey()))
            ->first();

        if ($existing instanceof AppointmentModel) {
            $existing->fill([
                'duration_minutes' => (int) ($this->nullableNumber($row, 'duration') ?? $existing->duration_minutes),
                'reason' => $this->nullable($row, 'reason') ?? $existing->reason,
                'notes' => $this->nullable($row, 'notes') ?? $existing->notes,
            ])->save();

            return $existing->wasChanged() ? 'updated' : 'skipped';
        }

        $this->createAppointment->execute([
            'patient_id' => $patient->getKey(),
            'department_id' => $clinic?->department_id,
            'clinic_id' => $clinic?->getKey(),
            'doctor_id' => $doctor?->getKey(),
            'scheduled_at' => $when->toDateTimeString(),
            'duration_minutes' => (int) ($this->nullableNumber($row, 'duration') ?? 15),
            'reason' => $this->nullable($row, 'reason'),
            'notes' => $this->nullable($row, 'notes'),
        ]);

        return 'created';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportEncounters(): iterable
    {
        $query = EncounterModel::query()->with(['patient', 'department', 'clinic', 'doctor.staff'])->where('hospital_id', $this->hospitalId())->orderByDesc('id');
        $this->guardCount($query);

        foreach ($query->get() as $encounter) {
            $staff = $encounter->doctor?->staff;

            yield [
                'mrn' => $encounter->patient?->mrn,
                'national_id' => $encounter->patient?->national_id,
                'patient_name' => $encounter->patient?->full_name,
                'department' => $encounter->department?->name,
                'clinic' => $encounter->clinic?->name,
                'doctor' => $staff === null ? '' : trim($staff->first_name.' '.$staff->last_name),
                'type' => $this->visitLabel((string) $encounter->type->value),
                'reason' => $encounter->reason,
                'status' => (string) $encounter->status->value,
                'opened_at' => $encounter->created_at?->format('Y-m-d H:i'),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importEncounter(array $row): string
    {
        $patient = $this->patientFromRow($row);
        $department = trim($row['department'] ?? '') === '' ? null : $this->department($row['department']);
        $clinic = trim($row['clinic'] ?? '') === '' ? null : $this->clinic($row['clinic'], $department?->getKey());
        $departmentId = $department?->getKey() ?? $clinic?->department_id;
        $open = EncounterModel::query()->open()
            ->where('patient_id', $patient->getKey())
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->exists();

        if ($open) {
            return 'skipped';
        }

        $this->createEncounter->execute($patient, [
            'type' => $this->visitType($row['type'] ?? ''),
            'department_id' => $departmentId ?? $this->currentUser->departmentId(),
            'clinic_id' => $clinic?->getKey(),
            'doctor_id' => $this->doctorFromRow($row)?->getKey(),
            'reason' => $this->nullable($row, 'reason') ?? 'زيارة من ملف إكسل',
        ]);

        return 'created';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportPrescriptions(): iterable
    {
        $query = PrescriptionModel::query()
            ->with(['encounter.patient', 'items.medication'])
            ->where('hospital_id', $this->hospitalId())
            ->orderByDesc('id');
        $this->guardCount($query);

        foreach ($query->get() as $prescription) {
            $patient = $prescription->encounter?->patient;

            foreach ($prescription->items as $item) {
                yield [
                    'mrn' => $patient?->mrn,
                    'national_id' => $patient?->national_id,
                    'patient_name' => $patient?->full_name,
                    'medication' => $item->medication?->name,
                    'barcode' => $item->medication?->barcode,
                    'dose' => $item->dose,
                    'frequency' => $item->frequency,
                    'duration' => $item->duration,
                    'quantity' => $item->quantity,
                    'notes' => $prescription->notes,
                    'status' => $prescription->status->label(),
                    'prescribed_at' => $prescription->prescribed_at?->format('Y-m-d'),
                ];
            }
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importPrescription(array $row): string
    {
        $patient = $this->patientFromRow($row);
        $barcode = trim($row['barcode'] ?? '');
        $name = trim($row['medication'] ?? '');
        $medication = $barcode !== '' ? \App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel::query()->where('barcode', $barcode)->first() : null;

        if ($medication === null && $name !== '') {
            $medication = \App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel::query()->where('name', $name)->first();
        }

        if ($medication === null) {
            throw new InvalidArgumentException('الدواء غير موجود في الدليل.');
        }

        $day = $this->sheetDay($row, 'prescribed_at');
        $already = PrescriptionItemModel::query()
            ->where('medication_id', $medication->getKey())
            ->whereHas('prescription', function ($prescription) use ($patient, $day): void {
                $prescription->whereDate('prescribed_at', $day)
                    ->where('status', '!=', 'cancelled')
                    ->whereHas('encounter', fn ($encounter) => $encounter->where('patient_id', $patient->getKey()));
            })
            ->exists();

        if ($already) {
            return 'skipped';
        }

        $encounter = EncounterModel::query()->open()->where('patient_id', $patient->getKey())->latest('id')->first();

        if (! $encounter instanceof EncounterModel) {
            $encounter = $this->openVisit($patient, [
                'type' => 'outpatient',
                'department_id' => $this->currentUser->departmentId(),
                'reason' => 'وصفة من ملف إكسل',
            ]);
        }

        $this->createPrescription->execute([
            'encounter_id' => $encounter->getKey(),
            'notes' => $this->nullable($row, 'notes'),
            'items' => [[
                'medication_id' => $medication->getKey(),
                'dose' => $this->nullable($row, 'dose'),
                'frequency' => $this->nullable($row, 'frequency'),
                'duration' => $this->nullable($row, 'duration'),
                'quantity' => $this->nullableNumber($row, 'quantity'),
            ]],
        ]);

        return 'created';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportPurchases(): iterable
    {
        $query = PharmacyPurchaseModel::query()->with('supplier')->where('hospital_id', $this->hospitalId())->orderByDesc('id');
        $this->guardCount($query);

        foreach ($query->get() as $purchase) {
            yield [
                'supplier' => $purchase->supplier?->name,
                'phone' => $purchase->supplier?->phone,
                'invoice_number' => $purchase->invoice_number,
                'total' => $this->money($purchase->total),
                'cash' => $this->money($purchase->cash_amount),
                'due' => $this->money($purchase->due_amount),
                'received_at' => $purchase->received_at?->format('Y-m-d'),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importPurchase(array $row): string
    {
        $number = $this->required($row, 'invoice_number', 'رقم الفاتورة');

        if (PharmacyPurchaseModel::query()->where('hospital_id', $this->hospitalId())->where('invoice_number', $number)->exists()) {
            return 'skipped';
        }

        $received = trim($row['received_at'] ?? '');
        $this->recordPurchase->store(
            $this->required($row, 'supplier', 'المورد'),
            $this->nullable($row, 'phone'),
            null,
            $number,
            (float) str_replace(',', '', $this->required($row, 'total', 'الإجمالي')),
            (float) str_replace(',', '', trim($row['cash'] ?? '') === '' ? '0' : $row['cash']),
            $received === '' ? null : $this->dateValue(['day' => $received], 'day'),
        );

        return 'created';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportDebts(): iterable
    {
        $query = PharmacySaleModel::query()->with('customer')->where('hospital_id', $this->hospitalId())->where('due_amount', '>', 0)->orderByDesc('id');
        $this->guardCount($query);

        foreach ($query->get() as $sale) {
            yield [
                'customer' => $sale->customer?->name,
                'phone' => $sale->customer?->phone,
                'sale_number' => $sale->sale_number,
                'total' => $this->money($sale->total),
                'due' => $this->money($sale->due_amount),
                'sold_at' => $sale->sold_at?->format('Y-m-d'),
            ];
        }
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportSettings(): iterable
    {
        $query = SettingModel::query()
            ->where(fn ($inner) => $inner->whereNull('hospital_id')->orWhere('hospital_id', $this->hospitalId()))
            ->orderBy('group')
            ->orderBy('key');
        $this->guardCount($query);

        foreach ($query->get() as $setting) {
            $value = $setting->value;

            yield [
                'group' => $setting->group,
                'key' => $setting->key,
                'value' => is_array($value) ? (string) json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value,
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importSetting(array $row): string
    {
        $group = trim($row['group'] ?? '') === '' ? 'general' : trim($row['group']);
        $key = $this->required($row, 'key', 'المفتاح');
        $value = $this->settingValue($this->required($row, 'value', 'القيمة'));
        $current = $this->settings->get($group, $key, null, $this->hospitalId());

        if ($current == $value) {
            return 'skipped';
        }

        $this->settings->set($group, $key, $value, $this->hospitalId(), get_debug_type($value));

        return $current === null ? 'created' : 'updated';
    }

    private function settingValue(string $raw): mixed
    {
        $trim = trim($raw);

        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $decoded = json_decode($trim, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $raw;
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportUsers(): iterable
    {
        $query = UserModel::query()->orderBy('username');
        $this->guardCount($query);

        foreach ($query->get() as $user) {
            yield [
                'username' => $user->username,
                'email' => $user->email,
                'password' => '',
                'is_active' => $this->yesNo($user->is_active),
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importUser(array $row): string
    {
        $username = $this->required($row, 'username', 'اسم المستخدم');
        $existing = UserModel::query()->where('username', $username)->first();
        $password = trim($row['password'] ?? '');

        if ($existing instanceof UserModel) {
            if ($password !== '') {
                if (mb_strlen($password) < 6) {
                    throw new InvalidArgumentException('كلمة المرور يجب أن تكون 6 أحرف على الأقل.');
                }

                $existing->password = $password;
            }

            $email = trim($row['email'] ?? '');

            if ($email !== '') {
                $existing->email = $email;
            }

            $existing->is_active = $this->optionalBool($row, 'is_active', (bool) $existing->is_active, true);
            $existing->save();

            return $existing->wasChanged() ? 'updated' : 'skipped';
        }

        if (mb_strlen($password) < 6) {
            throw new InvalidArgumentException('كلمة المرور مطلوبة عند إنشاء مستخدم جديد، و6 أحرف على الأقل.');
        }

        $this->createUser->execute(UserData::fromArray([
            'username' => $username,
            'email' => $this->nullable($row, 'email'),
            'password' => $password,
            'is_active' => $this->optionalBool($row, 'is_active', null, true),
        ]));

        return 'created';
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportNotifications(): iterable
    {
        $query = NotificationModel::query()->where('user_id', $this->currentUser->id())->orderByDesc('id');
        $this->guardCount($query);

        foreach ($query->get() as $notification) {
            yield [
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'created_at' => $notification->created_at?->format('Y-m-d H:i'),
            ];
        }
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportAudit(): iterable
    {
        $query = AuditLogModel::query()->with('user')->orderByDesc('id');
        $this->guardCount($query);

        foreach ($query->get() as $log) {
            yield [
                'action' => $log->action,
                'user' => $log->user?->username,
                'entity' => trim((string) $log->entity_type.' '.(string) $log->entity_id),
                'created_at' => $log->created_at?->format('Y-m-d H:i'),
            ];
        }
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportNursing(): iterable
    {
        $query = MedicationAdministrationModel::query()
            ->with(['patient', 'medication', 'prescriptionItem'])
            ->where('hospital_id', $this->hospitalId())
            ->orderBy('scheduled_at');
        $this->guardCount($query);

        foreach ($query->get() as $dose) {
            yield [
                'mrn' => $dose->patient?->mrn,
                'patient_name' => $dose->patient?->full_name,
                'medication' => $dose->medication?->name,
                'dose' => $dose->prescriptionItem?->dose,
                'scheduled_at' => $dose->scheduled_at?->format('Y-m-d H:i'),
                'status' => $dose->status->label(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function openVisit(PatientModel $patient, array $data): EncounterModel
    {
        $open = EncounterModel::query()->open()->where('patient_id', $patient->getKey())->latest('id')->first();

        if ($open instanceof EncounterModel && ($data['allow_parallel'] ?? false) !== true) {
            return $open;
        }

        try {
            return $this->createEncounter->execute($patient, $data);
        } catch (DomainException $exception) {
            $id = (int) ($exception->context()['encounter_id'] ?? 0);
            $found = $id > 0 ? EncounterModel::query()->find($id) : null;

            if ($found instanceof EncounterModel) {
                return $found;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function doctorFromRow(array $row): ?DoctorModel
    {
        $label = trim($row['doctor'] ?? '');

        if ($label === '') {
            return null;
        }

        $staff = StaffModel::query()->where('hospital_id', $this->hospitalId())->where('employee_number', $label)->first();

        if (! $staff instanceof StaffModel && str_contains($label, ' ')) {
            [$first, $last] = explode(' ', $label, 2);
            $staff = StaffModel::query()
                ->where('hospital_id', $this->hospitalId())
                ->where('first_name', $first)
                ->where('last_name', $last)
                ->first();
        }

        if (! $staff instanceof StaffModel) {
            throw new InvalidArgumentException('الطبيب غير موجود: '.$label);
        }

        $doctor = DoctorModel::query()->where('staff_id', $staff->getKey())->first();

        if (! $doctor instanceof DoctorModel) {
            throw new InvalidArgumentException('هذا الموظف ليس طبيباً: '.$label);
        }

        return $doctor;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function sheetMoment(array $row, string $key): Carbon
    {
        $raw = trim($row[$key] ?? '');

        if ($raw === '') {
            throw new InvalidArgumentException('التاريخ مطلوب.');
        }

        if (is_numeric($raw)) {
            return Carbon::create(1899, 12, 30)->addSeconds((int) round(((float) $raw) * 86400));
        }

        $parts = preg_split('/\s+/', str_replace('T', ' ', $raw)) ?: [];
        $day = $this->dateValue(['day' => str_replace('.', '/', (string) ($parts[0] ?? ''))], 'day');
        $time = (string) ($parts[1] ?? '09:00');

        if (! preg_match('/^\d{1,2}:\d{2}/', $time)) {
            $time = '09:00';
        }

        return Carbon::parse($day.' '.$time);
    }

    private function departmentKind(string $label): string
    {
        $label = trim($label);

        foreach (DepartmentType::cases() as $case) {
            if ($label === $case->value || $label === $case->label()) {
                return $case->value;
            }
        }

        throw new InvalidArgumentException('نوع القسم غير معروف: '.$label);
    }

    private function itemKind(string $label): string
    {
        $label = trim($label);

        foreach (ItemType::cases() as $case) {
            if ($label === $case->value || $label === $case->label()) {
                return $case->value;
            }
        }

        throw new InvalidArgumentException('نوع الصنف غير معروف: '.$label);
    }

    private function riskLevel(string $label): string
    {
        return match (mb_strtolower(trim($label))) {
            'low', 'منخفض' => 'low',
            'moderate', 'medium', 'متوسط' => 'moderate',
            'high', 'عالي', 'عالٍ' => 'high',
            'critical', 'حرج' => 'critical',
            default => throw new InvalidArgumentException('درجة الخطورة غير معروفة: '.$label),
        };
    }

    private function visitType(string $label): string
    {
        return match (mb_strtolower(trim($label))) {
            '', 'خارجي', 'outpatient' => 'outpatient',
            'طوارئ', 'emergency' => 'emergency',
            'داخلي', 'inpatient' => 'inpatient',
            'عملية', 'operation' => 'operation',
            'متابعة', 'follow_up', 'follow-up' => 'follow_up',
            default => throw new InvalidArgumentException('نوع الزيارة غير معروف: '.$label),
        };
    }

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    private function exportAssets(): iterable
    {
        $query = ItAssetModel::query()
            ->with('department')
            ->where('hospital_id', $this->hospitalId())
            ->where('is_active', true)
            ->orderBy('asset_code');
        $this->guardCount($query);

        foreach ($query->get() as $asset) {
            $category = $asset->category;
            $status = $asset->status;

            yield [
                'asset_code' => $asset->asset_code,
                'barcode' => $asset->barcode,
                'category' => $category instanceof AssetCategory ? $category->label() : (string) $category,
                'brand' => $asset->brand,
                'model_name' => $asset->model_name,
                'received_by' => $asset->received_by,
                'department' => $asset->department?->name,
                'location' => $asset->location,
                'delivered_on' => $asset->delivered_on?->toDateString(),
                'notes' => $asset->notes,
                'status' => $status instanceof AssetStatus ? $status->label() : (string) $status,
            ];
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importAsset(array $row): string
    {
        $hospitalId = $this->hospitalId();
        $code = $this->nullable($row, 'asset_code');
        $barcode = $this->nullable($row, 'barcode');
        $existing = null;

        if ($code !== null) {
            $existing = ItAssetModel::query()->where('hospital_id', $hospitalId)->where('asset_code', $code)->first();
        }

        if (! $existing instanceof ItAssetModel && $barcode !== null) {
            $existing = ItAssetModel::query()->where('hospital_id', $hospitalId)->where('barcode', $barcode)->first();
        }

        if ($code !== null) {
            $clash = ItAssetModel::query()->where('hospital_id', $hospitalId)->where('asset_code', $code);

            if ($existing instanceof ItAssetModel) {
                $clash->whereKeyNot($existing->getKey());
            }

            if ($clash->exists()) {
                throw new InvalidArgumentException('رقم الأصل مستخدم: '.$code);
            }
        }

        $deliveredOn = $this->dateValue($row, 'delivered_on');

        if ($deliveredOn === null) {
            throw new InvalidArgumentException('تاريخ التسليم مطلوب.');
        }

        $departmentLabel = $this->nullable($row, 'department');
        $departmentId = $departmentLabel === null ? null : (int) $this->department($departmentLabel)->getKey();
        $location = $this->nullable($row, 'location');
        $holder = $this->nullable($row, 'received_by');
        $category = $this->assetCategory($this->required($row, 'category', 'النوع'));

        if ($existing instanceof ItAssetModel && $existing->status !== AssetStatus::InStock) {
            $departmentChanged = (int) ($departmentId ?? 0) !== (int) ($existing->department_id ?? 0);
            $locationChanged = (string) ($location ?? '') !== (string) ($existing->location ?? '');

            if ($departmentChanged || $locationChanged) {
                throw new InvalidArgumentException('غيّر القسم أو المكان من إجراء التسليم أو الإرجاع.');
            }

            $departmentId = $existing->department_id === null ? null : (int) $existing->department_id;
            $location = $existing->location;
        }

        $attributes = [
            'category' => $category->value,
            'brand' => $this->nullable($row, 'brand'),
            'model_name' => $this->nullable($row, 'model_name'),
            'department_id' => $departmentId,
            'location' => $location,
            'received_by' => $holder,
            'delivered_on' => $deliveredOn,
            'notes' => $this->nullable($row, 'notes'),
        ];

        if (! $existing instanceof ItAssetModel) {
            app(RegisterAssetAction::class)->execute([
                ...$attributes,
                'hospital_id' => $hospitalId,
                'asset_code' => $code,
            ], $this->currentUser->id());

            return 'created';
        }

        if ($code !== null) {
            $attributes['asset_code'] = $code;
        }

        if (in_array($existing->status, [AssetStatus::InStock, AssetStatus::Assigned], true)) {
            $attributes['status'] = $holder === null ? AssetStatus::InStock : AssetStatus::Assigned;
        }

        $existing->fill($attributes)->save();

        return 'updated';
    }

    private function assetCategory(string $label): AssetCategory
    {
        $label = trim($label);

        foreach (AssetCategory::cases() as $case) {
            if ($label === $case->value || $label === $case->label()) {
                return $case;
            }
        }

        throw new InvalidArgumentException('نوع الأصل غير معروف: '.$label);
    }

    private function visitLabel(string $value): string
    {
        return match ($value) {
            'emergency' => 'طوارئ',
            'inpatient' => 'داخلي',
            'operation' => 'عملية',
            'follow_up' => 'متابعة',
            default => 'خارجي',
        };
    }
}
