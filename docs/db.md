تمام. سأثبت لك الآن **النسخة الأساسية من قاعدة البيانات** بطريقة تناسب الـModular Architecture التي اتفقنا عليها، وتكون قابلة للتوسع لاحقاً للمخبر والأشعة والصيدلية والعمليات والتنويم.

# Database Architecture — HMS

## 1. القاعدة الذهبية

العلاقة الرئيسية ستكون:

```text
PATIENT
   │
   └── ENCOUNTER
          │
          ├── Clinical
          │
          ├── Orders
          │      ├── Laboratory
          │      ├── Radiology
          │      └── Pharmacy
          │
          ├── Documents
          │
          ├── Procedures
          │
          └── Billing
```

يعني **المريض ليس هو الزيارة**.

المريض لديه ملف دائم:

```text
patients
```

وكل مرة يأتي فيها للمشفى:

```text
encounters
```

وكل شيء يحدث أثناء الزيارة يرتبط بالـ`encounter_id`.

---

# 2. تقسيم قاعدة البيانات حسب Modules

```text
Identity
├── users
├── roles
├── permissions
├── role_has_permissions
├── user_roles
└── user_departments

Hospital
├── hospitals
├── departments
├── clinics
├── doctors
└── staff

Patients
├── patients
├── patient_contacts
├── patient_allergies
├── patient_conditions
└── patient_documents

Appointments
├── appointments
└── appointment_status_history

Encounters
├── encounters
├── encounter_transfers
└── encounter_status_history

Clinical
├── vital_signs
├── clinical_notes
├── diagnoses
├── diagnosis_codes
└── procedures

Orders
├── orders
└── order_items

Laboratory
├── lab_tests
├── lab_test_parameters
├── lab_orders
├── lab_order_items
├── lab_samples
├── lab_results
└── lab_result_values

Radiology
├── radiology_services
├── radiology_orders
├── radiology_order_items
├── radiology_studies
└── radiology_reports

Pharmacy
├── medications
├── medication_categories
├── prescriptions
├── prescription_items
└── medication_dispenses

Inventory
├── warehouses
├── inventory_items
├── inventory_batches
├── stock_levels
└── stock_movements

Inpatient
├── wards
├── rooms
├── beds
├── admissions
└── bed_assignments

Operations
├── operation_types
├── operations
├── operation_team
└── operation_notes

Documents
├── files
└── file_links

Billing
├── services
├── charges
├── invoices
├── invoice_items
├── payments
└── refunds

Notifications
├── notifications
└── notification_reads

Audit
└── audit_logs
```

---

# 3. `hospitals`

حتى لو حالياً عندك مشفى واحد، أنصح بإضافة `hospital_id` من البداية في الجداول التي تحتاجه.

