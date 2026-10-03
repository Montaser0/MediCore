أكيد. هذا PRD كامل ومصمم ليكون **وثيقة تأسيس فعلية للمشروع**، وليس مجرد وصف عام. سأفترض أن النظام سيكون Multi-Department، وأن كل الأقسام تتعامل مع **نفس ملف المريض ونفس الزيارة**، مع إمكانية تشغيله داخل شبكة المشفى أو على Cloud.

# PRD — نظام إدارة المشفى المتكامل

### Hospital Information & Patient Management System — HIS

**الإصدار:** 1.0
**الحالة:** Product Requirements Document
**نوع النظام:** Hospital Information System / HIS
**المنصة:** Web Application
**اللغات:** العربية أولاً + الإنجليزية لاحقاً
**اتجاه الواجهة:** RTL / LTR
**التشغيل:** Local Server / Private Cloud / Public Cloud
**Backend:** Laravel
**Frontend:** Next.js
**Database:** MySQL
**File Storage:** Local Storage / S3-compatible Object Storage
**Authentication:** Laravel Sanctum
**API:** REST API
**UI:** Tailwind CSS + shadcn/ui أو نظام UI كلاسيكي مخصص

---

# 1. فكرة المشروع

النظام عبارة عن منصة مركزية لإدارة المشفى بالكامل، يكون فيها **المريض هو المحور الرئيسي لكل العمليات**.

من لحظة وصول المريض إلى المشفى:

```text
Reception
    ↓
Patient Registration
    ↓
Medical File
    ↓
Visit / Encounter
    ↓
Department / Clinic
    ↓
Doctor
    ↓
Orders
 ┌──┼────────┬──────────┐
 ↓  ↓        ↓          ↓
Lab Radiology Pharmacy  Other
 ↓   ↓        ↓
Results Images Medication
 └───┴────────┴──────────┘
            ↓
        Doctor Review
            ↓
       Diagnosis
            ↓
       Treatment
            ↓
       Billing
            ↓
        Discharge
            ↓
      Medical Archive
```

التنفيذ الحالي لهذا المسار موصوف في [مسار المريض](patient-journey.md): التسجيل يفتح رقم الملف، والزيارة تربط المريض بقسم وطبيب، وكل طلب أو ملاحظة أو تنويم أو خروج يُحفظ على نفس الملف.

الهدف أن لا تكون المعلومات موزعة بين أقسام منفصلة.

بل تكون جميع المعلومات مرتبطة بـ:

**Patient → Medical Record → Encounter → Orders → Results → Billing → Archive**

---

# 2. الرؤية

إنشاء نظام يسمح للمشفى بإدارة:

* المرضى
* الملفات الطبية
* الزيارات
* الأطباء
* العيادات
* العمليات
* التنويم
* المختبر
* الأشعة
* الصيدلية
* المستودع
* الأدوية
* المخزون
* الفواتير
* المدفوعات
* التقارير
* الأرشيف الطبي
* المستخدمين والصلاحيات
* المرفقات والصور والوثائق

من خلال نظام واحد.

---

# 3. المبدأ الأساسي للنظام

أهم مبدأ في المشروع:

> **أي معلومة تخص المريض يجب أن تكون قابلة للوصول من ملفه الطبي، وفق صلاحيات المستخدم.**

مثلاً:

الدكتور يفتح ملف المريض:

```text
محمد أحمد
رقم الملف: 000154

├── المعلومات الشخصية
├── الزيارات
├── التشخيصات
├── الوصفات
├── التحاليل
├── الأشعة
├── العمليات
├── الأدوية
├── الحساسية
├── الملاحظات الطبية
├── الملفات والصور
├── الفواتير
├── المدفوعات
└── السجل الزمني
```

---

# 4. Patient Master Record

كل مريض يحصل على:

```text
Patient ID
Medical Record Number
```

مثلاً:

```text
MRN-2026-000154
```

ولا يتم إنشاء ملف جديد عند كل زيارة.

بل:

```text
Patient
   │
   ├── Encounter #1
   ├── Encounter #2
   ├── Encounter #3
   └── Encounter #4
```

وهذا مهم جداً للأرشفة الطبية.

---

# 5. تسجيل المريض

## بيانات المريض

* رقم الملف
* الاسم الأول
* اسم الأب
* اسم العائلة
* اسم الأم
* الجنس
* تاريخ الميلاد
* العمر
* رقم الهوية
* رقم الهاتف
* العنوان
* المدينة
* الجنسية
* المهنة
* جهة التأمين
* رقم التأمين
* شخص للطوارئ
* رقم شخص الطوارئ
* صلة القرابة

## معلومات طبية أساسية

* فصيلة الدم
* الحساسية
* أمراض مزمنة
* أدوية دائمة
* عمليات سابقة
* ملاحظات

---

# 6. استقبال المريض — Reception

واجهة الاستقبال يجب أن تكون بسيطة وسريعة.

### Dashboard

```text
مرضى اليوم
زيارات اليوم
مواعيد اليوم
مرضى بانتظار الطبيب
مرضى داخل المشفى
مرضى خرجوا
إجمالي الإيرادات
```

### عمليات الاستقبال

* البحث عن المريض
* إنشاء مريض جديد
* فتح زيارة
* حجز موعد
* تحويل المريض لقسم
* تسجيل دخول
* تسجيل خروج
* طباعة بطاقة المريض
* طباعة رقم الملف
* طباعة وصل
* عرض الزيارات السابقة

---

# 7. Encounter — زيارة المريض

هذه من أهم أجزاء النظام.

