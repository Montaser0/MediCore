"use client";

import { useTranslations } from "next-intl";
import { arabicLabel } from "@/i18n/arabicLabels";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";
import { ModuleTabs } from "@/components/shared/ModuleTabs";

const DEPARTMENT_TYPES = [
  "medical",
  "laboratory",
  "radiology",
  "pharmacy",
  "warehouse",
  "emergency",
  "surgery",
  "inpatient",
  "nursing",
  "administration",
  "support",
];

export default function Page() {
  const t = useTranslations();
  return (
    <ModuleTabs
      tabs={[
        {
          id: "departments",
          label: t("nav.departments"),
          content: (
            <CrudResourcePage
              title={t("nav.departments")}
              endpoint="/departments"
              excel="departments"
              columns={[
                { key: "id", header: "#", width: 60 },
                { key: "name", header: t("crud.name"), render: (row) => String(row.name ?? "—") },
                { key: "type", header: t("crud.type"), width: 120, render: (row) => arabicLabel(row.type) },
                { key: "code", header: t("crud.code"), width: 100, render: (row) => String(row.code ?? "—") },
              ]}
              fields={[
                { key: "hospital_id", label: "", inject: "hospital_id" },
                { key: "name", label: t("crud.name"), required: true },
                { key: "code", label: t("crud.code"), required: true },
                {
                  key: "type",
                  label: t("crud.type"),
                  type: "select",
                  required: true,
                  options: DEPARTMENT_TYPES.map((value) => ({ value, label: arabicLabel(value) })),
                },
              ]}
            />
          ),
        },
        {
          id: "clinics",
          label: t("appointments.clinic"),
          content: (
            <CrudResourcePage
              title={t("appointments.clinic")}
              endpoint="/clinics"
              excel="clinics"
              columns={[
                { key: "id", header: "#", width: 60 },
                { key: "name", header: t("crud.name"), render: (row) => String(row.name ?? "—") },
                { key: "code", header: t("crud.code"), width: 100, render: (row) => String(row.code ?? "—") },
                {
                  key: "department",
                  header: t("workspace.department"),
                  render: (row) => String((row.department as { name?: string } | undefined)?.name ?? "—"),
                },
              ]}
              fields={[
                { key: "department_id", label: t("workspace.department"), required: true, optionsSource: "departments" },
                { key: "name", label: t("crud.name"), required: true },
                { key: "code", label: t("crud.code"), required: true },
              ]}
            />
          ),
        },
      ]}
    />
  );
}
