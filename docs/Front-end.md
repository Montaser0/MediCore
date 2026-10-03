إي، بما إننا عم نبني **HMS كامل** وبنفس الوقت بدنا يكون قابل للتوسع لاحقاً، أنا أنصحك يكون الـ Frontend منفصل تماماً عن Laravel API، وبنية الـ Modular Architecture تكون على مستوى **Features/Modules** وليس مجرد `components/pages`.

العمل السريري اليومي يتم من ملف المريض، لا من صفحات فارغة. التفاصيل ومقابلتها مع الـ API في [patient-journey.md](patient-journey.md).

## 1. الـ Stack المقترح

| الجزء              | التقنية                                      |
| ------------------ | -------------------------------------------- |
| Framework          | **Next.js 16 + App Router**                  |
| Language           | **TypeScript**                               |
| Styling            | **Tailwind CSS**                             |
| UI Components      | **shadcn/ui**                                |
| Icons              | **Lucide React**                             |
| Forms              | **React Hook Form**                          |
| Validation         | **Zod**                                      |
| Server State / API | **TanStack Query**                           |
| HTTP Client        | **Axios**                                    |
| Tables             | **TanStack Table**                           |
| Charts             | **Recharts**                                 |
| Date               | **date-fns**                                 |
| Global UI State    | **Zustand**                                  |
| Authentication     | Laravel Sanctum                              |
| Permissions        | Backend Laravel + frontend permission guards |
| Notifications      | Sonner                                       |
| i18n               | **next-intl**                                |
| RTL                | Arabic RTL + English LTR                     |
| Theme              | next-themes                                  |
| File Upload        | Uploader component + Laravel API             |
| Testing            | Vitest + Testing Library + Playwright        |

والأهم:

```text
Next.js
    ↓
API Client
    ↓
Laravel API
    ↓
MySQL
```

الـ Frontend **لا يتعامل مع MySQL مباشرة أبداً**.

---

# 2. البنية العامة للمشروع

أنا أقترح:

```text
hms-frontend/
│
├── app/
│   ├── [locale]/
│   │   ├── login/
│   │   └── dashboard/
│   │
│   ├── api/
│   │
│   ├── globals.css
│   └── layout.tsx
│
├── src/
│   │
│   ├── modules/
│   │
│   ├── components/
│   │
│   ├── layouts/
│   │
│   ├── lib/
│   │
│   ├── hooks/
│   │
│   ├── stores/
│   │
│   ├── providers/
│   │
│   ├── types/
│   │
│   ├── config/
│   │
│   ├── permissions/
│   │
│   └── i18n/
│
├── public/
│
├── tests/
│
├── middleware.ts
├── next.config.ts
├── package.json
└── tsconfig.json
```

لكن الجزء الأهم هو:

```text
src/modules/
```

---

# 3. الـ Modules

بما أن الـ Backend عندنا مقسم Modules، نخلي الـ Frontend مطابق تقريباً:

```text
src/modules/

├── auth/
├── dashboard/
├── patients/
├── appointments/
├── encounters/
├── clinical/
├── orders/
├── laboratory/
├── radiology/
├── pharmacy/
├── inventory/
├── inpatient/
├── nursing/
├── operations/
├── billing/
├── documents/
├── notifications/
├── reports/
├── staff/
├── settings/
└── audit/
```

كل Module يكون شبه تطبيق صغير مستقل.

---

# 4. شكل الـ Module

مثلاً `patients`:

```text
src/modules/patients/

├── components/
│   ├── PatientCard.tsx
│   ├── PatientTable.tsx
│   ├── PatientForm.tsx
│   ├── PatientHeader.tsx
│   ├── PatientSummary.tsx
│   ├── PatientTimeline.tsx
│   ├── PatientSearch.tsx
│   └── PatientStatusBadge.tsx
│
├── pages/
│   ├── PatientsPage.tsx
│   ├── PatientDetailsPage.tsx
│   └── PatientCreatePage.tsx
│
├── hooks/
│   ├── usePatients.ts
│   ├── usePatient.ts
│   ├── useCreatePatient.ts
│   └── useUpdatePatient.ts
│
├── api/
│   ├── patients.api.ts
│   └── patients.queries.ts
│
├── schemas/
│   └── patient.schema.ts
│
├── types/
│   └── patient.types.ts
│
├── utils/
│   └── patient.utils.ts
│
└── index.ts
```

