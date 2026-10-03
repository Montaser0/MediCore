# سكيما قاعدة البيانات

مرجع التعديل. الجداول والأعمدة هنا مطابقة لترحيلات Laravel في `back-end/app/Modules/*/Infrastructure/Persistence/Migrations` و`back-end/database/migrations`. عند أي تغيير في الجدول، عدّل الترحيل وهذا الملف معًا.

المحرك: MySQL. المفتاح الأساسي في كل جدول طبي هو `id` من نوع `bigint unsigned` ما لم يُذكر غيره.

## كيف تُقرأ الأعمدة

| الرمز | المعنى |
| --- | --- |
| مطلوب | لا يقبل `NULL` |
| اختياري | يقبل `NULL` |
| FK | مفتاح أجنبي. الحذف الافتراضي `RESTRICT` |
| CASCADE | حذف الأب يحذف الصف |
| SET NULL | حذف الأب يفرّغ العمود |
| فريد | قيد تفرّد |
| timestamps | `created_at` و`updated_at` |

`string` بدون طول يعني `varchar(255)`.

## العمود الفقري

المريض ملف دائم. الزيارة هي ما يتكرر. كل عمل سريري أو مالي أثناء الوجود في المشفى يعلّق على `encounter_id`.

```text
hospitals
  └── departments ── clinics
  └── staff ── doctors
        └── users
  └── patients                         رقم ملف واحد (mrn)
        ├── patient_contacts
        ├── patient_allergies
        ├── patient_conditions
        ├── patient_insurance
        └── encounters                 زيارة واحدة في كل دخول
              ├── appointments.encounter_id
              ├── vital_signs / clinical_notes / diagnoses / procedures
              ├── orders ── order_items
              │     ├── lab_orders ── lab_order_items ── lab_samples / lab_results
              │     └── radiology_orders ── radiology_order_items ── radiology_studies ── radiology_reports
              ├── prescriptions ── prescription_items ── medication_dispenses
              ├── admissions ── bed_assignments ── beds ── rooms ── wards
              ├── operations ── operation_team / operation_notes / operation_materials
              ├── nursing_notes / care_plans / medication_administrations
              ├── charges ── invoices ── invoice_items ── payments ── refunds
              └── timeline_events
```

روابط بلا مفتاح أجنبي (نوع + رقم):

| الجدول | العمودان | يشير إلى |
| --- | --- | --- |
| `order_items` | `service_type` + `service_id` | `lab_test` أو `radiology_service` أو `medication` أو `inventory_item` أو `service` |
| `charges` | `source_type` + `source_id` | صف المختبر أو الصرف أو الأشعة أو السرير أو العملية |
| `file_links` | `entity_type` + `entity_id` | أي كيان. `encounter_id` هنا رقم فقط بلا FK |
| `stock_movements` | `reference_type` + `reference_id` | حركة المخزون التي سببت السطر |
| `notifications` و`audit_logs` | `entity_type` + `entity_id` | الكيان موضوع التنبيه أو التدقيق |

أرقام الملفات والفواتير والزيارات تُولَّد من `number_sequences`، صف لكل مشفى ومفتاح وسنة.

---

## 1. المشفى والهيكل

### hospitals

| عمود | النوع | قيد |
| --- | --- | --- |
| name | string | مطلوب |
| code | string(50) | مطلوب، فريد |
| phone | string(50) | اختياري |
| email | string | اختياري |
| address | text | اختياري |
| city | string(100) | اختياري |
| country | string(100) | اختياري |
| logo_path | string(500) | اختياري |
| timezone | string(100) | مطلوب، افتراضي `Asia/Damascus` |
| currency | string(10) | مطلوب، افتراضي `USD` |
| is_active | boolean | مطلوب، افتراضي `true` |
| timestamps | | |

### departments

قسم جديد صف هنا، لا جدول جديد. `type`: `medical` `laboratory` `radiology` `pharmacy` `warehouse` `emergency` `surgery` `inpatient` `nursing` `administration` `support`.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب |
| name | string(150) | مطلوب |
| code | string(50) | مطلوب. فريد مع `hospital_id` |
| type | string(50) | مطلوب |
| description | text | اختياري |
| is_active | boolean | مطلوب، افتراضي `true` |
| timestamps | | |

### clinics

| عمود | النوع | قيد |
| --- | --- | --- |
| department_id | FK departments | مطلوب |
| name | string(150) | مطلوب |
| code | string(50) | مطلوب. فريد مع `department_id` |
| description | text | اختياري |
| is_active | boolean | مطلوب، افتراضي `true` |
| timestamps | | |

### staff

