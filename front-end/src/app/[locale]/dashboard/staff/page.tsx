"use client";

import { useTranslations } from "next-intl";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";

const ACCOUNT_ROLES = [
  "reception",
  "doctor",
  "nurse",
  "lab_technician",
  "lab_supervisor",
  "radiology_technician",
  "radiologist",
  "pharmacist",
  "cashier",
  "warehouse",
] as const;

const ROLE_LABELS = {
  admin: "staffRoles.admin",
  reception: "staffRoles.reception",
  doctor: "staffRoles.doctor",
  nurse: "staffRoles.nurse",
  lab_technician: "staffRoles.lab_technician",
  lab_supervisor: "staffRoles.lab_supervisor",
  radiology_technician: "staffRoles.radiology_technician",
  radiologist: "staffRoles.radiologist",
  pharmacist: "staffRoles.pharmacist",
  cashier: "staffRoles.cashier",
  warehouse: "staffRoles.warehouse",
} as const;

function accountRole(row: Record<string, unknown>): string {
  const user = row.user as { roles?: string[] } | null | undefined;
  return user?.roles?.[0] ?? "";
}

function roleLabel(role: string, t: (key: (typeof ROLE_LABELS)[keyof typeof ROLE_LABELS]) => string, jobTitle: unknown) {
  if (role in ROLE_LABELS) return t(ROLE_LABELS[role as keyof typeof ROLE_LABELS]);
  const title = String(jobTitle ?? "").trim();
  return title || role || "—";
}

export default function Page() {
  const t = useTranslations();
  return (
    <CrudResourcePage
      title={t("nav.staff")}
      endpoint="/staff"
      columns={[
        { key: "id", header: "#", width: 60 },
        { key: "full_name", header: t("crud.name"), render: (row) => String(row.full_name ?? "—") },
        {
          key: "role",
          header: t("staffRoles.label"),
          render: (row) => {
            const role = accountRole(row);
            return role ? roleLabel(role, t, row.job_title) : String(row.job_title ?? "—");
          },
        },
        {
          key: "username",
          header: t("crud.username"),
          render: (row) => String((row.user as { username?: string } | null | undefined)?.username ?? "—"),
        },
        {
          key: "department",
          header: t("workspace.department"),
          render: (row) => String((row.department as { name?: string } | undefined)?.name ?? "—"),
        },
      ]}
      fields={[
        { key: "hospital_id", label: "", inject: "hospital_id" },
        { key: "first_name", label: t("patients.firstName"), required: true },
        { key: "last_name", label: t("patients.lastName"), required: true },
        { key: "employee_number", label: t("crud.code") },
        { key: "phone", label: t("patients.phone") },
        { key: "department_id", label: t("workspace.department"), optionsSource: "departments" },
        {
          key: "role",
          label: t("staffRoles.label"),
          type: "select",
          createOnly: true,
          options: ACCOUNT_ROLES.map((role) => ({ value: role, label: t(ROLE_LABELS[role]) })),
        },
        { key: "username", label: t("crud.username"), createOnly: true, required: true, whenFilled: "role" },
        { key: "password", label: t("crud.password"), type: "password", createOnly: true, required: true, whenFilled: "role" },
        { key: "email", label: t("crud.email"), createOnly: true, whenFilled: "role" },
        { key: "specialty", label: t("staffRoles.specialty"), createOnly: true, when: { key: "role", value: "doctor" } },
      ]}
    />
  );
}
