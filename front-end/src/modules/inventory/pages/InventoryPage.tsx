"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { Panel } from "@/components/ui/Panel";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import {
  useInventoryItems,
  useStockLevels,
  useInventoryAlerts,
} from "../hooks/useInventory";
import type { InventoryAlertRow } from "../api/inventory.api";

type StockRow = {
  id: number | string;
  code?: string;
  name?: string;
  quantity?: number;
  warehouse?: string;
};

export function InventoryPage() {
  const t = useTranslations();
  const items = useInventoryItems();
  const stock = useStockLevels();
  const alerts = useInventoryAlerts();

  const stockRows = useMemo<StockRow[]>(() => {
    const levels = stock.data ?? [];
    if (levels.length > 0) {
      return levels.map((r, i) => ({
        id: r.id ?? r.item_id ?? i,
        code: r.item?.code,
        name: r.item?.name,
        quantity: r.quantity ?? r.available,
        warehouse: r.warehouse?.name,
      }));
    }
    return (items.data ?? []).map((r) => ({
      id: r.id,
      code: r.code,
      name: r.name,
      quantity: undefined,
    }));
  }, [items.data, stock.data]);

  const stockColumns = useMemo<Column<StockRow>[]>(
    () => [
      { key: "code", header: t("inventory.items"), width: 100, cell: (r) => r.code ?? "—" },
      { key: "name", header: t("patients.fullName"), cell: (r) => r.name ?? "—" },
      {
        key: "wh",
        header: t("inventory.warehouses"),
        width: 120,
        cell: (r) => r.warehouse ?? "—",
      },
      {
        key: "qty",
        header: t("inventory.stock"),
        width: 80,
        cell: (r) => (r.quantity != null ? r.quantity : "—"),
      },
    ],
    [t],
  );

  const alertColumns = useMemo<Column<InventoryAlertRow>[]>(
    () => [
      {
        key: "item",
        header: t("inventory.items"),
        cell: (r) => r.item?.name ?? r.message ?? "—",
      },
      {
        key: "type",
        header: t("app.status"),
        width: 100,
        cell: (r) => <Badge tone="danger">{r.type ?? "—"}</Badge>,
      },
      {
        key: "qty",
        header: t("inventory.stock"),
        width: 80,
        cell: (r) => (r.quantity != null ? r.quantity : "—"),
      },
    ],
    [t],
  );

  const refetchAll = () => {
    items.refetch();
    stock.refetch();
    alerts.refetch();
  };

  const loading = items.isLoading || stock.isLoading;

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("inventory.title")}>
        <Button
          onClick={refetchAll}
          disabled={items.isFetching || stock.isFetching}
        >
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
      </PageToolbar>

      <div className="grid gap-2 p-2 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <Panel title={`${t("inventory.items")} / ${t("inventory.stock")}`}>
            <DataTable
              columns={stockColumns}
              rows={stockRows}
              rowKey={(r) => r.id}
              loading={loading}
              emptyText={t("app.noData")}
            />
          </Panel>
        </div>
        <div>
          <Panel title={t("inventory.alerts")}>
            <DataTable
              columns={alertColumns}
              rows={alerts.data ?? []}
              rowKey={(r, i) => r.id ?? i}
              loading={alerts.isLoading}
              emptyText={t("app.noData")}
            />
          </Panel>
        </div>
      </div>
    </div>
  );
}