الموظف ليس حساب دخول.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب |
| employee_number | string(50) | مطلوب. فريد مع `hospital_id` |
| first_name | string(100) | مطلوب |
| last_name | string(100) | مطلوب |
| phone | string(50) | اختياري |
| job_title | string(150) | اختياري |
| department_id | FK departments | اختياري |
| is_active | boolean | مطلوب، افتراضي `true` |
| timestamps | | |

### doctors

صف واحد لكل موظف طبيب. `staff_id` فريد.

| عمود | النوع | قيد |
| --- | --- | --- |
| staff_id | FK staff | مطلوب، فريد |
| license_number | string(100) | اختياري |
| specialty | string(150) | اختياري |
| timestamps | | |

### users

حساب الدخول. المشفى يُستنتج من `staff.hospital_id`، وليس عمودًا على المستخدم.

| عمود | النوع | قيد |
| --- | --- | --- |
| staff_id | FK staff | اختياري |
| username | string(100) | مطلوب، فريد |
| email | string | اختياري، فريد |
| password | string | مطلوب |
| is_active | boolean | مطلوب، افتراضي `true` |
| last_login_at | timestamp | اختياري |
| remember_token | string | اختياري |
| timestamps | | |

### user_departments

صلاحية الرؤية على أكثر من قسم. فريد `(user_id, department_id)`.

| عمود | النوع | قيد |
| --- | --- | --- |
| user_id | FK users | مطلوب، CASCADE |
| department_id | FK departments | مطلوب، CASCADE |
| is_primary | boolean | مطلوب، افتراضي `false` |
| timestamps | | |

### number_sequences

فريد `(hospital_id, sequence_key, period)`.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب |
| sequence_key | string(50) | مطلوب |
| period | string(10) | مطلوب |
| current_value | bigint unsigned | مطلوب، افتراضي `0` |
| timestamps | | |

---

## 2. المريض

### patients

المريض ملف دائم برقم `mrn` واحد. العودة بعد أيام أو أشهر لا تفتح ملفاً جديداً: التسجيل يرفض الرقم الوطني أو تطابق الاسم مع الهاتف أو تاريخ الميلاد، والزيارة الجديدة تُضاف على `encounters` لنفس `patient_id`. فريد `(hospital_id, mrn)`. فيه `deleted_at`.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب |
| mrn | string(50) | مطلوب |
| first_name | string(100) | مطلوب |
| father_name | string(100) | اختياري |
| grandfather_name | string(100) | اختياري |
| last_name | string(100) | مطلوب |
| mother_name | string(200) | اختياري |
| gender | enum | مطلوب: `male` `female` `other` |
| date_of_birth | date | اختياري |
| national_id | string(100) | اختياري. فريد مع `hospital_id` حتى لا يُفتح ملف ثانٍ لنفس الشخص |
| phone | string(50) | اختياري |
| phone_secondary | string(50) | اختياري |
| email | string | اختياري |
| address | text | اختياري |
| city | string(100) | اختياري |
| nationality | string(100) | اختياري |
| blood_type | string(10) | اختياري |
| marital_status | string(30) | اختياري |
| occupation | string(150) | اختياري |
| status | enum | مطلوب، افتراضي `active`: `active` `inactive` `deceased` |
| notes | text | اختياري |
| timestamps, deleted_at | | |

هذه الجداول الثلاثة تتبع المريض لا الزيارة، والحذف CASCADE من `patients`.

### patient_contacts

| عمود | النوع | قيد |
| --- | --- | --- |
| patient_id | FK patients | مطلوب، CASCADE |
| name | string(200) | مطلوب |
| relationship | string(100) | اختياري |
| phone | string(50) | اختياري |
| alternate_phone | string(50) | اختياري |
| is_emergency_contact | boolean | مطلوب، افتراضي `false` |
| timestamps | | |

### patient_allergies

| عمود | النوع | قيد |
| --- | --- | --- |
| patient_id | FK patients | مطلوب، CASCADE |
| allergen | string(200) | مطلوب |
| reaction | text | اختياري |
| severity | enum | مطلوب، افتراضي `unknown`: `mild` `moderate` `severe` `unknown` |
| status | enum | مطلوب، افتراضي `active`: `active` `inactive` `unknown` |
| notes | text | اختياري |
| timestamps | | |

### patient_conditions

| عمود | النوع | قيد |
| --- | --- | --- |
| patient_id | FK patients | مطلوب، CASCADE |
| condition_name | string | مطلوب |
| diagnosed_at | date | اختياري |
| status | enum | مطلوب، افتراضي `active`: `active` `resolved` `unknown` |
| notes | text | اختياري |
| timestamps | | |

