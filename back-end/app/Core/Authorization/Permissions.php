<?php

declare(strict_types=1);

namespace App\Core\Authorization;

/**
 * The single catalogue of permission names. FormRequests reference these
 * constants, the seeder creates them, and the Next.js client receives the same
 * strings in the `/api/auth/me` payload for its `<Can permission="..."/>`
 * guards.
 */
final class Permissions
{
    // Patients
    public const PATIENTS_VIEW = 'patients.view';
    public const PATIENTS_CREATE = 'patients.create';
    public const PATIENTS_UPDATE = 'patients.update';
    public const PATIENTS_DELETE = 'patients.delete';

    // Appointments
    public const APPOINTMENTS_VIEW = 'appointments.view';
    public const APPOINTMENTS_CREATE = 'appointments.create';
    public const APPOINTMENTS_UPDATE = 'appointments.update';

    // Encounters
    public const ENCOUNTERS_VIEW = 'encounters.view';
    public const ENCOUNTERS_CREATE = 'encounters.create';
    public const ENCOUNTERS_UPDATE = 'encounters.update';
    public const ENCOUNTERS_TRANSFER = 'encounters.transfer';
    public const ENCOUNTERS_DISCHARGE = 'encounters.discharge';

    // Clinical
    public const CLINICAL_NOTES_VIEW = 'clinical.notes.view';
    public const CLINICAL_NOTES_CREATE = 'clinical.notes.create';
    public const CLINICAL_VITALS_VIEW = 'clinical.vitals.view';
    public const CLINICAL_VITALS_CREATE = 'clinical.vitals.create';
    public const CLINICAL_DIAGNOSIS_VIEW = 'clinical.diagnosis.view';
    public const CLINICAL_DIAGNOSIS_CREATE = 'clinical.diagnosis.create';
    public const CLINICAL_DIAGNOSIS_UPDATE = 'clinical.diagnosis.update';
    public const CLINICAL_PROCEDURES_CREATE = 'clinical.procedures.create';

    // Orders engine
    public const ORDERS_VIEW = 'orders.view';
    public const ORDERS_CREATE = 'orders.create';
    public const ORDERS_CANCEL = 'orders.cancel';

    // Laboratory
    public const LABORATORY_TESTS_MANAGE = 'laboratory.tests.manage';
    public const LABORATORY_ORDERS_VIEW = 'laboratory.orders.view';
    public const LABORATORY_SAMPLES_MANAGE = 'laboratory.samples.manage';
    public const LABORATORY_RESULTS_CREATE = 'laboratory.results.create';
    public const LABORATORY_RESULTS_VERIFY = 'laboratory.results.verify';

    // Radiology
    public const RADIOLOGY_SERVICES_MANAGE = 'radiology.services.manage';
    public const RADIOLOGY_ORDERS_VIEW = 'radiology.orders.view';
    public const RADIOLOGY_STUDIES_CREATE = 'radiology.studies.create';
    public const RADIOLOGY_IMAGES_UPLOAD = 'radiology.images.upload';
    public const RADIOLOGY_REPORTS_CREATE = 'radiology.reports.create';
    public const RADIOLOGY_REPORTS_VERIFY = 'radiology.reports.verify';

    // Pharmacy
    public const PHARMACY_MEDICATIONS_MANAGE = 'pharmacy.medications.manage';
    public const PHARMACY_PRESCRIPTIONS_VIEW = 'pharmacy.prescriptions.view';
    public const PHARMACY_PRESCRIPTIONS_CREATE = 'pharmacy.prescriptions.create';
    public const PHARMACY_DISPENSING_CREATE = 'pharmacy.dispensing.create';

    // Inventory
    public const INVENTORY_ITEMS_MANAGE = 'inventory.items.manage';
    public const INVENTORY_STOCK_VIEW = 'inventory.stock.view';
    public const INVENTORY_STOCK_RECEIVE = 'inventory.stock.receive';
    public const INVENTORY_STOCK_ISSUE = 'inventory.stock.issue';
    public const INVENTORY_STOCK_TRANSFER = 'inventory.stock.transfer';
    public const INVENTORY_STOCK_ADJUST = 'inventory.stock.adjust';

    // Inpatient
    public const INPATIENT_VIEW = 'inpatient.view';
    public const INPATIENT_ADMIT = 'inpatient.admit';
    public const INPATIENT_BEDS_MANAGE = 'inpatient.beds.manage';
    public const INPATIENT_TRANSFER = 'inpatient.transfer';
    public const INPATIENT_DISCHARGE = 'inpatient.discharge';

    // Nursing
    public const NURSING_NOTES_VIEW = 'nursing.notes.view';
    public const NURSING_NOTES_CREATE = 'nursing.notes.create';
    public const NURSING_MEDICATION_ADMINISTER = 'nursing.medication.administer';

    // Operations
    public const OPERATIONS_VIEW = 'operations.view';
    public const OPERATIONS_SCHEDULE = 'operations.schedule';
    public const OPERATIONS_UPDATE = 'operations.update';