المريض الواحد قد يملك عشرات الزيارات.

مثلاً:

```text
Patient
MRN-000154

Encounter:
ENC-2026-00981

Date:
28/09/2026

Type:
Outpatient

Department:
Dental

Doctor:
Dr. Ahmad

Status:
In Progress
```

كل عملية تتم أثناء الزيارة ترتبط بالـEncounter.

مثلاً:

```text
Encounter
│
├── Diagnosis
├── Doctor Notes
├── Lab Orders
├── Radiology Orders
├── Medication Orders
├── Procedures
├── Attachments
├── Payments
└── Discharge
```

---

# 8. الأقسام

النظام يجب أن يدعم الأقسام بشكل ديناميكي.

مثلاً:

### Medical Departments

* الأسنان
* الداخلية
* الأطفال
* النسائية
* الجراحة
* الجلدية
* العينية
* الأنف والأذن
* الإسعاف
* العناية
* العمليات
* التنويم

### Service Departments

* المختبر
* الأشعة
* الصيدلية
* المستودع
* التعقيم
* بنك الدم

يمكن إضافة أقسام جديدة بدون تعديل النظام برمجياً.

---

# 9. Department Workflow

كل قسم لديه:

```text
Department
   ↓
Queue
   ↓
Patient
   ↓
Encounter
   ↓
Service
   ↓
Order
   ↓
Result
```

مثلاً الطبيب يطلب تحليل CBC.

```text
Doctor
   ↓
Lab Order
   ↓
Laboratory Queue
   ↓
Lab Technician
   ↓
Sample Collection
   ↓
Analysis
   ↓
Result
   ↓
Doctor
```

---

# 10. نظام الطلبات Orders

هذا هو قلب الربط بين الأقسام.

يجب ألا يكون النظام مجرد صفحات منفصلة.

يجب وجود نظام:

**Orders Engine**

يدعم:

* Lab Order
* Radiology Order
* Pharmacy Order
* Medication Order
* Procedure Order
* Consultation Order
* Supply Order
* Other Service Order

---

# 11. مثال الأشعة

الدكتور:

```text
Patient
↓
Create Radiology Order

X-Ray Chest
Reason:
Chest pain
Priority:
Normal
```

الأشعة تستقبل الطلب.

```text
Radiology Dashboard

Pending Orders: 12

Patient:
محمد أحمد

Requested By:
Dr. Ahmad

Study:
Chest X-Ray
```

الفني:

```text
Start Examination
↓
Upload Images
↓
Write Report
↓
Complete
```

ثم:

```text
Radiology Result
    ↓
Patient File
    ↓
Doctor
```

والدكتور يحصل على إشعار بوجود نتيجة جديدة.

---

# 12. صور الأشعة

لا أنصح بتخزين الصور الكبيرة داخل MySQL.

قاعدة البيانات تخزن:

```text
patient_id
encounter_id
order_id
file_id
file_type
storage_path
uploaded_by
created_at
```

والصورة نفسها تكون في:

```text
Storage
```

مثلاً:

```text
patients/
  000154/
    encounters/
      00981/
        radiology/
          chest-xray-01.jpg
```

---

# 13. دعم DICOM

في المرحلة المتقدمة يجب أن يكون النظام قابلاً للربط مع أجهزة الأشعة التي تستخدم:

**DICOM**

والأفضل مستقبلاً إضافة:

**PACS**

بحيث:

```text
X-Ray / CT / MRI
       ↓
    DICOM
       ↓
    PACS
       ↓
     HIS
       ↓
 Patient
```

أما MVP فيمكن البدء برفع:

* JPG
* PNG
* PDF
* WEBP

ثم إضافة DICOM لاحقاً.

---

# 14. المختبر Laboratory

## Dashboard

```text
Pending Orders
Samples Collected
Processing
Completed
Critical Results
```

## الطلب

```text
Patient
Encounter
Doctor
Test
Priority
Clinical Notes
```

مثلاً:

```text
CBC
Blood Sugar
Creatinine
Liver Function
Urine Analysis
```

---

# 15. نظام العينات

يجب ألا يكون التحليل مجرد Result.

يجب وجود:

```text
Lab Order
     ↓
Sample
     ↓
Sample Collection
     ↓
Processing
     ↓
Result
     ↓
Verification
```

مثلاً:

```text
Sample No:
LAB-2026-00125

Type:
Blood

Collected:
10:30

Collected By:
Technician
```

---

# 16. نتائج المختبر

يجب دعم:

```text
Test
Result
Unit
Reference Range
Flag
Comment
```

مثلاً:

| Test       | Result | Unit | Reference | Flag   |
| ---------- | -----: | ---- | --------- | ------ |
| WBC        |   12.5 | K/uL | 4-11      | High   |
| Hemoglobin |   13.2 | g/dL | 12-16     | Normal |

ويستطيع الطبيب رؤية النتيجة مباشرة.

---

# 17. Laboratory Verification

النتيجة تمر:

```text
Draft
↓
Technician Completed
↓
Doctor/Lab Verification
↓
Final
```

ولا يسمح بتعديل النتيجة النهائية إلا بصلاحية خاصة.

أي تعديل يجب أن يسجل في:

**Audit Log**

---

# 18. الصيدلية Pharmacy

الصيدلية مرتبطة بالوصفة الطبية.

الطبيب:

```text
Prescription

Paracetamol
500mg
3 times/day
5 days
```

الصيدلية تستقبل:

```text
Pending Prescription
```

الصيدلي:

