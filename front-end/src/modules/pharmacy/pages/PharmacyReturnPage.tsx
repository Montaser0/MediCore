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

type SaleItem = {
  id: number;
  name: string;
  barcode?: string | null;
  quantity: number;
  returned_quantity?: number;
  unit_price: number;
  back?: string;
};

type Sale = {
  id: number;
  sale_number: string;
  subtotal?: number;
  discount?: number;
  total?: number;
  sold_at?: string;
  items?: SaleItem[];
};

export function PharmacyReturnPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const ready = useEditionStore((state) => state.ready);
  const pos = useEditionStore((state) => state.hasFeature("pharmacy.pos"));
  const [search, setSearch] = useState("");
  const [sales, setSales] = useState<Sale[]>([]);
  const [sale, setSale] = useState<Sale | null>(null);
  const [lines, setLines] = useState<SaleItem[]>([]);
  const [reason, setReason] = useState("");
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [listedQuery, setListedQuery] = useState("");

  useEffect(() => {
    if (ready && !pos) router.replace(`/${locale}/dashboard/pharmacy`);
  }, [ready, pos, router, locale]);

  const load = useCallback(async (query = "") => {
    setLoading(true);
    setListedQuery(query);
    try {
      const res = await apiGet<Sale[]>("/pharmacy/pos/sales", { params: query ? { search: query } : {} });
      setSales(res.data ?? []);
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (ready && pos) void load();
  }, [ready, pos, load]);

  function openSale(row: Sale) {
    setSale(row);
    setLines((row.items ?? []).map((item) => ({ ...item, back: "" })));
  }

  const saleColumns = useMemo<Column<Sale>[]>(
    () => [
      { key: "when", header: t("pharmacy.soldAt"), width: 150, cell: (row) => formatDate(row.sold_at, true) },
      { key: "number", header: t("pharmacy.saleNumber"), width: 150, cell: (row) => row.sale_number },
      {
        key: "items",
        header: t("pharmacy.medications"),
        cell: (row) => (row.items ?? []).map((item) => `${item.name} ×${item.quantity}`).join("، ") || "—",
      },
      { key: "total", header: t("pharmacy.net"), width: 120, cell: (row) => formatMoney(row.total, "0") },
    ],
    [t],
  );

  const lineColumns = useMemo<Column<SaleItem>[]>(
    () => [
      { key: "name", header: t("crud.name"), cell: (row) => row.name },
      { key: "barcode", header: t("pharmacy.barcode"), width: 140, cell: (row) => row.barcode ?? "—" },
      { key: "sold", header: t("pharmacy.soldQty"), width: 90, cell: (row) => String(row.quantity) },
      {
        key: "returned",
        header: t("pharmacy.alreadyReturned"),
        width: 110,
        cell: (row) => String(row.returned_quantity ?? 0),
      },
      {
        key: "back",
        header: t("pharmacy.returnQty"),
        width: 120,
        cell: (row) => {
          const left = Math.max(row.quantity - Number(row.returned_quantity ?? 0), 0);
          return (
            <Input
              type="number"
              min="0"
              step="0.01"
              max={left}
              disabled={left <= 0}
              value={row.back ?? ""}
              onClick={(event) => event.stopPropagation()}
              onChange={(event) => {
                const next = event.target.value;
                setLines((current) => current.map((line) => (line.id === row.id ? { ...line, back: next } : line)));
              }}
            />
          );
        },
      },
    ],
    [t],
  );

  function find(event: FormEvent) {
    event.preventDefault();
    void load(search.trim());
  }

  async function submit() {
    if (!sale) return;
    const payload = lines
      .map((row) => ({ sale_item_id: row.id, quantity: Number(row.back) }))
      .filter((line) => Number.isFinite(line.quantity) && line.quantity > 0);
    if (payload.length === 0) return;
    setSaving(true);
    try {
      const res = await apiPost<{ return_number?: string; debt_reduction?: number }>("/pharmacy/pos/returns", {
        sale_id: sale.id,
        reason,
        lines: payload,
      });
      const debt = Number(res.data?.debt_reduction ?? 0);
      toast.success(
        `${t("pharmacy.returnSaved")} ${res.data?.return_number ?? ""}${debt > 0 ? ` · ${t("pharmacy.debtCut")} ${formatMoney(debt, "0")}` : ""}`.trim(),
      );
      setSale(null);
      setLines([]);
      setReason("");
      await load(search.trim());
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("pharmacy.returnTitle")}>
        {sale ? (
          <>
            <span className="text-[13px] font-bold">{sale.sale_number}</span>
            <Button
              onClick={() => {
                setSale(null);
                setLines([]);
              }}
            >
              {t("pharmacy.backToSales")}
            </Button>
            <Button variant="primary" disabled={saving} onClick={() => void submit()}>
              {t("pharmacy.completeReturn")}
            </Button>
          </>
        ) : (
          <span className="text-[13px]">{listedQuery ? t("pharmacy.findSale") : t("pharmacy.todaySales")}</span>
        )}
      </PageToolbar>
      {sale ? (
        <div className="flex flex-wrap items-center gap-2 border-b border-[var(--his-border)] bg-white p-2">
          <Input
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            placeholder={t("pharmacy.returnReason")}
            className="max-w-xs"
          />
          <span className="text-[13px]">
            {formatDate(sale.sold_at, true)} · {t("pharmacy.discount")} {formatMoney(sale.discount, "0")} · {t("pharmacy.net")}{" "}
            {formatMoney(sale.total, "0")}
          </span>
        </div>
      ) : (
        <form onSubmit={find} className="flex gap-2 border-b border-[var(--his-border)] bg-white p-2">
          <Input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={t("pharmacy.searchSale")}
            className="max-w-md"
          />
          <Button type="submit" variant="primary">
            {t("pharmacy.findSale")}
          </Button>
        </form>
      )}
      <p className="px-2 pt-2 text-[12px] text-[var(--his-muted)]">{t("pharmacy.returnHint")}</p>
      <div className="min-h-0 flex-1 p-2">
        {sale ? (
          <DataTable columns={lineColumns} rows={lines} rowKey={(row) => row.id} emptyText={t("pharmacy.noSales")} />
        ) : (
          <DataTable
            columns={saleColumns}
            rows={sales}
            rowKey={(row) => row.id}
            loading={loading}
            emptyText={t("pharmacy.noSales")}
            onSelect={openSale}
          />
        )}
      </div>
    </div>
  );
}
