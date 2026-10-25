"use client";

import { FormEvent, useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { formatMoney } from "@/lib/utils";
import { useEditionStore } from "@/stores/edition-store";

type Drug = {
  id: number;
  name: string;
  barcode?: string | null;
  quantity?: number;
  price?: number;
  stock_ready?: boolean;
  stock?: { quantity_on_hand?: number } | null;
};

function sellable(drug: Drug): number {
  if (drug.stock_ready) return Number(drug.stock?.quantity_on_hand ?? 0);
  return Number(drug.quantity ?? 0);
}

type Line = {
  medication_id: number;
  name: string;
  barcode: string;
  quantity: number;
  unit_price: number;
  stock: number;
};

export function PharmacySalePage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const ready = useEditionStore((state) => state.ready);
  const pos = useEditionStore((state) => state.hasFeature("pharmacy.pos"));
  const input = useRef<HTMLInputElement>(null);
  const shelf = useRef<Drug[]>([]);
  const linesRef = useRef<Line[]>([]);
  const barcodeRef = useRef("");
  const lookupTimer = useRef<number | null>(null);
  const [barcode, setBarcode] = useState("");
  const [nameHits, setNameHits] = useState<Drug[]>([]);
  const [lines, setLines] = useState<Line[]>([]);
  const [discount, setDiscount] = useState("");
  const [paid, setPaid] = useState("");
  const [customerId, setCustomerId] = useState<number | null>(null);
  const [customerName, setCustomerName] = useState("");
  const [customerPhone, setCustomerPhone] = useState("");
  const [suggestions, setSuggestions] = useState<Array<{ id: number; name: string; phone?: string | null; balance?: number }>>([]);
  const [saving, setSaving] = useState(false);
  linesRef.current = lines;

  useEffect(() => {
    if (ready && !pos) router.replace(`/${locale}/dashboard/pharmacy`);
  }, [ready, pos, router, locale]);

  useEffect(() => {
    input.current?.focus();
  }, []);

  useEffect(() => {
    if (!ready || !pos) return;
    apiGet<Drug[]>("/pharmacy/pos/medications", { params: { per_page: 200 } })
      .then((res) => {
        shelf.current = res.data ?? [];
      })
      .catch(() => {
        shelf.current = [];
      });
    return () => {
      if (lookupTimer.current) window.clearTimeout(lookupTimer.current);
    };
  }, [ready, pos]);

  const subtotal = lines.reduce((sum, line) => sum + line.quantity * line.unit_price, 0);
  const discountAmount = Math.min(Math.max(Number(discount) || 0, 0), subtotal);
  const total = Math.max(subtotal - discountAmount, 0);
  const paidAmount = paid.trim() === "" ? total : Math.min(Math.max(Number(paid) || 0, 0), total);
  const due = Math.max(Math.round((total - paidAmount) * 100) / 100, 0);

  useEffect(() => {
    if (due <= 0 || customerName.trim() === "") {
      setSuggestions([]);
      return;
    }
    const timer = window.setTimeout(() => {
      apiGet<Array<{ id: number; name: string; phone?: string | null; balance?: number }>>("/pharmacy/pos/customers", {
        params: { search: customerName.trim() },
      })
        .then((res) => setSuggestions(res.data ?? []))
        .catch(() => setSuggestions([]));
    }, 250);
    return () => window.clearTimeout(timer);
  }, [customerName, due]);

  const columns = useMemo<Column<Line>[]>(
    () => [
      { key: "name", header: t("crud.name"), cell: (row) => row.name },
      { key: "barcode", header: t("pharmacy.barcode"), width: 140, cell: (row) => row.barcode },
      {
        key: "quantity",
        header: t("pharmacy.quantity"),
        width: 110,
        cell: (row) => (
          <Input
            type="number"
            min="0.01"
            step="0.01"
            max={row.stock}
            value={String(row.quantity)}
            onChange={(event) => {
              const next = Number(event.target.value);
              setLines((current) =>
                current.map((line) =>
                  line.medication_id === row.medication_id
                    ? { ...line, quantity: Number.isFinite(next) && next > 0 ? Math.min(next, line.stock) : line.quantity }
                    : line,
                ),
              );
            }}
          />
        ),
      },
      { key: "price", header: t("pharmacy.price"), width: 120, cell: (row) => formatMoney(row.unit_price) },
      {
        key: "line",
        header: t("pharmacy.lineTotal"),
        width: 120,
        cell: (row) => formatMoney(row.quantity * row.unit_price),
      },
    ],
    [t],
  );

  function remember(code: string) {
    barcodeRef.current = code;
    setBarcode(code);
  }

  function addDrug(drug: Drug, code: string) {
    const stock = sellable(drug);
    const existing = linesRef.current.find((line) => line.medication_id === drug.id);
    if (stock <= 0 || (existing && existing.quantity >= stock)) {
      toast.error(t("pharmacy.outOfStock"));
      return;
    }
    setLines((current) => {
      const line = current.find((item) => item.medication_id === drug.id);
      if (!line) {
        return [
          ...current,
          {
            medication_id: drug.id,
            name: drug.name,
            barcode: drug.barcode ?? code,
            quantity: 1,
            unit_price: Number(drug.price ?? 0),
            stock,
          },
        ];
      }
      if (line.quantity >= stock) return current;
      return current.map((item) =>
        item.medication_id === drug.id ? { ...item, quantity: item.quantity + 1 } : item,
      );
    });
    remember("");
    input.current?.focus();
  }

  function localMatch(code: string) {
    const exact = shelf.current.find((drug) => (drug.barcode ?? "").trim() === code);
    const prefix = shelf.current.some((drug) => {
      const value = (drug.barcode ?? "").trim();
      return value.startsWith(code) && value !== code;
    });
    return { exact, prefix };
  }

  async function lookup(code: string, announce: boolean) {
    if (!code || barcodeRef.current !== code) return;
    const { exact } = localMatch(code);
    if (exact) {
      addDrug(exact, code);
      return;
    }
    try {
      const res = await apiGet<Drug>(`/pharmacy/pos/barcode/${encodeURIComponent(code)}`);
      if (barcodeRef.current !== code) return;
      const drug = res.data;
      if (!drug?.id) {
        if (announce) toast.error(t("pharmacy.notFound"));
        return;
      }
      const known = shelf.current.some((item) => item.id === drug.id);
      if (!known) shelf.current = [...shelf.current, drug];
      addDrug(drug, code);
    } catch (error) {
      if (announce && barcodeRef.current === code) toast.error(getErrorMessage(error));
    }
  }

  function hasLetter(value: string) {
    return /[A-Za-z\u0600-\u06FF]/.test(value);
  }

  function onCode(value: string) {
    const code = value.trim();
    remember(code);
    if (lookupTimer.current) window.clearTimeout(lookupTimer.current);
    if (!code) {
      setNameHits([]);
      return;
    }
    if (hasLetter(code)) {
      lookupTimer.current = window.setTimeout(() => {
        void apiGet<Drug[]>("/pharmacy/pos/medications", { params: { search: code, per_page: 8 } })
          .then((res) => {
            if (barcodeRef.current !== code) return;
            setNameHits(res.data ?? []);
          })
          .catch(() => {
            if (barcodeRef.current === code) setNameHits([]);
          });
      }, 250);
      return;
    }
    setNameHits([]);
    const { exact, prefix } = localMatch(code);
    if (exact && !prefix) {
      addDrug(exact, code);
      return;
    }
    lookupTimer.current = window.setTimeout(() => {
      void lookup(code, false);
    }, 280);
  }

  async function pickByName(code: string) {
    try {
      const res = await apiGet<Drug[]>("/pharmacy/pos/medications", { params: { search: code, per_page: 8 } });
      if (barcodeRef.current !== code) return;
      const hits = res.data ?? [];
      if (hits.length === 1) {
        setNameHits([]);
        addDrug(hits[0], hits[0].barcode ?? "");
        return;
      }
      setNameHits(hits);
      if (hits.length === 0) toast.error(t("pharmacy.notFound"));
    } catch (error) {
      if (barcodeRef.current === code) toast.error(getErrorMessage(error));
    }
  }

  function scan(event: FormEvent) {
    event.preventDefault();
    if (lookupTimer.current) window.clearTimeout(lookupTimer.current);
    const code = barcodeRef.current.trim();
    if (!code) return;
    if (hasLetter(code)) {
      void pickByName(code);
      return;
    }
    void lookup(code, true);
  }

  async function sell() {
    if (lines.length === 0) return;
    if (due > 0 && !customerId && customerName.trim() === "") {
      toast.error(t("pharmacy.customerRequired"));
      return;
    }
    setSaving(true);
    try {
      const res = await apiPost<{ sale_number?: string; due_amount?: number; customer?: { name?: string } | null }>(
        "/pharmacy/pos/sales",
        {
          discount: discountAmount,
          ...(paid.trim() !== "" ? { cash_amount: paidAmount } : {}),
          ...(due > 0
            ? {
                customer_id: customerId ?? undefined,
                customer_name: customerName.trim(),
                customer_phone: customerPhone.trim() || undefined,
              }
            : {}),
          lines: lines.map((line) => ({ medication_id: line.medication_id, quantity: line.quantity })),
        },
      );
      const name = res.data?.customer?.name;
      toast.success(
        `${t("pharmacy.savedSale")} ${res.data?.sale_number ?? ""}${name ? ` · ${t("pharmacy.debtSaved")} ${name}` : ""}`.trim(),
      );
      setLines([]);
      setDiscount("");
      setPaid("");
      setCustomerId(null);
      setCustomerName("");
      setCustomerPhone("");
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
      input.current?.focus();
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("pharmacy.posTitle")}>
        <span className="text-[13px] font-bold">{formatMoney(total, "0")}</span>
        <Button variant="primary" disabled={saving || lines.length === 0} onClick={() => void sell()}>
          {t("pharmacy.completeSale")}
        </Button>
      </PageToolbar>
      <form onSubmit={scan} className="flex flex-wrap items-center gap-2 border-b border-[var(--his-border)] bg-white p-2">
        <div className="relative max-w-md flex-1">
          <Input
            ref={input}
            value={barcode}
            onChange={(event) => onCode(event.target.value)}
            placeholder={t("pharmacy.scan")}
          />
          {nameHits.length > 0 ? (
            <div className="absolute z-20 mt-1 max-h-64 w-full overflow-auto border border-[var(--his-border)] bg-white shadow">
              {nameHits.map((drug) => (
                <button
                  key={drug.id}
                  type="button"
                  className="block w-full border-b border-[var(--his-border)] px-2 py-1 text-start text-[13px] last:border-b-0 hover:bg-[var(--his-surface)]"
                  onClick={() => {
                    setNameHits([]);
                    addDrug(drug, drug.barcode ?? "");
                  }}
                >
                  {drug.name}
                  <span className="text-[var(--his-muted)]"> · {sellable(drug)}</span>
                </button>
              ))}
            </div>
          ) : null}
        </div>
        <Button type="submit" variant="primary">
          {t("pharmacy.barcode")}
        </Button>
        <span className="text-[13px]">{t("pharmacy.subtotal")} {formatMoney(subtotal, "0")}</span>
        <label className="flex items-center gap-2 text-[13px]">
          {t("pharmacy.discount")}
          <Input
            type="number"
            min="0"
            step="0.01"
            value={discount}
            onChange={(event) => setDiscount(event.target.value)}
            className="w-28"
          />
        </label>
      </form>
      <div className="flex flex-wrap items-end gap-2 border-b border-[var(--his-border)] bg-white px-2 pb-2">
        <label className="flex items-center gap-2 text-[13px]">
          {t("pharmacy.paid")}
          <Input
            type="number"
            min="0"
            step="0.01"
            value={paid}
            placeholder={String(Math.round(total * 100) / 100)}
            onChange={(event) => setPaid(event.target.value)}
            className="w-28"
          />
        </label>
        <span className="text-[13px] font-bold">
          {t("pharmacy.due")} {formatMoney(due, "0")}
        </span>
        {due > 0 ? (
          <>
            <div className="relative">
              <Input
                value={customerName}
                onChange={(event) => {
                  setCustomerId(null);
                  setCustomerName(event.target.value);
                }}
                placeholder={t("pharmacy.customerName")}
                className="w-44"
              />
              {suggestions.length > 0 ? (
                <div className="absolute z-10 mt-1 w-56 border border-[var(--his-border)] bg-white shadow">
                  {suggestions.map((customer) => (
                    <button
                      key={customer.id}
                      type="button"
                      className="block w-full px-2 py-1 text-start text-[13px] hover:bg-[var(--his-surface)]"
                      onClick={() => {
                        setCustomerId(customer.id);
                        setCustomerName(customer.name);
                        setCustomerPhone(customer.phone ?? "");
                        setSuggestions([]);
                      }}
                    >
                      {customer.name}
                      {customer.phone ? ` · ${customer.phone}` : ""}
                    </button>
                  ))}
                </div>
              ) : null}
            </div>
            <Input
              value={customerPhone}
              onChange={(event) => setCustomerPhone(event.target.value)}
              placeholder={t("pharmacy.customerPhone")}
              className="w-36"
            />
          </>
        ) : null}
      </div>
      <p className="px-2 pt-2 text-[12px] text-[var(--his-muted)]">{t("pharmacy.debtHint")}</p>
      <div className="min-h-0 flex-1 p-2">
        <DataTable columns={columns} rows={lines} rowKey={(row) => row.medication_id} emptyText={t("pharmacy.scan")} />
      </div>
    </div>
  );
}