```sql
CREATE TABLE hospitals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    phone VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    logo_path VARCHAR(500) NULL,
    timezone VARCHAR(100) NOT NULL DEFAULT 'Asia/Damascus',
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

# 4. `departments`

القسم لا يعني بالضرورة عيادة.

مثلاً:

```text
Dental
Laboratory
Radiology
Pharmacy
Warehouse
Surgery
Emergency
```

```sql
CREATE TABLE departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL,
    type VARCHAR(50) NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uq_department_code (hospital_id, code),

    CONSTRAINT fk_department_hospital
        FOREIGN KEY (hospital_id)
        REFERENCES hospitals(id)
);
```

---

# 5. `clinics`

مثلاً:

```text
Dental Clinic
Internal Medicine
Pediatrics
```

```sql
CREATE TABLE clinics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uq_clinic_code (department_id, code),

    CONSTRAINT fk_clinic_department
        FOREIGN KEY (department_id)
        REFERENCES departments(id)
);
```

---

# 6. المرضى — `patients`

هذا أهم جدول في النظام.

```sql
CREATE TABLE patients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    hospital_id BIGINT UNSIGNED NOT NULL,

    mrn VARCHAR(50) NOT NULL,

    first_name VARCHAR(100) NOT NULL,
    father_name VARCHAR(100) NULL,
    grandfather_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,

    mother_name VARCHAR(200) NULL,

    gender ENUM('male', 'female', 'other') NOT NULL,

    date_of_birth DATE NULL,

    national_id VARCHAR(100) NULL,

    phone VARCHAR(50) NULL,
    phone_secondary VARCHAR(50) NULL,

    email VARCHAR(255) NULL,

    address TEXT NULL,
    city VARCHAR(100) NULL,

    nationality VARCHAR(100) NULL,

    blood_type VARCHAR(10) NULL,

    marital_status VARCHAR(30) NULL,

    occupation VARCHAR(150) NULL,

    status ENUM(
        'active',
        'inactive',
        'deceased'
    ) NOT NULL DEFAULT 'active',

    notes TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    UNIQUE KEY uq_patient_mrn (hospital_id, mrn),

    INDEX idx_patient_name (
        last_name,
        first_name
    ),

    INDEX idx_patient_phone (phone),
    INDEX idx_patient_national_id (national_id),

    CONSTRAINT fk_patient_hospital
        FOREIGN KEY (hospital_id)
        REFERENCES hospitals(id)
);
```

---

# 7. جهات اتصال المريض

```sql
CREATE TABLE patient_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    patient_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(200) NOT NULL,
    relationship VARCHAR(100) NULL,
    phone VARCHAR(50) NULL,
    alternate_phone VARCHAR(50) NULL,

    is_emergency_contact BOOLEAN NOT NULL DEFAULT FALSE,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_contact_patient
        FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE CASCADE
);
```

---

# 8. الحساسية

```sql
CREATE TABLE patient_allergies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    patient_id BIGINT UNSIGNED NOT NULL,

    allergen VARCHAR(200) NOT NULL,

    reaction TEXT NULL,

    severity ENUM(
        'mild',
        'moderate',
        'severe',
        'unknown'
    ) DEFAULT 'unknown',

    status ENUM(
        'active',
        'inactive',
        'unknown'
    ) DEFAULT 'active',

    notes TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_allergy_patient
        FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE CASCADE
);
```

---

# 9. الأمراض المزمنة / الحالات السابقة

```sql
CREATE TABLE patient_conditions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    patient_id BIGINT UNSIGNED NOT NULL,

    condition_name VARCHAR(255) NOT NULL,

    diagnosed_at DATE NULL,

    status ENUM(
        'active',
        'resolved',
        'unknown'
    ) DEFAULT 'active',

    notes TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_condition_patient
        FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE CASCADE
);
```

---

# 10. الموظفون والأطباء

لا تجعل `doctor` هو المستخدم نفسه.

الأصح:

```text
users
   ↓
staff
   ↓
doctor
```

لأن الطبيب قد يكون له حساب، لكن الموظف الإداري أيضاً له حساب.

```sql
CREATE TABLE staff (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    hospital_id BIGINT UNSIGNED NOT NULL,

    employee_number VARCHAR(50) NOT NULL,

    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,

    phone VARCHAR(50) NULL,

    job_title VARCHAR(150) NULL,

    department_id BIGINT UNSIGNED NULL,

    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uq_employee_number (
        hospital_id,
        employee_number
    ),

    FOREIGN KEY (hospital_id)
        REFERENCES hospitals(id),

    FOREIGN KEY (department_id)
        REFERENCES departments(id)
);
```

والطبيب:

```sql
CREATE TABLE doctors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    staff_id BIGINT UNSIGNED NOT NULL,

    license_number VARCHAR(100) NULL,

    specialty VARCHAR(150) NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uq_doctor_staff (staff_id),

    FOREIGN KEY (staff_id)
        REFERENCES staff(id)
);
```

---

# 11. Users

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    staff_id BIGINT UNSIGNED NULL,

    username VARCHAR(100) NOT NULL UNIQUE,

    email VARCHAR(255) NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    last_login_at TIMESTAMP NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

# 12. الـEncounter

هذا أهم جدول بعد المرضى.

```sql
CREATE TABLE encounters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    hospital_id BIGINT UNSIGNED NOT NULL,

    patient_id BIGINT UNSIGNED NOT NULL,

    encounter_number VARCHAR(50) NOT NULL,

    type ENUM(
        'outpatient',
        'emergency',
        'inpatient',
        'operation',
        'follow_up'
    ) NOT NULL,

    status ENUM(
        'registered',
        'waiting',
        'in_progress',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'registered',

    department_id BIGINT UNSIGNED NULL,

    clinic_id BIGINT UNSIGNED NULL,

    doctor_id BIGINT UNSIGNED NULL,

    started_at DATETIME NULL,
    ended_at DATETIME NULL,

    reason TEXT NULL,

    created_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uq_encounter_number (
        hospital_id,
        encounter_number
    ),

    INDEX idx_encounter_patient (patient_id),
    INDEX idx_encounter_status (status),
    INDEX idx_encounter_date (created_at),

    FOREIGN KEY (hospital_id)
        REFERENCES hospitals(id),

    FOREIGN KEY (patient_id)
        REFERENCES patients(id),

    FOREIGN KEY (department_id)
        REFERENCES departments(id),

    FOREIGN KEY (clinic_id)
        REFERENCES clinics(id),

    FOREIGN KEY (doctor_id)
        REFERENCES doctors(id),

    FOREIGN KEY (created_by)
        REFERENCES users(id)
);
```

---

# 13. تحويل المريض بين الأقسام

هذا مهم جداً لفكرتك.

```sql
CREATE TABLE encounter_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    encounter_id BIGINT UNSIGNED NOT NULL,

    from_department_id BIGINT UNSIGNED NULL,
    to_department_id BIGINT UNSIGNED NOT NULL,

    from_user_id BIGINT UNSIGNED NULL,
    to_user_id BIGINT UNSIGNED NULL,

    reason TEXT NULL,

    transferred_at DATETIME NOT NULL,

    status ENUM(
        'pending',
        'accepted',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (encounter_id)
        REFERENCES encounters(id),

    FOREIGN KEY (from_department_id)
        REFERENCES departments(id),

    FOREIGN KEY (to_department_id)
        REFERENCES departments(id),

    FOREIGN KEY (from_user_id)
        REFERENCES users(id),

    FOREIGN KEY (to_user_id)
        REFERENCES users(id)
);
```

وهذا يسمح:

```text
Dental
 ↓
