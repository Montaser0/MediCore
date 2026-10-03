"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatDate } from "@/lib/utils";
import { arabicLabel } from "@/i18n/arabicLabels";
import { useAuditLogs } from "../hooks/useAudit";
import type { AuditLogRow } from "../api/audit.api";

export function AuditPage() {
  const t = useTranslations();
  const { data: rows = [], isLoading, refetch, isFetching } = useAuditLogs();

  const columns = useMemo<Column<AuditLogRow>[]>(
    () => [
      { key: "id", header: "#", width: 60, cell: (r) => r.id },
      {
        key: "action",
        header: t("app.actions"),
        width: 120,
        cell: (r) => arabicLabel(r.action),
      },
      {
        key: "entity",
        header: t("nav.audit"),
        cell: (r) =>
          r.entity_type
            ? `${r.entity_type}${r.entity_id != null ? ` #${r.entity_id}` : ""}`
            : "—",
      },
      {
        key: "user",
        header: t("app.user"),
        width: 120,
        cell: (r) => r.user?.name ?? "—",
      },
      {
        key: "date",
        header: t("app.date"),
        width: 130,
        cell: (r) => formatDate(r.created_at, true),
      },
    ],
    [t],
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.audit")}>
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
