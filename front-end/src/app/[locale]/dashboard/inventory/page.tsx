"use client";

import { useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { arabicLabel } from "@/i18n/arabicLabels";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { Button } from "@/components/ui/Button";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";
import { ModuleTabs } from "@/components/shared/ModuleTabs";
import { ResourceListPage, statusBadge } from "@/components/shared/ResourceListPage";
import { BarcodeImage } from "@/modules/inventory/components/BarcodeImage";
import { ReceiveStockForm } from "@/modules/inventory/components/ReceiveStockForm";
import { printBarcodeLabels } from "@/modules/inventory/printBarcodeLabels";

const ITEM_TYPES = ["medicine", "medical_supply", "equipment", "general"];

export default function Page() {
  const t = useTranslations();
  const qc = useQueryClient();

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
  );
}
