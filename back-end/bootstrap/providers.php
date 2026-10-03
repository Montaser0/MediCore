<?php

declare(strict_types=1);

use App\Core\Providers\CoreServiceProvider;
use App\Modules\Appointments\Providers\AppointmentsServiceProvider;
use App\Modules\Audit\Providers\AuditServiceProvider;
use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Clinical\Providers\ClinicalServiceProvider;
use App\Modules\Departments\Providers\DepartmentsServiceProvider;
use App\Modules\Documents\Providers\DocumentsServiceProvider;
use App\Modules\Encounters\Providers\EncountersServiceProvider;
use App\Modules\Hospital\Providers\HospitalServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Inpatient\Providers\InpatientServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Laboratory\Providers\LaboratoryServiceProvider;
use App\Modules\Notifications\Providers\NotificationsServiceProvider;
use App\Modules\Nursing\Providers\NursingServiceProvider;
use App\Modules\Operations\Providers\OperationsServiceProvider;
use App\Modules\Orders\Providers\OrdersServiceProvider;
use App\Modules\Patients\Providers\PatientsServiceProvider;
use App\Modules\Pharmacy\Providers\PharmacyServiceProvider;
use App\Modules\Radiology\Providers\RadiologyServiceProvider;
use App\Modules\Reports\Providers\ReportsServiceProvider;
use App\Modules\Settings\Providers\SettingsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,

    // Foundation
    AuditServiceProvider::class,
    SettingsServiceProvider::class,
    NotificationsServiceProvider::class,
    DocumentsServiceProvider::class,

    // Organisation
    IdentityServiceProvider::class,
    HospitalServiceProvider::class,
    DepartmentsServiceProvider::class,

    // Patient backbone
    PatientsServiceProvider::class,
    AppointmentsServiceProvider::class,
    EncountersServiceProvider::class,
    ClinicalServiceProvider::class,
    OrdersServiceProvider::class,

    // Service departments
    LaboratoryServiceProvider::class,
    RadiologyServiceProvider::class,
    PharmacyServiceProvider::class,
    InventoryServiceProvider::class,

    // Inpatient care
    InpatientServiceProvider::class,
    NursingServiceProvider::class,
    OperationsServiceProvider::class,

    // Finance and reporting
    BillingServiceProvider::class,
    ReportsServiceProvider::class,
];