---

## 3. الموعد والزيارة

### appointments

تسجيل الحضور يملأ `encounter_id`. فريد `(hospital_id, appointment_number)`.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب |
| patient_id | FK patients | مطلوب |
| appointment_number | string(50) | مطلوب |
| department_id | FK departments | اختياري |
| clinic_id | FK clinics | اختياري |
| doctor_id | FK doctors | اختياري |
| scheduled_at | datetime | مطلوب |
| duration_minutes | smallint unsigned | مطلوب، افتراضي `15` |
| status | enum | مطلوب، افتراضي `scheduled`: `scheduled` `confirmed` `checked_in` `completed` `cancelled` `no_show` |
| reason | text | اختياري |
| notes | text | اختياري |
| encounter_id | FK encounters | اختياري، SET NULL |
| created_by | FK users | اختياري |
| timestamps | | |

### appointment_status_history

`appointment_id` مطلوب CASCADE. الأعمدة: `from_status` string(30) اختياري، `to_status` string(30) مطلوب، `changed_by` FK users اختياري، `note` text اختياري، `created_at`.

### encounters

الزيارة. فريد `(hospital_id, encounter_number)`. لا تُغلق إلى `completed` إلا من `in_progress`.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب |
| patient_id | FK patients | مطلوب |
| encounter_number | string(50) | مطلوب |
| type | enum | مطلوب: `outpatient` `emergency` `inpatient` `operation` `follow_up` |
| status | enum | مطلوب، افتراضي `registered`: `registered` `waiting` `in_progress` `completed` `cancelled` |
| department_id | FK departments | اختياري |
| clinic_id | FK clinics | اختياري |
| doctor_id | FK doctors | اختياري |
| started_at | datetime | اختياري |
| ended_at | datetime | اختياري |
| reason | text | اختياري |
| chief_complaint | text | اختياري |
| discharge_instructions | text | اختياري |
| discharge_disposition | string(100) | اختياري |
| follow_up_date | date | اختياري |
| created_by | FK users | اختياري |
| timestamps | | |

### encounter_transfers

| عمود | النوع | قيد |
| --- | --- | --- |
| encounter_id | FK encounters | مطلوب، CASCADE |
| from_department_id | FK departments | اختياري |
| to_department_id | FK departments | مطلوب |
| from_user_id | FK users | اختياري |
| to_user_id | FK users | اختياري |
| reason | text | اختياري |
| transferred_at | datetime | مطلوب |
| status | enum | مطلوب، افتراضي `pending`: `pending` `accepted` `completed` `cancelled` |
| timestamps | | |

### encounter_status_history

`encounter_id` مطلوب CASCADE. الأعمدة: `from_status` string(30) اختياري، `to_status` string(30) مطلوب، `changed_by` FK users اختياري، `note` text اختياري، `created_at`.

### timeline_events

سطر لكل حدث يظهر في الخط الزمني. لا `updated_at`.

| عمود | النوع | قيد |
| --- | --- | --- |
| patient_id | FK patients | مطلوب، CASCADE |
| encounter_id | FK encounters | اختياري، CASCADE |
| department_id | FK departments | اختياري |
| actor_id | FK users | اختياري، SET NULL |
| event_type | string(100) | مطلوب |
| title | string | مطلوب |
| description | text | اختياري |
| payload | json | اختياري |
| occurred_at | timestamp | مطلوب |

---

## 4. السريري

كلها معلقة على `encounter_id` CASCADE، ما عدا قائمة التشخيصات المرجعية.

### diagnosis_codes

فريد `(system, code)`. `system` افتراضي `icd10`. الأعمدة: `code` string(30)، `title` string، `category` string(150) اختياري، `is_active` boolean افتراضي `true`، timestamps.

### vital_signs

| عمود | النوع | قيد |
| --- | --- | --- |
| encounter_id | FK encounters | مطلوب، CASCADE |
| temperature | decimal(4,1) | اختياري |
| systolic_bp | smallint unsigned | اختياري |
| diastolic_bp | smallint unsigned | اختياري |
| heart_rate | smallint unsigned | اختياري |
| respiratory_rate | smallint unsigned | اختياري |
| oxygen_saturation | decimal(5,2) | اختياري |
| weight | decimal(6,2) | اختياري |
| height | decimal(6,2) | اختياري |
| recorded_by | FK users | اختياري، SET NULL |
| recorded_at | datetime | مطلوب |
| notes | text | اختياري |
| timestamps | | |

### clinical_notes

`encounter_id` CASCADE، `author_id` FK users مطلوب، `note_type` enum افتراضي `general`: `general` `history` `examination` `progress` `discharge`، `content` longText مطلوب، timestamps.