Radiology
 ↓
Laboratory
 ↓
Pharmacy
 ↓
Billing
 ↓
Discharge
```

---

# 14. العلامات الحيوية

```sql
CREATE TABLE vital_signs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    encounter_id BIGINT UNSIGNED NOT NULL,

    temperature DECIMAL(4,1) NULL,

    systolic_bp SMALLINT NULL,
    diastolic_bp SMALLINT NULL,

    heart_rate SMALLINT NULL,

    respiratory_rate SMALLINT NULL,

    oxygen_saturation DECIMAL(5,2) NULL,

    weight DECIMAL(6,2) NULL,
    height DECIMAL(6,2) NULL,

    recorded_by BIGINT UNSIGNED NULL,

    recorded_at DATETIME NOT NULL,

    notes TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (encounter_id)
        REFERENCES encounters(id),

    FOREIGN KEY (recorded_by)
        REFERENCES users(id)
);
```

---

# 15. Clinical Notes

```sql
CREATE TABLE clinical_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    encounter_id BIGINT UNSIGNED NOT NULL,

    author_id BIGINT UNSIGNED NOT NULL,

    note_type ENUM(
        'general',
        'history',
        'examination',
        'progress',
        'discharge'
    ) NOT NULL DEFAULT 'general',

    content LONGTEXT NOT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (encounter_id)
        REFERENCES encounters(id),

    FOREIGN KEY (author_id)
        REFERENCES users(id)
);
```

---

# 16. Diagnoses

```sql
CREATE TABLE diagnoses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    encounter_id BIGINT UNSIGNED NOT NULL,

    doctor_id BIGINT UNSIGNED NOT NULL,

    diagnosis_code_id BIGINT UNSIGNED NULL,

    diagnosis_name VARCHAR(255) NOT NULL,

    type ENUM(
        'provisional',
        'primary',
        'secondary',
        'final'
    ) NOT NULL DEFAULT 'primary',

    notes TEXT NULL,

    diagnosed_at DATETIME NOT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (encounter_id)
        REFERENCES encounters(id),

    FOREIGN KEY (doctor_id)
        REFERENCES doctors(id)
);
```

لاحقاً نربط `diagnosis_code_id` مع ICD-10.

---

# 17. Order Engine

وهنا أهم نقطة في المشروع.

بدل ما نعمل:

```text
lab_requests
radiology_requests
pharmacy_requests
```

كأنها أنظمة منفصلة، نعمل:

```text
orders
   ↓
