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

    /**
     * Screens an administrator can grant from the staff form. Each one is a
     * checkbox: checked shows that page, unchecked hides it.
     *
     * @return array<string, array{group: string, label_key: string, permissions: array<int, string>}>
     */
    public static function screens(): array
    {
        $pos = [
            self::PHARMACY_MEDICATIONS_MANAGE,
            self::PHARMACY_DISPENSING_CREATE,
            self::INVENTORY_STOCK_VIEW,
        ];

        return [
            'patients' => self::screen('reception', 'nav.patients', [
                self::PATIENTS_VIEW, self::PATIENTS_CREATE, self::PATIENTS_UPDATE,
            ]),
            'appointments' => self::screen('reception', 'nav.appointments', [
                self::APPOINTMENTS_VIEW, self::APPOINTMENTS_CREATE, self::APPOINTMENTS_UPDATE,
            ]),
            'booking-link' => self::screen('reception', 'nav.bookingLink', [
                self::APPOINTMENTS_VIEW, self::APPOINTMENTS_CREATE,
            ]),
            'encounters' => self::screen('reception', 'nav.encounters', [
                self::ENCOUNTERS_VIEW, self::ENCOUNTERS_CREATE, self::ENCOUNTERS_UPDATE,
                self::ENCOUNTERS_TRANSFER, self::ENCOUNTERS_DISCHARGE, self::PATIENTS_VIEW,
            ]),
            'emergency' => self::screen('care', 'nav.emergency', [
                self::ENCOUNTERS_VIEW, self::ENCOUNTERS_CREATE, self::PATIENTS_VIEW,
            ]),
            'laboratory' => self::screen('diagnostics', 'nav.laboratory', [
                self::LABORATORY_ORDERS_VIEW, self::LABORATORY_SAMPLES_MANAGE,
                self::LABORATORY_RESULTS_CREATE, self::LABORATORY_RESULTS_VERIFY,
                self::ORDERS_VIEW, self::PATIENTS_VIEW, self::ENCOUNTERS_VIEW,
                self::DOCUMENTS_VIEW, self::DOCUMENTS_UPLOAD,
            ]),
            'lab-tests' => self::screen('diagnostics', 'nav.labTests', [
                self::LABORATORY_TESTS_MANAGE, self::LABORATORY_ORDERS_VIEW,
            ]),
            'blood-products' => self::screen('diagnostics', 'nav.bloodProducts', [
                self::LABORATORY_TESTS_MANAGE, self::LABORATORY_ORDERS_VIEW,
            ]),
            'radiology' => self::screen('diagnostics', 'nav.radiology', [
                self::RADIOLOGY_ORDERS_VIEW, self::RADIOLOGY_STUDIES_CREATE,
                self::RADIOLOGY_IMAGES_UPLOAD, self::RADIOLOGY_REPORTS_CREATE,
                self::RADIOLOGY_REPORTS_VERIFY, self::ORDERS_VIEW,
                self::PATIENTS_VIEW, self::ENCOUNTERS_VIEW,
                self::DOCUMENTS_VIEW, self::DOCUMENTS_UPLOAD,
            ]),
            'radiology-services' => self::screen('diagnostics', 'nav.radiologyServices', [
                self::RADIOLOGY_SERVICES_MANAGE, self::RADIOLOGY_ORDERS_VIEW,
            ]),
            'pharmacy' => self::screen('diagnostics', 'nav.pharmacy', [
                self::PHARMACY_PRESCRIPTIONS_VIEW, self::PHARMACY_PRESCRIPTIONS_CREATE,
                self::PATIENTS_VIEW, self::ORDERS_VIEW,
            ]),
            'pharmacy-stock' => self::screen('diagnostics', 'nav.pharmacyStock', [
                self::INVENTORY_STOCK_VIEW, self::INVENTORY_STOCK_RECEIVE, self::INVENTORY_STOCK_ISSUE,
            ]),
            'pharmacy-catalog' => self::screen('diagnostics', 'nav.pharmacyCatalog', $pos),
            'pharmacy-sale' => self::screen('diagnostics', 'nav.pharmacySale', $pos),
            'pharmacy-return' => self::screen('diagnostics', 'nav.pharmacyReturn', $pos),
            'pharmacy-debts' => self::screen('diagnostics', 'nav.pharmacyDebts', $pos),
            'pharmacy-warehouses' => self::screen('diagnostics', 'nav.pharmacyWarehouses', $pos),
            'pharmacy-stocktake' => self::screen('diagnostics', 'nav.pharmacyStocktake', $pos),
            'inpatient' => self::screen('care', 'nav.inpatient', [
                self::INPATIENT_VIEW, self::INPATIENT_ADMIT, self::INPATIENT_BEDS_MANAGE,
                self::INPATIENT_TRANSFER, self::INPATIENT_DISCHARGE, self::PATIENTS_VIEW,
            ]),
            'operations' => self::screen('care', 'nav.operations', [
                self::OPERATIONS_VIEW, self::OPERATIONS_SCHEDULE, self::OPERATIONS_UPDATE, self::PATIENTS_VIEW,
            ]),
            'nursing' => self::screen('care', 'nav.nursing', [
                self::NURSING_NOTES_VIEW, self::NURSING_NOTES_CREATE, self::NURSING_MEDICATION_ADMINISTER,
                self::CLINICAL_VITALS_VIEW, self::CLINICAL_VITALS_CREATE, self::PATIENTS_VIEW,
            ]),
            'inventory' => self::screen('admin', 'nav.inventory', [
                self::INVENTORY_ITEMS_MANAGE, self::INVENTORY_STOCK_VIEW, self::INVENTORY_STOCK_RECEIVE,
                self::INVENTORY_STOCK_ISSUE, self::INVENTORY_STOCK_TRANSFER, self::INVENTORY_STOCK_ADJUST,
            ]),
            'staff' => self::screen('admin', 'nav.staff', [self::STAFF_MANAGE]),
            'departments' => self::screen('admin', 'nav.departments', [self::DEPARTMENTS_MANAGE]),
            'diagnoses' => self::screen('admin', 'nav.diagnoses', [self::DEPARTMENTS_MANAGE]),
            'operation-types' => self::screen('admin', 'nav.operationTypes', [self::DEPARTMENTS_MANAGE]),
            'documents' => self::screen('admin', 'nav.documents', [self::DOCUMENTS_VIEW, self::DOCUMENTS_UPLOAD]),
            'billing' => self::screen('finance', 'nav.billing', [
                self::BILLING_CHARGES_VIEW, self::BILLING_CHARGES_CREATE,
                self::BILLING_INVOICES_VIEW, self::BILLING_INVOICES_CREATE,
                self::BILLING_PAYMENTS_CREATE, self::BILLING_DISCOUNTS_APPLY,
            ]),
            'reports' => self::screen('finance', 'nav.reports', [self::REPORTS_VIEW]),
            'audit' => self::screen('system', 'nav.audit', [self::AUDIT_VIEW]),
            'settings' => self::screen('system', 'nav.settings', [self::SETTINGS_MANAGE, self::USERS_MANAGE]),
        ];
    }

    /**
     * Menu a role sees today. Saving these boxes leaves that menu in place;
     * clearing a box hides it, and ticking a new one grants that screen.
     *
     * @return array<int, string>
     */
    public static function screensForRole(string $role): array
    {
        $reception = ['patients', 'appointments', 'booking-link', 'encounters', 'emergency', 'documents'];

        if (in_array((string) config('modules.edition', 'hospital'), ['clinic', 'laboratory', 'radiology'], true)) {
            $reception[] = 'billing';
        }

        return match ($role) {
            'reception' => $reception,
            'doctor', 'data_entry' => ['patients', 'encounters', 'emergency'],
            'nurse' => ['patients', 'encounters', 'emergency', 'nursing', 'inpatient', 'pharmacy'],
            'lab_technician' => ['laboratory'],
            'lab_supervisor' => ['laboratory', 'lab-tests', 'blood-products'],
            'radiology_technician' => ['radiology'],
            'radiologist' => ['radiology', 'radiology-services'],
            'pharmacist' => [
                'pharmacy', 'pharmacy-stock', 'pharmacy-catalog', 'pharmacy-sale',
                'pharmacy-return', 'pharmacy-debts', 'pharmacy-warehouses', 'pharmacy-stocktake',
            ],
            'cashier' => ['patients', 'encounters', 'emergency', 'billing'],
            'warehouse' => ['inventory', 'pharmacy-stock'],
            default => [],
        };
    }

    /**
     * Effective permission list after the staff form's checkboxes.
     *
     * The role blueprint stays the base, so a doctor keeps in-file clinical
     * access. A box that was on by default and is now off drops the permissions
     * that belong to it. A newly ticked box adds that screen's bundle.
     *
     * @param  array<int, string>  $screens
     * @return array<int, string>
     */
    public static function permissionsFor(string $role, array $screens): array
    {
        $known = array_values(array_unique(array_values(array_filter(
            $screens,
            static fn (mixed $id): bool => is_string($id) && array_key_exists($id, self::screens()),
        ))));

        if ($known === []) {
            return [];
        }

        if (! array_key_exists($role, self::roles())) {
            return array_values(array_unique(self::bundle($known)));
        }

        $removed = array_diff(self::screensForRole($role), $known);
        $added = array_diff($known, self::screensForRole($role));
        $catalogue = self::screens();

        $permissions = array_values(array_filter(
            self::roles()[$role],
            static function (string $permission) use ($removed, $known, $catalogue): bool {
                $owner = self::permissionScreen($permission);

                if ($owner === null || ! in_array($owner, $removed, true)) {
                    return true;
                }

                foreach ($known as $screen) {
                    if (in_array($permission, $catalogue[$screen]['permissions'], true)) {
                        return true;
                    }
                }

                return false;
            },
        ));

        return array_values(array_unique(array_merge($permissions, self::bundle($added))));
    }

    /**
     * Which checkbox owns a permission, so unchecking that box can revoke it.
     */
    public static function permissionScreen(string $permission): ?string
    {
        return match (true) {
            str_starts_with($permission, 'patients.') => 'patients',
            str_starts_with($permission, 'appointments.') => 'appointments',
            str_starts_with($permission, 'encounters.') => 'encounters',
            str_starts_with($permission, 'clinical.') => 'patients',
            str_starts_with($permission, 'orders.') => 'patients',
            str_starts_with($permission, 'laboratory.tests.') => 'lab-tests',
            str_starts_with($permission, 'laboratory.') => 'laboratory',
            str_starts_with($permission, 'radiology.services.') => 'radiology-services',
            str_starts_with($permission, 'radiology.') => 'radiology',
            str_starts_with($permission, 'pharmacy.') => 'pharmacy',
            str_starts_with($permission, 'inventory.') => 'inventory',
            str_starts_with($permission, 'inpatient.') => 'inpatient',
            str_starts_with($permission, 'nursing.') => 'nursing',
            str_starts_with($permission, 'operations.') => 'operations',
            str_starts_with($permission, 'billing.') => 'billing',
            str_starts_with($permission, 'documents.') => 'documents',
            $permission === self::REPORTS_VIEW => 'reports',
            $permission === self::STAFF_MANAGE => 'staff',
            $permission === self::DEPARTMENTS_MANAGE => 'departments',
            $permission === self::SETTINGS_MANAGE, $permission === self::USERS_MANAGE, $permission === self::ROLES_MANAGE => 'settings',
            $permission === self::AUDIT_VIEW => 'audit',
            default => null,
        };
    }

    /**
     * Payload for the staff form: groups of checkboxes and the preset each role ticks.
     *
     * @return array{groups: array<int, array{id: string, label_key: string, screens: array<int, array{id: string, label_key: string}>}>, roles: array<string, array<int, string>>}
     */
    public static function accessCatalogue(): array
    {
        $labels = [
            'reception' => 'nav.reception',
            'diagnostics' => 'nav.diagnostics',
            'care' => 'nav.care',
            'admin' => 'nav.admin',
            'finance' => 'nav.finance',
            'system' => 'nav.system',
        ];

        $grouped = [];

        foreach (self::screens() as $id => $screen) {
            $grouped[$screen['group']][] = [
                'id' => $id,
                'label_key' => $screen['label_key'],
            ];
        }

        $groups = [];

        foreach ($labels as $group => $labelKey) {
            $groups[] = [
                'id' => $group,
                'label_key' => $labelKey,
                'screens' => $grouped[$group] ?? [],
            ];
        }

        $roles = [];

        foreach (array_keys(self::staffRoles()) as $role) {
            $roles[$role] = self::screensForRole($role);
        }

        return ['groups' => $groups, 'roles' => $roles];
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array{group: string, label_key: string, permissions: array<int, string>}
     */
    private static function screen(string $group, string $labelKey, array $permissions): array
    {
        return [
            'group' => $group,
            'label_key' => $labelKey,
            'permissions' => $permissions,
        ];
    }

    /**
     * @param  array<int, string>  $screenIds
     * @return array<int, string>
     */
    private static function bundle(array $screenIds): array
    {
        $catalogue = self::screens();
        $permissions = [];

        foreach ($screenIds as $id) {
            $permissions = array_merge($permissions, $catalogue[$id]['permissions'] ?? []);
        }

        return $permissions;
    }
}
