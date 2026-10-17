"use client";

import { FormEvent, useMemo, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { toast } from "sonner";
import { apiDelete, apiGet, apiPatch, apiPost, apiPut, getErrorMessage } from "@/lib/api/client";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatMoney } from "@/lib/utils";
import { RADIOLOGY_CATEGORIES, type RadiologyCategoryId } from "../categories";

type RadiologyServiceRow = {
  id: number;
  name?: string;
  code?: string;
  modality?: string;
  price?: number;
};

const empty = { id: 0, name: "", code: "", price: "" };

export function RadiologyCatalogPage() {
  const t = useTranslations();
  const qc = useQueryClient();
  const [category, setCategory] = useState<RadiologyCategoryId>("ct");
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [draft, setDraft] = useState(empty);
  const [saving, setSaving] = useState(false);
  const [injectionPrice, setInjectionPrice] = useState<string | null>(null);
  const [savingInjection, setSavingInjection] = useState(false);

  const { data: savedInjection = 0 } = useQuery({
    queryKey: ["/radiology/injection-price"],
    queryFn: async () => {
      const res = await apiGet<{ price?: number }>("/radiology/injection-price");
      return Number(res.data?.price) || 0;
    },
  });

  const { data: rows = [], isLoading, refetch, isFetching } = useQuery({
    queryKey: ["/radiology/services", "catalog", category, q],
    queryFn: async () => {
      const res = await apiGet<RadiologyServiceRow[]>("/radiology/services", {
        params: { modality: category, per_page: 200, ...(q ? { search: q } : {}) },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const columns = useMemo<Column<RadiologyServiceRow>[]>(
    () => [
      { key: "name", header: t("crud.name"), cell: (row) => row.name ?? "—" },
      { key: "code", header: t("crud.code"), width: 110, cell: (row) => row.code ?? "—" },
      { key: "price", header: t("crud.price"), width: 110, cell: (row) => formatMoney(row.price) },
      {
        key: "actions",
        header: t("app.actions"),
        width: 150,
        cell: (row) => (
          <div className="flex gap-1">
            <Button
              onClick={() =>
                setDraft({
                  id: row.id,
                  name: row.name ?? "",
                  code: row.code ?? "",
                  price: row.price == null ? "" : String(row.price),
                })
              }
            >
              {t("app.edit")}
            </Button>
            <Button
              variant="danger"
              onClick={async () => {
                if (!window.confirm(t("crud.confirmDelete"))) return;
                try {
                  await apiDelete(`/radiology/services/${row.id}`);
                  toast.success(t("workspace.saved"));
                  if (draft.id === row.id) setDraft(empty);
                  await qc.invalidateQueries({ queryKey: ["/radiology/services"] });
                } catch (error) {
                  toast.error(getErrorMessage(error));
                }
              }}
            >
              {t("app.delete")}
            </Button>
          </div>
        ),
      },
    ],
    [t, qc, draft.id],
  );

  function openCategory(next: RadiologyCategoryId) {
    setCategory(next);
    setDraft(empty);
    setSearch("");
    setQ("");
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    const body = {
      name: draft.name.trim(),
      code: draft.code.trim(),
      modality: category,
      price: draft.price === "" ? 0 : Number(draft.price),
    };
    setSaving(true);
    try {
      if (draft.id) await apiPatch(`/radiology/services/${draft.id}`, body);
      else await apiPost("/radiology/services", body);
      toast.success(t("workspace.saved"));
      setDraft(empty);
      await qc.invalidateQueries({ queryKey: ["/radiology/services"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function saveInjection(event: FormEvent) {
    event.preventDefault();
    setSavingInjection(true);
    try {
      const raw = injectionPrice ?? (savedInjection ? String(savedInjection) : "");
      const price = raw === "" ? 0 : Number(raw);
      await apiPut("/radiology/injection-price", { price: Number.isFinite(price) ? price : 0 });
      setInjectionPrice(String(Number.isFinite(price) ? price : 0));
      toast.success(t("workspace.saved"));
      await qc.invalidateQueries({ queryKey: ["/radiology/injection-price"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSavingInjection(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.radiologyServices")}>
        <form onSubmit={saveInjection} className="flex items-center gap-1">
          <span className="whitespace-nowrap text-[12px]">{t("radiology.injectionPrice")}</span>
          <div className="w-20">
            <Input
              type="number"
              min={0}
              placeholder={t("crud.price")}
              value={injectionPrice ?? (savedInjection ? String(savedInjection) : "")}
              onChange={(event) => setInjectionPrice(event.target.value)}
            />
          </div>
          <Button type="submit" variant="primary" disabled={savingInjection}>
            {t("app.save")}
          </Button>
        </form>
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          onKeyDown={(event) => event.key === "Enter" && setQ(search)}
          placeholder={t("app.search")}
          className="w-56"
        />
        <Button onClick={() => setQ(search)}>{t("app.search")}</Button>
        <Button onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
      </PageToolbar>
      <div className="his-tabs flex-wrap border-b border-[var(--his-border)] px-1">
        {RADIOLOGY_CATEGORIES.map((item) => (
          <button
            key={item.id}
            type="button"
            className={`his-tab ${category === item.id ? "active" : ""}`}
            onClick={() => openCategory(item.id)}
          >
            {t(item.labelKey)}
          </button>
        ))}
      </div>
      <form
        onSubmit={save}
        className="grid gap-2 border-b border-[var(--his-border)] bg-white p-2 md:grid-cols-[1.4fr_8rem_8rem_auto]"
      >
        <Input
          required
          placeholder={t("crud.name")}
          value={draft.name}
          onChange={(event) => setDraft((current) => ({ ...current, name: event.target.value }))}
        />
        <Input
          placeholder={t("radiology.codeOptional")}
          value={draft.code}
          onChange={(event) => setDraft((current) => ({ ...current, code: event.target.value }))}
        />
        <Input
          type="number"
          min={0}
          placeholder={t("crud.price")}
          value={draft.price}
          onChange={(event) => setDraft((current) => ({ ...current, price: event.target.value }))}
        />
        <div className="flex gap-2">
          <Button type="submit" variant="primary" disabled={saving}>
            {draft.id ? t("app.save") : t("app.add")}
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
              name: row.name ?? "",
              code: row.code ?? "",
              price: row.price == null ? "" : String(row.price),
            })
          }
        />
      </div>
    </div>
  );
}
