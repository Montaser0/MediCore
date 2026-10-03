"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { useStaffList } from "../hooks/useStaff";
import type { StaffRow } from "../api/staff.api";

export function StaffPage() {
  const t = useTranslations();
  const { data: rows = [], isLoading, refetch, isFetching } = useStaffList();

  const columns = useMemo<Column<StaffRow>[]>(
    () => [
      { key: "id", header: "#", width: 60, cell: (r) => r.id },
      {
        key: "name",
        header: t("patients.fullName"),
        cell: (r) =>
          (r.full_name as string | undefined) ??
          (r.name as string | undefined) ??
          "—",
      },
      {
        key: "title",
        header: t("workspace.department"),
        cell: (r) =>
          (r.job_title as string | undefined) ??
          (r.department as { name?: string } | undefined)?.name ??
          "—",
      },
    ],
    [t],
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.staff")}>
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
