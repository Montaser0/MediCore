"use client";

import { useTranslations } from "next-intl";
import { apiPost } from "@/lib/api/client";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";
import { RoomsPage } from "@/modules/inpatient/pages/RoomsPage";
import { ModuleTabs } from "@/components/shared/ModuleTabs";
import { ResourceListPage, dateCell, statusBadge } from "@/components/shared/ResourceListPage";

export default function Page() {
  const t = useTranslations();
  return (
    <ModuleTabs
      tabs={[
        {
          id: "admissions",
          label: t("inpatient.admissions"),
          content: (
            <ResourceListPage
              title={t("inpatient.title")}
              endpoint="/inpatient/admissions"
              excel="admissions"
              removeLabel={t("inpatient.discharge")}
              onRemove={(row) =>
                apiPost(`/inpatient/admissions/${row.id}/discharge`, {
                  discharge_type: "routine",
                  discharge_summary: t("inpatient.discharge"),
                })
              }
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
                  key: "ward",
                  header: t("inpatient.wards"),
                  render: (row) =>
                    String((row.ward as { name?: string } | undefined)?.name ?? row.ward_name ?? "—"),
                },
                {
                  key: "bed",
                  header: t("inpatient.beds"),
                  render: (row) =>
                    String(
                      (row.bed as { code?: string; name?: string } | undefined)?.code ??
                        (row.bed as { name?: string } | undefined)?.name ??
                        row.bed_code ??
                        "—",
                    ),
                },
                {
                  key: "admitted_at",
                  header: t("workspace.admissionDate"),
                  width: 140,
                  render: (row) => dateCell(row.admitted_at ?? row.created_at, true),
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
          id: "rooms",
          label: t("inpatient.rooms"),
          content: <RoomsPage />,
        },
        {
          id: "wards",
          label: t("inpatient.wards"),
          content: (
            <CrudResourcePage
              title={t("inpatient.wards")}
              endpoint="/inpatient/wards"
              excel="wards"
              columns={[
                { key: "id", header: "#", width: 60 },
                { key: "name", header: t("crud.name"), render: (row) => String(row.name ?? "—") },
                { key: "code", header: t("crud.code"), width: 100, render: (row) => String(row.code ?? "—") },
                {
                  key: "department",
                  header: t("workspace.department"),
                  render: (row) => String((row.department as { name?: string } | undefined)?.name ?? "—"),
                },
                { key: "floor", header: t("inpatient.floor"), width: 80, render: (row) => String(row.floor ?? "—") },
                {
                  key: "daily_rate",
                  header: t("crud.price"),
                  width: 100,
                  render: (row) => String(row.daily_rate ?? "—"),
                },
              ]}
              fields={[
                { key: "department_id", label: t("workspace.department"), required: true, optionsSource: "departments" },
                { key: "name", label: t("crud.name"), required: true },
                { key: "code", label: t("crud.code"), required: true },
                { key: "type", label: t("crud.type") },
                { key: "floor", label: t("inpatient.floor"), type: "number" },
                { key: "daily_rate", label: t("crud.price"), type: "number" },
              ]}
            />
          ),
        },
      ]}
    />
  );
}
