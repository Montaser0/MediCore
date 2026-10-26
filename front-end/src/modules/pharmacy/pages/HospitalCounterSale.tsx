"use client";

import { FormEvent, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { dailyNo } from "@/lib/arabicText";
import { formatMoney } from "@/lib/utils";

type Ticket = {
  id: number;
  sent_at?: string;
  patient?: { id?: number; full_name?: string; mrn?: string } | null;
};

type Drug = {
  id: number;
  name: string;
  price?: number;
  quantity?: number;
  stock_ready?: boolean;
  stock?: { quantity_on_hand?: number } | null;
};

type Line = { medication_id: number; name: string; quantity: number; unit_price: number; stock: number };

function onHand(drug: Drug): number {
  if (drug.stock_ready) return Number(drug.stock?.quantity_on_hand ?? 0);
  return Number(drug.quantity ?? 0);
}

export function HospitalCounterSale() {
  const t = useTranslations();
  const qc = useQueryClient();
  const [ticketId, setTicketId] = useState<number | null>(null);
  const [query, setQuery] = useState("");
  const [hits, setHits] = useState<Drug[]>([]);
  const [lines, setLines] = useState<Line[]>([]);
  const [saving, setSaving] = useState(false);

  const tickets = useQuery({
    queryKey: ["/pharmacy/counter"],
    queryFn: async () => {
      const res = await apiGet<Ticket[]>("/pharmacy/counter", { params: { status: "waiting", per_page: 50 } });
      return res.data ?? [];
    },
  });

  const selected = (tickets.data ?? []).find((row) => row.id === ticketId) ?? null;
  const total = lines.reduce((sum, line) => sum + line.quantity * line.unit_price, 0);

  async function search(event: FormEvent) {
    event.preventDefault();
    const term = query.trim();
    if (term.length < 2) return;
    try {
      const res = await apiGet<Drug[]>("/pharmacy/medications", { params: { search: term, per_page: 12 } });
      const found = res.data ?? [];
      setHits(found);
      if (found.length === 0) toast.error(t("pharmacy.notFound"));
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  function addDrug(drug: Drug) {
    const stock = onHand(drug);
    if (stock <= 0) {
      toast.error(t("pharmacy.outOfStock"));
      return;
    }
    setLines((current) => {
      const existing = current.find((line) => line.medication_id === drug.id);
      if (existing) {
        return current.map((line) =>
          line.medication_id === drug.id ? { ...line, quantity: Math.min(line.quantity + 1, stock) } : line,
        );
      }
      return [...current, { medication_id: drug.id, name: drug.name, quantity: 1, unit_price: Number(drug.price ?? 0), stock }];
    });
    setHits([]);
    setQuery("");
  }

  async function sell() {
    if (!selected || lines.length === 0) return;
    setSaving(true);
    try {
      await apiPost(`/pharmacy/counter/${selected.id}/sell`, {
        lines: lines.map((line) => ({ medication_id: line.medication_id, quantity: line.quantity })),
      });
      toast.success(t("pharmacy.counterSaved"));
      setTicketId(null);
      setLines([]);
      await qc.invalidateQueries({ queryKey: ["/pharmacy/counter"] });
      await qc.invalidateQueries({ queryKey: ["patients"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="grid h-full min-h-0 grid-cols-1 md:grid-cols-[280px_1fr]">
      <aside className="min-h-0 overflow-auto border-b border-[var(--his-border)] md:border-b-0 md:border-e">
        <div className="his-panel-header">{t("pharmacy.counterWaiting")}</div>
        {(tickets.data ?? []).length === 0 ? (
          <p className="p-2 text-[12px] text-[var(--his-muted)]">{t("pharmacy.counterEmpty")}</p>
        ) : (
          (tickets.data ?? []).map((row) => (
            <button
              key={row.id}
              type="button"
              className="block w-full border-b border-[var(--his-border)] px-2 py-2 text-start text-[13px] hover:bg-[var(--his-surface)]"
              style={{ background: row.id === ticketId ? "var(--his-accent-soft)" : "transparent" }}
              onClick={() => {
                setTicketId(row.id);
                setLines([]);
              }}
            >
              <span className="block font-semibold">{row.patient?.full_name ?? "—"}</span>
              <span className="text-[var(--his-muted)]">{row.patient?.mrn ? dailyNo(row.patient.mrn) : ""}</span>
            </button>
          ))
        )}
      </aside>
      <section className="flex min-h-0 flex-col">
        {!selected ? (
          <p className="p-3 text-[13px] text-[var(--his-muted)]">
            {(tickets.data ?? []).length === 0 ? t("pharmacy.counterEmpty") : t("pharmacy.counterPick")}
          </p>
        ) : (
          <>
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--his-border)] bg-white p-2">
              <span className="text-[13px] font-semibold">
                {selected.patient?.full_name}
                {selected.patient?.mrn ? ` — ${dailyNo(selected.patient.mrn)}` : ""}
              </span>
              <span className="text-[13px] font-bold">{formatMoney(total, "0")}</span>
              <Button variant="primary" disabled={saving || lines.length === 0} onClick={() => void sell()}>
                {t("pharmacy.counterComplete")}
              </Button>
            </div>
            <form onSubmit={(event) => void search(event)} className="border-b border-[var(--his-border)] bg-white p-2">
              <div className="relative max-w-md">
                <Input value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t("pharmacy.searchByName")} />
                {hits.length > 0 ? (
                  <div className="absolute z-20 mt-1 max-h-64 w-full overflow-auto border border-[var(--his-border)] bg-white shadow">
                    {hits.map((drug) => (
                      <button
                        key={drug.id}
                        type="button"
                        className="block w-full border-b border-[var(--his-border)] px-2 py-1 text-start text-[13px] last:border-b-0 hover:bg-[var(--his-surface)]"
                        onClick={() => addDrug(drug)}
                      >
                        {drug.name}
                        <span className="text-[var(--his-muted)]"> · {onHand(drug)}</span>
                      </button>
                    ))}
                  </div>
                ) : null}
              </div>
            </form>
            <ul className="m-0 min-h-0 flex-1 space-y-1 overflow-auto p-2 text-[13px]">
              {lines.map((line) => (
                <li key={line.medication_id} className="flex items-center justify-between gap-2 border-b border-[var(--his-border)] py-1">
                  <span>{line.name}</span>
                  <Input
                    type="number"
                    min="0.01"
                    step="0.01"
                    max={line.stock}
                    value={String(line.quantity)}
                    className="w-24"
                    onChange={(event) => {
                      const quantity = Number(event.target.value);
                      setLines((current) =>
                        current.map((item) => (item.medication_id === line.medication_id ? { ...item, quantity } : item)),
                      );
                    }}
                  />
                  <span>{formatMoney(line.quantity * line.unit_price, "0")}</span>
                  <Button variant="danger" onClick={() => setLines((current) => current.filter((item) => item.medication_id !== line.medication_id))}>
                    {t("app.delete")}
                  </Button>
                </li>
              ))}
            </ul>
          </>
        )}
      </section>
    </div>
  );
}