هيك أي مطور يفوت على:

```text
modules/patients
```

بيلاقي كل شيء متعلق بالمرضى.

---

# 5. الـ App Router

ما بدي نحط كل الكود داخل `app`.

`app` يكون مسؤول بشكل أساسي عن **Routing**.

مثلاً:

```text
app/
└── [locale]/
    └── dashboard/
        ├── page.tsx
        │
        ├── patients/
        │   ├── page.tsx
        │   ├── new/
        │   │   └── page.tsx
        │   └── [id]/
        │       └── page.tsx
        │
        ├── appointments/
        │   └── page.tsx
        │
        ├── laboratory/
        │   └── page.tsx
        │
        ├── radiology/
        │   └── page.tsx
        │
        ├── pharmacy/
        │   └── page.tsx
        │
        └── billing/
            └── page.tsx
```

لكن الصفحة نفسها تستدعي Module:

```tsx
import { PatientsPage } from "@/modules/patients";

export default function Page() {
    return <PatientsPage />;
}
```

---

# 6. ليش هذا أفضل؟

بدل:

```text
app/patients/page.tsx
app/patients/components/...
app/patients/hooks/...
app/patients/api/...
```

نحن نفصل:

```text
app
    ↓
Routing

modules
    ↓
Business UI

components
    ↓
Reusable UI

lib
    ↓
Infrastructure
```

وهذا مهم جداً لنظام HMS كبير.

---

# 7. الـ Components المشتركة

أي Component ما له علاقة مباشرة بموديول معين يروح:

```text
src/components/
```

مثلاً:

```text
components/

├── ui/
│   ├── Button.tsx
│   ├── Input.tsx
│   ├── Select.tsx
│   ├── Dialog.tsx
│   ├── Drawer.tsx
│   ├── Card.tsx
│   ├── Badge.tsx
│   ├── Tabs.tsx
│   └── ...
│
├── data-table/
│   ├── DataTable.tsx
│   ├── DataTablePagination.tsx
│   ├── DataTableFilters.tsx
│   └── DataTableToolbar.tsx
│
├── forms/
│   ├── FormInput.tsx
│   ├── FormSelect.tsx
│   ├── FormDatePicker.tsx
│   └── FormFileUpload.tsx
│
├── patient/
│   ├── PatientMiniCard.tsx
│   └── PatientSelector.tsx
│
├── medical/
│   ├── DiagnosisBadge.tsx
│   ├── VitalSigns.tsx
│   └── MedicalStatus.tsx
│
├── loading/
├── errors/
└── feedback/
```

مثلاً:

```tsx
<PatientSelector />
```

يُستخدم في:

* الطبيب
* المختبر
* الأشعة
* الصيدلية
* الفوترة
* العمليات

---

# 8. Layout النظام

نظام المستشفى يحتاج Layout ثابت.

```text
src/layouts/

├── DashboardLayout.tsx
├── AuthLayout.tsx
├── PatientLayout.tsx
└── PrintLayout.tsx
```

الشكل:

```text
┌───────────────────────────────────────────────┐
│ Header                         User / Alerts  │
├───────────────┬───────────────────────────────┤
│               │                               │
│ Sidebar       │       Main Content            │
│               │                               │
│ Dashboard     │                               │
│ Patients      │                               │
│ Appointments  │                               │
│ Clinics       │                               │
│ Laboratory    │                               │
│ Radiology     │                               │
│ Pharmacy      │                               │
│ Inventory     │                               │
│ Billing       │                               │
│ Reports       │                               │
│               │                               │
└───────────────┴───────────────────────────────┘
```

---

# 9. Patient Workspace

هذه أهم شاشة بالنظام كله.

لما الموظف يفتح مريض:

```text
/patients/{patientId}
```

ما بدي مجرد صفحة بيانات.

بل:

```text
Patient Workspace
```

مثلاً:

