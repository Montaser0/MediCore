# مسار المريض — من الدخول حتى الخروج

هذا الملف يطابق الواجهة مع الـ API. المريض يبقى على رقم ملف واحد، وكل ما يحدث بعده يُربط بزيارة مفتوحة.

## الخطوات

1. **فتح الأضبارة** من تسجيل المرضى: `POST /api/patients`  
   يرجع رقم الملف `MRN`. لا يُنشأ ملف جديد عند كل زيارة.

2. **تعيين القسم والطبيب** من شريط ملف المريض نفسه، حتى لو عاد بعد شهر: `POST /api/encounters` ثم `POST /api/encounters/{id}/start`  
   الحقول: `patient_id`، `type`، `department_id`، `clinic_id`، `doctor_id`، `chief_complaint`.  
   لا يُنشأ رقم ملف جديد. `POST /api/patients` يرفض إذا طابق الرقم الوطني، أو الاسم مع الهاتف، أو الاسم مع اسم الأب وتاريخ الميلاد ملفاً موجوداً، ويرجع `patient_id` و`mrn` للملف الأصلي. التحاليل والصور تبقى مربوطة بزيارات هذا الملف.

3. **موعد مسبق** اختياري: `POST /api/appointments` ثم `POST /api/appointments/{id}/check-in`  
   تسجيل الحضور يفتح زيارة ويربطها بالموعد.

4. **ما يُسجل داخل الزيارة المفتوحة** من أزرار الملف:

| الإجراء في الملف | الطلب |
| --- | --- |
| علامات حيوية | `POST /api/encounters/{id}/vitals` |
| ملاحظة سريرية | `POST /api/encounters/{id}/notes` |
| تشخيص | `POST /api/encounters/{id}/diagnoses` |
| إجراء | `POST /api/encounters/{id}/procedures` |
| مختبر | `POST /api/orders` بالنوع `laboratory` |
| أشعة | `POST /api/orders` بالنوع `radiology` |
| دواء | `POST /api/pharmacy/prescriptions` |
| تمريض | `POST /api/encounters/{id}/nursing-notes` |
| تنويم | `POST /api/inpatient/admissions` |
| عملية | `POST /api/operations` |
| أجر | `POST /api/billing/charges` |
| تحويل لقسم آخر | `POST /api/encounters/{id}/transfer` |

5. **الخروج**  
   إنهاء التنويم: `POST /api/inpatient/admissions/{id}/discharge` مع `discharge_summary`.  
   إغلاق الزيارة: `POST /api/encounters/{id}/close` مع تعليمات الخروج. الزيارة يجب أن تكون `in_progress`؛ الواجهة تبدأ المعاينة ثم تغلقها.

## ماذا يعرض الملف

`GET /api/patients/{id}` يعيد الحساسية والأمراض وجهات الاتصال.  
`GET /api/patients/{id}/file` يعيد أقسام السجل بهذه المفاتيح:

`encounters`، `clinical`، `orders`، `laboratory`، `radiology`، `pharmacy`، `nursing`، `admissions`، `operations`، `billing`، `documents`.

قائمة انتظار الطبيب والملف السريري يفتحان نفس الملف: `GET /api/encounters/queue` ثم الانتقال إلى `/dashboard/patients/{id}`.

تمريض الجرعات المستحقة: `GET /api/nursing/worklist` والحقل `doses` يتضمن اسم المريض ورقم الملف.
