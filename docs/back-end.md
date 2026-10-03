أكيد. بما أن المشروع **HMS كبير**، فأنا أنصحك ألا تبني Laravel بالهيكل التقليدي الذي يضع كل الـControllers والـModels لكل النظام في مجلدات مشتركة. الأفضل استخدام **Modular Architecture / Domain Modules** بحيث كل قسم من النظام يكون Module شبه مستقل.

وبما أنك ستستخدم **Next.js كواجهة منفصلة**، فالـLaravel يكون API Backend فقط.

مسار المريض المنفّذ (من فتح الأضبارة حتى الخروج) وربطه بنقاط الـ API موجود في [patient-journey.md](patient-journey.md).

# Laravel Modular Architecture

الهيكل المقترح:

```text
hospital-backend/
│
├── app/
│   ├── Core/
│   │   ├── Auth/
│   │   ├── Authorization/
│   │   ├── Database/
│   │   ├── Exceptions/
│   │   ├── Http/
│   │   ├── Logging/
│   │   ├── Notifications/
│   │   ├── Storage/
│   │   └── Support/
│   │
│   └── Modules/
│
├── bootstrap/
│
├── config/
│
├── database/
│   ├── factories/
│   ├── seeders/
│   └── migrations/
│
├── routes/
│   └── api.php
│
├── storage/
│
├── tests/
│
├── composer.json
└── .env
```

لكن الأهم هو ما داخل:

```text
app/Modules/
```

---

# 1. Modules

أقترح تقسيم النظام بهذا الشكل:

```text
app/
└── Modules/
    │
    ├── Identity/
    ├── Patients/
    ├── Appointments/
    ├── Encounters/
    ├── Departments/
    ├── Clinical/
    ├── Laboratory/
    ├── Radiology/
    ├── Pharmacy/
    ├── Inventory/
    ├── Inpatient/
    ├── Operations/
    ├── Nursing/
    ├── Billing/
    ├── Documents/
    ├── Notifications/
    ├── Reports/
    ├── Audit/
    └── Settings/
```

كل Module يعتبر **Bounded Context** خاص به.

---

# 2. الشكل الداخلي لكل Module

مثلاً:

```text
app/Modules/Laboratory/
│
├── Domain/
│   ├── Entities/
│   ├── Enums/
│   ├── Events/
│   ├── Exceptions/
│   └── ValueObjects/
│
├── Application/
│   ├── Actions/
│   ├── DTOs/
│   ├── Services/
│   └── Queries/
│
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Models/
│   │   ├── Repositories/
│   │   └── Migrations/
│   │
│   └── Services/
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
├── Providers/
│   └── LaboratoryServiceProvider.php
│
└── Tests/
    ├── Feature/
    └── Unit/
```

وهذا الهيكل أنصح أن يكون **القاعدة لكل Module**.

---

# 3. مثال Patient Module

```text
app/Modules/Patients/
│
├── Domain/
│   ├── Entities/
│   │   └── Patient.php
│   │
│   ├── Enums/
│   │   ├── Gender.php
│   │   └── PatientStatus.php
│   │
│   ├── Events/
│   │   └── PatientCreated.php
│   │
│   └── Exceptions/
│       └── PatientNotFoundException.php
│
├── Application/
│   ├── Actions/
│   │   ├── CreatePatientAction.php
│   │   ├── UpdatePatientAction.php
│   │   └── RegisterPatientAction.php
│   │
│   ├── DTOs/
│   │   └── PatientData.php
│   │
│   └── Queries/
│       ├── GetPatientQuery.php
│       └── SearchPatientsQuery.php
│
├── Infrastructure/
│   └── Persistence/
│       ├── Models/
│       │   ├── PatientModel.php
│       │   ├── PatientAllergyModel.php
│       │   └── PatientConditionModel.php
│       │
│       └── Repositories/
│           └── PatientRepository.php
│
├── Presentation/
│   ├── Controllers/
│   │   └── PatientController.php
│   │
│   ├── Requests/
│   │   ├── CreatePatientRequest.php
│   │   └── UpdatePatientRequest.php
│   │
│   ├── Resources/
│   │   └── PatientResource.php
│   │
│   └── Routes/
│       └── api.php
│
├── Providers/
│   └── PatientsServiceProvider.php
│
└── Tests/
    ├── Feature/
    └── Unit/
```

