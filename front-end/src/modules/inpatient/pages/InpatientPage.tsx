"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { Panel } from "@/components/ui/Panel";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatDate } from "@/lib/utils";
import { arabicLabel } from "@/i18n/arabicLabels";
import { localize } from "@/lib/arabicText";
import { useAdmissions, useBedOccupancy } from "../hooks/useInpatient";
import type { AdmissionRow } from "../api/inpatient.api";

export function InpatientPage() {
  const t = useTranslations();
  const { data: rows = [], isLoading, refetch, isFetching } = useAdmissions();
  const { data: occupancy } = useBedOccupancy();

  const columns = useMemo<Column<AdmissionRow>[]>(
    () => [
      {
        key: "num",
        header: "#",
        width: 90,
        cell: (r) => localize(r.admission_number ?? r.id),
      },
      {
        key: "patient",
        header: t("patients.fullName"),
        cell: (r) => r.patient?.full_name ?? "—",
      },
      {
        key: "ward",
        header: t("inpatient.wards"),
        width: 120,
        cell: (r) => r.ward?.name ?? "—",
      },
      {
        key: "bed",
        header: t("inpatient.beds"),
        width: 90,
        cell: (r) => r.bed?.code ?? "—",
      },
      {
        key: "status",
        header: t("app.status"),
        width: 100,
        cell: (r) => <Badge tone="info">{arabicLabel(r.status)}</Badge>,
      },
      {
        key: "admitted",
        header: t("app.date"),
        width: 120,
        cell: (r) => formatDate(r.admitted_at, true),
      },
    ],
    [t],
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("inpatient.title")}>
        <Button onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
      </PageToolbar>

      <div className="grid gap-2 p-2 md:grid-cols-4">
        <Panel title={t("inpatient.occupancy")} className="md:col-span-4">
          <div className="grid grid-cols-2 gap-2 md:grid-cols-4 text-[12px]">
            <Stat
              label={t("inpatient.beds")}
              value={occupancy?.total_beds ?? "—"}
            />
            <Stat
              label={t("inpatient.occupancy")}
              value={
                occupancy?.occupancy_rate != null
                  ? `${occupancy.occupancy_rate}%`
                  : "—"
              }
            />
            <Stat
              label={t("inpatient.admissions")}
              value={occupancy?.occupied_beds ?? "—"}
            />
            <Stat
              label={t("pharmacy.availability")}
              value={occupancy?.available_beds ?? "—"}
            />
          </div>
        </Panel>
      </div>

      <div className="flex-1 p-2 pt-0">
        <Panel title={t("inpatient.admissions")}>
          <DataTable
            columns={columns}
            rows={rows}
            rowKey={(r) => r.id}
            loading={isLoading}
            emptyText={t("app.noData")}
          />
        </Panel>
      </div>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="border border-[var(--his-border)] bg-[var(--his-bg)] px-2 py-1.5">
      <div className="text-[var(--his-muted)]">{label}</div>
      <div className="text-lg font-semibold tabular-nums">{value}</div>
    </div>
  );
}
