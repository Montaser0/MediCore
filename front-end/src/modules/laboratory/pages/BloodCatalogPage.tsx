"use client";

import { FormEvent, useMemo, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { toast } from "sonner";
import { apiDelete, apiGet, apiPatch, apiPost, getErrorMessage } from "@/lib/api/client";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { ExcelButtons } from "@/components/shared/ExcelButtons";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatMoney } from "@/lib/utils";

type BloodProductRow = {
  id: number;
  name?: string;
  code?: string;
  price?: number;
};

const empty = { id: 0, name: "", price: "" };

export function BloodCatalogPage() {
  const t = useTranslations();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [draft, setDraft] = useState(empty);
  const [saving, setSaving] = useState(false);

  const { data: rows = [], isLoading, refetch, isFetching } = useQuery({
    queryKey: ["/laboratory/blood-products", "catalog", q],
    queryFn: async () => {
      const res = await apiGet<BloodProductRow[]>("/laboratory/blood-products", {
        params: { per_page: 200, ...(q ? { search: q } : {}) },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const columns = useMemo<Column<BloodProductRow>[]>(
    () => [
      { key: "name", header: t("crud.name"), cell: (row) => row.name ?? "—" },
      { key: "price", header: t("crud.price"), width: 120, cell: (row) => formatMoney(row.price) },
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
                  await apiDelete(`/laboratory/blood-products/${row.id}`);
                  toast.success(t("workspace.saved"));
                  if (draft.id === row.id) setDraft(empty);
                  await qc.invalidateQueries({ queryKey: ["/laboratory/blood-products"] });
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

  async function save(event: FormEvent) {
    event.preventDefault();
    const body = {
      name: draft.name.trim(),
      price: draft.price === "" ? 0 : Number(draft.price),
    };
    setSaving(true);
    try {
      if (draft.id) await apiPatch(`/laboratory/blood-products/${draft.id}`, body);
      else await apiPost("/laboratory/blood-products", body);
      toast.success(t("workspace.saved"));
      setDraft(empty);
      await qc.invalidateQueries({ queryKey: ["/laboratory/blood-products"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.bloodProducts")}>
        <ExcelButtons
          resource="blood-products"
          onImported={() => qc.invalidateQueries({ queryKey: ["/laboratory/blood-products"] })}
        />
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
      <form
        onSubmit={save}
        className="grid gap-2 border-b border-[var(--his-border)] bg-white p-2 md:grid-cols-[1.4fr_8rem_auto]"
      >
        <Input
          required
          placeholder={t("crud.name")}
          value={draft.name}
          onChange={(event) => setDraft((current) => ({ ...current, name: event.target.value }))}
        />
        <Input
          required
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
              price: row.price == null ? "" : String(row.price),
            })
          }
        />
      </div>
    </div>
  );
}