---

# 4. لماذا Domain / Application / Infrastructure / Presentation؟

لأننا لا نريد أن يصبح المشروع بعد سنة:

```text
Controllers/
    200 files

Models/
    150 files

Services/
    100 files
```

ولا تعرف أي ملف تابع لأي نظام.

بدلاً من ذلك:

```text
Patients/
    كل شيء متعلق بالمرضى

Laboratory/
    كل شيء متعلق بالمخبر

Radiology/
    كل شيء متعلق بالأشعة
```

---

# 5. Identity Module

مسؤول عن هوية المستخدمين وليس المرضى.

```text
Identity/
│
├── Domain/
│   ├── Entities/
│   │   └── User.php
│   │
│   ├── Enums/
│   │   └── UserStatus.php
│   │
│   └── Events/
│       ├── UserCreated.php
│       └── UserLoggedIn.php
│
├── Application/
│   ├── Actions/
│   │   ├── CreateUserAction.php
│   │   ├── DisableUserAction.php
│   │   └── ResetPasswordAction.php
│   │
│   └── DTOs/
│
├── Infrastructure/
│   └── Persistence/
│       ├── Models/
│       │   └── UserModel.php
│       └── Repositories/
│
├── Presentation/
│   ├── Controllers/
│   │   └── AuthController.php
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 6. Departments Module

هذا يمثل أقسام المشفى.

```text
Departments/
│
├── Domain/
│   ├── Entities/
│   │   └── Department.php
│   ├── Enums/
│   │   └── DepartmentType.php
│   └── Events/
│
├── Application/
│   ├── Actions/
│   │   ├── CreateDepartmentAction.php
│   │   └── UpdateDepartmentAction.php
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│       ├── Models/
│       └── Repositories/
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

مثلاً:

```text
Dental
Laboratory
Radiology
Pharmacy
Warehouse
Surgery
Emergency
Internal Medicine
```

كلها Data وليس Modules منفصلة.

---

# 7. Encounters Module

هذا أهم Module بعد Patients.

```text
Encounters/
│
├── Domain/
│   ├── Entities/
│   │   └── Encounter.php
│   │
│   ├── Enums/
│   │   ├── EncounterType.php
│   │   └── EncounterStatus.php
│   │
│   └── Events/
│       ├── EncounterCreated.php
│       ├── EncounterStarted.php
│       └── EncounterCompleted.php
│
├── Application/
│   ├── Actions/
│   │   ├── CreateEncounterAction.php
│   │   ├── StartEncounterAction.php
│   │   ├── TransferEncounterAction.php
│   │   └── CloseEncounterAction.php
│   │
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│       ├── Models/
│       │   └── EncounterModel.php
│       └── Repositories/
│
├── Presentation/
│   ├── Controllers/
│   │   └── EncounterController.php
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 8. Clinical Module

لا أنصح بوضع التشخيص والوصفات والعلامات الحيوية ضمن Patient Module.

لأنها مرتبطة بالزيارة الطبية.

```text
Clinical/
│
├── Domain/
│   ├── Entities/
│   │   ├── Diagnosis.php
│   │   ├── ClinicalNote.php
│   │   └── VitalSigns.php
│   │
│   ├── Enums/
│   │   ├── DiagnosisType.php
│   │   └── NoteType.php
│   │
│   └── Events/
│
├── Application/
│   ├── Actions/
│   │   ├── AddDiagnosisAction.php
│   │   ├── AddClinicalNoteAction.php
│   │   └── RecordVitalSignsAction.php
│   │
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 9. Laboratory Module

