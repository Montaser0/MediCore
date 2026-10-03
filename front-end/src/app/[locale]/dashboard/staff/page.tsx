"use client";

import { useTranslations } from "next-intl";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";

export default function Page() {
  const t = useTranslations();
  return (
    <CrudResourcePage
      title={t("nav.staff")}
      endpoint="/staff"
      columns={[
        { key: "id", header: "#", width: 60 },
        { key: "full_name", header: t("crud.name"), render: (row) => String(row.full_name ?? "—") },
        { key: "job_title", header: t("crud.type"), render: (row) => String(row.job_title ?? "—") },
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
        { key: "job_title", label: t("crud.type") },
        { key: "department_id", label: t("workspace.department"), optionsSource: "departments" },
        { key: "is_doctor", label: t("appointments.doctor"), boolean: true },
        { key: "username", label: t("crud.username"), createOnly: true, required: true, when: { key: "is_doctor", value: "1" } },
        { key: "password", label: t("crud.password"), type: "password", createOnly: true, required: true, when: { key: "is_doctor", value: "1" } },
        { key: "email", label: t("crud.email"), createOnly: true, when: { key: "is_doctor", value: "1" } },
        { key: "specialty", label: t("crud.type") },
      ]}
    />
  );
}