order_items
```

ثم كل Module يكمل تفاصيله.

```sql
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    hospital_id BIGINT UNSIGNED NOT NULL,

    encounter_id BIGINT UNSIGNED NOT NULL,

    order_number VARCHAR(50) NOT NULL,

    type ENUM(
        'laboratory',
        'radiology',
        'pharmacy',
        'procedure',
        'consultation',
        'supply',
        'other'
    ) NOT NULL,

    status ENUM(
        'pending',
        'accepted',
        'in_progress',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',

    priority ENUM(
        'routine',
        'urgent',
        'stat'
    ) NOT NULL DEFAULT 'routine',

    ordered_by BIGINT UNSIGNED NOT NULL,

    department_id BIGINT UNSIGNED NULL,

    clinical_notes TEXT NULL,

    ordered_at DATETIME NOT NULL,

    completed_at DATETIME NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uq_order_number (
        hospital_id,
        order_number
    ),

    FOREIGN KEY (hospital_id)
        REFERENCES hospitals(id),

    FOREIGN KEY (encounter_id)
        REFERENCES encounters(id),

    FOREIGN KEY (ordered_by)
        REFERENCES users(id),

    FOREIGN KEY (department_id)
        REFERENCES departments(id)
);
```

---

# 18. Order Items

```sql
CREATE TABLE order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id BIGINT UNSIGNED NOT NULL,

    service_type VARCHAR(100) NOT NULL,

    service_id BIGINT UNSIGNED NULL,

    name VARCHAR(255) NOT NULL,

    quantity DECIMAL(12,2) DEFAULT 1,

    instructions TEXT NULL,

    status ENUM(
        'pending',
        'in_progress',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (order_id)
        REFERENCES orders(id)
        ON DELETE CASCADE
);
```

---

# 19. Laboratory

## Lab Tests

```sql
CREATE TABLE lab_tests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(255) NOT NULL,

    code VARCHAR(50) NOT NULL UNIQUE,

    category VARCHAR(150) NULL,

    sample_type VARCHAR(100) NULL,

    unit VARCHAR(50) NULL,

    reference_range VARCHAR(255) NULL,

    price DECIMAL(12,2) DEFAULT 0,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

## Lab Order

```sql
CREATE TABLE lab_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id BIGINT UNSIGNED NOT NULL UNIQUE,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (order_id)
        REFERENCES orders(id)
);
```

## Lab Items

```sql
CREATE TABLE lab_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    lab_order_id BIGINT UNSIGNED NOT NULL,

    lab_test_id BIGINT UNSIGNED NOT NULL,

    status ENUM(
        'pending',
        'sample_collected',
        'processing',
        'result_ready',
        'verified',
        'cancelled'
    ) DEFAULT 'pending',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (lab_order_id)
        REFERENCES lab_orders(id),

    FOREIGN KEY (lab_test_id)
        REFERENCES lab_tests(id)
);
```

---

# 20. عينات المختبر

```sql
CREATE TABLE lab_samples (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    lab_order_item_id BIGINT UNSIGNED NOT NULL,

    sample_number VARCHAR(50) NOT NULL UNIQUE,

    sample_type VARCHAR(100) NULL,

    collected_by BIGINT UNSIGNED NULL,

    collected_at DATETIME NULL,

    status ENUM(
        'pending',
        'collected',
        'received',
        'processing',
        'rejected'
    ) DEFAULT 'pending',

    rejection_reason TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (lab_order_item_id)
        REFERENCES lab_order_items(id),

    FOREIGN KEY (collected_by)
        REFERENCES users(id)
);
```

---

# 21. نتائج المختبر

```sql
CREATE TABLE lab_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    lab_order_item_id BIGINT UNSIGNED NOT NULL UNIQUE,

    result_value TEXT NULL,

    unit VARCHAR(50) NULL,

    reference_range VARCHAR(255) NULL,

    flag ENUM(
        'normal',
        'low',
        'high',
        'critical'
    ) DEFAULT 'normal',

    comment TEXT NULL,

    entered_by BIGINT UNSIGNED NULL,

    verified_by BIGINT UNSIGNED NULL,

    entered_at DATETIME NULL,

    verified_at DATETIME NULL,

    status ENUM(
        'draft',
        'final'
    ) DEFAULT 'draft',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (lab_order_item_id)
        REFERENCES lab_order_items(id),

    FOREIGN KEY (entered_by)
        REFERENCES users(id),

    FOREIGN KEY (verified_by)
        REFERENCES users(id)
);
```

---

# 22. Radiology

