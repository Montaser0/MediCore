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
import { apiGet, getErrorMessage } from "@/lib/api/client";
import { roleNames } from "@/config/desk";
import { useAuthStore } from "@/stores/auth-store";
import { formatMoney } from "@/lib/utils";
import { CatalogueSearch, catalogueDetail, type CatalogueHit } from "../components/CatalogueSearch";
import { defineDrug, earliestExpiry } from "../defineDrug";

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
  stock_ready?: boolean;
  stock?: { quantity_on_hand?: number } | null;
};

const blank = {
  medicationId: 0,
  catalogueId: 0,
  name: "",
  detail: "",
  barcode: "",
  price: "",
  cost: "",
  quantity: "",
  expiry: "",
  shelfQuantity: 0,
  stockReady: false,
};

function onHand(row: ShelfDrug): number {
  if (row.stock_ready) return Number(row.stock?.quantity_on_hand ?? 0);
  return Number(row.quantity ?? 0);
}

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
        key: "stock",
        header: t("pharmacy.onHand"),
        width: 140,
        cell: (row) => (
          <span>
            <span className="block">{onHand(row)}</span>
            <span className="block text-[11px] text-[var(--his-muted)]">
              {row.stock_ready ? t("pharmacy.stockReady") : t("pharmacy.stockMissing")}
            </span>
          </span>
        ),
      },
      { key: "price", header: t("pharmacy.price"), width: 110, cell: (row) => formatMoney(row.price) },
    ],
    [t],
  );

  function chooseCatalogue(item: CatalogueHit) {
    const known = rows.find((row) => row.id === item.stock?.medication_id);
    setDraft({
      medicationId: item.stock?.medication_id ?? 0,
      catalogueId: item.id,
      name: item.display_name,
      detail: catalogueDetail(item),
      barcode: item.barcode || item.stock?.barcode || "",
      price: item.stock ? String(item.stock.price ?? "") : "",
      cost: "",
      quantity: "",
      expiry: "",
      shelfQuantity: known ? Number(known.quantity ?? 0) : Number(item.stock?.quantity ?? 0),
      stockReady: Boolean(known?.stock_ready),
    });
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    try {
      await defineDrug({
        catalogueId: draft.catalogueId || undefined,
        medicationId: draft.medicationId || undefined,
        name: draft.name,
        barcode: draft.barcode,
        price: Number(draft.price),
        cost: Number(draft.cost),
        quantity: Number(draft.quantity),
        expiry: draft.expiry,
      });
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
      {fromCatalogue ? (
        <form onSubmit={save} className="grid gap-2 border-b border-[var(--his-border)] bg-white p-2 md:grid-cols-6">
          <div className="md:col-span-2">
            <p className="text-[13px] font-bold">{draft.name}</p>
            <p className="text-[12px] text-[var(--his-muted)]">{draft.detail || "—"}</p>
          </div>
          <label className="grid gap-1 text-[12px]">
            <span>{t("pharmacy.barcode")}</span>
            <Input value={draft.barcode} onChange={(event) => setDraft((current) => ({ ...current, barcode: event.target.value }))} />
          </label>
          <label className="grid gap-1 text-[12px]">
            <span>{t("pharmacy.expiry")}</span>
            <Input required type="date" min={earliestExpiry()} value={draft.expiry} onChange={(event) => setDraft((current) => ({ ...current, expiry: event.target.value }))} />
          </label>
          <label className="grid gap-1 text-[12px]">
            <span>{t("pharmacy.price")}</span>
            <Input required type="number" min="0" step="0.01" value={draft.price} onChange={(event) => setDraft((current) => ({ ...current, price: event.target.value }))} />
          </label>
          <label className="grid gap-1 text-[12px]">
            <span>{t("pharmacy.purchasePrice")}</span>
            <Input required type="number" min="0" step="0.01" value={draft.cost} onChange={(event) => setDraft((current) => ({ ...current, cost: event.target.value }))} />
          </label>
          <label className="grid gap-1 text-[12px]">
            <span>{t("pharmacy.quantity")}</span>
            <Input required type="number" min="0.01" step="0.01" value={draft.quantity} onChange={(event) => setDraft((current) => ({ ...current, quantity: event.target.value }))} />
          </label>
          <div className="flex items-end gap-2">
            <Button type="submit" variant="primary" disabled={saving}>
              {t("pharmacy.saveMedicine")}
            </Button>
            <Button type="button" onClick={() => setDraft(blank)}>
              {t("app.cancel")}
            </Button>
          </div>
        </form>
      ) : null}
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
        />
      </div>
    </div>
  );
}
