"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { ExcelButtons } from "@/components/shared/ExcelButtons";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { formatDate, formatMoney } from "@/lib/utils";
import { useEditionStore } from "@/stores/edition-store";

type PurchaseRow = {
  id: number;
  supplier_name?: string | null;
  phone?: string | null;
  invoice_number: string;
  total?: number;
  cash_amount?: number;
  due_amount?: number;
  received_at?: string;
};

type PurchaseBook = {
  outstanding?: number;
  rows?: PurchaseRow[];
};

type Period = "all" | "today" | "week" | "month";

export function PharmacyWarehousesPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const ready = useEditionStore((state) => state.ready);
  const pos = useEditionStore((state) => state.hasFeature("pharmacy.pos"));
  const [search, setSearch] = useState("");
  const [period, setPeriod] = useState<Period>("all");
  const [date, setDate] = useState("");
  const [book, setBook] = useState<PurchaseBook | null>(null);
  const [amounts, setAmounts] = useState<Record<number, string>>({});
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [supplierName, setSupplierName] = useState("");
  const [supplierPhone, setSupplierPhone] = useState("");
  const [invoiceNumber, setInvoiceNumber] = useState("");
  const [total, setTotal] = useState("");
  const [paid, setPaid] = useState("");
  const [receivedAt, setReceivedAt] = useState("");

  useEffect(() => {
    if (ready && !pos) router.replace(`/${locale}/dashboard/pharmacy`);
  }, [ready, pos, router, locale]);

  const load = useCallback(async (query: string, selected: Period, day: string) => {
    setLoading(true);
    try {
      const res = await apiGet<PurchaseBook>("/pharmacy/pos/purchases", {
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

  async function saveInvoice(event: FormEvent) {
    event.preventDefault();
    const amount = Number(total);
    const cash = paid.trim() === "" ? 0 : Number(paid);
    if (!supplierName.trim() || !invoiceNumber.trim() || !Number.isFinite(amount) || amount <= 0) return;
    setSaving(true);
    try {
      await apiPost("/pharmacy/pos/purchases", {
        supplier_name: supplierName.trim(),
        supplier_phone: supplierPhone.trim() || null,
        invoice_number: invoiceNumber.trim(),
        total: amount,
        cash_amount: Number.isFinite(cash) ? cash : 0,
        received_at: receivedAt || null,
      });
      toast.success(t("pharmacy.invoiceSaved"));
      setInvoiceNumber("");
      setTotal("");
      setPaid("");
      await load(search.trim(), period, date);
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function pay(row: PurchaseRow) {
    const typed = amounts[row.id];
    const amount = typed && typed.trim() !== "" ? Number(typed) : Number(row.due_amount);
    if (!Number.isFinite(amount) || amount <= 0) return;
    setSaving(true);
    try {
      await apiPost("/pharmacy/pos/purchases/payments", { purchase_id: row.id, amount });
      toast.success(t("pharmacy.supplierPaid"));
      setAmounts((current) => ({ ...current, [row.id]: "" }));
      await load(search.trim(), period, date);
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  const columns = useMemo<Column<PurchaseRow>[]>(
    () => [
      { key: "when", header: t("pharmacy.soldAt"), width: 150, cell: (row) => formatDate(row.received_at, true) },
      { key: "name", header: t("pharmacy.supplierName"), cell: (row) => row.supplier_name ?? "—" },
      { key: "phone", header: t("pharmacy.customerPhone"), width: 130, cell: (row) => row.phone ?? "—" },
      { key: "number", header: t("pharmacy.invoiceNumber"), width: 150, cell: (row) => row.invoice_number },
      { key: "total", header: t("pharmacy.invoiceTotal"), width: 120, cell: (row) => formatMoney(row.total, "0") },
      { key: "cash", header: t("pharmacy.paidNow"), width: 120, cell: (row) => formatMoney(row.cash_amount, "0") },
      { key: "due", header: t("pharmacy.due"), width: 120, cell: (row) => formatMoney(row.due_amount, "0") },
      {
        key: "pay",
        header: t("pharmacy.payInvoice"),
        width: 220,
        cell: (row) =>
          Number(row.due_amount) > 0 ? (
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
              <Button variant="primary" disabled={saving} onClick={() => void pay(row)}>
                {t("pharmacy.payInvoice")}
              </Button>
            </div>
          ) : (
            "—"
          ),
      },
    ],
    // pay closes over the latest amounts and filter.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [amounts, saving, t],
  );

  const filters: { id: Period; label: string }[] = [
    { id: "all", label: t("pharmacy.filterInvoices") },
    { id: "today", label: t("reports.periodToday") },
    { id: "week", label: t("reports.periodWeek") },
    { id: "month", label: t("reports.periodMonth") },
  ];

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("pharmacy.warehousesTitle")}>
        <ExcelButtons resource="purchases" onImported={() => load(search.trim(), period, date)} />
        <span className="text-[13px] font-bold">
          {t("pharmacy.supplierDebt")} {formatMoney(book?.outstanding, "0")}
        </span>
      </PageToolbar>
      <form onSubmit={saveInvoice} className="flex flex-wrap items-end gap-2 border-b border-[var(--his-border)] bg-white p-2">
        <label className="flex flex-col gap-1 text-[11px] text-[var(--his-muted)]">
          {t("pharmacy.supplierName")}
          <Input value={supplierName} onChange={(event) => setSupplierName(event.target.value)} className="w-44" required />
        </label>
        <label className="flex flex-col gap-1 text-[11px] text-[var(--his-muted)]">
          {t("pharmacy.customerPhone")}
          <Input value={supplierPhone} onChange={(event) => setSupplierPhone(event.target.value)} className="w-36" />
        </label>
        <label className="flex flex-col gap-1 text-[11px] text-[var(--his-muted)]">
          {t("pharmacy.invoiceNumber")}
          <Input value={invoiceNumber} onChange={(event) => setInvoiceNumber(event.target.value)} className="w-36" required />
        </label>
        <label className="flex flex-col gap-1 text-[11px] text-[var(--his-muted)]">
          {t("pharmacy.invoiceTotal")}
          <Input type="number" min="0.01" step="0.01" value={total} onChange={(event) => setTotal(event.target.value)} className="w-28" required />
        </label>
        <label className="flex flex-col gap-1 text-[11px] text-[var(--his-muted)]">
          {t("pharmacy.paidNow")}
          <Input type="number" min="0" step="0.01" value={paid} onChange={(event) => setPaid(event.target.value)} placeholder="0" className="w-28" />
        </label>
        <label className="flex flex-col gap-1 text-[11px] text-[var(--his-muted)]">
          {t("pharmacy.soldAt")}
          <Input type="date" value={receivedAt} onChange={(event) => setReceivedAt(event.target.value)} className="w-40" />
        </label>
        <Button type="submit" variant="primary" disabled={saving}>
          {t("pharmacy.saveInvoice")}
        </Button>
        <span className="text-[11px] text-[var(--his-muted)]">{t("pharmacy.supplierHint")}</span>
      </form>
      <div className="flex flex-wrap items-center gap-2 border-b border-[var(--his-border)] bg-white p-2">
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
        <Input type="date" value={date} onChange={(event) => setDate(event.target.value)} className="w-40" />
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={t("pharmacy.supplierName")}
          className="max-w-xs"
        />
      </div>
      <div className="min-h-0 flex-1 p-2">
        <DataTable columns={columns} rows={rows} rowKey={(row) => row.id} loading={loading} emptyText={t("pharmacy.noInvoices")} />
      </div>
    </div>
  );
}