```sql
CREATE TABLE radiology_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(255) NOT NULL,

    code VARCHAR(50) NOT NULL UNIQUE,

    modality VARCHAR(100) NULL,

    description TEXT NULL,

    price DECIMAL(12,2) DEFAULT 0,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

# 23. Radiology Orders

```sql
CREATE TABLE radiology_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id BIGINT UNSIGNED NOT NULL UNIQUE,

    clinical_indication TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (order_id)
        REFERENCES orders(id)
);
```

---

# 24. Radiology Studies

```sql
CREATE TABLE radiology_studies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    radiology_order_id BIGINT UNSIGNED NOT NULL,

    study_number VARCHAR(100) NOT NULL UNIQUE,

    performed_by BIGINT UNSIGNED NULL,

    performed_at DATETIME NULL,

    status ENUM(
        'pending',
        'in_progress',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (radiology_order_id)
        REFERENCES radiology_orders(id),

    FOREIGN KEY (performed_by)
        REFERENCES users(id)
);
```

---

# 25. Radiology Reports

```sql
CREATE TABLE radiology_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    study_id BIGINT UNSIGNED NOT NULL UNIQUE,

    findings LONGTEXT NULL,

    impression LONGTEXT NULL,

    reported_by BIGINT UNSIGNED NULL,

    verified_by BIGINT UNSIGNED NULL,

    reported_at DATETIME NULL,

    verified_at DATETIME NULL,

    status ENUM(
        'draft',
        'final'
    ) DEFAULT 'draft',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (study_id)
        REFERENCES radiology_studies(id),

    FOREIGN KEY (reported_by)
        REFERENCES users(id),

    FOREIGN KEY (verified_by)
        REFERENCES users(id)
);
```

---

# 26. الملفات والصور

هذه نقطة مركزية.

بدلاً من:

```text
radiology_images
lab_files
patient_files
```

نستخدم نظام Files عام.

```sql
CREATE TABLE files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    uuid CHAR(36) NOT NULL UNIQUE,

    original_name VARCHAR(255) NOT NULL,

    stored_name VARCHAR(255) NOT NULL,

    disk VARCHAR(50) NOT NULL,

    path VARCHAR(1000) NOT NULL,

    mime_type VARCHAR(150) NULL,

    extension VARCHAR(20) NULL,

    size BIGINT UNSIGNED NULL,

    checksum VARCHAR(128) NULL,

    uploaded_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

ثم الربط:

```sql
CREATE TABLE file_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    file_id BIGINT UNSIGNED NOT NULL,

    entity_type VARCHAR(100) NOT NULL,

    entity_id BIGINT UNSIGNED NOT NULL,

    category VARCHAR(100) NULL,

    created_at TIMESTAMP NULL,

    INDEX idx_file_entity (
        entity_type,
        entity_id
    ),

    FOREIGN KEY (file_id)
        REFERENCES files(id)
        ON DELETE CASCADE
);
```

وهكذا:

```text
Radiology Study
      ↓
File
      ↓
X-Ray Image
```

ونفس الملف يمكن أن يكون:

```text
PDF
JPG
PNG
DICOM
Medical Report
ID
Consent
```

---

# 27. Pharmacy

## Medication

```sql
CREATE TABLE medications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(255) NOT NULL,

    generic_name VARCHAR(255) NULL,

    strength VARCHAR(100) NULL,

    dosage_form VARCHAR(100) NULL,

    route VARCHAR(100) NULL,

    barcode VARCHAR(100) NULL,

    category VARCHAR(150) NULL,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

# 28. Prescription

```sql
CREATE TABLE prescriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    encounter_id BIGINT UNSIGNED NOT NULL,

    prescription_number VARCHAR(50) NOT NULL UNIQUE,

    doctor_id BIGINT UNSIGNED NOT NULL,

    status ENUM(
        'draft',
        'active',
        'partially_dispensed',
        'dispensed',
        'cancelled'
    ) DEFAULT 'draft',

    notes TEXT NULL,

    prescribed_at DATETIME NOT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (encounter_id)
        REFERENCES encounters(id),

    FOREIGN KEY (doctor_id)
        REFERENCES doctors(id)
);
```

---

# 29. Prescription Items

```sql
CREATE TABLE prescription_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    prescription_id BIGINT UNSIGNED NOT NULL,

    medication_id BIGINT UNSIGNED NOT NULL,

    dose VARCHAR(100) NULL,

    frequency VARCHAR(100) NULL,

    duration VARCHAR(100) NULL,

    route VARCHAR(100) NULL,

    quantity DECIMAL(12,2) NULL,

    instructions TEXT NULL,

    FOREIGN KEY (prescription_id)
        REFERENCES prescriptions(id)
        ON DELETE CASCADE,

    FOREIGN KEY (medication_id)
        REFERENCES medications(id)
);
```

---

# 30. Inventory

## Warehouses

```sql
CREATE TABLE warehouses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    hospital_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(150) NOT NULL,

    code VARCHAR(50) NOT NULL,

    type VARCHAR(50) NULL,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uq_warehouse (
        hospital_id,
        code
    ),

    FOREIGN KEY (hospital_id)
        REFERENCES hospitals(id)
);
```

---

# 31. Inventory Items

```sql
CREATE TABLE inventory_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(255) NOT NULL,

    sku VARCHAR(100) NOT NULL UNIQUE,

    barcode VARCHAR(100) NULL,

    category VARCHAR(150) NULL,

    unit VARCHAR(50) NOT NULL,

    item_type ENUM(
        'medicine',
        'medical_supply',
        'equipment',
        'general'
    ) NOT NULL,

    minimum_stock DECIMAL(12,2) DEFAULT 0,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