### diagnoses

| عمود | النوع | قيد |
| --- | --- | --- |
| encounter_id | FK encounters | مطلوب، CASCADE |
| doctor_id | FK doctors | مطلوب |
| diagnosis_code_id | FK diagnosis_codes | اختياري، SET NULL |
| diagnosis_name | string | مطلوب |
| type | enum | مطلوب، افتراضي `primary`: `provisional` `primary` `secondary` `final` |
| notes | text | اختياري |
| diagnosed_at | datetime | مطلوب |
| timestamps | | |

### procedures

إجراء داخل العيادة أو السرير. عمليات غرفة العمليات في `operations`.

| عمود | النوع | قيد |
| --- | --- | --- |
| encounter_id | FK encounters | مطلوب، CASCADE |
| doctor_id | FK doctors | اختياري، SET NULL |
| name | string | مطلوب |
| code | string(50) | اختياري |
| description | text | اختياري |
| outcome | text | اختياري |
| performed_at | datetime | مطلوب |
| recorded_by | FK users | اختياري، SET NULL |
| timestamps | | |

---

## 5. الطلبات

طلب واحد لكل الأقسام. المختبر والأشعة يضيفان جداولهما فوق `orders`، ولا يوجد جدول `lab_requests` منفصل.

### orders

فريد `(hospital_id, order_number)`.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب |
| encounter_id | FK encounters | مطلوب |
| order_number | string(50) | مطلوب |
| type | enum | مطلوب: `laboratory` `radiology` `pharmacy` `procedure` `consultation` `supply` `other` |
| status | enum | مطلوب، افتراضي `pending`: `pending` `accepted` `in_progress` `completed` `cancelled` |
| priority | enum | مطلوب، افتراضي `routine`: `routine` `urgent` `stat` |
| ordered_by | FK users | مطلوب |
| department_id | FK departments | اختياري، SET NULL |
| clinical_notes | text | اختياري |
| cancellation_reason | text | اختياري |
| ordered_at | datetime | مطلوب |
| completed_at | datetime | اختياري |
| timestamps | | |

### order_items

`order_id` CASCADE. `service_type` string(100) مطلوب، `service_id` bigint اختياري، `name` string مطلوب، `quantity` decimal(12,2) افتراضي `1`، `instructions` text اختياري، `status` enum افتراضي `pending`: `pending` `in_progress` `completed` `cancelled`، timestamps.

---

## 6. المختبر

### lab_tests

`code` فريد. الأعمدة: `name`، `category` string(150) اختياري، `sample_type` string(100) اختياري، `unit` string(50) اختياري، `reference_range` string اختياري، `price` decimal(12,2) افتراضي `0`، `turnaround_hours` smallint اختياري، `is_panel` boolean افتراضي `false`، `is_active` boolean افتراضي `true`، timestamps.

### lab_test_parameters

`lab_test_id` CASCADE. `name`، `code` string(50) اختياري، `unit`، `reference_range`، `normal_low` و`normal_high` و`critical_low` و`critical_high` كلها decimal(12,4) اختيارية، `display_order` smallint افتراضي `0`، timestamps.

### lab_orders

`order_id` فريد وCASCADE إلى `orders`. timestamps فقط بالإضافة إلى `id`.

### lab_order_items

| عمود | النوع | قيد |
| --- | --- | --- |
| lab_order_id | FK lab_orders | مطلوب، CASCADE |
| lab_test_id | FK lab_tests | مطلوب |
| order_item_id | FK order_items | اختياري، SET NULL |
| status | enum | مطلوب، افتراضي `pending`: `pending` `sample_collected` `processing` `result_ready` `verified` `cancelled` |
| timestamps | | |

### lab_samples

`lab_order_item_id` CASCADE. `sample_number` string(50) فريد، `sample_type` اختياري، `collected_by` FK users اختياري SET NULL، `collected_at` و`received_at` اختياريان، `status` enum افتراضي `pending`: `pending` `collected` `received` `processing` `rejected`، `rejection_reason` text اختياري، timestamps.

### lab_results

`lab_order_item_id` فريد CASCADE. `result_value` text اختياري، `unit`، `reference_range`، `flag` enum افتراضي `normal`: `normal` `low` `high` `critical`، `comment` text، `entered_by` و`verified_by` FK users اختياري SET NULL، `entered_at` و`verified_at` اختياريان، `status` enum افتراضي `draft`: `draft` `final`، timestamps.

### lab_result_values