```text
Laboratory/
│
├── Domain/
│   ├── Entities/
│   │   ├── LabOrder.php
│   │   ├── LabTest.php
│   │   ├── LabSample.php
│   │   └── LabResult.php
│   │
│   ├── Enums/
│   │   ├── LabOrderStatus.php
│   │   ├── SampleStatus.php
│   │   └── ResultStatus.php
│   │
│   ├── Events/
│   │   ├── LabOrderCreated.php
│   │   ├── SampleCollected.php
│   │   └── LabResultVerified.php
│   │
│   └── Exceptions/
│
├── Application/
│   ├── Actions/
│   │   ├── CreateLabOrderAction.php
│   │   ├── CollectSampleAction.php
│   │   ├── EnterLabResultAction.php
│   │   ├── VerifyLabResultAction.php
│   │   └── CancelLabOrderAction.php
│   │
│   ├── DTOs/
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│       ├── Models/
│       │   ├── LabOrderModel.php
│       │   ├── LabTestModel.php
│       │   ├── LabSampleModel.php
│       │   └── LabResultModel.php
│       │
│       └── Repositories/
│
├── Presentation/
│   ├── Controllers/
│   │   ├── LabOrderController.php
│   │   ├── LabSampleController.php
│   │   └── LabResultController.php
│   │
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│       └── api.php
│
├── Providers/
│   └── LaboratoryServiceProvider.php
│
└── Tests/
```

---

# 10. Radiology Module

```text
Radiology/
│
├── Domain/
│   ├── Entities/
│   │   ├── RadiologyOrder.php
│   │   ├── RadiologyStudy.php
│   │   └── RadiologyReport.php
│   │
│   ├── Enums/
│   │   ├── RadiologyStatus.php
│   │   └── StudyType.php
│   │
│   └── Events/
│       ├── RadiologyOrderCreated.php
│       ├── ImagesUploaded.php
│       └── ReportCompleted.php
│
├── Application/
│   ├── Actions/
│   │   ├── CreateRadiologyOrderAction.php
│   │   ├── StartStudyAction.php
│   │   ├── UploadRadiologyImageAction.php
│   │   └── CompleteRadiologyReportAction.php
│   │
│   └── Queries/
│
├── Infrastructure/
│   ├── Persistence/
│   └── Services/
│       └── DicomService.php
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 11. Documents Module

هذا مهم لأن كل الأقسام سترفع ملفات.

لا تجعل كل Module يخترع نظام Files خاص به.

اعمل Module مركزي:

```text
Documents/
│
├── Domain/
│   ├── Entities/
│   │   └── Document.php
│   │
│   ├── Enums/
│   │   └── DocumentType.php
│   │
│   └── Events/
│
├── Application/
│   ├── Actions/
│   │   ├── UploadDocumentAction.php
│   │   ├── DeleteDocumentAction.php
│   │   └── DownloadDocumentAction.php
│   │
│   └── Queries/
│
├── Infrastructure/
│   ├── Persistence/
│   └── Storage/
│       ├── LocalStorageService.php
│       └── ObjectStorageService.php
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

وبالتالي:

```text
Radiology
     ↓
Documents Module
     ↓
Storage
```

---

# 12. Pharmacy Module

