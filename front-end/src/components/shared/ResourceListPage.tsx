"use client";

import { useMemo, useState, type ReactNode } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { toast } from "sonner";
import { apiGet, getErrorMessage } from "@/lib/api/client";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatDate } from "@/lib/utils";
import { arabicLabel } from "@/i18n/arabicLabels";

type Row = Record<string, unknown>;

export function ResourceListPage({
  title,
  endpoint,
  columns,
  searchParam = "search",
  params,
  onRemove,
  removeLabel,
  extraActions,
  actionsHeader,
  toolbarExtra,
}: {
  title: string;
  endpoint: string;
  columns: Array<{
    key: string;
    header: string;
    width?: number | string;
    render?: (row: Row) => React.ReactNode;
  }>;
  searchParam?: string;
  params?: Record<string, string | number | boolean>;
  onRemove?: (row: Row) => Promise<unknown>;
  removeLabel?: string;
  extraActions?: (row: Row) => React.ReactNode;
  actionsHeader?: string;
  toolbarExtra?: ReactNode;
}) {
  const t = useTranslations("app");
  const messages = useTranslations();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");

  const { data, isLoading, refetch, isFetching } = useQuery({
    queryKey: [endpoint, q, params],
    queryFn: async () => {
      const res = await apiGet<Row[]>(endpoint, {
        params: { per_page: 50, ...params, ...(q ? { [searchParam]: q } : {}) },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  async function remove(row: Row) {
    if (!onRemove) return;
    if (!window.confirm(messages("crud.confirmDelete"))) return;
    try {
      await onRemove(row);
      toast.success(messages("workspace.saved"));
      await qc.invalidateQueries({ queryKey: [endpoint] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  const tableColumns = useMemo<Column<Row>[]>(() => {
    const mapped = columns.map((col) => ({
      key: col.key,
      header: col.header,
      width: col.width,
      cell: (row: Row) => (col.render ? col.render(row) : String(row[col.key] ?? "—")),
    }));
    if (onRemove || extraActions) {
      mapped.splice(Math.min(2, mapped.length), 0, {
        key: "operations",
        header: actionsHeader ?? t("actions"),
        width: extraActions ? 280 : 170,
        cell: (row: Row) => (
          <div className="flex flex-wrap gap-1">
            {extraActions?.(row)}
            {onRemove ? (
              <Button variant="danger" onClick={() => remove(row)}>
                {removeLabel ?? t("delete")}
              </Button>
            ) : null}
          </div>
        ),
      });
    }
    return mapped;
  }, [columns, onRemove, removeLabel, extraActions, actionsHeader, t]);

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={title}>
        {toolbarExtra}
        <Input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && setQ(search)}
          placeholder={t("search")}
          className="w-56"
        />
        <Button onClick={() => setQ(search)}>{t("search")}</Button>
        <Button onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw size={13} />
          {t("refresh")}
        </Button>
      </PageToolbar>
      <div className="p-2 flex-1">
        <DataTable
          columns={tableColumns}
          rows={data ?? []}
          rowKey={(r, i) => Number(r.id ?? i)}
          loading={isLoading}
          emptyText={t("noData")}
        />
      </div>
    </div>
  );
}

export function statusBadge(value: unknown) {
  const v = String(value ?? "—");
  const tone =
    ["completed", "paid", "active", "verified", "dispensed", "fulfilled"].includes(v)
      ? "success"
      : ["pending", "scheduled", "draft", "ordered", "requested"].includes(v)
        ? "warning"
        : ["cancelled", "rejected", "failed"].includes(v)
          ? "danger"
          : "info";
  return <Badge tone={tone as "success"}>{arabicLabel(v)}</Badge>;
}

export function dateCell(value: unknown, withTime = false) {
  return formatDate(value ? String(value) : null, withTime);
}
