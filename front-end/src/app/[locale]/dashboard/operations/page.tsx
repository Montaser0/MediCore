"use client";

import { useTranslations } from "next-intl";
import { apiPost } from "@/lib/api/client";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";
import { ModuleTabs } from "@/components/shared/ModuleTabs";
import { ResourceListPage, dateCell, statusBadge } from "@/components/shared/ResourceListPage";
import { formatMoney } from "@/lib/utils";

const RISKS = ["low", "moderate", "high", "critical"];

export default function Page() {
  const t = useTranslations();
  return (
    <ModuleTabs
      tabs={[
        {
          id: "operations",
          label: t("nav.operations"),
          content: (
            <ResourceListPage
              title={t("nav.operations")}
              endpoint="/operations"
              excel="operations"
              removeLabel={t("app.delete")}
              onRemove={(row) => apiPost(`/operations/${row.id}/status`, { status: "cancelled" })}
              columns={[
                { key: "id", header: "#", width: 60 },
                {
                  key: "patient",
                  header: t("patients.fullName"),
                  render: (row) =>
                    String(
                      (row.patient as { full_name?: string } | undefined)?.full_name ??
                        row.patient_name ??
                        "—",
                    ),
                },
                {
                  key: "type",
                  header: t("crud.type"),
                  render: (row) =>
                    String(
                      (row.operation_type as { name?: string } | undefined)?.name ??
                        (row.type as { name?: string } | undefined)?.name ??
                        row.type_name ??
                        "—",
                    ),
                },
                {
                  key: "scheduled_at",
                  header: t("app.date"),
                  width: 140,
                  render: (row) => dateCell(row.scheduled_at ?? row.created_at, true),
                },
                {
                  key: "status",
                  header: t("app.status"),
                  width: 110,
                  render: (row) => statusBadge(row.status),
                },
              ]}
            />
          ),
        },
        {
          id: "types",
          label: t("crud.operationTypes"),
          content: (
            <CrudResourcePage
              title={t("crud.operationTypes")}
              endpoint="/operations/types"
              excel="operation-types"
              columns={[
                { key: "id", header: "#", width: 60 },
                { key: "name", header: t("crud.name"), render: (row) => String(row.name ?? "—") },
                { key: "code", header: t("crud.code"), width: 100, render: (row) => String(row.code ?? "—") },
                {
                  key: "clinic",
                  header: t("workspace.clinic"),
                  render: (row) => {
                    const clinics = row.clinics as Array<{ name?: string }> | undefined;
                    return clinics?.map((clinic) => clinic.name).filter(Boolean).join("، ") || "—";
                  },
                },
                { key: "price", header: t("crud.price"), width: 110, render: (row) => formatMoney(row.price) },
              ]}
              fields={[
                { key: "name", label: t("crud.name"), required: true },
                { key: "code", label: t("crud.code"), required: true },
                { key: "department_id", label: t("workspace.department"), type: "select", required: true, optionsSource: "departments" },
                { key: "clinic_id", label: t("workspace.clinic"), type: "select", required: true, optionsSource: "clinics" },
                { key: "specialty", label: t("crud.type") },
                { key: "estimated_duration_minutes", label: t("app.time"), type: "number" },
                {
                  key: "risk_level",
                  label: t("crud.type"),
                  type: "select",
                  options: RISKS.map((value) => ({ value, label: value })),
                },
                { key: "price", label: t("crud.price"), type: "number", required: true },
              ]}
            />
          ),
        },
      ]}
    />
  );
}