```text
Pharmacy/
│
├── Domain/
│   ├── Entities/
│   │   ├── Prescription.php
│   │   ├── PrescriptionItem.php
│   │   └── Dispense.php
│   │
│   ├── Enums/
│   │   ├── PrescriptionStatus.php
│   │   └── DispenseStatus.php
│   │
│   └── Events/
│       ├── PrescriptionCreated.php
│       └── MedicationDispensed.php
│
├── Application/
│   ├── Actions/
│   │   ├── CreatePrescriptionAction.php
│   │   ├── DispenseMedicationAction.php
│   │   └── CancelPrescriptionAction.php
│   │
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 13. Inventory Module

وهذا يكون مسؤولاً عن المستودعات والمخزون.

```text
Inventory/
│
├── Domain/
│   ├── Entities/
│   │   ├── Item.php
│   │   ├── Batch.php
│   │   ├── Warehouse.php
│   │   ├── Stock.php
│   │   └── StockMovement.php
│   │
│   ├── Enums/
│   │   ├── MovementType.php
│   │   └── StockStatus.php
│   │
│   └── Events/
│       ├── StockReceived.php
│       ├── StockIssued.php
│       └── LowStockDetected.php
│
├── Application/
│   ├── Actions/
│   │   ├── ReceiveStockAction.php
│   │   ├── IssueStockAction.php
│   │   ├── TransferStockAction.php
│   │   └── AdjustStockAction.php
│   │
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 14. Billing Module

```text
Billing/
│
├── Domain/
│   ├── Entities/
│   │   ├── Invoice.php
│   │   ├── InvoiceItem.php
│   │   ├── Payment.php
│   │   └── Charge.php
│   │
│   ├── Enums/
│   │   ├── InvoiceStatus.php
│   │   └── PaymentMethod.php
│   │
│   └── Events/
│       ├── ChargeCreated.php
│       ├── InvoiceCreated.php
│       └── PaymentReceived.php
│
├── Application/
│   ├── Actions/
│   │   ├── CreateChargeAction.php
│   │   ├── CreateInvoiceAction.php
│   │   ├── ReceivePaymentAction.php
│   │   └── ApplyDiscountAction.php
│   │
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 15. Inpatient Module

```text
Inpatient/
│
├── Domain/
│   ├── Entities/
│   │   ├── Admission.php
│   │   ├── Ward.php
│   │   ├── Room.php
│   │   └── Bed.php
│   │
│   ├── Enums/
│   │   ├── AdmissionStatus.php
│   │   └── BedStatus.php
│   │
│   └── Events/
│       ├── PatientAdmitted.php
│       ├── BedAssigned.php
│       └── PatientDischarged.php
│
├── Application/
│   ├── Actions/
│   │   ├── AdmitPatientAction.php
│   │   ├── AssignBedAction.php
│   │   ├── TransferPatientAction.php
│   │   └── DischargePatientAction.php
│   │
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 16. Operations Module

```text
Operations/
│
├── Domain/
│   ├── Entities/
│   │   ├── Operation.php
│   │   ├── OperationTeam.php
│   │   └── OperationNote.php
│   │
│   ├── Enums/
│   │   └── OperationStatus.php
│   │
│   └── Events/
│
├── Application/
│   ├── Actions/
│   │   ├── ScheduleOperationAction.php
│   │   ├── StartOperationAction.php
│   │   └── CompleteOperationAction.php
│   │
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 17. Nursing Module

```text
Nursing/
│
├── Domain/
│   ├── Entities/
│   │   ├── NursingNote.php
│   │   ├── CarePlan.php
│   │   └── MedicationAdministration.php
│   │
│   ├── Enums/
│   └── Events/
│
├── Application/
│   ├── Actions/
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 18. Appointments

```text
Appointments/
│
├── Domain/
│   ├── Entities/
│   │   └── Appointment.php
│   │
│   ├── Enums/
│   │   └── AppointmentStatus.php
│   │
│   └── Events/
│
├── Application/
│   ├── Actions/
│   │   ├── CreateAppointmentAction.php
│   │   ├── ConfirmAppointmentAction.php
│   │   ├── CancelAppointmentAction.php
│   │   └── CheckInAppointmentAction.php
│   │
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 19. Audit Module

يجب أن يكون مستقلاً.

```text
Audit/
│
├── Domain/
│   ├── Entities/
│   │   └── AuditLog.php
│   └── Enums/
│
├── Application/
│   ├── Actions/
│   │   └── RecordAuditAction.php
│   └── Queries/
│       └── SearchAuditLogsQuery.php
│
├── Infrastructure/
│   └── Persistence/
│
├── Presentation/
│   ├── Controllers/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 20. Reports Module