# 32. Batches

```sql
CREATE TABLE inventory_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    inventory_item_id BIGINT UNSIGNED NOT NULL,

    warehouse_id BIGINT UNSIGNED NOT NULL,

    batch_number VARCHAR(100) NOT NULL,

    expiry_date DATE NULL,

    quantity DECIMAL(12,2) NOT NULL DEFAULT 0,

    unit_cost DECIMAL(12,2) DEFAULT 0,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_batch_expiry (expiry_date),

    FOREIGN KEY (inventory_item_id)
        REFERENCES inventory_items(id),

    FOREIGN KEY (warehouse_id)
        REFERENCES warehouses(id)
);
```

---

# 33. Stock Movements

كل حركة مخزون يجب تسجيلها.

```sql
CREATE TABLE stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    inventory_item_id BIGINT UNSIGNED NOT NULL,

    warehouse_id BIGINT UNSIGNED NOT NULL,

    batch_id BIGINT UNSIGNED NULL,

    type ENUM(
        'purchase',
        'sale',
        'dispense',
        'transfer_in',
        'transfer_out',
        'adjustment',
        'return',
        'expired'
    ) NOT NULL,

    quantity DECIMAL(12,2) NOT NULL,

    reference_type VARCHAR(100) NULL,
    reference_id BIGINT UNSIGNED NULL,

    performed_by BIGINT UNSIGNED NULL,

    notes TEXT NULL,

    created_at TIMESTAMP NULL,

    FOREIGN KEY (inventory_item_id)
        REFERENCES inventory_items(id),

    FOREIGN KEY (warehouse_id)
        REFERENCES warehouses(id),

    FOREIGN KEY (batch_id)
        REFERENCES inventory_batches(id),

    FOREIGN KEY (performed_by)
        REFERENCES users(id)
);
```

---

# 34. Inpatient

## Wards

```sql
CREATE TABLE wards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    department_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(150) NOT NULL,

    code VARCHAR(50) NOT NULL,

    type VARCHAR(50) NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (department_id)
        REFERENCES departments(id)
);
```

## Rooms

```sql
CREATE TABLE rooms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    ward_id BIGINT UNSIGNED NOT NULL,

    room_number VARCHAR(50) NOT NULL,

    room_type VARCHAR(50) NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (ward_id)
        REFERENCES wards(id)
);
```

## Beds

```sql
CREATE TABLE beds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    room_id BIGINT UNSIGNED NOT NULL,

    bed_number VARCHAR(50) NOT NULL,

    status ENUM(
        'available',
        'occupied',
        'reserved',
        'cleaning',
        'maintenance'
    ) DEFAULT 'available',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (room_id)
        REFERENCES rooms(id)
);
```

---

# 35. Admissions

```sql
CREATE TABLE admissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    encounter_id BIGINT UNSIGNED NOT NULL UNIQUE,

    admission_number VARCHAR(50) NOT NULL UNIQUE,

    admitted_by BIGINT UNSIGNED NOT NULL,

    admitted_at DATETIME NOT NULL,

    discharged_at DATETIME NULL,

    status ENUM(
        'admitted',
        'transferred',
        'discharged',
        'cancelled'
    ) DEFAULT 'admitted',

    admission_reason TEXT NULL,

    discharge_summary LONGTEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (encounter_id)
        REFERENCES encounters(id),

    FOREIGN KEY (admitted_by)
        REFERENCES users(id)
);
```

---

# 36. Bed Assignments

لأن المريض يمكن أن ينتقل من غرفة إلى غرفة.

```sql
CREATE TABLE bed_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    admission_id BIGINT UNSIGNED NOT NULL,

    bed_id BIGINT UNSIGNED NOT NULL,

    assigned_at DATETIME NOT NULL,

    released_at DATETIME NULL,

    assigned_by BIGINT UNSIGNED NULL,

    reason TEXT NULL,

    FOREIGN KEY (admission_id)
        REFERENCES admissions(id),

    FOREIGN KEY (bed_id)
        REFERENCES beds(id),

    FOREIGN KEY (assigned_by)
        REFERENCES users(id)
);
```