```text
┌────────────────────────────────────────────────────┐
│ أحمد محمد علي                    MRN: 000123       │
│ العمر 35 | ذكر | الهاتف ...                       │
├────────────────────────────────────────────────────┤
│ Overview | Visits | Diagnoses | Lab | Radiology   │
│ Pharmacy | Documents | Billing | Timeline          │
├────────────────────────────────────────────────────┤
│                                                    │
│ Patient information                               │
│                                                    │
│ Current Encounter                                  │
│                                                    │
│ Recent Diagnoses                                   │
│                                                    │
│ Recent Lab Results                                 │
│                                                    │
└────────────────────────────────────────────────────┘
```

---

# 10. Patient Timeline

ميزة مهمة جداً.

مثلاً:

```text
Patient Timeline

2026-09-28
│
├── 09:10 Reception
│   └── Patient registered
│
├── 09:30 Internal Medicine
│   └── Dr. Ahmad examined patient
│
├── 09:45 Laboratory
│   └── CBC requested
│
├── 10:15 Laboratory
│   └── CBC completed
│
├── 10:20 Radiology
│   └── Chest X-Ray requested
│
├── 10:50 Radiology
│   └── Report completed
│
├── 11:10 Pharmacy
│   └── Prescription dispensed
│
└── 11:30 Billing
    └── Invoice paid
```

هذا الـ Component يكون:

```text
modules/patients/components/PatientTimeline.tsx
```

---

# 11. Module المختبر

مثلاً:

```text
modules/laboratory/

├── components/
│   ├── LabOrdersTable.tsx
│   ├── LabOrderDetails.tsx
│   ├── SampleCard.tsx
│   ├── ResultEntryForm.tsx
│   ├── ResultVerification.tsx
│   └── CriticalResultAlert.tsx
│
├── pages/
│   ├── LaboratoryPage.tsx
│   ├── LabOrderPage.tsx
│   └── LabResultPage.tsx
│
├── hooks/
│   ├── useLabOrders.ts
│   ├── useLabOrder.ts
│   ├── useLabResult.ts
│   └── useVerifyResult.ts
│
├── api/
│   └── laboratory.api.ts
│
├── schemas/
│   └── laboratory.schema.ts
│
└── types/
    └── laboratory.types.ts
```

---

# 12. Module الأشعة

```text
modules/radiology/

├── components/
│   ├── RadiologyOrdersTable.tsx
│   ├── StudyDetails.tsx
│   ├── StudyViewer.tsx
│   ├── RadiologyReportForm.tsx
│   ├── ImageUploader.tsx
│   └── ReportViewer.tsx
│
├── pages/
│
├── hooks/
│
├── api/
│
├── schemas/
│
└── types/
```

والـ Flow:

```text
Doctor
   ↓
Radiology Order
   ↓
Radiology Queue
   ↓
Technician
   ↓
Study
   ↓
Images
   ↓
Radiologist
   ↓
Report
   ↓
Doctor
```

---

# 13. Pharmacy

```text
modules/pharmacy/

├── components/
│   ├── PrescriptionQueue.tsx
│   ├── PrescriptionDetails.tsx
│   ├── MedicationSelector.tsx
│   ├── DispenseForm.tsx
│   ├── DispenseItems.tsx
│   └── StockWarning.tsx
│
├── pages/
├── hooks/
├── api/
├── schemas/
└── types/
```

والصيدلي لا يعمل:

```text
INSERT stock
```

من الـ Frontend.

فقط:

```text
Dispense Prescription
        ↓
Laravel API
        ↓
Transaction
        ↓
Stock Movement
        ↓
Charge
        ↓
Audit Log
```

---

# 14. Orders Engine

هذا مهم جداً.

بدل ما كل قسم يعمل نظام مختلف:

```text
Lab Request
Radiology Request
Pharmacy Request
```

الـ Frontend يتعامل مع:

```text
Order
```

ثم:

```text
Order Type

LABORATORY
RADIOLOGY
PHARMACY
PROCEDURE
CONSULTATION
SUPPLY
```

مثلاً:

```tsx
<OrderCard order={order} />
```

ثم كل Module يعرض التفاصيل الخاصة فيه.

---

# 15. API Layer

ما بدي نكتب:

```tsx
axios.get(...)
```

داخل Components.

نعمل:

```text
src/lib/api/
```

```text
api/
├── client.ts
├── auth.ts
├── errors.ts
└── endpoints.ts
```

ثم داخل Module:

