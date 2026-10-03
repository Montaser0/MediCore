"use client";

import { useTranslations } from "next-intl";
import { arabicLabel } from "@/i18n/arabicLabels";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";
import { ModuleTabs } from "@/components/shared/ModuleTabs";
import { ResourceListPage, statusBadge } from "@/components/shared/ResourceListPage";
import { ReceiveStockForm } from "@/modules/inventory/components/ReceiveStockForm";

const ITEM_TYPES = ["medicine", "medical_supply", "equipment", "general"];

export default function Page() {
  const t = useTranslations();
  return (
    <ModuleTabs
      tabs={[
        {
          id: "stock",
          label: t("inventory.stock"),
          content: (
            <>
            <ReceiveStockForm />
            <ResourceListPage
              title={t("inventory.title")}
              endpoint="/inventory/stock-levels"
              columns={[
                { key: "id", header: "#", width: 60 },
                {
                  key: "item",
                  header: t("inventory.items"),
                  render: (row) =>
                    String(
                      (typeof row.item === "object" && row.item
                        ? (row.item as { name?: string }).name
                        : row.item) ?? row.item_name ?? row.name ?? "—",
                    ),
                },
                {
                  key: "warehouse",
                  header: t("inventory.warehouses"),
                  render: (row) =>
                    String(
                      (typeof row.warehouse === "object" && row.warehouse
                        ? (row.warehouse as { name?: string }).name
                        : row.warehouse) ?? row.warehouse_name ?? "—",
                    ),
                },
                {
                  key: "quantity",
                  header: t("inventory.stock"),
                  width: 90,
                  render: (row) => String(row.quantity ?? row.qty ?? "—"),
                },
                {
                  key: "status",
                  header: t("app.status"),
                  width: 110,
                  render: (row) => statusBadge(row.status ?? "ok"),
                },
              ]}
            />
            </>
          ),
        },
        {
          id: "items",
          label: t("inventory.items"),
          content: (
            <CrudResourcePage
              title={t("inventory.items")}
              endpoint="/inventory/items"
              columns={[
                { key: "id", header: "#", width: 60 },
                { key: "name", header: t("crud.name"), render: (row) => String(row.name ?? "—") },
                { key: "sku", header: t("crud.code"), width: 120, render: (row) => String(row.sku ?? "—") },
                { key: "unit", header: t("crud.type"), width: 80, render: (row) => String(row.unit ?? "—") },
              ]}
              fields={[
                { key: "name", label: t("crud.name"), required: true },
                { key: "sku", label: t("crud.code"), required: true },
                { key: "unit", label: t("crud.type"), required: true },
                {
                  key: "item_type",
                  label: t("crud.type"),
                  type: "select",
                  required: true,
                  options: ITEM_TYPES.map((value) => ({ value, label: arabicLabel(value) })),
                },
              ]}
            />
          ),
        },
        {
          id: "warehouses",
          label: t("inventory.warehouses"),
          content: (
            <CrudResourcePage
              title={t("inventory.warehouses")}
              endpoint="/inventory/warehouses"
              columns={[
                { key: "id", header: "#", width: 60 },
                { key: "name", header: t("crud.name"), render: (row) => String(row.name ?? "—") },
                { key: "code", header: t("crud.code"), width: 100, render: (row) => String(row.code ?? "—") },
              ]}
              fields={[
                { key: "name", label: t("crud.name"), required: true },
                { key: "code", label: t("crud.code"), required: true },
                { key: "is_pharmacy", label: t("pharmacy.title"), boolean: true },
              ]}
            />
          ),
        },
      ]}
    />
  );
}