---

# 37. Billing

أنا أنصح أن يكون عندنا:

```text
Service
   ↓
Charge
   ↓
Invoice
   ↓
Payment
```

## Services

```sql
CREATE TABLE services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(255) NOT NULL,

    code VARCHAR(50) NOT NULL UNIQUE,

    category VARCHAR(100) NULL,

    price DECIMAL(12,2) NOT NULL DEFAULT 0,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

# 38. Charges

كل خدمة حصل عليها المريض.

```sql
CREATE TABLE charges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    patient_id BIGINT UNSIGNED NOT NULL,

    encounter_id BIGINT UNSIGNED NOT NULL,

    service_id BIGINT UNSIGNED NULL,

    description VARCHAR(255) NOT NULL,

    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,

    unit_price DECIMAL(12,2) NOT NULL,

    discount DECIMAL(12,2) NOT NULL DEFAULT 0,

    total DECIMAL(12,2) NOT NULL,

    source_type VARCHAR(100) NULL,
    source_id BIGINT UNSIGNED NULL,

    created_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (patient_id)
        REFERENCES patients(id),

    FOREIGN KEY (encounter_id)
        REFERENCES encounters(id),

    FOREIGN KEY (service_id)
        REFERENCES services(id),

    FOREIGN KEY (created_by)
        REFERENCES users(id)
);
```

---

# 39. Invoices

```sql
CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    patient_id BIGINT UNSIGNED NOT NULL,

    encounter_id BIGINT UNSIGNED NULL,

    invoice_number VARCHAR(50) NOT NULL UNIQUE,

    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,

    discount DECIMAL(12,2) NOT NULL DEFAULT 0,

    tax DECIMAL(12,2) NOT NULL DEFAULT 0,

    total DECIMAL(12,2) NOT NULL DEFAULT 0,

    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,

    balance DECIMAL(12,2) NOT NULL DEFAULT 0,

    status ENUM(
        'draft',
        'issued',
        'partially_paid',
        'paid',
        'cancelled'
    ) DEFAULT 'draft',

    issued_at DATETIME NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (patient_id)
        REFERENCES patients(id),

    FOREIGN KEY (encounter_id)
        REFERENCES encounters(id)
);
```

---

# 40. Invoice Items

```sql
CREATE TABLE invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    invoice_id BIGINT UNSIGNED NOT NULL,

    charge_id BIGINT UNSIGNED NULL,

    description VARCHAR(255) NOT NULL,

    quantity DECIMAL(12,2) DEFAULT 1,

    unit_price DECIMAL(12,2) NOT NULL,

    discount DECIMAL(12,2) DEFAULT 0,

    total DECIMAL(12,2) NOT NULL,

    FOREIGN KEY (invoice_id)
        REFERENCES invoices(id)
        ON DELETE CASCADE,

    FOREIGN KEY (charge_id)
        REFERENCES charges(id)
);
```

---

# 41. Payments

```sql
CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    invoice_id BIGINT UNSIGNED NOT NULL,

    receipt_number VARCHAR(50) NOT NULL UNIQUE,

    amount DECIMAL(12,2) NOT NULL,

    method ENUM(
        'cash',
        'card',
        'bank_transfer',
        'insurance',
        'other'
    ) NOT NULL,

    received_by BIGINT UNSIGNED NOT NULL,

    paid_at DATETIME NOT NULL,

    notes TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (invoice_id)
        REFERENCES invoices(id),

    FOREIGN KEY (received_by)
        REFERENCES users(id)
);
```

---

# 42. Notifications

Laravel عنده Notifications جاهزة، لكن يمكن الاحتفاظ بجدول مخصص للأحداث الطبية.

مثلاً:

```text
New Lab Result
New Radiology Result
Prescription Ready
Low Stock
Critical Result
Patient Transfer
```

```sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id BIGINT UNSIGNED NOT NULL,

    type VARCHAR(100) NOT NULL,

    title VARCHAR(255) NOT NULL,

    message TEXT NULL,

    entity_type VARCHAR(100) NULL,

    entity_id BIGINT UNSIGNED NULL,

    read_at DATETIME NULL,

    created_at TIMESTAMP NULL,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);
```

---

# 43. Audit Logs

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id BIGINT UNSIGNED NULL,

    hospital_id BIGINT UNSIGNED NULL,

    action VARCHAR(100) NOT NULL,

    entity_type VARCHAR(100) NULL,

    entity_id BIGINT UNSIGNED NULL,

    old_values JSON NULL,

    new_values JSON NULL,

    ip_address VARCHAR(45) NULL,

    user_agent TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_entity (
        entity_type,
        entity_id
    ),

    INDEX idx_audit_user (user_id),

    INDEX idx_audit_created (created_at),

    FOREIGN KEY (user_id)
        REFERENCES users(id),

    FOREIGN KEY (hospital_id)
        REFERENCES hospitals(id)
);
```

