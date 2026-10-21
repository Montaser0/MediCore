"use client";

import { useTranslations } from "next-intl";
import { ResourceListPage, statusBadge } from "@/components/shared/ResourceListPage";
import { ReceiveStockForm } from "@/modules/inventory/components/ReceiveStockForm";

export default function Page() {
  const t = useTranslations();
  return (
    <>
    <ReceiveStockForm pharmacyOnly />
    <ResourceListPage
      title={t("nav.pharmacyStock")}
      endpoint="/inventory/stock-levels"
      excel="pharmacy-stock"
      params={{ pharmacy_only: 1 }}
      searchParam="search"
      columns={[
        {
          key: "item",
          header: t("inventory.items"),
          render: (row) => String(row.item ?? row.item_name ?? "—"),
        },
        {
          key: "warehouse",
          header: t("inventory.warehouses"),
          render: (row) => String(row.warehouse ?? "—"),
        },
        {
          key: "quantity",
          header: t("workspace.quantity"),
          width: 90,
          render: (row) => String(row.quantity ?? "—"),
        },
        {
          key: "available",
          header: t("pharmacy.availability"),
          width: 90,
          render: (row) => String(row.available ?? "—"),
        },
        {
          key: "status",
          header: t("app.status"),
          width: 110,
          render: (row) => statusBadge(row.below_minimum ? "low_stock" : "in_stock"),
        },
      ]}
    />
    </>
  );
}