فريد `(lab_result_id, lab_test_parameter_id)`. `value` string اختياري، `unit`، `reference_range`، `flag` بنفس قيم نتيجة التحليل، timestamps. الأبوان CASCADE / مطلوب.

---

## 7. الأشعة

الصور نفسها تُخزَّن في `files` و`file_links`، لا في جدول صور خاص.

### radiology_services

`code` فريد. `name`، `modality` string(100) اختياري، `description`، `preparation_instructions`، `price` decimal(12,2) افتراضي `0`، `requires_contrast` boolean افتراضي `false`، `is_active` افتراضي `true`، timestamps.

### radiology_orders

`order_id` فريد CASCADE. `clinical_indication` text اختياري، timestamps.

### radiology_order_items

`radiology_order_id` CASCADE، `radiology_service_id` مطلوب، `order_item_id` اختياري SET NULL، `status` enum افتراضي `pending`: `pending` `scheduled` `in_progress` `completed` `reported` `cancelled`، timestamps.

### radiology_studies

| عمود | النوع | قيد |
| --- | --- | --- |
| radiology_order_id | FK radiology_orders | مطلوب، CASCADE |
| radiology_order_item_id | FK radiology_order_items | اختياري، SET NULL |
| study_number | string(100) | مطلوب، فريد |
| accession_number | string(100) | اختياري |
| study_instance_uid | string | اختياري |
| performed_by | FK users | اختياري، SET NULL |
| performed_at | datetime | اختياري |
| image_count | smallint unsigned | مطلوب، افتراضي `0` |
| technician_notes | text | اختياري |
| status | enum | مطلوب، افتراضي `pending`: `pending` `in_progress` `completed` `cancelled` |
| timestamps | | |

### radiology_reports

`study_id` فريد CASCADE. `findings` و`impression` longText اختياريان، `is_critical` boolean افتراضي `false`، `reported_by` و`verified_by` FK users اختياري SET NULL، `reported_at` و`verified_at` اختياريان، `status` enum افتراضي `draft`: `draft` `final`، timestamps.

---

## 8. الصيدلية

### medication_categories

`name` string(150) فريد، `code` string(50) اختياري، `description` اختياري، `is_controlled` boolean افتراضي `false`، timestamps.

### medications

`inventory_item_id` يُضاف بمفتاح أجنبي لاحقًا إلى `inventory_items` مع SET NULL.

| عمود | النوع | قيد |
| --- | --- | --- |
| name | string | مطلوب |
| generic_name | string | اختياري |
| strength | string(100) | اختياري |
| dosage_form | string(100) | اختياري |
| route | string(100) | اختياري |
| barcode | string(100) | اختياري |
| category | string(150) | اختياري |
| medication_category_id | FK medication_categories | اختياري، SET NULL |
| inventory_item_id | FK inventory_items | اختياري، SET NULL |
| price | decimal(12,2) | مطلوب، افتراضي `0` |
| requires_prescription | boolean | مطلوب، افتراضي `true` |
| is_active | boolean | مطلوب، افتراضي `true` |
| timestamps | | |

### prescriptions

فريد `(hospital_id, prescription_number)`.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب |
| encounter_id | FK encounters | مطلوب |
| prescription_number | string(50) | مطلوب |
| doctor_id | FK doctors | مطلوب |
| order_id | FK orders | اختياري، SET NULL |
| status | enum | مطلوب، افتراضي `draft`: `draft` `active` `partially_dispensed` `dispensed` `cancelled` |
| notes | text | اختياري |
| cancellation_reason | text | اختياري |
| prescribed_at | datetime | مطلوب |
| timestamps | | |

### prescription_items

`prescription_id` CASCADE، `medication_id` مطلوب. `dose` و`frequency` و`duration` و`route` كلها string(100) اختيارية، `quantity` decimal(12,2) اختياري، `dispensed_quantity` decimal(12,2) افتراضي `0`، `instructions` text اختياري، timestamps.

### medication_dispenses

فريد `(hospital_id, dispense_number)`.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب |
| dispense_number | string(50) | مطلوب |
| prescription_id | FK prescriptions | اختياري، SET NULL |
| prescription_item_id | FK prescription_items | اختياري، SET NULL |
| encounter_id | FK encounters | اختياري، SET NULL |
| medication_id | FK medications | مطلوب |
| warehouse_id | FK warehouses | مطلوب |
| inventory_batch_id | FK inventory_batches | اختياري، SET NULL |
| quantity | decimal(12,2) | مطلوب |
| unit_price | decimal(12,2) | مطلوب، افتراضي `0` |
| total_price | decimal(12,2) | مطلوب، افتراضي `0` |
| batch_number | string(100) | اختياري |
| batch_expiry_date | date | اختياري |
| dispensed_by | FK users | اختياري، SET NULL |
| dispensed_at | datetime | مطلوب |
| notes | text | اختياري |
| timestamps | | |

