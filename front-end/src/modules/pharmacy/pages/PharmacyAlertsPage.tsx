"use client";

import { FormEvent, useMemo, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { apiGet, apiPatch, getErrorMessage } from "@/lib/api/client";
import { formatDate } from "@/lib/utils";

type Expiring = {
  medication_id?: number | null;
  name: string;
  batch_number?: string;
  expiry_date?: string;
  days_left?: number | null;
  quantity?: number;
};

type Low = {
  medication_id: number;
  name: string;
  on_hand: number;
  reorder_level: number;
};

type Alerts = { expiring: Expiring[]; low_stock: Low[] };

type Drug = {
  id: number;
  name: string;
  reorder_level?: number;
  quantity?: number;
  stock_ready?: boolean;
  stock?: { quantity_on_hand?: number } | null;
};

function onHand(drug: Drug): number {
  if (drug.stock_ready) return Number(drug.stock?.quantity_on_hand ?? 0);
  return Number(drug.quantity ?? 0);
}

export function PharmacyAlertsPage() {
  const t = useTranslations();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [term, setTerm] = useState("");
  const [levels, setLevels] = useState<Record<number, string>>({});

  const { data, isLoading } = useQuery({
    queryKey: ["pharmacy-pos", "alerts"],
    queryFn: async () => {
      const res = await apiGet<Alerts>("/pharmacy/pos/alerts");
      return res.data ?? { expiring: [], low_stock: [] };
    },
  });

  const { data: found = [], isFetching } = useQuery({
    queryKey: ["pharmacy-pos", "reorder-search", term],
    enabled: term.length >= 2,
    queryFn: async () => {
      const res = await apiGet<Drug[]>("/pharmacy/pos/medications", { params: { search: term, per_page: 12 } });
      return res.data ?? [];
    },
  });

  const expiringColumns = useMemo<Column<Expiring>[]>(
    () => [
      { key: "name", header: t("crud.name"), cell: (row) => row.name },
      { key: "batch", header: t("inventory.batch"), cell: (row) => row.batch_number || "—" },
      { key: "expiry", header: t("pharmacy.expiry"), cell: (row) => formatDate(row.expiry_date) },
      { key: "days", header: t("pharmacy.daysLeft"), width: 90, cell: (row) => String(row.days_left ?? "—") },
      { key: "qty", header: t("pharmacy.quantity"), width: 90, cell: (row) => String(row.quantity ?? 0) },
    ],
    [t],
  );

  const lowColumns = useMemo<Column<Low>[]>(
    () => [
      { key: "name", header: t("crud.name"), cell: (row) => row.name },
      { key: "on", header: t("pharmacy.onHand"), width: 90, cell: (row) => String(row.on_hand) },
      { key: "level", header: t("pharmacy.reorderLevel"), width: 110, cell: (row) => String(row.reorder_level) },
    ],
    [t],
  );

  async function saveLevel(medicationId: number, event?: FormEvent) {
    event?.preventDefault();
    const level = Number(levels[medicationId]);
    if (!Number.isFinite(level) || level < 0) return;
    try {
      await apiPatch(`/pharmacy/pos/medications/${medicationId}/reorder`, { reorder_level: level });
      toast.success(t("pharmacy.reorderSaved"));
      await qc.invalidateQueries({ queryKey: ["pharmacy-pos", "alerts"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("pharmacy.alertsTitle")} />
      <p className="border-b border-[var(--his-border)] bg-white px-2 py-1 text-[12px] text-[var(--his-muted)]">
        {t("pharmacy.alertsHint")}
      </p>
      <div className="grid min-h-0 flex-1 gap-2 overflow-auto p-2 lg:grid-cols-2">
        <section className="min-h-64">
          <h2 className="mb-1 text-[13px] font-bold">{t("pharmacy.expiringTitle")}</h2>
          <DataTable columns={expiringColumns} rows={data?.expiring ?? []} rowKey={(row) => `${row.medication_id}-${row.batch_number}-${row.expiry_date}`} loading={isLoading} emptyText={t("pharmacy.noAlerts")} />
        </section>
        <section className="min-h-64">
          <h2 className="mb-1 text-[13px] font-bold">{t("pharmacy.lowStockTitle")}</h2>
          <DataTable columns={lowColumns} rows={data?.low_stock ?? []} rowKey={(row) => row.medication_id} loading={isLoading} emptyText={t("pharmacy.noAlerts")} />
        </section>
      </div>
      <form
        className="border-t border-[var(--his-border)] bg-white p-2"
        onSubmit={(event) => {
          event.preventDefault();
          setTerm(search.trim());
        }}
      >
        <div className="mb-2 flex gap-2">
          <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t("pharmacy.shelfSearch")} className="max-w-sm" />
          <Button type="submit">{t("app.search")}</Button>
        </div>
        {isFetching ? <p className="text-[12px] text-[var(--his-muted)]">{t("app.loading")}</p> : null}
        <div className="grid gap-1">
          {found.map((drug) => (
            <div key={drug.id} className="flex flex-wrap items-center gap-2 text-[13px]">
              <span className="min-w-40 flex-1">{drug.name}</span>
              <span>{t("pharmacy.onHand")} {onHand(drug)}</span>
              <Input
                type="number"
                min="0"
                step="1"
                className="w-24"
                value={levels[drug.id] ?? String(drug.reorder_level ?? 0)}
                onChange={(event) => setLevels((current) => ({ ...current, [drug.id]: event.target.value }))}
              />
              <Button type="button" onClick={() => void saveLevel(drug.id)}>
                {t("pharmacy.saveReorder")}
              </Button>
            </div>
          ))}
        </div>
      </form>
    </div>
  );
}