    // Billing
    public const BILLING_CHARGES_VIEW = 'billing.charges.view';
    public const BILLING_CHARGES_CREATE = 'billing.charges.create';
    public const BILLING_INVOICES_VIEW = 'billing.invoices.view';
    public const BILLING_INVOICES_CREATE = 'billing.invoices.create';
    public const BILLING_PAYMENTS_CREATE = 'billing.payments.create';
    public const BILLING_DISCOUNTS_APPLY = 'billing.discounts.apply';
    public const BILLING_SERVICES_MANAGE = 'billing.services.manage';

    // Documents
    public const DOCUMENTS_VIEW = 'documents.view';
    public const DOCUMENTS_UPLOAD = 'documents.upload';
    public const DOCUMENTS_DELETE = 'documents.delete';

    // Reports, administration and audit
    public const REPORTS_VIEW = 'reports.view';
    public const USERS_MANAGE = 'users.manage';
    public const ROLES_MANAGE = 'roles.manage';
    public const STAFF_MANAGE = 'staff.manage';
    public const DEPARTMENTS_MANAGE = 'departments.manage';
    public const SETTINGS_MANAGE = 'settings.manage';
    public const AUDIT_VIEW = 'audit.view';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    /**
     * Role blueprints straight out of the PRD. The seeder turns these into
     * spatie roles with the listed permissions attached.
     *
     * @return array<string, array<int, string>>
     */
    public static function roles(): array
    {
        return [
            'admin' => self::all(),

            'reception' => [
                self::PATIENTS_VIEW,
                self::PATIENTS_CREATE,
                self::PATIENTS_UPDATE,
                self::APPOINTMENTS_VIEW,
                self::APPOINTMENTS_CREATE,
                self::APPOINTMENTS_UPDATE,
                self::ENCOUNTERS_VIEW,
                self::ENCOUNTERS_CREATE,
                self::ENCOUNTERS_TRANSFER,
                self::DOCUMENTS_VIEW,
                self::DOCUMENTS_UPLOAD,
                ...self::receptionBilling(),
            ],

            'doctor' => self::clinicalDesk(),

            // Enters the doctor's clinical work for one clinic or wing, under their own name.
            'data_entry' => self::clinicalDesk(),

            'lab_technician' => [
                self::PATIENTS_VIEW,
                self::ENCOUNTERS_VIEW,
                self::ORDERS_VIEW,
                self::LABORATORY_ORDERS_VIEW,
                self::LABORATORY_SAMPLES_MANAGE,
                self::LABORATORY_RESULTS_CREATE,
                self::LABORATORY_RESULTS_VERIFY,
                self::DOCUMENTS_VIEW,
                self::DOCUMENTS_UPLOAD,
            ],

            'lab_supervisor' => [
                self::PATIENTS_VIEW,
                self::ENCOUNTERS_VIEW,
                self::ORDERS_VIEW,
                self::LABORATORY_TESTS_MANAGE,
                self::LABORATORY_ORDERS_VIEW,
                self::LABORATORY_SAMPLES_MANAGE,
                self::LABORATORY_RESULTS_CREATE,
                self::LABORATORY_RESULTS_VERIFY,
                self::DOCUMENTS_VIEW,
                self::DOCUMENTS_UPLOAD,
            ],

            'radiology_technician' => [
                self::PATIENTS_VIEW,
                self::ENCOUNTERS_VIEW,
                self::ORDERS_VIEW,
                self::RADIOLOGY_ORDERS_VIEW,
                self::RADIOLOGY_STUDIES_CREATE,
                self::RADIOLOGY_IMAGES_UPLOAD,
                self::RADIOLOGY_REPORTS_CREATE,
                self::RADIOLOGY_REPORTS_VERIFY,
                self::DOCUMENTS_VIEW,
                self::DOCUMENTS_UPLOAD,
            ],

            'radiologist' => [
                self::PATIENTS_VIEW,
                self::ENCOUNTERS_VIEW,
                self::ORDERS_VIEW,
                self::RADIOLOGY_SERVICES_MANAGE,
                self::RADIOLOGY_ORDERS_VIEW,
                self::RADIOLOGY_STUDIES_CREATE,
                self::RADIOLOGY_IMAGES_UPLOAD,
                self::RADIOLOGY_REPORTS_CREATE,
                self::RADIOLOGY_REPORTS_VERIFY,
                self::DOCUMENTS_VIEW,
                self::DOCUMENTS_UPLOAD,
            ],

            'pharmacist' => [
                self::PATIENTS_VIEW,
                self::ENCOUNTERS_VIEW,
                self::ORDERS_VIEW,
                self::PHARMACY_MEDICATIONS_MANAGE,
                self::PHARMACY_PRESCRIPTIONS_VIEW,
                self::PHARMACY_DISPENSING_CREATE,
                self::INVENTORY_STOCK_VIEW,
                self::INVENTORY_STOCK_RECEIVE,
                self::INVENTORY_STOCK_ISSUE,
                self::DOCUMENTS_VIEW,
                self::DOCUMENTS_UPLOAD,
            ],

            'warehouse' => [
                self::INVENTORY_ITEMS_MANAGE,
                self::INVENTORY_STOCK_VIEW,
                self::INVENTORY_STOCK_RECEIVE,
                self::INVENTORY_STOCK_ISSUE,
                self::INVENTORY_STOCK_TRANSFER,
                self::INVENTORY_STOCK_ADJUST,
            ],

            'nurse' => [
                self::PATIENTS_VIEW,
                self::ENCOUNTERS_VIEW,
                self::CLINICAL_VITALS_VIEW,
                self::CLINICAL_VITALS_CREATE,
                self::CLINICAL_NOTES_VIEW,
                self::NURSING_NOTES_VIEW,
                self::NURSING_NOTES_CREATE,
                self::NURSING_MEDICATION_ADMINISTER,
                self::INPATIENT_VIEW,
                self::INPATIENT_BEDS_MANAGE,
                self::INPATIENT_TRANSFER,
                self::PHARMACY_PRESCRIPTIONS_VIEW,
            ],

            'cashier' => [
                self::PATIENTS_VIEW,
                self::ENCOUNTERS_VIEW,
                self::BILLING_CHARGES_VIEW,
                self::BILLING_CHARGES_CREATE,
                self::BILLING_INVOICES_VIEW,
                self::BILLING_INVOICES_CREATE,
                self::BILLING_PAYMENTS_CREATE,
                self::BILLING_DISCOUNTS_APPLY,
            ],
        ];
    }

