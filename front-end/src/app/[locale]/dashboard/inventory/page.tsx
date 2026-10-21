"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { arabicLabel } from "@/i18n/arabicLabels";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { Button } from "@/components/ui/Button";
import { Select } from "@/components/ui/Select";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";
import { ModuleTabs } from "@/components/shared/ModuleTabs";
import { ResourceListPage, statusBadge } from "@/components/shared/ResourceListPage";
import { BarcodeImage } from "@/modules/inventory/components/BarcodeImage";
import { ReceiveStockForm } from "@/modules/inventory/components/ReceiveStockForm";
import { printBarcodeLabels } from "@/modules/inventory/printBarcodeLabels";

const ITEM_TYPES = ["medicine", "medical_supply", "equipment", "general"];

type Warehouse = { id: number; name?: string; is_pharmacy?: boolean };
type ItemStock = { by_warehouse?: Array<{ warehouse?: string | null }> };

export default function Page() {
  const t = useTranslations();
  const qc = useQueryClient();
  const [warehouseId, setWarehouseId] = useState("");
  const { data: warehouses = [] } = useQuery({
    queryKey: ["/inventory/warehouses", "inventory-filter"],
    queryFn: async () => {
      const res = await apiGet<Warehouse[]>("/inventory/warehouses");
      return Array.isArray(res.data) ? res.data : [];
    },
  });
  const warehouseParams = warehouseId ? { warehouse_id: warehouseId } : undefined;

  function warehouseNames(row: { stock?: unknown }) {
    const stock = row.stock as ItemStock | undefined;
    const names = (stock?.by_warehouse ?? []).map((level) => level.warehouse).filter(Boolean);
    return names.length > 0 ? names.join("، ") : "—";
  }

  function barcodeCell(code: unknown) {
    const value = String(code ?? "").trim();
    if (!value) return "—";
    return <BarcodeImage code={value} />;
  }

  async function printLabels() {
    const labels: Array<{ name: string; code: string; caption?: string }> = [];
    let page = 1;
    let last = 1;
    try {
      do {
        const res = await apiGet<Array<{ name?: string; sku?: string; barcode?: string | null }>>(
          "/inventory/items",
          { params: { per_page: 200, page } },
        );
        for (const item of res.data ?? []) {
          const code = String(item.barcode ?? "").trim();
          if (code) labels.push({ name: String(item.name ?? ""), code, caption: String(item.sku ?? "") });
        }
        last = res.meta?.last_page ?? page;
        page += 1;
      } while (page <= last && page <= 30);
      if (!printBarcodeLabels(labels)) toast.error(t("inventory.nothingToPrint"));
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  async function generateBarcodes() {
    try {
      const res = await apiPost<{ generated: number }>("/inventory/items/generate-barcodes");
      toast.success(t("inventory.generated", { count: res.data.generated }));
      await qc.invalidateQueries({ queryKey: ["/inventory/items"] });
      await qc.invalidateQueries({ queryKey: ["/inventory/stock-levels"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center gap-2 border-b border-[var(--his-border)] bg-white px-2 py-1">
        <label className="text-[12px]" htmlFor="inventory-warehouse">
          {t("inventory.warehouse")}
        </label>
        <Select
          id="inventory-warehouse"
          className="w-56"
          value={warehouseId}
          onChange={(event) => setWarehouseId(event.target.value)}
        >
          <option value="">{t("inventory.allWarehouses")}</option>
          {warehouses.map((warehouse) => (
            <option key={warehouse.id} value={warehouse.id}>
              {warehouse.is_pharmacy ? `${warehouse.name} — ${t("pharmacy.title")}` : warehouse.name}
            </option>
          ))}
        </Select>
      </div>
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
              excel="stock"
              params={warehouseParams}
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
                  key: "barcode",
                  header: t("inventory.barcode"),
                  width: 150,
                  render: (row) => barcodeCell(row.barcode),
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
              excel="inventory-items"
              params={warehouseParams}
              createDefaults={warehouseId ? { warehouse_id: warehouseId } : undefined}
              onSaved={() => {
                void qc.invalidateQueries({ queryKey: ["/inventory/stock-levels"] });
              }}
              toolbarExtra={
                <>
                  <Button onClick={() => void generateBarcodes()}>{t("inventory.generateBarcodes")}</Button>
                  <Button onClick={() => void printLabels()}>{t("inventory.printBarcodes")}</Button>
                </>
              }
              columns={[
                { key: "id", header: "#", width: 60 },
                { key: "name", header: t("crud.name"), render: (row) => String(row.name ?? "—") },
                { key: "sku", header: t("crud.code"), width: 120, render: (row) => String(row.sku ?? "—") },
                { key: "warehouse", header: t("inventory.warehouse"), width: 140, render: (row) => warehouseNames(row) },
                {
                  key: "barcode",
                  header: t("inventory.barcode"),
                  width: 190,
                  render: (row) => {
                    const code = String(row.barcode ?? "").trim();
                    if (!code) return "—";
                    return (
                      <span className="flex flex-col items-start gap-1">
                        <BarcodeImage code={code} />
                        <button
                          type="button"
                          className="text-[11px] underline"
                          onClick={() => {
                            if (!printBarcodeLabels([{ name: String(row.name ?? ""), code, caption: String(row.sku ?? "") }])) {
                              toast.error(t("inventory.nothingToPrint"));
                            }
                          }}
                        >
                          {t("inventory.printBarcode")}
                        </button>
                      </span>
                    );
                  },
                },
                { key: "unit", header: t("crud.type"), width: 80, render: (row) => String(row.unit ?? "—") },
              ]}
              fields={[
                { key: "name", label: t("crud.name"), required: true },
                { key: "sku", label: t("crud.code"), required: true },
                { key: "unit", label: t("crud.type"), required: true },
                {
                  key: "item_type",
                  label: t("inventory.productKind"),
                  type: "select",
                  required: true,
                  createOptions: [
                    { value: "medicine", label: arabicLabel("medicine") },
                    { value: "general", label: arabicLabel("general") },
                  ],
                  options: ITEM_TYPES.map((value) => ({ value, label: arabicLabel(value) })),
                  afterChange: (value, form) => {
                    if (value !== "medicine") {
                      const current = warehouses.find((warehouse) => String(warehouse.id) === form.warehouse_id);
                      const filtered = warehouses.find((warehouse) => String(warehouse.id) === warehouseId && !warehouse.is_pharmacy);
                      return {
                        ...form,
                        item_type: value,
                        warehouse_id: current && !current.is_pharmacy ? form.warehouse_id : filtered ? String(filtered.id) : "",
                      };
                    }
                    const selected = warehouses.find((warehouse) => String(warehouse.id) === warehouseId && warehouse.is_pharmacy);
                    const pharmacy = selected ?? warehouses.find((warehouse) => warehouse.is_pharmacy);
                    return { ...form, item_type: value, warehouse_id: pharmacy ? String(pharmacy.id) : "" };
                  },
                },
                {
                  key: "warehouse_id",
                  label: t("inventory.warehouse"),
                  type: "select",
                  required: true,
                  createOnly: true,
                  whenFilled: "item_type",
                  disabledWhen: { key: "item_type", value: "medicine" },
                  optionsForForm: (form) =>
                    warehouses
                      .filter((warehouse) => (form.item_type === "medicine" ? warehouse.is_pharmacy : !warehouse.is_pharmacy))
                      .map((warehouse) => ({
                        value: String(warehouse.id),
                        label: warehouse.is_pharmacy ? `${warehouse.name ?? ""} — ${t("pharmacy.title")}` : String(warehouse.name ?? ""),
                      })),
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
              excel="warehouses"
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
    </div>
  );
}