```text
Reports/
│
├── Domain/
│
├── Application/
│   ├── Queries/
│   │   ├── PatientStatisticsQuery.php
│   │   ├── RevenueReportQuery.php
│   │   ├── LabReportQuery.php
│   │   └── InventoryReportQuery.php
│   │
│   └── Services/
│       └── ReportExportService.php
│
├── Infrastructure/
│
├── Presentation/
│   ├── Controllers/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 21. Settings Module

```text
Settings/
│
├── Domain/
│   ├── Entities/
│   │   ├── HospitalSetting.php
│   │   └── SystemSetting.php
│   │
│   └── Enums/
│
├── Application/
│   ├── Actions/
│   └── Queries/
│
├── Infrastructure/
│   └── Persistence/
│
├── Presentation/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Routes/
│
└── Providers/
```

---

# 22. Core

الـCore يحتوي الأشياء المشتركة التي **لا تنتمي لقسم طبي معين**.

```text
app/Core/
│
├── Auth/
│   ├── AuthManager.php
│   └── CurrentUser.php
│
├── Authorization/
│   ├── PermissionChecker.php
│   └── RoleChecker.php
│
├── Exceptions/
│   ├── DomainException.php
│   ├── BusinessRuleException.php
│   └── UnauthorizedException.php
│
├── Http/
│   ├── Middleware/
│   ├── Responses/
│   └── Pagination/
│
├── Logging/
│
├── Notifications/
│
├── Storage/
│   ├── FileStorage.php
│   └── StorageManager.php
│
└── Support/
    ├── Result.php
    ├── Helpers/
    └── Traits/
```

---

# 23. قاعدة مهمة جداً

لا تعمل:

```text
Core/
    PatientService.php
```

لأن PatientService تابع لـ:

```text
Patients
```

ولا تعمل:

```text
Core/
    LabService.php
```

لأن المختبر تابع لـ:

```text
Laboratory
```

الـCore فقط للأشياء المشتركة فعلاً.

---

# 24. Routes

لا أريد كل الـAPI في:

```text
routes/api.php
```

مثلاً:

```php
require base_path(
    'app/Modules/Patients/Presentation/Routes/api.php'
);

require base_path(
    'app/Modules/Laboratory/Presentation/Routes/api.php'
);

require base_path(
    'app/Modules/Radiology/Presentation/Routes/api.php'
);
```

أو يتم تحميلها من Service Providers.

فتصبح كل وحدة مسؤولة عن Routes الخاصة بها.

---

# 25. مثال Routes للمختبر

```text
app/Modules/Laboratory/Presentation/Routes/api.php
```

```php
Route::prefix('laboratory')
    ->middleware('auth:sanctum')
    ->group(function () {

        Route::get('/orders', ...);

        Route::post('/orders', ...);

        Route::get('/orders/{order}', ...);

        Route::post('/orders/{order}/samples', ...);

        Route::post('/samples/{sample}/results', ...);

        Route::post('/results/{result}/verify', ...);

    });
```

فيصبح الـAPI:

```text
/api/laboratory/orders
/api/laboratory/orders/15
/api/laboratory/orders/15/samples
/api/laboratory/samples/50/results
/api/laboratory/results/100/verify
```

---

# 26. Naming Convention

أنصح بشدة باستخدام:

```text
PatientModel
LabOrderModel
RadiologyOrderModel
InvoiceModel
```

بدلاً من أن يكون لديك:

```text
Patient
LabOrder
Invoice
```

داخل Infrastructure.

بينما الـDomain Entity يمكن أن يكون:

```text
Patient
LabOrder
Invoice
```

وهذا يوضح الفرق بين:

```text
Domain
```

و:

```text
Eloquent
```

---

# 27. Actions

مثلاً إنشاء طلب أشعة:

```text
CreateRadiologyOrderAction.php
```

يعمل:

```text
Validate
   ↓