```text
Check Stock
↓
Dispense
↓
Reduce Inventory
↓
Complete
```

---

# 19. المستودع Inventory

المستودع يجب أن يكون نظاماً مستقلاً داخل النظام.

يدعم:

* الأدوية
* المواد الطبية
* الأدوات
* المستلزمات
* الأجهزة
* الأصناف
* الموردين
* المخازن
* الوحدات
* Batch Number
* Expiry Date
* Stock In
* Stock Out
* Transfers
* Adjustments

---

# 20. إدارة المخزون

مثلاً:

```text
Paracetamol 500mg

Stock:
500 boxes

Minimum:
100

Expiry:
2027-04
```

إذا أصبح:

```text
Stock = 95
```

يظهر:

**Low Stock Alert**

---

# 21. Batch Tracking

مهم جداً للأدوية.

```text
Medicine
   ↓
Batch
   ↓
Expiry
   ↓
Quantity
```

حتى تعرف:

* أي دفعة دخلت؟
* متى تنتهي؟
* أين تم صرفها؟
* كم تبقى؟

---

# 22. العيادات

العيادة تحتوي على:

```text
Doctor Queue
```

مثلاً:

```text
Dental Clinic

Waiting:
01 Ahmed
02 Mohammed
03 Sara
```

الدكتور يضغط:

**Open Patient**

فتظهر:

```text
Patient Summary
```

---

# 23. شاشة الطبيب

يجب أن تكون الشاشة الطبية مختلفة عن شاشة الإدارة.

مثلاً:

```text
Patient Header
────────────────────────

Name
Age
Gender
MRN
Allergies
Blood Type

────────────────────────

Current Visit

Chief Complaint

Vital Signs

Medical History

Examination

Diagnosis

Treatment

Orders

Prescription

Attachments

Notes
```

---

# 24. التشخيص

يدعم:

* التشخيص الرئيسي
* التشخيص الثانوي
* التشخيص المبدئي
* التشخيص النهائي

ويفضل مستقبلاً دعم:

**ICD-10**

بحيث يمكن للطبيب اختيار التشخيص من قائمة موحدة.

---

# 25. العلامات الحيوية

يمكن تسجيل:

* Temperature
* Blood Pressure
* Heart Rate
* Respiratory Rate
* Oxygen Saturation
* Weight
* Height
* BMI

---

# 26. الوصفة الطبية

الطبيب:

```text
Medication
Dose
Frequency
Duration
Route
Instructions
```

مثلاً:

```text
Amoxicillin
500 mg
3x/day
7 days
Oral
After food
```

ثم تتحول للصيدلية.

---

# 27. العمليات

يجب وجود قسم Operations.

### قبل العملية

* Diagnosis
* Surgeon
* Anesthesia
* Consent
* Pre-op tests
* Medical history

### أثناء العملية

* Operation
* Surgeon
* Assistant
* Anesthetist
* Start time
* End time
* Notes
* Materials used

### بعد العملية

* Recovery
* Post-op diagnosis
* Instructions
* Follow-up

---

# 28. التنويم Inpatient

إذا كان المريض بحاجة إلى دخول المشفى:

```text
Admission
↓
Ward
↓
Room
↓
Bed
```

ويتم تسجيل:

* تاريخ الدخول
* الطبيب
* القسم
* الغرفة
* السرير
* التشخيص
* حالة المريض

---

# 29. Bed Management

يجب وجود:

```text
Ward A

Room 101
├── Bed 1 Occupied
├── Bed 2 Available
├── Bed 3 Occupied
└── Bed 4 Available
```

وتغيير حالة السرير:

```text
Available
Occupied
Reserved
Cleaning
Maintenance
```

---

# 30. Nursing

واجهة التمريض يمكن أن تحتوي:

* المرضى الحاليين
* العلامات الحيوية
* Medication Administration
* Nursing Notes
* Care Plans
* Tasks
* Observations

---

# 31. Patient Timeline

ميزة مهمة جداً.

كل شيء يحدث للمريض يظهر زمنياً:

```text
28 Sep 09:00
Patient Registered

28 Sep 09:20
Dental Visit

28 Sep 09:45
Diagnosis Added

28 Sep 10:00
X-Ray Ordered

28 Sep 10:20
Radiology Completed

28 Sep 10:25
Image Uploaded

28 Sep 10:30
Doctor Viewed Result

28 Sep 11:00
Prescription Created

28 Sep 11:20
Medication Dispensed

28 Sep 12:00
Patient Discharged
```

هذه الميزة ستجعل النظام قوي جداً.

---

# 32. Patient File

ملف المريض يجب أن يكون مركز النظام.

```text
Medical Record
```

يحتوي:

### Overview

### Visits

### Diagnoses

### Lab

### Radiology

### Prescriptions

### Medications

### Procedures

### Operations

### Admissions

### Discharge

### Documents

### Images

### Billing

### Payments

### Timeline

---

# 33. المستندات

يمكن رفع:

* تقارير
* وصفات
* هويات
* إحالات
* تقارير طبية
* ملفات PDF
* صور
* وثائق إدارية

مع:

```text
Document Type
Uploaded By
Date
Department
Encounter
```

---

# 34. الإشعارات

يجب وجود Notification System.

مثلاً:

```text
🔔 New Lab Result

Patient:
Ahmed

Test:
CBC

Requested by:
Dr. Ahmad
```

أو:

```text
🔔 Radiology Result Available
```

---

# 35. Real-Time

يمكن جعل الإشعارات فورية باستخدام:

**Laravel Reverb + WebSockets**

