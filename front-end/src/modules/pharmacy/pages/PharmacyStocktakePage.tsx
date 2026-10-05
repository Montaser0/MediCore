"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { apiGet, apiPatch, apiPost, getErrorMessage } from "@/lib/api/client";
import { useEditionStore } from "@/stores/edition-store";

type CountLine = {
  id: number;
  name: string;
  barcode?: string | null;
  system_quantity?: number | null;
  counted_quantity?: number | null;
  variance?: number | null;
};

type Stocktake = {
  id: number;
  stocktake_number?: string;
  status?: string;
  lines?: CountLine[];
};

export function PharmacyStocktakePage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const ready = useEditionStore((state) => state.ready);
  const pos = useEditionStore((state) => state.hasFeature("pharmacy.pos"));
  const [sheet, setSheet] = useState<Stocktake | null>(null);
  const [drafts, setDrafts] = useState<Record<number, string>>({});
  const [barcode, setBarcode] = useState("");
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (ready && !pos) router.replace(`/${locale}/dashboard/pharmacy`);
  }, [ready, pos, router, locale]);

  useEffect(() => {
    if (!ready || !pos) return;
    apiGet<Stocktake | null>("/pharmacy/pos/stocktakes/open")
      .then((res) => setSheet(res.data ?? null))
      .catch((error) => toast.error(getErrorMessage(error)));
  }, [ready, pos]);

  const rows = sheet?.lines ?? [];

  async function openSheet() {
    setSaving(true);
    try {
      const res = await apiPost<Stocktake>("/pharmacy/pos/stocktakes", {});
      setSheet(res.data ?? null);
      setDrafts({});
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function saveLine(line: CountLine) {
    if (!sheet) return;
    const raw = drafts[line.id];
    if (raw === undefined || raw.trim() === "") return;
    const counted = Number(raw);
    if (!Number.isFinite(counted) || counted < 0) return;
    if (line.counted_quantity != null && Number(line.counted_quantity) === counted) return;
    try {
      const res = await apiPatch<CountLine>(`/pharmacy/pos/stocktakes/${sheet.id}/lines/${line.id}`, {
        counted_quantity: counted,
      });
      const saved = res.data;
      setSheet((current) => {
        if (!current?.lines || !saved) return current;
        return {
          ...current,
          lines: current.lines.map((row) =>
            row.id === line.id
              ? {
                  ...row,
                  system_quantity: saved.system_quantity,
                  counted_quantity: saved.counted_quantity,
                  variance:
                    saved.system_quantity == null || saved.counted_quantity == null
                      ? null
                      : Math.round((saved.counted_quantity - saved.system_quantity) * 100) / 100,
                }
              : row,
          ),
        };
      });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  function jump(event: FormEvent) {
    event.preventDefault();
    const code = barcode.trim();
    if (!code) return;
    const line = rows.find((row) => row.barcode === code);
    if (!line) {
      toast.error(t("pharmacy.notFound"));
      return;
    }
    document.getElementById(`count-${line.id}`)?.focus();
    setBarcode("");
  }

  async function post() {
    if (!sheet) return;
    setSaving(true);
    try {
      await apiPost(`/pharmacy/pos/stocktakes/${sheet.id}/post`, {});
      toast.success(t("pharmacy.stocktakePosted"));
      setSheet(null);
      setDrafts({});
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  const columns = useMemo<Column<CountLine>[]>(
    () => [
      { key: "name", header: t("crud.name"), cell: (row) => row.name },
      { key: "barcode", header: t("pharmacy.barcode"), width: 140, cell: (row) => row.barcode ?? "—" },
      {
        key: "system",
        header: t("pharmacy.systemQty"),
        width: 110,
        cell: (row) => (row.system_quantity == null ? "—" : String(row.system_quantity)),
      },
      {
        key: "counted",
        header: t("pharmacy.countedQty"),
        width: 130,
        cell: (row) => (
          <Input
            id={`count-${row.id}`}
            type="number"
            min="0"
            step="0.01"
            value={drafts[row.id] ?? (row.counted_quantity == null ? "" : String(row.counted_quantity))}
            onChange={(event) => setDrafts((current) => ({ ...current, [row.id]: event.target.value }))}
            onBlur={() => void saveLine(row)}
            onKeyDown={(event) => {
              if (event.key === "Enter") {
                event.preventDefault();
                void saveLine(row);
              }
            }}
          />
        ),
      },
      {
        key: "variance",
        header: t("pharmacy.variance"),
        width: 90,
        cell: (row) => (row.variance == null ? "—" : String(row.variance)),
      },
    ],
    // saveLine closes over drafts and sheet; including them keeps the blur handler current.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [drafts, sheet, t],
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("pharmacy.stocktakeTitle")}>
        {sheet ? (
          <span className="text-[13px] font-bold">{sheet.stocktake_number}</span>
        ) : null}
        {sheet ? (
          <Button variant="primary" disabled={saving} onClick={() => void post()}>
            {t("pharmacy.postStocktake")}
          </Button>
        ) : (
          <Button variant="primary" disabled={saving} onClick={() => void openSheet()}>
            {t("pharmacy.startStocktake")}
          </Button>
        )}
      </PageToolbar>
      <p className="px-2 pt-2 text-[12px] text-[var(--his-muted)]">{t("pharmacy.stocktakeHint")}</p>
      {sheet ? (
        <form onSubmit={jump} className="flex gap-2 border-b border-[var(--his-border)] bg-white p-2">
          <Input
            value={barcode}
            onChange={(event) => setBarcode(event.target.value)}
            placeholder={t("pharmacy.scan")}
            className="max-w-md"
          />
          <Button type="submit" variant="primary">
            {t("pharmacy.barcode")}
          </Button>
        </form>
      ) : null}
      <div className="min-h-0 flex-1 p-2">
        <DataTable columns={columns} rows={rows} rowKey={(row) => row.id} emptyText={t("pharmacy.startStocktake")} />
      </div>
    </div>
  );
}