---

## 9. المستودع

### warehouses

فريد `(hospital_id, code)`. `department_id` اختياري SET NULL، `name` string(150)، `code` string(50)، `type` string(50) اختياري، `is_pharmacy` boolean افتراضي `false`، `is_active` افتراضي `true`، timestamps.

### inventory_items

`sku` string(100) فريد. `name`، `barcode` اختياري، `category` اختياري، `unit` string(50) مطلوب، `item_type` enum مطلوب: `medicine` `medical_supply` `equipment` `general`، `minimum_stock` و`reorder_quantity` decimal(12,2) افتراضي `0`، `is_batch_tracked` افتراضي `true`، `is_active` افتراضي `true`، timestamps.

### inventory_batches

فريد `(inventory_item_id, warehouse_id, batch_number)` باسم `uq_batch_item_warehouse`. `batch_number` string(100)، `expiry_date` اختياري، `quantity` و`unit_cost` decimal(12,2) افتراضي `0`، timestamps.

### stock_levels

فريد `(inventory_item_id, warehouse_id)`. `quantity` و`reserved_quantity` decimal(12,2) افتراضي `0`، timestamps.

### stock_movements

دفتر لا يُحدَّث. `type` enum: `purchase` `sale` `dispense` `transfer_in` `transfer_out` `adjustment` `return` `expired`. `quantity` decimal(12,2) مطلوب، `balance_after` اختياري، `batch_id` FK اختياري SET NULL، `reference_type` و`reference_id` اختياريان، `performed_by` FK users اختياري SET NULL، `notes` اختياري، `created_at` فقط.

---

## 10. التنويم

### wards

فريد `(hospital_id, code)`. `department_id` مطلوب، `name` string(150)، `code` string(50)، `type` string(50) اختياري، `floor` int اختياري، `daily_rate` decimal(12,2) افتراضي `0`، `is_active` افتراضي `true`. `hospital_id` CASCADE.

### rooms

`ward_id` CASCADE. فريد `(ward_id, room_number)`. `room_number` string(50)، `room_type` اختياري، `capacity` int افتراضي `1`، `daily_rate` اختياري، `is_active` افتراضي `true`.

### beds

`room_id` CASCADE. فريد `(room_id, bed_number)`. `bed_number` string(50)، `status` string(30) افتراضي `available`، `notes` اختياري.

### admissions

زيارة داخلية واحدة لكل encounter: `encounter_id` فريد. `admission_number` فريد.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب، CASCADE |
| patient_id | FK patients | مطلوب |
| encounter_id | FK encounters | مطلوب، فريد |
| admission_number | string(50) | مطلوب، فريد |
| department_id | FK departments | اختياري |
| attending_doctor_id | FK users | اختياري. هذا مستخدم لا صف `doctors` |
| admitted_by | FK users | مطلوب |
| admitted_at | datetime | مطلوب |
| expected_discharge_at | datetime | اختياري |
| discharged_at | datetime | اختياري |
| status | string(30) | مطلوب، افتراضي `admitted`. القيم المستخدمة: `admitted` `transferred` `discharged` `cancelled` |
| discharge_type | string(30) | اختياري: `routine` `against_medical_advice` `transfer_to_another_facility` `death` `absconded` |
| admission_reason | text | اختياري |
| discharge_summary | longText | اختياري. مطلوب في الخروج الروتيني من منطق التطبيق |
| discharged_by | FK users | اختياري |
| timestamps | | |

### bed_assignments

`admission_id` CASCADE، `bed_id` مطلوب. `assigned_at` مطلوب، `released_at` اختياري (السطر المفتوح هو التعيين الحالي)، `assigned_by` FK users اختياري، `reason` text اختياري، timestamps.

---

## 11. العمليات

### operation_types

`code` فريد. `name`، `specialty` string(100) اختياري، `estimated_duration_minutes` int افتراضي `60`، `risk_level` string(30) افتراضي `moderate`، `price` decimal(12,2) افتراضي `0`، `description` اختياري، `is_active` افتراضي `true`.

### operation_theatres

`hospital_id` CASCADE. فريد `(hospital_id, code)`. `name` string(150)، `code` string(50)، `is_active` افتراضي `true`.

### operations