مثلاً:

الفني يرفع صورة.

فوراً تظهر عند الطبيب:

```text
🔔 New Radiology Result
```

بدون Refresh.

---

# 36. نظام الفوترة Billing

كل خدمة للمريض يمكن أن تولد:

**Charge**

مثلاً:

```text
Dental Consultation     $20
X-Ray                   $15
CBC                     $10
Medication              $8
---------------------------
Total                   $53
Paid                    $30
Remaining               $23
```

---

# 37. ربط الخدمات بالفوترة

عند تنفيذ:

```text
Lab
Radiology
Consultation
Procedure
Medication
Room
Operation
```

يمكن إنشاء تكلفة تلقائياً.

مثلاً:

```text
Radiology Order
       ↓
Completed
       ↓
Generate Charge
       ↓
Patient Invoice
```

---

# 38. الحساب النهائي للمريض

صفحة:

**Patient Financial Account**

```text
Total Charges
Payments
Discounts
Insurance
Remaining
```

ويظهر:

```text
Total:       $250
Discount:     $20
Paid:        $150
Remaining:    $80
```

---

# 39. التأمين

النظام يجب أن يكون قابلاً للتوسع للتأمين.

```text
Insurance Company
Insurance Plan
Patient Policy
Coverage
Authorization
Claim
```

لا يشترط بناء كل هذه الوظائف في MVP.

---

# 40. Discharge

عند انتهاء الزيارة:

```text
Discharge
```

يتم تسجيل:

* سبب الخروج
* التشخيص النهائي
* العلاج
* الأدوية
* تعليمات المريض
* موعد المراجعة
* الطبيب
* وقت الخروج

ويصبح:

```text
Encounter = Completed
```

---

# 41. مدة بقاء المريض

النظام يحسب تلقائياً:

```text
Admission:
09:00

Discharge:
17:30

Duration:
8h 30m
```

وبالنسبة للتنويم:

```text
Admission:
20/09

Discharge:
28/09

Length of Stay:
8 days
```

---

# 42. Audit Log

هذه من أهم أجزاء النظام.

يجب تسجيل:

```text
User
Action
Patient
Record
IP
Date
Time
Old Value
New Value
```

مثلاً:

```text
Dr. Ahmad
changed diagnosis

Old:
Dental Caries

New:
Acute Pulpitis

28/09/2026 11:42
```

ولا يجب حذف الـAudit Logs.

---

# 43. الصلاحيات

لا تعتمد فقط على Role.

الأفضل:

**RBAC + Permissions**

مثلاً:

### Reception

```text
patients.view
patients.create
appointments.create
billing.view
```

### Doctor

```text
patients.view
encounters.view
diagnosis.create
orders.create
prescriptions.create
```

### Radiology Technician

```text
radiology.orders.view
radiology.images.upload
radiology.report.create
```

### Lab Technician

```text
lab.orders.view
lab.samples.manage
lab.results.create
```

### Pharmacist

```text
prescriptions.view
medications.dispense
inventory.view
```

### Warehouse

```text
inventory.view
inventory.create
inventory.transfer
```

### Admin

```text
*
```

---

# 44. الوصول حسب القسم

ليس كل موظف يجب أن يرى كل شيء.

مثلاً:

فني الأشعة يستطيع رؤية:

```text
Patient
Relevant Encounter
Radiology Order
Radiology History
```

لكن لا يحتاج إلى رؤية:

```text
Patient Financial Account
```

إلا إذا أعطي صلاحية لذلك.

---

# 45. نظام المستخدمين

```text
Users
Roles
Permissions
Departments
Doctors
Staff
```

ويمكن للمستخدم أن يكون:

```text
User
 ↓
Employee
 ↓
Department
 ↓
Role
```

---

# 46. Dashboard الإدارة

يحتوي على:

```text
Patients Today
Appointments
Admissions
Discharges
Emergency
Lab Orders
Radiology Orders
Pending Prescriptions
Low Stock
Revenue
Outstanding Payments
```

مع Charts.

---

# 47. التقارير

النظام يجب أن يحتوي Report Engine.

### تقارير المرضى

* عدد المرضى
* المرضى الجدد
* المرضى حسب العمر
* الجنس
* الأمراض

### التقارير الطبية

* التشخيصات
* العمليات
* التحاليل
* الأشعة
* الأطباء

### التقارير المالية

* الإيرادات
* المدفوعات
* الديون
* الخصومات
* الخدمات

### المخزون

* المخزون الحالي
* المنصرف
* الداخل
* المنتهي
* القريب من الانتهاء

---

# 48. البحث

البحث يجب أن يكون سريعاً جداً.

يمكن البحث عن المريض بواسطة:

```text
MRN
Name
Phone
National ID
Date of Birth
```

ويفضل وجود Global Search:

```text
Search Patient...
```

---

# 49. QR / Barcode

يمكن إعطاء المريض:

```text
Patient Card
```

تحوي:

```text
QR Code
MRN
Patient Name
```

ويستخدم QR داخل المشفى للوصول السريع للملف بعد تسجيل الدخول والصلاحية.

---

# 50. طباعة

النظام يجب أن يدعم طباعة:

* Patient Card
* Appointment
* Lab Request
* Lab Result
* Radiology Request
* Radiology Report
* Prescription
* Invoice
* Receipt
* Discharge Summary
* Medical Report

---

# 51. Architecture

الاقتراح:

