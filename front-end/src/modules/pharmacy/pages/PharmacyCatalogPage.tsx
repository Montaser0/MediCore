"use client";

import { FormEvent, useMemo, useState } from "react";
import Link from "next/link";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { ExcelButtons } from "@/components/shared/ExcelButtons";
import { apiGet, apiPatch, apiPost, getErrorMessage } from "@/lib/api/client";
import { roleNames } from "@/config/desk";
import { useAuthStore } from "@/stores/auth-store";
import { formatMoney } from "@/lib/utils";
import { BarcodeImage } from "@/modules/inventory/components/BarcodeImage";
import { printBarcodeLabels } from "@/modules/inventory/printBarcodeLabels";
import { CatalogueSearch, catalogueDetail, type CatalogueHit } from "../components/CatalogueSearch";

type ShelfDrug = {
  id: number;
  name: string;
  generic_name?: string | null;
  strength?: string | null;
  dosage_form?: string | null;
  pack?: string | null;
  manufacturer?: string | null;
  barcode?: string | null;
  quantity?: number;
  price?: number;
  catalogue_item_id?: number | null;
};

const blank = {
  medicationId: 0,
  catalogueId: 0,
  name: "",
  detail: "",
  barcode: "",
  quantity: "",
  price: "",
};

const PRODUCT_TYPES = ["دواء", "مكمّل غذائي", "غير محسوم", "مستحضر تجميلي", "معقم/مطهر", "مستلزم/أداة"];