`operation_number` فريد.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب، CASCADE |
| patient_id | FK patients | مطلوب |
| encounter_id | FK encounters | مطلوب |
| operation_type_id | FK operation_types | مطلوب |
| theatre_id | FK operation_theatres | اختياري |
| operation_number | string(50) | مطلوب، فريد |
| status | string(30) | مطلوب، افتراضي `scheduled` |
| scheduled_at | datetime | مطلوب |
| estimated_duration_minutes | int | مطلوب، افتراضي `60` |
| started_at | datetime | اختياري |
| ended_at | datetime | اختياري |
| pre_op_diagnosis | text | اختياري |
| anesthesia_type | string(50) | اختياري |
| consent_obtained | boolean | مطلوب، افتراضي `false` |
| consent_obtained_at | datetime | اختياري |
| pre_op_notes | text | اختياري |
| post_op_diagnosis | text | اختياري |
| post_op_instructions | text | اختياري |
| complications | text | اختياري |
| outcome | string(50) | اختياري |
| follow_up_date | date | اختياري |
| cancellation_reason | text | اختياري |
| created_by | FK users | اختياري |
| timestamps | | |

### operation_team

فريد `(operation_id, user_id, role)`. `operation_id` CASCADE، `user_id` مطلوب، `role` string(50) مطلوب (`surgeon` `assistant_surgeon` `anesthetist` `scrub_nurse` `circulating_nurse` `technician` `observer`)، `notes` اختياري.

### operation_notes

`operation_id` CASCADE، `note_type` string(50) افتراضي `operative`، `content` longText مطلوب، `created_by` FK users مطلوب.

### operation_materials

`operation_id` CASCADE، `inventory_item_id` مطلوب، `quantity` decimal(12,3) مطلوب، `unit_cost` decimal(12,2) افتراضي `0`، `recorded_by` FK users اختياري.

---

## 12. التمريض

### nursing_notes

`hospital_id` CASCADE، `patient_id` و`encounter_id` مطلوبان، `admission_id` اختياري، `note_type` string(50) افتراضي `progress`، `shift` string(20) اختياري، `content` longText مطلوب، `created_by` FK users مطلوب.

### care_plans

`hospital_id` CASCADE، `patient_id` و`encounter_id` مطلوبان، `admission_id` اختياري، `title` string، `problem` text مطلوب، `goal` و`interventions` و`evaluation` اختيارية، `status` string(30) افتراضي `active`، `start_date` مطلوب، `target_date` و`resolved_date` اختياريان، `created_by` مطلوب.

### medication_administrations

جرعة مجدولة أو معطاة أو مرفوضة على نفس الصف.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب، CASCADE |
| patient_id | FK patients | مطلوب |
| encounter_id | FK encounters | مطلوب |
| prescription_item_id | FK prescription_items | اختياري |
| medication_id | FK medications | مطلوب |
| scheduled_at | datetime | اختياري |
| administered_at | datetime | اختياري |
| dose | string(100) | اختياري |
| route | string(100) | اختياري |
| status | string(30) | مطلوب، افتراضي `due` |
| reason | text | اختياري |
| notes | text | اختياري |
| administered_by | FK users | اختياري |
| timestamps | | |

---

## 13. الفوترة

### services

لائحة أسعار ما لا يملك سعره جدول المختبر أو الأشعة أو الدواء. `code` فريد. `name`، `category` string(100) اختياري، `price` decimal(12,2) افتراضي `0`، `is_taxable` افتراضي `false`، `is_active` افتراضي `true`.

### charges

يُنشأ عند تنفيذ العمل. `invoice_id` مفتاح أجنبي إلى `invoices` مع SET NULL، ويُضاف بعد إنشاء جدول الفواتير.

| عمود | النوع | قيد |
| --- | --- | --- |
| hospital_id | FK hospitals | مطلوب |
| patient_id | FK patients | مطلوب |
| encounter_id | FK encounters | مطلوب |
| service_id | FK services | اختياري، SET NULL |
| invoice_id | FK invoices | اختياري، SET NULL |
| description | string | مطلوب |
| quantity | decimal(12,2) | مطلوب، افتراضي `1` |
| unit_price | decimal(12,2) | مطلوب |
| discount | decimal(12,2) | مطلوب، افتراضي `0` |
| total | decimal(12,2) | مطلوب |
| source_type | string(100) | اختياري |
| source_id | bigint | اختياري |
| created_by | FK users | اختياري، SET NULL |
| timestamps | | |

### invoices

فريد `(hospital_id, invoice_number)`. `encounter_id` اختياري SET NULL. المبالغ كلها decimal(12,2) افتراضي `0`: `subtotal` `discount` `tax` `total` `paid_amount` `balance`. `status` enum افتراضي `draft`: `draft` `issued` `partially_paid` `paid` `cancelled`. `notes` اختياري، `issued_at` اختياري، `created_by` اختياري SET NULL.

