"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { formatDate, formatMoney } from "@/lib/utils";
import { useEditionStore } from "@/stores/edition-store";

type DebtRow = {
  id: number;
  customer_id: number;
  customer_name?: string | null;
  phone?: string | null;
  sale_number: string;
  sold_at?: string;
  total?: number;
  due_amount?: number;
};

type DebtBook = {
  outstanding?: number;
  rows?: DebtRow[];
};

type Period = "all" | "today" | "week" | "month";

export function PharmacyDebtsPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const ready = useEditionStore((state) => state.ready);
  const pos = useEditionStore((state) => state.hasFeature("pharmacy.pos"));
  const [search, setSearch] = useState("");
  const [period, setPeriod] = useState<Period>("all");
  const [date, setDate] = useState("");
  const [book, setBook] = useState<DebtBook | null>(null);
  const [amounts, setAmounts] = useState<Record<number, string>>({});
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (ready && !pos) router.replace(`/${locale}/dashboard/pharmacy`);
  }, [ready, pos, router, locale]);

  const load = useCallback(async (query: string, selected: Period, day: string) => {
    setLoading(true);
    try {
      const res = await apiGet<DebtBook>("/pharmacy/pos/debts", {
        params: {
          ...(query ? { search: query } : {}),
          ...(day ? { date: day } : selected !== "all" ? { period: selected } : {}),
        },
      });
      setBook(res.data ?? null);
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (ready && pos) void load(search.trim(), period, date);
  }, [ready, pos, search, period, date, load]);

  const rows = book?.rows ?? [];

  async function collect(row: DebtRow) {
    const typed = amounts[row.id];
    const amount = typed && typed.trim() !== "" ? Number(typed) : Number(row.due_amount);
    if (!Number.isFinite(amount) || amount <= 0) return;
    setSaving(true);
    try {
      await apiPost("/pharmacy/pos/debts/payments", {
        customer_id: row.customer_id,
        sale_id: row.id,
        amount,
      });
      toast.success(t("pharmacy.collected"));
      setAmounts((current) => ({ ...current, [row.id]: "" }));
      await load(search.trim(), period, date);
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  const columns = useMemo<Column<DebtRow>[]>(
    () => [
      { key: "when", header: t("pharmacy.soldAt"), width: 150, cell: (row) => formatDate(row.sold_at, true) },
      { key: "name", header: t("pharmacy.customerName"), cell: (row) => row.customer_name ?? "—" },
      { key: "phone", header: t("pharmacy.customerPhone"), width: 130, cell: (row) => row.phone ?? "—" },
      { key: "number", header: t("pharmacy.saleNumber"), width: 150, cell: (row) => row.sale_number },
      { key: "total", header: t("pharmacy.net"), width: 120, cell: (row) => formatMoney(row.total, "0") },
      { key: "due", header: t("pharmacy.due"), width: 120, cell: (row) => formatMoney(row.due_amount, "0") },
      {
        key: "pay",
        header: t("pharmacy.collect"),
        width: 220,
        cell: (row) => (
          <div className="flex items-center gap-1" onClick={(event) => event.stopPropagation()}>
            <Input
              type="number"
              min="0.01"
              step="0.01"
              value={amounts[row.id] ?? ""}
              placeholder={String(row.due_amount ?? "")}
              onChange={(event) => setAmounts((current) => ({ ...current, [row.id]: event.target.value }))}
              className="w-24"
            />
            <Button variant="primary" disabled={saving} onClick={() => void collect(row)}>
              {t("pharmacy.collect")}
            </Button>
          </div>
        ),
      },
    ],
    // collect closes over the latest amounts and filter.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [amounts, saving, t],
  );

  function find(event: FormEvent) {
    event.preventDefault();
    void load(search.trim(), period, date);
  }

  const filters: { id: Period; label: string }[] = [
    { id: "all", label: t("pharmacy.filterAll") },
    { id: "today", label: t("reports.periodToday") },
    { id: "week", label: t("reports.periodWeek") },
    { id: "month", label: t("reports.periodMonth") },
  ];

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("pharmacy.debtsTitle")}>
        <span className="text-[13px] font-bold">
          {t("dashboard.outstandingDebt")} {formatMoney(book?.outstanding, "0")}
        </span>
      </PageToolbar>
      <form onSubmit={find} className="flex flex-wrap items-center gap-2 border-b border-[var(--his-border)] bg-white p-2">
        {filters.map((filter) => (
          <Button
            key={filter.id}
            variant={!date && period === filter.id ? "primary" : "default"}
            onClick={() => {
              setDate("");
              setPeriod(filter.id);
            }}
          >
            {filter.label}
          </Button>
        ))}
        <Input
          type="date"
          value={date}
          onChange={(event) => setDate(event.target.value)}
          className="w-40"
        />
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={t("pharmacy.customerName")}
          className="max-w-xs"
        />
        <Button type="submit" variant="primary">
          {t("pharmacy.findSale")}
        </Button>
      </form>
      <div className="min-h-0 flex-1 p-2">
        <DataTable columns={columns} rows={rows} rowKey={(row) => row.id} loading={loading} emptyText={t("pharmacy.noDebts")} />
      </div>
    </div>
  );
}