```text
                    ┌───────────────┐
                    │   Next.js     │
                    │   Frontend    │
                    └───────┬───────┘
                            │
                         REST API
                            │
                    ┌───────▼───────┐
                    │    Laravel    │
                    │    Backend    │
                    └───────┬───────┘
                            │
          ┌─────────────────┼─────────────────┐
          │                 │                 │
       MySQL            Redis            Storage
          │                 │                 │
       Database          Queue        Files/Images
```

---

# 52. Backend — Laravel

بما أنك تعرف Laravel، لا يوجد داعي لتغيير التقنية.

استخدم:

### Laravel

لـ:

* API
* Authentication
* Authorization
* Business Logic
* Validation
* Database
* Queues
* Notifications
* Events
* Jobs
* Audit Logs
* Reports

---

# 53. Laravel Packages / Components

يمكن استخدام:

```text
Laravel Sanctum
Laravel Reverb
Laravel Queues
Laravel Notifications
Laravel Policies
Laravel Events
Laravel Jobs
Laravel Scheduler
```

وللصلاحيات يمكن استخدام:

**spatie/laravel-permission**

---

# 54. Frontend — Next.js

استخدم:

**Next.js + TypeScript**

والنظام سيكون:

```text
Dashboard
Patients
Appointments
Departments
Doctors
Laboratory
Radiology
Pharmacy
Inventory
Billing
Reports
Settings
```

---

# 55. Frontend Structure

مثلاً:

```text
src/
├── app/
│   ├── dashboard/
│   ├── patients/
│   ├── appointments/
│   ├── encounters/
│   ├── laboratory/
│   ├── radiology/
│   ├── pharmacy/
│   ├── inventory/
│   ├── billing/
│   ├── reports/
│   └── settings/
│
├── components/
├── features/
├── hooks/
├── services/
├── lib/
├── types/
└── permissions/
```

---

# 56. State Management

لا تحتاج إلى تعقيد كبير.

يمكن استخدام:

```text
TanStack Query
```

لإدارة:

* API data
* caching
* mutations
* loading
* refetching

مع Zustand فقط للحالة المحلية عند الحاجة.

---

# 57. UI Design

بما أنك تريد التصميم الكلاسيكي الموجود عادةً في أنظمة المشافي، لا أنصح بتصميم Startup حديث مبالغ فيه.

التصميم:

```text
┌──────────────────────────────────────────────┐
│ Logo        Search       Notifications User │
├────────────┬─────────────────────────────────┤
│            │                                 │
│ Dashboard  │                                 │
│ Patients   │          Main Content            │
│ Visits     │                                 │
│ Doctors    │                                 │
│ Laboratory │                                 │
│ Radiology  │                                 │
│ Pharmacy   │                                 │
│ Inventory  │                                 │
│ Billing    │                                 │
│ Reports    │                                 │
│ Settings   │                                 │
│            │                                 │
└────────────┴─────────────────────────────────┘
```

ألوان هادئة.

Tables كثيرة.

Forms واضحة.

Buttons واضحة.

ولا نستخدم Cards ضخمة لكل شيء.

---

# 58. Database

MySQL مناسب جداً للمشروع.

لكن يجب تصميم Database من البداية بشكل احترافي.

المجموعات الأساسية:

```text
users
roles
permissions
departments
employees
doctors
patients
patient_contacts
patient_allergies
patient_conditions
encounters
appointments
diagnoses
vital_signs
clinical_notes
orders
order_items
lab_orders
lab_samples
lab_results
radiology_orders
radiology_reports
files
prescriptions
prescription_items
medications
inventory_items
inventory_batches
stock_movements
warehouses
admissions
wards
rooms
beds
operations
operation_notes
invoices
invoice_items
payments
insurance_companies
patient_insurance
notifications
audit_logs
```

---

# 59. أهم العلاقات

العلاقة المركزية:

```text
patients
    │
    └── encounters
            │
            ├── diagnoses
            ├── notes
            ├── orders
            │      ├── lab_orders
            │      ├── radiology_orders
            │      └── other_orders
            │
            ├── prescriptions
            ├── procedures
            ├── files
            ├── invoices
            └── payments
```

---

# 60. File System

لا تخزن:

```text
Images
PDFs
DICOM
```

داخل MySQL كـ BLOB في النسخة الأساسية.

استخدم:

```text
MySQL
+
Object/File Storage
```

### Local

```text
Ubuntu Server
    ↓
/storage
```

### Cloud

```text
S3
Cloudflare R2
MinIO
```

ويمكن جعل Laravel Storage abstraction يتعامل مع الاثنين.

---

# 61. Local Deployment

بما أن المشفى قد يريد تشغيل النظام داخلياً:

```text
Hospital LAN

PC Reception ─┐
PC Doctor ────┤
PC Lab ───────┤
PC Radiology ─┤
PC Pharmacy ──┤
PC Admin ─────┤
               │
          Network Switch
               │
          Ubuntu Server
               │
        Laravel + MySQL
```

يمكن فتح:

```text
http://hms.local
```

أو:

```text
https://hms.alumayyad.hospital
```

داخل الشبكة حسب إعداد DNS.

---

# 62. Cloud Deployment

يمكن تشغيل:

```text
Next.js
     ↓
Cloud Server

Laravel
     ↓
Cloud Server

MySQL
     ↓
Managed Database

Storage
     ↓
S3 / R2
```

لكن بالنسبة لنظام طبي، يجب تصميم الأمن والنسخ الاحتياطي والتشفير قبل الإنتاج.

---

# 63. Hybrid Architecture

الأفضل مستقبلاً دعم:

```text
Hospital Local Server
        │
        │ Secure Sync
        ↓
Cloud Backup
```

