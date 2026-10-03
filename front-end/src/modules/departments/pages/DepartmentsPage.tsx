"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { useDepartments } from "../hooks/useDepartments";
import type { DepartmentRow } from "../api/departments.api";

export function DepartmentsPage() {
  const t = useTranslations();
  const { data: rows = [], isLoading, refetch, isFetching } = useDepartments();

  const columns = useMemo<Column<DepartmentRow>[]>(
    () => [
      { key: "code", header: "#", width: 80, cell: (r) => r.code ?? r.id },
      { key: "name", header: t("workspace.department"), cell: (r) => r.name ?? "—" },
      { key: "type", header: t("encounters.type"), width: 120, cell: (r) => r.type ?? "—" },
    ],
    [t],
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.departments")}>
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
