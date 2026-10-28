"use client";

import { FormEvent, useEffect, useMemo, useRef, useState, type KeyboardEvent } from "react";
import { useRouter } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { formatMoney } from "@/lib/utils";
import { useEditionStore } from "@/stores/edition-store";
import { printPharmacyReceipt, type PharmacyReceipt } from "../print/printPharmacyReceipt";
import { availableIn, baseOnHand, formatPack, preferredSaleUnit, SALE_UNITS, unitPrice, type SaleUnit } from "../pack";

type Drug = {
  id: number;
  name: string;
  barcode?: string | null;
  generic_name?: string | null;
  quantity?: number;
  price?: number;
  price_strip?: number;
  price_tablet?: number;
  strips_per_box?: number;
  tablets_per_strip?: number;
  stock_ready?: boolean;
  stock?: { quantity_on_hand?: number } | null;
};

type Alternative = {
  id: number;
  name: string;
  generic_name?: string | null;
  strength?: string | null;
  manufacturer?: string | null;
  barcode?: string | null;
  price?: number;
  price_strip?: number;
  price_tablet?: number;
  strips_per_box?: number;
  tablets_per_strip?: number;
  on_hand?: number;
  expiry_date?: string | null;
};

type Line = {
  medication_id: number;
  name: string;
  barcode: string;
  quantity: number;
  unit: SaleUnit;
  unit_price: number;
  stock: number;
  base: number;
  strips_per_box: number;
  tablets_per_strip: number;
  price_box: number;
  price_strip: number;
  price_tablet: number;
};

function lineKey(line: { medication_id: number; unit: SaleUnit }) {
  return `${line.medication_id}-${line.unit}`;
}