    /**
     * Roles that can be opened from the staff screen. Each one already carries
     * its permission set in {@see self::roles()}.
     *
     * @return array<string, string>
     */
    public static function staffRoles(): array
    {
        return [
            'reception' => 'استقبال',
            'doctor' => 'طبيب',
            'data_entry' => 'مدخل بيانات',
            'nurse' => 'تمريض',
            'lab_technician' => 'مخبر',
            'lab_supervisor' => 'مشرف مخبر',
            'radiology_technician' => 'أشعة',
            'radiologist' => 'طبيب أشعة',
            'pharmacist' => 'صيدلية',
            'cashier' => 'محاسب',
            'warehouse' => 'مستودع',
        ];
    }

    /**
     * What a doctor can do. The data-entry account for a clinic or wing
     * uses the same list, so the work is identical and the actor stays distinct.
     *
     * @return array<int, string>
     */
    private static function clinicalDesk(): array
    {
        return [
            self::PATIENTS_VIEW,
            self::APPOINTMENTS_VIEW,
            self::APPOINTMENTS_UPDATE,
            self::ENCOUNTERS_VIEW,
            self::ENCOUNTERS_CREATE,
            self::ENCOUNTERS_UPDATE,
            self::ENCOUNTERS_TRANSFER,
            self::ENCOUNTERS_DISCHARGE,
            self::CLINICAL_NOTES_VIEW,
            self::CLINICAL_NOTES_CREATE,
            self::CLINICAL_VITALS_VIEW,
            self::CLINICAL_VITALS_CREATE,
            self::CLINICAL_DIAGNOSIS_VIEW,
            self::CLINICAL_DIAGNOSIS_CREATE,
            self::CLINICAL_DIAGNOSIS_UPDATE,
            self::CLINICAL_PROCEDURES_CREATE,
            self::ORDERS_VIEW,
            self::ORDERS_CREATE,
            self::ORDERS_CANCEL,
            self::LABORATORY_ORDERS_VIEW,
            self::RADIOLOGY_ORDERS_VIEW,
            self::PHARMACY_PRESCRIPTIONS_VIEW,
            self::PHARMACY_PRESCRIPTIONS_CREATE,
            self::DOCUMENTS_VIEW,
            self::DOCUMENTS_UPLOAD,
            self::BILLING_CHARGES_VIEW,
            self::BILLING_INVOICES_VIEW,
            self::OPERATIONS_VIEW,
            self::OPERATIONS_SCHEDULE,
            self::INPATIENT_VIEW,
            self::INPATIENT_ADMIT,
            self::INPATIENT_TRANSFER,
            self::INPATIENT_DISCHARGE,
            self::NURSING_NOTES_VIEW,
            self::REPORTS_VIEW,
        ];
    }

    /**
     * A clinic, laboratory, or radiology desk collects its own payments.
     * A hospital leaves invoices to the accountant.
     *
     * @return array<int, string>
     */
    private static function receptionBilling(): array
    {
        $edition = function_exists('config') ? (string) config('modules.edition', 'hospital') : 'hospital';

        if (! in_array($edition, ['clinic', 'laboratory', 'radiology'], true)) {
            return [];
        }

        return [
            self::BILLING_INVOICES_VIEW,
            self::BILLING_INVOICES_CREATE,
            self::BILLING_PAYMENTS_CREATE,
            self::BILLING_CHARGES_VIEW,
        ];
    }
}