---

# 44. الشكل النهائي للعلاقات

هذا هو أهم شيء في التصميم:

```text
                    ┌──────────────┐
                    │   HOSPITAL   │
                    └──────┬───────┘
                           │
                    ┌──────▼───────┐
                    │ DEPARTMENTS  │
                    └──────────────┘


┌─────────────┐
│  PATIENTS   │
└──────┬──────┘
       │
       │ 1:N
       ▼
┌─────────────┐
│ ENCOUNTERS  │
└──────┬──────┘
       │
       ├───────────────┐
       │               │
       ▼               ▼
┌─────────────┐   ┌─────────────┐
│  CLINICAL   │   │   ORDERS    │
└─────────────┘   └──────┬──────┘
                         │
              ┌──────────┼───────────┐
              │          │           │
              ▼          ▼           ▼
           ┌─────┐   ┌────────┐  ┌────────┐
           │ LAB │   │RADIOLOGY│  │PHARMACY│
           └──┬──┘   └────┬───┘  └────┬───┘
              │           │           │
              ▼           ▼           ▼
           RESULTS      IMAGES      DISPENSE
              │           │           │
              └───────────┼───────────┘
                          │
                          ▼
                     ┌─────────┐
                     │ FILES   │
                     └─────────┘

                         │
                         ▼
                    ┌──────────┐
                    │ CHARGES  │
                    └────┬─────┘
                         ▼
                    ┌──────────┐
                    │ INVOICE  │
                    └────┬─────┘
                         ▼
                    ┌──────────┐
                    │ PAYMENTS │
                    └──────────┘
```

---

# 45. أهم نقطة: لا نربط الأقسام ببعض بشكل فوضوي

مثلاً:

```text
Doctor
  ↓
Order
  ↓
Radiology
```

وليس:

```text
Doctor
 ↓
RadiologyOrder
```

فقط.

لأن نفس الـOrder Engine سيخدم:

```text
Laboratory
Radiology
Pharmacy
Procedure
Consultation
Supply
```

وهذا سيجعل النظام قابلاً للتوسع.

---

# 46. رحلة البيانات الحقيقية

مثلاً مريض:

```text
MRN-000001
```

دخل إلى الأسنان.

يصبح:

```text
patients
     ↓
encounters
```

الطبيب طلب أشعة:

```text
orders
     ↓
order_items
     ↓
radiology_orders
     ↓
radiology_order_items
     ↓
radiology_studies
     ↓
radiology_reports
```

الفني رفع صورة:

```text
files
     ↓
file_links
     ↓
radiology_study
```

الدكتور كتب وصفة:

```text
prescriptions
     ↓
prescription_items
```

الصيدلية صرفت:

```text
medication_dispenses
     ↓
stock_movements
```

والحساب:

```text
charges
     ↓
invoice
     ↓
invoice_items
     ↓
payments
```

وفي النهاية:

```text
encounter
     ↓
completed
```

ويبقى كل شيء في:

```text
PATIENT MEDICAL RECORD
```

---

# 47. شيء مهم سأعدله عن التصميم السابق

هناك بعض الجداول التي **لا أنصح بتنفيذها كـCRUD مستقل منذ البداية**.

خصوصاً:

```text
orders
diagnoses
billing
inventory
documents
```

لأنها تحتاج Business Logic.

مثلاً صرف دواء لا يجب أن يكون:

```text
INSERT INTO stock_movements
```

فقط.

بل:

```text
Prescription
      ↓
Check Stock
      ↓
Select Batch
      ↓
Check Expiry
      ↓
Dispense
      ↓
Stock Movement
      ↓
Charge
      ↓
Audit
      ↓
Notification
```

وكل ذلك داخل:

```php
DB::transaction()
```

---

# 48. الـModules النهائية التي سأبني عليها Laravel

```text
app/Modules/

├── Identity
├── Hospital
├── Patients
├── Appointments
├── Encounters
├── Clinical
├── Orders
│
├── Laboratory
├── Radiology
├── Pharmacy
├── Inventory
│
├── Inpatient
├── Nursing
├── Operations
│
├── Billing
├── Documents
├── Notifications
├── Reports
├── Audit
└── Settings
```