export function PharmacySalePage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const ready = useEditionStore((state) => state.ready);
  const pos = useEditionStore((state) => state.hasFeature("pharmacy.pos"));
  const input = useRef<HTMLInputElement>(null);
  const discountRef = useRef<HTMLInputElement>(null);
  const paidRef = useRef<HTMLInputElement>(null);
  const sellRef = useRef<() => void>(() => {});
  const pendingQty = useRef<string | null>(null);
  const shelf = useRef<Drug[]>([]);
  const linesRef = useRef<Line[]>([]);
  const barcodeRef = useRef("");
  const lookupTimer = useRef<number | null>(null);
  const [barcode, setBarcode] = useState("");
  const [nameHits, setNameHits] = useState<Drug[]>([]);
  const [hitIndex, setHitIndex] = useState(0);
  const [alternatives, setAlternatives] = useState<{ forName: string; rows: Alternative[] } | null>(null);
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
    setHitIndex(0);
  }, [nameHits]);

  useEffect(() => {
    if (pendingQty.current == null) return;
    const id = pendingQty.current;
    pendingQty.current = null;
    document.querySelector<HTMLInputElement>(`[data-qty="${id}"]`)?.focus();
  }, [lines]);

  useEffect(() => {
    function onKey(event: globalThis.KeyboardEvent) {
      if (event.key === "F2") {
        event.preventDefault();
        discountRef.current?.focus();
        return;
      }
      if (event.key === "F4") {
        event.preventDefault();
        paidRef.current?.focus();
        return;
      }
      if (event.key === "Escape") {
        setAlternatives(null);
        setNameHits([]);
        input.current?.focus();
        return;
      }
      if (event.key === "F9" || (event.ctrlKey && event.key === "Enter")) {
        event.preventDefault();
        sellRef.current();
      }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
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
      {
        key: "unit",
        header: t("pharmacy.saleUnit"),
        width: 110,
        cell: (row) => (
          <Select
            value={row.unit}
            onChange={(event) => {
              const unit = event.target.value as SaleUnit;
              setLines((current) =>
                current.map((line) => {
                  if (lineKey(line) !== lineKey(row)) return line;
                  const stock = availableIn(line.base, unit, line.strips_per_box, line.tablets_per_strip);
                  if (stock < 1) return line;
                  const price = unitPrice(
                    { price: line.price_box, price_strip: line.price_strip, price_tablet: line.price_tablet, strips_per_box: line.strips_per_box, tablets_per_strip: line.tablets_per_strip },
                    unit,
                  );
                  return { ...line, unit, unit_price: price, stock, quantity: Math.min(line.quantity, stock) };
                }),
              );
            }}
          >
            {SALE_UNITS.map((unit) => (
              <option key={unit} value={unit}>
                {unit}
              </option>
            ))}
          </Select>
        ),
      },
      {
        key: "quantity",
        header: t("pharmacy.quantity"),
        width: 110,
        cell: (row) => (
            <Input
            data-qty={lineKey(row)}
            type="number"
            min="1"
            step="1"
            max={row.stock}
            value={String(row.quantity)}
            onKeyDown={(event) => {
              if (event.key === "Enter") {
                event.preventDefault();
                input.current?.focus();
              }
            }}
            onChange={(event) => {
              const next = Number(event.target.value);
              setLines((current) =>
                current.map((line) =>
                  lineKey(line) === lineKey(row)
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
      {
        key: "alt",
        header: t("pharmacy.alternatives"),
        width: 90,
        cell: (row) => (
          <Button type="button" onClick={() => void loadAlternatives({ id: row.medication_id, name: row.name })}>
            {t("pharmacy.alternatives")}
          </Button>
        ),
      },
    ],
    [t],
  );

  function remember(code: string) {
    barcodeRef.current = code;
    setBarcode(code);
  }

  async function loadAlternatives(drug: Pick<Drug, "id" | "name">) {
    try {
      const res = await apiGet<Alternative[]>(`/pharmacy/pos/medications/${drug.id}/alternatives`);
      setAlternatives({ forName: drug.name, rows: res.data ?? [] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  function addDrug(drug: Drug, code: string) {
    const base = baseOnHand(drug);
    const strips = Math.max(1, Number(drug.strips_per_box ?? 1));
    const tablets = Math.max(1, Number(drug.tablets_per_strip ?? 1));
    const unit = preferredSaleUnit(base, strips, tablets);
    const stock = availableIn(base, unit, strips, tablets);
    const existing = linesRef.current.find((line) => line.medication_id === drug.id && line.unit === unit);
    if (stock <= 0 || (existing && existing.quantity >= stock)) {
      toast.error(t("pharmacy.outOfStock"));
      void loadAlternatives(drug);
      return;
    }
    setAlternatives(null);
    pendingQty.current = `${drug.id}-${unit}`;
    setLines((current) => {
      const line = current.find((item) => item.medication_id === drug.id && item.unit === unit);
      if (!line) {
        return [
          ...current,
          {
            medication_id: drug.id,
            name: drug.name,
            barcode: drug.barcode ?? code,
            quantity: 1,
            unit,
            unit_price: unitPrice(drug, unit),
            stock,
            base,
            strips_per_box: strips,
            tablets_per_strip: tablets,
            price_box: Number(drug.price ?? 0),
            price_strip: Number(drug.price_strip ?? 0),
            price_tablet: Number(drug.price_tablet ?? 0),
          },
        ];
      }
      if (line.quantity >= stock) return current;
      return current.map((item) =>
        item.medication_id === drug.id && item.unit === unit ? { ...item, quantity: item.quantity + 1, base, stock } : item,
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

  function onSearchKey(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === "ArrowDown" && nameHits.length > 0) {
      event.preventDefault();
      setHitIndex((current) => Math.min(current + 1, nameHits.length - 1));
    }
    if (event.key === "ArrowUp" && nameHits.length > 0) {
      event.preventDefault();
      setHitIndex((current) => Math.max(current - 1, 0));
    }
  }

  function scan(event: FormEvent) {
    event.preventDefault();
    if (lookupTimer.current) window.clearTimeout(lookupTimer.current);
    if (nameHits.length > 0) {
      const drug = nameHits[Math.min(hitIndex, nameHits.length - 1)];
      setNameHits([]);
      addDrug(drug, drug.barcode ?? "");
      return;
    }
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
      const res = await apiPost<PharmacyReceipt & { customer?: { name?: string } | null }>(
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
          lines: lines.map((line) => ({ medication_id: line.medication_id, quantity: line.quantity, unit: line.unit })),
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
      if (res.data) {
        try {
          printPharmacyReceipt(res.data);
        } catch (error) {
          toast.error(getErrorMessage(error));
        }
      }
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
      input.current?.focus();
    }
  }

  sellRef.current = () => {
    void sell();
  };

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
            onKeyDown={onSearchKey}
            placeholder={t("pharmacy.scan")}
          />
          {nameHits.length > 0 ? (
            <div className="absolute z-20 mt-1 max-h-64 w-full overflow-auto border border-[var(--his-border)] bg-white shadow">
              {nameHits.map((drug, index) => (
                <button
                  key={drug.id}
                  type="button"
                  className={`block w-full border-b border-[var(--his-border)] px-2 py-1 text-start text-[13px] last:border-b-0 hover:bg-[var(--his-surface)] ${index === hitIndex ? "bg-[var(--his-surface)]" : ""}`}
                  onClick={() => {
                    setNameHits([]);
                    addDrug(drug, drug.barcode ?? "");
                  }}
                >
                  {drug.name}
                  <span className="text-[var(--his-muted)]">
                    {" "}
                    · {formatPack(baseOnHand(drug), drug.strips_per_box, drug.tablets_per_strip, { box: t("pharmacy.unitBox"), strip: t("pharmacy.unitStrip"), tablet: t("pharmacy.unitTablet") })}
                  </span>
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
            ref={discountRef}
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
            ref={paidRef}
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
      <p className="px-2 text-[12px] text-[var(--his-muted)]">{t("pharmacy.keysHint")}</p>
      {alternatives ? (
        <div className="border-b border-[var(--his-border)] bg-white px-2 py-2">
          <p className="mb-1 text-[13px] font-bold">{t("pharmacy.alternativesFor", { name: alternatives.forName })}</p>
          {alternatives.rows.length === 0 ? (
            <p className="text-[12px] text-[var(--his-muted)]">{t("pharmacy.alternativesEmpty")}</p>
          ) : (
            <div className="flex flex-wrap gap-1">
              {alternatives.rows.map((drug) => (
                <Button
                  key={drug.id}
                  type="button"
                  onClick={() =>
                    addDrug(
                      {
                        id: drug.id,
                        name: drug.name,
                        barcode: drug.barcode,
                        price: drug.price,
                        price_strip: drug.price_strip,
                        price_tablet: drug.price_tablet,
                        strips_per_box: drug.strips_per_box,
                        tablets_per_strip: drug.tablets_per_strip,
                        stock_ready: true,
                        stock: { quantity_on_hand: drug.on_hand },
                      },
                      drug.barcode ?? "",
                    )
                  }
                >
                  {drug.name} · {drug.on_hand}
                  {drug.expiry_date ? ` · ${drug.expiry_date}` : ""}
                </Button>
              ))}
            </div>
          )}
        </div>
      ) : null}
      <div className="min-h-0 flex-1 p-2">
        <DataTable columns={columns} rows={lines} rowKey={(row) => lineKey(row)} emptyText={t("pharmacy.scan")} />
      </div>
    </div>
  );
}