export function PharmacyCatalogPage() {
  const t = useTranslations();
  const locale = useLocale();
  const qc = useQueryClient();
  const isAdmin = roleNames(useAuthStore((state) => state.user)).includes("admin");
  const [draft, setDraft] = useState(blank);
  const [productType, setProductType] = useState("");
  const [shelfQuery, setShelfQuery] = useState("");
  const [shelfTerm, setShelfTerm] = useState("");
  const [saving, setSaving] = useState(false);
  const { data: rows = [], isLoading } = useQuery({
    queryKey: ["pharmacy-pos", "medications", shelfTerm],
    queryFn: async () => {
      const res = await apiGet<ShelfDrug[]>("/pharmacy/pos/medications", {
        params: { per_page: 100, ...(shelfTerm ? { search: shelfTerm } : {}) },
      });
      return res.data ?? [];
    },
  });

  const columns = useMemo<Column<ShelfDrug>[]>(
    () => [
      {
        key: "name",
        header: t("pharmacy.tradeName"),
        cell: (row) => (
          <span>
            <span className="block">{row.name}</span>
            <span className="block text-[11px] text-[var(--his-muted)]">
              {[row.generic_name, row.strength, row.dosage_form, row.pack, row.manufacturer].filter(Boolean).join(" · ") || "—"}
            </span>
          </span>
        ),
      },
      {
        key: "barcode",
        header: t("pharmacy.barcode"),
        width: 170,
        cell: (row) => {
          const code = row.barcode?.trim() ?? "";
          if (!code) return "—";
          return (
            <span className="flex flex-col items-start gap-1">
              <BarcodeImage code={code} />
              <button
                type="button"
                className="text-[11px] underline"
                onClick={(event) => {
                  event.stopPropagation();
                  if (!printBarcodeLabels([{ name: row.name, code }])) toast.error(t("inventory.nothingToPrint"));
                }}
              >
                {t("inventory.printBarcode")}
              </button>
            </span>
          );
        },
      },
      { key: "quantity", header: t("pharmacy.quantity"), width: 90, cell: (row) => String(row.quantity ?? 0) },
      { key: "price", header: t("pharmacy.price"), width: 110, cell: (row) => formatMoney(row.price) },
    ],
    [t],
  );

  function chooseCatalogue(item: CatalogueHit) {
    setDraft({
      medicationId: item.stock?.medication_id ?? 0,
      catalogueId: item.id,
      name: item.display_name,
      detail: catalogueDetail(item),
      barcode: item.stock?.barcode ?? "",
      quantity: item.stock ? String(item.stock.quantity ?? 0) : "",
      price: item.stock ? String(item.stock.price ?? 0) : "",
    });
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    const quantity = Number(draft.quantity);
    const price = Number(draft.price);
    try {
      if (draft.catalogueId) {
        await apiPost(`/pharmacy/catalogue/${draft.catalogueId}/stock`, {
          quantity,
          price,
          barcode: draft.barcode.trim(),
        });
      } else if (draft.medicationId) {
        await apiPatch(`/pharmacy/pos/medications/${draft.medicationId}`, {
          name: draft.name.trim(),
          barcode: draft.barcode.trim(),
          quantity,
          price,
        });
      } else {
        await apiPost("/pharmacy/pos/medications", {
          name: draft.name.trim(),
          barcode: draft.barcode.trim(),
          quantity,
          price,
        });
      }
      toast.success(t("pharmacy.drugSaved"));
      setDraft(blank);
      await qc.invalidateQueries({ queryKey: ["pharmacy-pos", "medications"] });
      await qc.invalidateQueries({ queryKey: ["pharmacy-catalogue"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  const fromCatalogue = draft.catalogueId > 0;

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("pharmacy.catalog")}>
        <Button
          onClick={() => {
            const labels = rows.flatMap((row) => {
              const code = row.barcode?.trim() ?? "";
              return code ? [{ name: row.name, code }] : [];
            });
            if (!printBarcodeLabels(labels)) toast.error(t("inventory.nothingToPrint"));
          }}
        >
          {t("inventory.printBarcodes")}
        </Button>
        <ExcelButtons
          resource="pharmacy"
          onImported={() => qc.invalidateQueries({ queryKey: ["pharmacy-pos", "medications"] })}
        />
        {isAdmin ? (
          <Link href={`/${locale}/dashboard/pharmacy/backups`} className="text-[12px] underline">
            {t("station.backup")}
          </Link>
        ) : null}
      </PageToolbar>
      <p className="border-b border-[var(--his-border)] bg-white px-2 py-1 text-[12px] text-[var(--his-muted)]">
        {t("pharmacy.catalogueHint")}
      </p>
      <div className="grid gap-2 border-b border-[var(--his-border)] bg-white p-2 md:grid-cols-[160px_1fr]">
        <Select value={productType} onChange={(event) => setProductType(event.target.value)}>
          <option value="">{t("pharmacy.productType")}</option>
          {PRODUCT_TYPES.map((type) => (
            <option key={type} value={type}>
              {type}
            </option>
          ))}
        </Select>
        <CatalogueSearch productType={productType} selectedLabel={fromCatalogue ? draft.name : ""} onSelect={chooseCatalogue} />
      </div>
      <form onSubmit={save} className="grid gap-2 border-b border-[var(--his-border)] bg-white p-2 md:grid-cols-5">
        {fromCatalogue ? (
          <div className="md:col-span-2">
            <p className="text-[13px] font-bold">{draft.name}</p>
            <p className="text-[12px] text-[var(--his-muted)]">{draft.detail || "—"}</p>
          </div>
        ) : (
          <Input
            required
            placeholder={t("pharmacy.manualDrug")}
            value={draft.name}
            onChange={(event) => setDraft((current) => ({ ...current, name: event.target.value, catalogueId: 0 }))}
          />
        )}
        <div className="flex flex-col gap-1">
          <Input
            placeholder={t("pharmacy.barcode")}
            value={draft.barcode}
            onChange={(event) => setDraft((current) => ({ ...current, barcode: event.target.value }))}
          />
          {draft.barcode.trim() ? <BarcodeImage code={draft.barcode.trim()} height={56} /> : null}
        </div>
        <Input
          required
          type="number"
          min="0"
          step="0.01"
          placeholder={t("pharmacy.quantity")}
          value={draft.quantity}
          onChange={(event) => setDraft((current) => ({ ...current, quantity: event.target.value }))}
        />
        <div className="flex gap-2">
          <Input
            required
            type="number"
            min="0"
            step="0.01"
            placeholder={t("pharmacy.price")}
            value={draft.price}
            onChange={(event) => setDraft((current) => ({ ...current, price: event.target.value }))}
          />
          <Button type="submit" variant="primary" disabled={saving}>
            {t("pharmacy.stockDrug")}
          </Button>
          {draft.catalogueId || draft.medicationId ? (
            <Button type="button" onClick={() => setDraft(blank)}>
              {t("app.cancel")}
            </Button>
          ) : null}
        </div>
      </form>
      <div className="flex items-center gap-2 border-b border-[var(--his-border)] bg-white px-2 py-1">
        <span className="text-[12px]">{t("pharmacy.shelf")}</span>
        <Input
          value={shelfQuery}
          onChange={(event) => setShelfQuery(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === "Enter") {
              event.preventDefault();
              setShelfTerm(shelfQuery.trim());
            }
          }}
          placeholder={t("pharmacy.shelfSearch")}
          className="max-w-xs"
        />
        <Button type="button" onClick={() => setShelfTerm(shelfQuery.trim())}>
          {t("app.search")}
        </Button>
      </div>
      <div className="min-h-0 flex-1 p-2">
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row) => row.id}
          loading={isLoading}
          emptyText={t("app.noData")}
          selectedKey={draft.medicationId || null}
          onSelect={(row) =>
            setDraft({
              medicationId: row.id,
              catalogueId: row.catalogue_item_id ?? 0,
              name: row.name,
              detail: [row.generic_name, row.strength, row.dosage_form, row.pack, row.manufacturer].filter(Boolean).join(" · "),
              barcode: row.barcode ?? "",
              quantity: String(row.quantity ?? 0),
              price: String(row.price ?? 0),
            })
          }
        />
      </div>
    </div>
  );
}