بحيث يستمر النظام داخل المشفى حتى عند انقطاع الإنترنت.

هذه ليست MVP، لكنها مهمة جداً إذا أردت تحويل المشروع لاحقاً إلى منتج تجاري.

---

# 64. Backup

يجب وجود:

### Database Backup

```text
Daily
Weekly
Monthly
```

### File Backup

للصور والتقارير.

مثلاً:

```text
Local Backup
+
External Backup
```

ويجب ألا يكون Backup على نفس القرص فقط.

---

# 65. Security

بما أن النظام طبي، الأمن ليس ميزة إضافية.

يجب تطبيق:

* HTTPS
* Password Hashing
* RBAC
* Policies
* API Authentication
* Session Management
* Audit Logs
* File Access Authorization
* Rate Limiting
* SQL Injection Protection
* XSS Protection
* CSRF protection
* Secure Headers
* Encryption where appropriate
* Backup Encryption

---

# 66. الوصول إلى ملفات المريض

مهم جداً:

لا تجعل الصورة هكذا:

```text
/storage/patient.jpg
```

بحيث يستطيع أي شخص يعرف الرابط فتحها.

الأفضل:

```text
GET /api/files/{id}
```

Laravel يتحقق:

```text
User
↓
Permission
↓
Patient Access
↓
File
```

ثم يسمح بالتحميل.

---

# 67. Audit

أي عملية حساسة:

```text
View Patient
Edit Patient
View Medical Record
Create Diagnosis
Edit Diagnosis
Upload Image
Download File
Delete File
Create Prescription
Dispense Medication
Edit Lab Result
```

يجب تسجيلها حسب مستوى الحساسية.

---

# 68. نظام الحالة Status Engine

يجب عدم استخدام النصوص العشوائية.

مثلاً Order:

```text
pending
accepted
in_progress
completed
cancelled
```

Lab:

```text
ordered
sample_pending
sample_collected
processing
result_ready
verified
```

Radiology:

```text
ordered
scheduled
in_progress
images_uploaded
reported
verified
```

---

# 69. Workflow Engine

هذه نقطة مهمة جداً.

بدلاً من كتابة:

```text
if department == lab
```

في كل مكان، أنشئ مفهوم:

```text
Order
Order Type
Order Status
Order Handler
```

وبالتالي يمكن مستقبلاً إضافة قسم جديد بسهولة.

---

# 70. مثال كامل لرحلة مريض

### 09:00

المريض يصل.

Reception:

```text
Search Patient
```

إذا كان موجوداً:

```text
Open New Encounter
```

---

### 09:10

تحويل:

```text
Dental Clinic
```

---

### 09:20

الطبيب يفتح الملف.

يكتب:

```text
Complaint:
Tooth pain
```

Diagnosis:

```text
Dental Caries
```

---

### 09:30

الطبيب يطلب:

```text
Dental X-Ray
CBC
```

---

### 09:31

المختبر يرى:

```text
New Lab Order
```

---

### 09:35

الأشعة ترى:

```text
New Radiology Order
```

---

### 09:45

الفني يرفع:

```text
X-Ray Image
```

---

### 09:46

الدكتور يحصل:

```text
🔔 New Radiology Result
```

---

### 10:00

المخبر ينهي CBC.

---

### 10:01

الطبيب يرى النتيجة.

---

### 10:15

الطبيب يكتب وصفة:

```text
Medication A
Medication B
```

---

### 10:16

الصيدلية تستقبل الطلب.

---

### 10:20

الصيدلي يصرف الدواء.

المخزون ينقص تلقائياً.

---

### 10:30

النظام يحسب:

```text
Consultation
+
Lab
+
Radiology
+
Medication
```

---

### 10:40

المريض يدفع.

---

### 10:45

Discharge.

---

### النتيجة

كل شيء محفوظ داخل:

```text
Patient
    ↓
Encounter
        ↓
Complete Medical + Financial History
```

---

# 71. صفحة Timeline

هذه الصفحة تحديداً أنصح أن تكون من أهم صفحات النظام.

```text
Patient Timeline

09:00
Patient Registered

09:10
Dental Encounter Created

09:20
Doctor Examination

09:30
Lab Order Created

09:30
Radiology Order Created

09:45
Radiology Image Uploaded

10:00
Lab Result Completed

10:15
Prescription Created

10:20
Medication Dispensed

10:40
Payment Received

10:45
Patient Discharged
```

---

# 72. MVP

لا تبدأ بكل شيء مرة واحدة.

النسخة الأولى يجب أن تحتوي:

### Phase 1

```text
Authentication
Users
Roles
Departments

Patients
Patient Files

Appointments
Encounters

Doctors
Clinics

Diagnoses
Medical Notes
Vital Signs

Lab Orders
Lab Results

Radiology Orders
Radiology Files

Prescriptions
Pharmacy

Inventory

Billing
Payments

Documents
Notifications

Audit Logs
```

هذا يعتبر MVP قوي جداً.

---

# 73. Phase 2

بعد تشغيل MVP:

```text
Inpatient
Beds
Rooms
Wards

Operations

Nursing

Insurance

Advanced Reports

Advanced Inventory

Advanced Billing

Patient Portal
```

---

# 74. Phase 3

المرحلة المتقدمة:

```text
DICOM
PACS
HL7
FHIR
External Labs
External Radiology Systems
Insurance Integration
SMS
WhatsApp Notifications
Mobile App
AI
```

---

# 75. AI مستقبلاً

بعد استقرار النظام يمكن إضافة AI.

