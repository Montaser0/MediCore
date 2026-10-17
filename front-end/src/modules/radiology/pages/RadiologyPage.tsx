"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatDate } from "@/lib/utils";
import { arabicLabel } from "@/i18n/arabicLabels";
import { useRadiologyWorklist } from "../hooks/useRadiology";
import type { RadiologyWorklistRow } from "../api/radiology.api";

export function RadiologyPage() {
  const t = useTranslations();
  const { data: rows = [], isLoading, refetch, isFetching } = useRadiologyWorklist();

  const columns = useMemo<Column<RadiologyWorklistRow>[]>(
    () => [
      { key: "id", header: "#", width: 60, cell: (r) => r.id },
      {
        key: "patient",
        header: t("patients.fullName"),
        cell: (r) => r.patient?.full_name ?? "—",
      },
      {
        key: "service",
        header: t("radiology.study"),
        cell: (r) => {
          const name = r.service?.name ?? r.service_name ?? "—";
          return r.with_injection ? `${name} — ${t("radiology.withInjection")}` : name;
        },
      },
      {
        key: "modality",
        header: t("radiology.title"),
        width: 90,
        cell: (r) => r.modality ?? "—",
      },
      {
        key: "status",
        header: t("app.status"),
        width: 110,
        cell: (r) => <Badge tone="info">{arabicLabel(r.status)}</Badge>,
      },
      {
        key: "scheduled",
        header: t("app.date"),
        width: 120,
        cell: (r) => formatDate(r.scheduled_at, true),
      },
    ],
    [t],
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("radiology.title")}>
        <span className="text-[12px] text-[var(--his-muted)]">
          {t("radiology.worklist")}
        </span>
        <Button onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
      </PageToolbar>
      <div className="flex-1 p-2">
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(r) => r.id}
          loading={isLoading}
          emptyText={t("app.noData")}
        />
      </div>
    </div>
  );
}
