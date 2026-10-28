"use client";

import { FormEvent, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { formatDate, formatMoney } from "@/lib/utils";

type Session = {
  id: number;
  opened_at?: string;
  closed_at?: string | null;
  opening_float?: number;
  cash_sales?: number | null;
  debt_collections?: number | null;
  cash_returns?: number | null;
  expenses?: number | null;
  expected?: number;
  counted_cash?: number | null;
  variance?: number | null;
  note?: string | null;
};

type Drawer = { open: Session | null; recent: Session[] };

export function PharmacyDrawerPage() {
  const t = useTranslations();
  const qc = useQueryClient();
  const [opening, setOpening] = useState("");
  const [counted, setCounted] = useState("");
  const [note, setNote] = useState("");
  const [saving, setSaving] = useState(false);

  const { data, isLoading } = useQuery({
    queryKey: ["pharmacy-pos", "cash-session"],
    queryFn: async () => {
      const res = await apiGet<Drawer>("/pharmacy/pos/cash-session");
      return res.data ?? { open: null, recent: [] };
    },
  });

  const open = data?.open ?? null;

  async function openDrawer(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    try {
      await apiPost("/pharmacy/pos/cash-session", { opening_float: Number(opening) || 0 });
      toast.success(t("pharmacy.drawerOpened"));
      setOpening("");
      await qc.invalidateQueries({ queryKey: ["pharmacy-pos", "cash-session"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function closeDrawer(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    try {
      await apiPost("/pharmacy/pos/cash-session/close", {
        counted_cash: Number(counted) || 0,
        note: note.trim() || undefined,
      });
      toast.success(t("pharmacy.drawerClosed"));
      setCounted("");
      setNote("");
      await qc.invalidateQueries({ queryKey: ["pharmacy-pos", "cash-session"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("pharmacy.drawerTitle")} />
      <div className="min-h-0 flex-1 space-y-3 overflow-auto p-2">
        {isLoading ? <p className="text-[12px] text-[var(--his-muted)]">{t("app.loading")}</p> : null}
        {open ? (
          <form onSubmit={closeDrawer} className="space-y-2 border border-[var(--his-border)] bg-white p-3">
            <p className="text-[12px] text-[var(--his-muted)]">{t("pharmacy.openedAt")} {formatDate(open.opened_at, true)}</p>
            <Line label={t("pharmacy.openingFloat")} value={open.opening_float} />
            <Line label={t("pharmacy.cashSales")} value={open.cash_sales} />
            <Line label={t("pharmacy.debtCollections")} value={open.debt_collections} />
            <Line label={t("pharmacy.cashReturns")} value={open.cash_returns} />
            <Line label={t("pharmacy.expensesPaid")} value={open.expenses} />
            <p className="text-[15px] font-bold">{t("pharmacy.expectedCash")} {formatMoney(open.expected, "0")}</p>
            <div className="flex flex-wrap items-end gap-2">
              <label className="grid gap-1 text-[12px]">
                {t("pharmacy.countedCash")}
                <Input required type="number" min="0" step="0.01" value={counted} onChange={(event) => setCounted(event.target.value)} className="w-36" />
              </label>
              <label className="grid gap-1 text-[12px]">
                {t("pharmacy.drawerNote")}
                <Input value={note} onChange={(event) => setNote(event.target.value)} className="w-56" />
              </label>
              <Button type="submit" variant="primary" disabled={saving}>{t("pharmacy.closeDrawer")}</Button>
            </div>
          </form>
        ) : (
          <form onSubmit={openDrawer} className="flex flex-wrap items-end gap-2 border border-[var(--his-border)] bg-white p-3">
            <label className="grid gap-1 text-[12px]">
              {t("pharmacy.openingFloat")}
              <Input required type="number" min="0" step="0.01" value={opening} onChange={(event) => setOpening(event.target.value)} className="w-36" />
            </label>
            <Button type="submit" variant="primary" disabled={saving}>{t("pharmacy.openDrawer")}</Button>
          </form>
        )}
        <section>
          <h2 className="mb-1 text-[13px] font-bold">{t("pharmacy.recentDrawers")}</h2>
          {(data?.recent ?? []).length === 0 ? <p className="text-[12px] text-[var(--his-muted)]">{t("app.noData")}</p> : null}
          <div className="grid gap-1">
            {(data?.recent ?? []).map((session) => (
              <div key={session.id} className="flex flex-wrap gap-x-4 gap-y-1 border border-[var(--his-border)] bg-white px-2 py-1 text-[12px]">
                <span>{formatDate(session.closed_at, true)}</span>
                <span>{t("pharmacy.expectedCash")} {formatMoney(session.expected, "0")}</span>
                <span>{t("pharmacy.countedCash")} {formatMoney(session.counted_cash, "0")}</span>
                <span>{t("pharmacy.drawerVariance")} {formatMoney(session.variance, "0")}</span>
              </div>
            ))}
          </div>
        </section>
      </div>
    </div>
  );
}

function Line({ label, value }: { label: string; value?: number | null }) {
  return (
    <div className="flex justify-between gap-4 text-[13px]">
      <span>{label}</span>
      <span>{formatMoney(value, "0")}</span>
    </div>
  );
}