مثلاً:

### Medical Summary

يأخذ:

```text
Visits
Diagnoses
Labs
Radiology Reports
Medications
```

ويولد:

```text
Patient Summary
```

لكن AI لا يجب أن يقرر التشخيص أو العلاج تلقائياً.

---

# 76. AI للإدارة

يمكن أن يكون:

```text
"كم مريض دخل المشفى هذا الشهر؟"

"كم تحليل CBC تم عمله؟"

"ما أكثر الخدمات استخداماً؟"

"ما الأدوية التي ستنتهي قريباً؟"

"ما إجمالي الإيرادات؟"
```

ويحول السؤال إلى Reports/Queries مع صلاحيات مناسبة.

---

# 77. API Design

مثلاً:

```text
/api/auth/login

/api/patients
/api/patients/{id}

/api/patients/{id}/encounters

/api/encounters/{id}

/api/orders
/api/orders/{id}

/api/lab/orders
/api/lab/orders/{id}

/api/radiology/orders
/api/radiology/orders/{id}

/api/prescriptions
/api/pharmacy/dispense

/api/inventory
/api/inventory/movements

/api/invoices
/api/payments
```

---

# 78. Database Rule مهمة جداً

لا تربط كل شيء مباشرة بالمريض فقط.

مثلاً لا تعمل:

```text
lab_results.patient_id
```

فقط.

الأفضل:

```text
patient_id
encounter_id
order_id
```

لأن نفس المريض يمكن أن يعمل نفس التحليل عشر مرات في زيارات مختلفة.

---

# 79. Data Integrity

استخدم:

```text
Foreign Keys
Indexes
Unique Constraints
Transactions
Soft Deletes
Enums / Status Tables
```

والعمليات الحساسة:

```text
DB::transaction()
```

خصوصاً:

```text
Dispensing
Billing
Inventory
Admission
Discharge
```

---

# 80. Performance

مع زيادة المرضى يجب استخدام:

* Database indexes
* Pagination
* Query optimization
* Eager loading
* Redis
* Queues
* File/object storage
* Background jobs

مثلاً رفع 50 صورة أشعة لا يجب أن يوقف الطلب الرئيسي.

---

# 81. Redis

استخدام Redis لـ:

```text
Cache
Queues
Sessions
Rate Limiting
Real-time features
```

سيكون مفيداً.

---

# 82. Queue

استخدم Laravel Queue لأشياء مثل:

```text
Generate PDF
Send Notification
Process Image
Backup
Generate Reports
Send SMS
Send Email
```

---

# 83. Notifications

النظام يدعم:

```text
In-App
Email
SMS
WhatsApp
```

لكن SMS وWhatsApp يمكن إضافتهم لاحقاً.

---

# 84. Multi-Hospital

إذا كنت تفكر بتحويل النظام لاحقاً إلى منتج SaaS، صممه من البداية بحيث يمكن دعم:

```text
Hospital
 ├── Departments
 ├── Users
 ├── Patients
 └── Data
```

لكن **لا أنصح ببناء Multi-Tenant SaaS في أول نسخة** إذا كان هدفك الآن تشغيله في مشفى واحد.

---

# 85. أهم الجداول — Core ERD

الصورة المنطقية للعلاقة:

```text
                    USERS
                      │
                ROLES/PERMISSIONS
                      │
                      ▼
PATIENTS ──────── ENCOUNTERS
   │                  │
   │                  ├──── DIAGNOSES
   │                  ├──── VITAL SIGNS
   │                  ├──── NOTES
   │                  │
   │                  └──── ORDERS
   │                         │
   │              ┌──────────┼───────────┐
   │              ▼          ▼           ▼
   │             LAB     RADIOLOGY    PHARMACY
   │              │          │           │
   │              ▼          ▼           ▼
   │           RESULTS    IMAGES      DISPENSE
   │
   ├──── FILES
   ├──── PRESCRIPTIONS
   ├──── ADMISSIONS
   └──── BILLING
```

---

# 86. التقنيات النهائية المقترحة

| الجزء            | التقنية                      |
| ---------------- | ---------------------------- |
| Frontend         | Next.js                      |
| Language         | TypeScript                   |
| UI               | Tailwind CSS                 |
| Components       | shadcn/ui                    |
| Backend          | Laravel                      |
| API              | REST                         |
| Authentication   | Laravel Sanctum              |
| Authorization    | Policies + Spatie Permission |
| Database         | MySQL                        |
| Cache            | Redis                        |
| Queue            | Redis + Laravel Queue        |
| Realtime         | Laravel Reverb               |
| Files            | Laravel Storage              |
| Local Storage    | Ubuntu/NAS                   |
| Cloud Storage    | S3 / R2                      |
| PDF              | Laravel PDF                  |
| Search           | MySQL initially              |
| Advanced Search  | Meilisearch later            |
| Web Server       | Nginx                        |
| OS               | Ubuntu Server                |
| Containerization | Docker optional              |
| Version Control  | Git/GitHub                   |

---

# 87. لماذا هذا Stack مناسب لك؟

لأنك أصلاً تعمل بـ:

**Laravel + Next.js + MySQL**

وبالتالي لا يوجد سبب لإضافة:

```text
Node Backend
Python Backend
MongoDB
PostgreSQL
```

من البداية.

هذا سيزيد التعقيد فقط.

الـStack المقترح قادر على بناء النظام بالكامل.

---

# 88. هيكل المشروع النهائي

```text
hospital-system/
│
├── backend/
│   └── Laravel
│
├── frontend/
│   └── Next.js
│
├── docker/
│
├── documentation/
│
└── deployment/
```

