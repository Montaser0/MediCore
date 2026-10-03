"use client";

import { useMemo } from "react";
import { arabicLabel } from "@/i18n/arabicLabels";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatDate } from "@/lib/utils";
import { dailyNo } from "@/lib/arabicText";
import { useLabWorklist } from "../hooks/useLaboratory";
import type { LabWorklistRow } from "../api/laboratory.api";

export function LaboratoryPage() {
  const t = useTranslations();
  const { data: rows = [], isLoading, refetch, isFetching } = useLabWorklist();

  const columns = useMemo<Column<LabWorklistRow>[]>(
    () => [
      { key: "id", header: "#", width: 60, cell: (r) => r.id },
      {
        key: "patient",
        header: t("patients.fullName"),
        cell: (r) => r.patient?.full_name ?? "—",
      },
      {
        key: "mrn",
        header: t("patients.mrn"),
        width: 100,
        cell: (r) => dailyNo(r.patient?.mrn) || "—",
      },
      {
        key: "test",
        header: t("laboratory.tests"),
        cell: (r) => r.test_name ?? r.test_code ?? "—",
      },
      {
        key: "status",
        header: t("app.status"),
        width: 110,
        cell: (r) => <Badge tone="info">{arabicLabel(r.status)}</Badge>,
      },
      {
        key: "ordered",
        header: t("app.date"),
        width: 120,
        cell: (r) => formatDate(r.ordered_at, true),
      },
    ],
    [t],
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("laboratory.title")}>
        <span className="text-[12px] text-[var(--his-muted)]">
          {t("laboratory.worklist")}
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