Check Patient
   ↓
Check Encounter
   ↓
Check Permission
   ↓
Create Order
   ↓
Create Billing Charge
   ↓
Dispatch Notification
   ↓
Audit
```

الـController لا يقوم بكل هذا.

---

# 28. Controller

يصبح بسيطاً:

```php
public function store(
    CreateRadiologyOrderRequest $request,
    CreateRadiologyOrderAction $action
) {
    $order = $action->execute(
        $request->validated()
    );

    return new RadiologyOrderResource($order);
}
```

وهذا يجعل الكود نظيفاً جداً.

---

# 29. Events

مثلاً:

```text
Radiology
```

ينشئ:

```text
RadiologyResultCompleted
```

ثم Listeners مختلفة تستجيب:

```text
RadiologyResultCompleted
        │
        ├── NotifyDoctor
        ├── CreateAuditLog
        ├── UpdateEncounterTimeline
        └── UpdateBilling
```

وهنا يصبح النظام **Event Driven** بدون أن تجعل كل Module يعرف تفاصيل الآخر.

---

# 30. Module Communication

مثلاً Pharmacy تحتاج Inventory.

لا تعمل:

```php
Pharmacy
    ↓
InventoryModel::where(...)
```

مباشرة في كل مكان.

الأفضل:

```text
Pharmacy
    ↓
Inventory Contract
    ↓
Inventory Module
```

مثلاً:

```php
interface InventoryServiceInterface
{
    public function reserveStock(...);

    public function dispenseStock(...);

    public function getAvailableStock(...);
}
```

---

# 31. أهم قاعدة في الـArchitecture

**Module A لا يدخل إلى Database الخاصة بـModule B بشكل مباشر إذا كان بالإمكان تجنب ذلك.**

مثلاً:

```text
Pharmacy
    ❌
    ↓
InventoryModel
```

الأفضل:

```text
Pharmacy
    ↓
InventoryService
    ↓
Inventory
```

وهذا سيجعل النظام قابلاً للتوسع بشكل كبير.

---

# 32. الشكل النهائي

الـBackend بالكامل سيكون تقريباً:

```text
app/
│
├── Core/
│
└── Modules/
    │
    ├── Identity/
    ├── Patients/
    ├── Departments/
    ├── Appointments/
    ├── Encounters/
    ├── Clinical/
    │
    ├── Laboratory/
    ├── Radiology/
    ├── Pharmacy/
    ├── Inventory/
    │
    ├── Inpatient/
    ├── Nursing/
    ├── Operations/
    │
    ├── Billing/
    ├── Documents/
    │
    ├── Notifications/
    ├── Reports/
    ├── Audit/
    └── Settings/
```

وكل Module:

```text
Module/
│
├── Domain/
│
├── Application/
│
├── Infrastructure/
│
├── Presentation/
│
├── Providers/
│
└── Tests/
```

## ملاحظة مهمة

أنا **لا أنصح أن تبدأ الآن بإنشاء كل هذه المجلدات والملفات يدوياً**. الأفضل أن نثبت أولاً الـArchitecture بهذا الشكل، ثم نبني **الـDatabase ERD والـModules بالتسلسل**.

والترتيب الذي سأعتمده للمشروع هو:

```text
Identity
   ↓
Patients
   ↓
Departments
   ↓
Appointments
   ↓
Encounters
   ↓
Clinical
   ↓
Orders
   ↓
Laboratory / Radiology / Pharmacy
   ↓
Inventory
   ↓
Billing
   ↓
Inpatient / Operations / Nursing
   ↓
Documents / Notifications / Reports / Audit
```

بهذا الشكل عندما نبدأ كتابة Laravel، كل جزء سيكون في مكانه من البداية، ولن نضطر بعد 6 أشهر إلى إعادة هيكلة المشروع بالكامل.