والـBackend:

```text
app/
├── Models
├── Http
│   ├── Controllers
│   ├── Requests
│   └── Resources
├── Services
├── Actions
├── Policies
├── Events
├── Listeners
├── Jobs
└── Notifications
```

أنصحك باستخدام **Services/Actions** للـbusiness logic بدلاً من وضع كل شيء داخل Controllers.

---

# 89. أهم مبدأ معماري

لا تجعل:

```text
Controller
   ↓
1000 lines of code
```

بل:

```text
Controller
   ↓
Action / Service
   ↓
Model
   ↓
Database
```

مثلاً:

```text
DispenseMedicationAction
```

تقوم بـ:

```text
Validate Prescription
↓
Check Stock
↓
Select Batch
↓
Create Dispense
↓
Create Stock Movement
↓
Create Charge
↓
Audit
↓
Notification
```

وكلها داخل Transaction.

---

# 90. مراحل تنفيذ المشروع

## المرحلة 1 — Foundation

```text
Laravel
Next.js
MySQL
Auth
Users
Roles
Permissions
Departments
```

## المرحلة 2 — Patient Core

```text
Patients
Medical Records
Appointments
Encounters
Timeline
Documents
```

## المرحلة 3 — Clinical

```text
Doctors
Clinics
Diagnoses
Notes
Vitals
Prescriptions
```

## المرحلة 4 — Laboratory

```text
Lab
Orders
Samples
Results
Verification
```

## المرحلة 5 — Radiology

```text
Radiology
Orders
Images
Reports
```

## المرحلة 6 — Pharmacy

```text
Pharmacy
Prescriptions
Dispensing
```

## المرحلة 7 — Inventory

```text
Warehouse
Stock
Batches
Expiry
Movements
```

## المرحلة 8 — Financial

```text
Services
Charges
Invoices
Payments
Discounts
```

## المرحلة 9 — Inpatient

```text
Admissions
Wards
Rooms
Beds
Nursing
Discharge
```

## المرحلة 10 — Reports & Administration

```text
Reports
Dashboards
Audit
Settings
```

---

# 91. Definition of Done

لا تعتبر الوحدة مكتملة لمجرد أن الصفحة تعمل.

مثلاً Laboratory لا يعتبر منتهياً حتى يعمل:

```text
Doctor
 ↓
Order
 ↓
Lab Queue
 ↓
Sample
 ↓
Technician
 ↓
Result
 ↓
Verification
 ↓
Doctor
 ↓
Patient Archive
 ↓
Billing
```

هذا هو الفرق بين **نظام إدارة مشفى فعلي** وبين مجموعة CRUD Pages.

---

# 92. الأولوية القصوى في التصميم

لو كنت سأبني المشروع معك، سأركز على هذه الأشياء بالترتيب:

### 1

**Patient**

### 2

**Encounter**

### 3

**Order Engine**

### 4

**Department Workflow**

### 5

**Medical Record**

### 6

**Timeline**

### 7

**Billing**

### 8

**Audit**

لأن هذه هي البنية التي ستجعل إضافة المختبر والصيدلية والأشعة والعمليات لاحقاً سهلة.

---

# 93. الشكل النهائي للنظام

في النهاية يصبح النظام بهذا الشكل:

```text
                         HOSPITAL HMS
                              │
        ┌─────────────────────┼──────────────────────┐
        │                     │                      │
   ADMINISTRATION          CLINICAL              FINANCE
        │                     │                      │
        │              ┌──────┼───────┐             │
        │              │      │       │             │
   Users/Roles       Clinics  Lab  Radiology      Billing
   Departments       Doctors  │      │             │
   Reports           Nurses   │      │          Payments
        │                     │      │             │
        └─────────────────────┼──────┘             │
                              │                     │
                           PATIENT                  │
                              │                     │
                          ENCOUNTER ────────────────┘
                              │
                  ┌───────────┼───────────┐
                  │           │           │
              Diagnosis     Orders      Files
                  │           │           │
                  │      ┌────┼────┐      │
                  │      │    │    │      │
                  │     Lab  Xray Pharmacy │
                  │      │    │    │      │
                  └──────┴────┴────┴──────┘
                              │
                           TIMELINE
                              │
                           ARCHIVE
```

## الخلاصة التقنية

**لا تحتاج لتغيير التقنيات التي تعرفها.**

البنية التي أنصح بها هي:

> **Next.js + TypeScript + Tailwind/shadcn → Laravel REST API → MySQL + Redis → Laravel Reverb → Local/S3 Storage → Nginx → Ubuntu**

والأهم من التقنية نفسها هو أن نبني **Patient → Encounter → Order → Result → Billing → Archive** كعمود فقري للنظام.

بهذا التصميم، عندما تقول لاحقاً: *أريد إضافة قسم غسيل الكلى أو الأسنان أو العمليات أو بنك الدم*، لا نعيد بناء النظام؛ نضيف **Department + Workflow + Order Types + Permissions** فوق البنية الموجودة.

**هذه الوثيقة هي PRD مستوى 1.0.** الخطوة البرمجية التالية المنطقية قبل كتابة أي كود هي تحويلها إلى **Database Schema + ERD كامل (كل جدول، كل عمود، PK/FK، العلاقات، Indexes، Enums)** ثم **API Specification** ثم تقسيم المشروع إلى مراحل تنفيذ وTasks؛ لأن هذين الجزأين سيحددان فعلياً طريقة بناء Laravel وNext.js.
