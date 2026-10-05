"use client";

import { FormEvent, useMemo, useState } from "react";
import Link from "next/link";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { apiGet, apiPatch, apiPost, getErrorMessage } from "@/lib/api/client";
import { roleNames } from "@/config/desk";
import { useAuthStore } from "@/stores/auth-store";
import { formatMoney } from "@/lib/utils";

type Drug = {
  id: number;
  name: string;
  barcode?: string | null;
  quantity?: number;
  price?: number;
};

const empty = { id: 0, name: "", barcode: "", quantity: "", price: "" };

export function PharmacyCatalogPage() {
  const t = useTranslations();
  const locale = useLocale();
  const qc = useQueryClient();
  const isAdmin = roleNames(useAuthStore((state) => state.user)).includes("admin");
  const [draft, setDraft] = useState(empty);
  const [saving, setSaving] = useState(false);
  const { data: rows = [], isLoading } = useQuery({
    queryKey: ["pharmacy-pos", "medications"],
    queryFn: async () => {
      const res = await apiGet<Drug[]>("/pharmacy/pos/medications", { params: { per_page: 200 } });
      return res.data ?? [];
    },
  });

  const columns = useMemo<Column<Drug>[]>(
    () => [
      { key: "name", header: t("crud.name"), cell: (row) => row.name },
      { key: "barcode", header: t("pharmacy.barcode"), width: 160, cell: (row) => row.barcode || "—" },
      { key: "quantity", header: t("pharmacy.quantity"), width: 100, cell: (row) => String(row.quantity ?? 0) },
      { key: "price", header: t("pharmacy.price"), width: 120, cell: (row) => formatMoney(row.price) },
    ],
    [t],
  );

  async function save(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    const body = {
      name: draft.name.trim(),
      barcode: draft.barcode.trim(),
      quantity: Number(draft.quantity),
      price: Number(draft.price),
    };
    try {
      if (draft.id) await apiPatch(`/pharmacy/pos/medications/${draft.id}`, body);
      else await apiPost("/pharmacy/pos/medications", body);
      toast.success(t("pharmacy.drugSaved"));
      setDraft(empty);
      await qc.invalidateQueries({ queryKey: ["pharmacy-pos", "medications"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("pharmacy.catalog")}>
        {isAdmin ? (
          <Link href={`/${locale}/dashboard/pharmacy/backups`} className="text-[12px] underline">
            {t("station.backup")}
          </Link>
        ) : null}
      </PageToolbar>
      <form onSubmit={save} className="grid gap-2 border-b border-[var(--his-border)] bg-white p-2 md:grid-cols-5">
        <Input
          required
          placeholder={t("crud.name")}
          value={draft.name}
          onChange={(event) => setDraft((current) => ({ ...current, name: event.target.value }))}
        />
        <Input
          required
          placeholder={t("pharmacy.barcode")}
          value={draft.barcode}
          onChange={(event) => setDraft((current) => ({ ...current, barcode: event.target.value }))}
        />
        <Input
          required
          type="number"
          min="0"
          step="0.01"
          placeholder={t("pharmacy.quantity")}
          value={draft.quantity}
          onChange={(event) => setDraft((current) => ({ ...current, quantity: event.target.value }))}
        />
        <Input
          required
          type="number"
          min="0"
          step="0.01"
          placeholder={t("pharmacy.price")}
          value={draft.price}
          onChange={(event) => setDraft((current) => ({ ...current, price: event.target.value }))}
        />
        <div className="flex gap-2">
          <Button type="submit" variant="primary" disabled={saving}>
            {t("pharmacy.addDrug")}
          </Button>
          {draft.id ? (
            <Button type="button" onClick={() => setDraft(empty)}>
              {t("app.cancel")}
            </Button>
          ) : null}
        </div>
      </form>
      <div className="min-h-0 flex-1 p-2">
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row) => row.id}
          loading={isLoading}
          emptyText={t("app.noData")}
          onSelect={(row) =>
            setDraft({
              id: row.id,
              name: row.name,
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