### invoice_items

`invoice_id` CASCADE، `charge_id` اختياري SET NULL، `description` مطلوب، `quantity` افتراضي `1`، `unit_price` و`discount` و`total` decimal(12,2).

### payments

فريد `(hospital_id, receipt_number)`. `invoice_id` مطلوب، `amount` decimal(12,2)، `method` enum: `cash` `card` `bank_transfer` `insurance` `other`، `reference` string(100) اختياري، `received_by` FK users مطلوب، `paid_at` مطلوب، `notes` اختياري.

### refunds

`payment_id` مطلوب، `amount` decimal(12,2)، `reason` string(500) مطلوب، `refunded_by` FK users مطلوب، `refunded_at` مطلوب.

### insurance_companies

`name`، `code` string(50) فريد، `phone` و`email` اختياريان، `default_coverage_percentage` decimal(5,2) افتراضي `0`، `is_active` افتراضي `true`.

### patient_insurance

`patient_id` CASCADE، `insurance_company_id` مطلوب، `policy_number` string(100) مطلوب، `member_number` اختياري، `coverage_percentage` decimal(5,2) افتراضي `0`، `valid_from` و`valid_to` اختياريان، `is_primary` افتراضي `true`، `is_active` افتراضي `true`.

---

## 14. الملفات والتنبيهات والتدقيق والإعدادات

### files

`uuid` فريد. `original_name`، `stored_name`، `disk` string(50)، `path` string(1000)، `mime_type` string(150) اختياري، `extension` string(20) اختياري، `size` bigint اختياري، `checksum` string(128) اختياري، `uploaded_by` FK users اختياري SET NULL، timestamps، `deleted_at`.

### file_links

`file_id` CASCADE. `entity_type` string(100) و`entity_id` bigint مطلوبان. `category` اختياري. `patient_id` اختياري CASCADE. `encounter_id` bigint اختياري **بدون** مفتاح أجنبي. `created_at` فقط.

### notifications

`user_id` CASCADE. `type` string(100)، `title`، `message` text اختياري، `entity_type` و`entity_id` اختياريان، `payload` json اختياري، `read_at` اختياري، `created_at`. لا `updated_at`.

### audit_logs

إلحاق فقط. `user_id` اختياري SET NULL، `hospital_id` اختياري، `action` string(100) مطلوب، `entity_type` و`entity_id` اختياريان، `old_values` و`new_values` json اختياريان، `ip_address` string(45)، `user_agent` text، `created_at`.

### settings

`hospital_id` اختياري CASCADE؛ القيمة `NULL` تعني إعدادًا لكل المشافي. فريد `(hospital_id, group, key)`. `group` string(100) افتراضي `general`، `key` string(150)، `value` json اختياري، `type` string(30) افتراضي `string`، `description` اختياري.

---

## 15. الهوية وجداول Laravel

الفرق (teams) في Spatie **مغلقة**. لا عمود `team_id`.

| جدول | الأعمدة المهمة |
| --- | --- |
| permissions | `name`، `guard_name`. فريد `(name, guard_name)` |
| roles | `name`، `guard_name`. فريد `(name, guard_name)` |
| role_has_permissions | مفتاح `(permission_id, role_id)` وكلاهما CASCADE |
| model_has_roles | `role_id`، `model_type`، `model_id`. المفتاح يضم الثلاثة. CASCADE على الدور |
| model_has_permissions | `permission_id`، `model_type`، `model_id`. CASCADE على الصلاحية |
| personal_access_tokens | Sanctum: `tokenable_type`، `tokenable_id`، `name`، `token` فريد طول 64، `abilities`، `last_used_at`، `expires_at` |
| password_reset_tokens | `email` مفتاح أساسي، `token`، `created_at` |
| sessions | `id` مفتاح نصي، `user_id` اختياري بلا FK، `ip_address`، `user_agent`، `payload`، `last_activity` |
| jobs / job_batches / failed_jobs | طوابير Laravel الافتراضية |
| cache / cache_locks | تخزين Laravel المؤقت |

---

## عند إضافة جدول

1. علّق العمل السريري أو المالي على `encounter_id`، وبيانات المريض الدائمة على `patient_id` فقط.
2. طلب قسم جديد يمر عبر `orders` و`order_items` ثم جدول تفصيلي واحد، لا جدول طلبات موازٍ.
3. المرفق يمر عبر `files` و`file_links`.
4. الرقم البشري (ملف، زيارة، فاتورة، عملية) يمر عبر `number_sequences`.
5. حدّث هذا الملف في نفس التعديل الذي يضيف الترحيل.
