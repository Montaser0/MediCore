"use client";

import { FormEvent, useMemo, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { toast } from "sonner";
import { apiDelete, apiGet, apiPatch, apiPost, getErrorMessage } from "@/lib/api/client";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatMoney } from "@/lib/utils";
import { LAB_CATEGORIES, type LabCategoryId } from "../categories";

type LabTestRow = {
  id: number;
  name?: string;
  code?: string;
  category?: string;
  sample_type?: string;
  unit?: string;
  reference_range?: string;
  price?: number;
};

const empty = { id: 0, name: "", code: "", price: "", sampleType: "", unit: "", referenceRange: "" };

export function LabCatalogPage() {
  const t = useTranslations();
  const qc = useQueryClient();
  const [category, setCategory] = useState<LabCategoryId>("hematology");
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [draft, setDraft] = useState(empty);
  const [saving, setSaving] = useState(false);

  const { data: rows = [], isLoading, refetch, isFetching } = useQuery({
    queryKey: ["/laboratory/tests", "catalog", category, q],
    queryFn: async () => {
      const res = await apiGet<LabTestRow[]>("/laboratory/tests", {
        params: { category, per_page: 200, ...(q ? { search: q } : {}) },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const columns = useMemo<Column<LabTestRow>[]>(
    () => [
      { key: "name", header: t("crud.name"), cell: (row) => row.name ?? "—" },
      { key: "code", header: t("crud.code"), width: 110, cell: (row) => row.code ?? "—" },
      { key: "sample", header: t("laboratory.sampleType"), width: 110, cell: (row) => row.sample_type || "—" },
      { key: "unit", header: t("laboratory.unit"), width: 90, cell: (row) => row.unit || "—" },
      {
        key: "range",
        header: t("laboratory.referenceRange"),
        cell: (row) => row.reference_range || "—",
      },
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
                  sampleType: row.sample_type ?? "",
                  unit: row.unit ?? "",
                  referenceRange: row.reference_range ?? "",
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
                  await apiDelete(`/laboratory/tests/${row.id}`);
                  toast.success(t("workspace.saved"));
                  if (draft.id === row.id) setDraft(empty);
                  await qc.invalidateQueries({ queryKey: ["/laboratory/tests"] });
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

  function openCategory(next: LabCategoryId) {
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
      category,
      sample_type: draft.sampleType.trim(),
      unit: draft.unit.trim(),
      reference_range: draft.referenceRange.trim(),
      price: draft.price === "" ? 0 : Number(draft.price),
    };
    setSaving(true);
    try {
      if (draft.id) await apiPatch(`/laboratory/tests/${draft.id}`, body);
      else await apiPost("/laboratory/tests", body);
      toast.success(t("workspace.saved"));
      setDraft(empty);
      await qc.invalidateQueries({ queryKey: ["/laboratory/tests"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.labTests")}>
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
        {LAB_CATEGORIES.map((item) => (
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
        className="grid gap-2 border-b border-[var(--his-border)] bg-white p-2 md:grid-cols-[1.4fr_8rem_8rem_8rem_8rem_1fr_auto]"
      >
        <Input
          required
          placeholder={t("crud.name")}
          value={draft.name}
          onChange={(event) => setDraft((current) => ({ ...current, name: event.target.value }))}
        />
        <Input
          placeholder={t("laboratory.codeOptional")}
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
        <Input
          placeholder={t("laboratory.sampleType")}
          value={draft.sampleType}
          onChange={(event) => setDraft((current) => ({ ...current, sampleType: event.target.value }))}
        />
        <Input
          placeholder={t("laboratory.unit")}
          value={draft.unit}
          onChange={(event) => setDraft((current) => ({ ...current, unit: event.target.value }))}
        />
        <Input
          placeholder={t("laboratory.referenceRange")}
          value={draft.referenceRange}
          onChange={(event) => setDraft((current) => ({ ...current, referenceRange: event.target.value }))}
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
              sampleType: row.sample_type ?? "",
              unit: row.unit ?? "",
              referenceRange: row.reference_range ?? "",
            })
          }
        />
      </div>
    </div>
  );
}