```text
modules/patients/api/patients.api.ts
```

مثلاً:

```tsx
export const patientsApi = {
    getAll: async () => {
        return api.get("/patients");
    },

    getById: async (id: string) => {
        return api.get(`/patients/${id}`);
    },

    create: async (data: CreatePatientInput) => {
        return api.post("/patients", data);
    },
};
```

---

# 16. TanStack Query

وهذا سيكون مسؤول عن:

* Fetching
* Caching
* Loading
* Error
* Refetch
* Mutations
* Pagination

مثلاً:

```tsx
const {
    data,
    isLoading,
    error
} = usePatients();
```

والـ Hook:

```text
modules/patients/hooks/usePatients.ts
```

---

# 17. Forms

كل Form:

```text
React Hook Form
        +
Zod
```

مثلاً:

```text
PatientForm
      ↓
patient.schema.ts
      ↓
React Hook Form
      ↓
patientsApi.create()
      ↓
Laravel
```

---

# 18. Authentication

```text
Login
   ↓
Laravel Sanctum
   ↓
Authenticated User
   ↓
User Permissions
   ↓
Frontend Permission System
```

مثلاً:

```tsx
<Can permission="patients.create">
    <CreatePatientButton />
</Can>
```

أو:

```tsx
<Can permission="lab.results.verify">
    <VerifyResultButton />
</Can>
```

لكن **الأمان الحقيقي بالـ Laravel**.

الـ Frontend فقط يخفي الأشياء التي لا يملك المستخدم صلاحيتها.

---

# 19. Permissions

أقترح أسماء مثل:

```text
patients.view
patients.create
patients.update

appointments.view
appointments.create

encounters.view
encounters.create
encounters.update

clinical.notes.create
clinical.notes.view

laboratory.orders.view
laboratory.results.create
laboratory.results.verify

radiology.orders.view
radiology.studies.create
radiology.reports.create
radiology.reports.verify

pharmacy.prescriptions.view
pharmacy.dispensing.create

inventory.stock.view
inventory.stock.adjust

billing.invoices.view
billing.invoices.create
billing.payments.create

reports.view

users.manage
roles.manage
audit.view
```

---

# 20. Zustand

لا تستخدم Zustand لكل شيء.

نستخدمه للأشياء العامة مثل:

```text
Sidebar state
UI preferences
Selected hospital
Selected department
Current patient context
Filters that need persistence
```

أما بيانات المرضى والطلبات والنتائج:

```text
TanStack Query
```

وليس Zustand.

---

# 21. i18n

من البداية:

```text
Arabic
English
```

مثلاً:

```text
messages/

├── ar.json
└── en.json
```

والـ URL:

```text
/ar/dashboard
/en/dashboard
```

العربي:

```html
<html dir="rtl">
```

الإنكليزي:

```html
<html dir="ltr">
```

---

# 22. Responsive

النظام لازم يكون:

```text
Desktop
Tablet
Mobile
```

لكن الأولوية:

```text
Desktop → Hospital PCs
Tablet → Doctors/Nurses
Mobile → الإدارة والتنبيهات والمتابعة
```

---

# 23. Dashboard

```text
modules/dashboard/

├── components/
│   ├── StatsCards.tsx
│   ├── PatientStatistics.tsx
│   ├── AppointmentStatistics.tsx
│   ├── RevenueChart.tsx
│   ├── LaboratoryStatistics.tsx
│   ├── OccupancyChart.tsx
│   └── RecentActivity.tsx
│
├── api/
├── hooks/
└── types/
```

---

# 24. الـ Sidebar

ما نكتب Sidebar ثابت فيه كل شيء.

يكون مبني على permissions.

مثلاً:

```text
Dashboard

Patients

Appointments

Clinical
    ├── Encounters
    ├── Diagnoses
    └── Notes

Laboratory

Radiology

Pharmacy

Inventory

Inpatient
    ├── Wards
    ├── Rooms
    └── Beds

Operations

Billing

Reports

Administration
    ├── Users
    ├── Roles
    ├── Departments
    └── Settings

Audit Logs
```

والمستخدم يشوف فقط الأشياء المسموحة له.

---

# 25. Structure النهائي الذي أنصح نعتمده

