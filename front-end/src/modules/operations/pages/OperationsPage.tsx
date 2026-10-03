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
import { localize } from "@/lib/arabicText";
import { useOperations } from "../hooks/useOperations";
import type { OperationRow } from "../api/operations.api";

export function OperationsPage() {
  const t = useTranslations();
  const { data: rows = [], isLoading, refetch, isFetching } = useOperations();

  const columns = useMemo<Column<OperationRow>[]>(
    () => [
      { key: "id", header: "#", width: 60, cell: (r) => localize(r.operation_number ?? r.id) },
      {
        key: "patient",
        header: t("patients.fullName"),
        cell: (r) => r.patient?.full_name ?? "—",
      },
      {
        key: "type",
        header: t("nav.operations"),
        cell: (r) => r.type?.name ?? "—",
      },
      {
        key: "theatre",
        header: t("inpatient.wards"),
        width: 120,
        cell: (r) => r.theatre?.name ?? "—",
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
      <PageToolbar title={t("nav.operations")}>
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