```text
src/
│
├── modules/
│   │
│   ├── auth/
│   ├── dashboard/
│   │
│   ├── patients/
│   ├── appointments/
│   ├── encounters/
│   ├── clinical/
│   ├── orders/
│   │
│   ├── laboratory/
│   ├── radiology/
│   ├── pharmacy/
│   ├── inventory/
│   │
│   ├── inpatient/
│   ├── nursing/
│   ├── operations/
│   │
│   ├── billing/
│   ├── documents/
│   ├── notifications/
│   ├── reports/
│   │
│   ├── staff/
│   ├── settings/
│   └── audit/
│
├── components/
│   ├── ui/
│   ├── forms/
│   ├── data-table/
│   ├── patient/
│   ├── medical/
│   ├── file-upload/
│   └── feedback/
│
├── layouts/
│   ├── DashboardLayout.tsx
│   ├── AuthLayout.tsx
│   └── PrintLayout.tsx
│
├── lib/
│   ├── api/
│   ├── auth/
│   ├── permissions/
│   ├── utils/
│   └── constants/
│
├── hooks/
│
├── stores/
│
├── providers/
│   ├── QueryProvider.tsx
│   ├── AuthProvider.tsx
│   ├── ThemeProvider.tsx
│   └── I18nProvider.tsx
│
├── types/
│   ├── api.ts
│   ├── pagination.ts
│   └── common.ts
│
├── config/
│   ├── navigation.ts
│   ├── permissions.ts
│   └── environment.ts
│
└── i18n/
    ├── ar.json
    └── en.json
```

---

# 26. العلاقة بين Frontend وBackend

نريدها بهذا الشكل:

```text
                  HMS FRONTEND
                       │
              ┌────────┴────────┐
              │                 │
          Next.js            TypeScript
              │
        ┌─────┴─────┐
        │           │
     Modules     Components
        │
        ↓
  TanStack Query
        │
        ↓
     Axios API
        │
        ↓
══════════════════════════════
          REST API
══════════════════════════════
        │
        ↓
      Laravel
        │
 ┌──────┼─────────────┐
 │      │             │
Auth  Services      Modules
 │      │             │
 └──────┼─────────────┘
        ↓
      MySQL
```

---

## 27. والأهم: لا نبدأ البرمجة عشوائياً

قبل ما نكتب أول صفحة، أنا أقترح نعتمد **3 مستويات**:

### Level 1 — Design System

```text
Button
Input
Select
Dialog
Table
Tabs
Badge
Card
Form
DatePicker
Upload
```

### Level 2 — Core HMS Components

```text
PatientSelector
PatientHeader
PatientTimeline
EncounterSelector
DoctorSelector
DepartmentSelector
OrderCard
ServiceSelector
MedicalFileViewer
```

### Level 3 — Modules

```text
Patients
Encounters
Laboratory
Radiology
Pharmacy
Inventory
Billing
...
```

وبهذا الشكل إذا عملنا مثلاً:

```text
PatientSelector
```

مرة واحدة، رح تستخدمه عشرات الأماكن بالنظام.

---

## 28. النتيجة

أنا أنصح أن يكون المشروع النهائي:

**Frontend**

```text
Next.js
+
TypeScript
+
Tailwind
+
shadcn/ui
+
TanStack Query
+
React Hook Form
+
Zod
+
Axios
+
TanStack Table
+
Zustand
+
next-intl
+
Laravel Sanctum
```

**Architecture**

```text
Feature / Module Based Architecture
+
Shared Component Architecture
+
API Layer
+
Permission Layer
+
Design System
```

والنقطة المهمة جداً: **ما نربط تصميم الـ Frontend مباشرةً بقاعدة البيانات**. الـ Frontend يتعامل مع API contracts، والـ Laravel هو الذي يملك الـ business logic والصلاحيات والمعاملات.

إذا بدنا نمشي صح، **الخطوة التالية ليست كتابة الصفحات**؛ الأفضل نبني أولاً **Frontend Architecture v1 كامل**: كل الـ routes، الـ modules، الـ pages داخل كل module، الـ components، الـ hooks، الـ API endpoints التي يحتاجها كل component، والـ permissions، بحيث يصبح عندك مخطط تنفيذ كامل قبل كتابة الكود.
