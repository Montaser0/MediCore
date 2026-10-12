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
import { Select } from "@/components/ui/Select";
import { DataTable, type Column } from "@/components/data-table/DataTable";

type ClinicOption = { id: number; name?: string; department?: { name?: string } };

type DiagnosisRow = {
  id: number;
  title?: string;
  clinics?: Array<{ id: number; name?: string }>;
};

const empty = { id: 0, title: "", clinicId: "" };

function clinicLabel(clinic: ClinicOption) {
  return clinic.department?.name ? `${clinic.name} — ${clinic.department.name}` : (clinic.name ?? `#${clinic.id}`);
}

export function DiagnosisCatalogPage() {
  const t = useTranslations();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [draft, setDraft] = useState(empty);
  const [saving, setSaving] = useState(false);

  const { data: rows = [], isLoading, refetch, isFetching } = useQuery({
    queryKey: ["/diagnosis-codes", "catalog", q],
    queryFn: async () => {
      const res = await apiGet<DiagnosisRow[]>("/diagnosis-codes", {
        params: q ? { search: q, per_page: 200 } : { per_page: 200 },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const { data: clinics = [] } = useQuery({
    queryKey: ["/clinics", "diagnosis-catalog"],
    queryFn: async () => {
      const res = await apiGet<ClinicOption[]>("/clinics", { params: { per_page: 200 } });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const columns = useMemo<Column<DiagnosisRow>[]>(
    () => [
      { key: "title", header: t("workspace.diagnosis"), cell: (row) => row.title ?? "—" },
      {
        key: "clinics",
        header: t("workspace.clinic"),
        cell: (row) => (row.clinics ?? []).map((clinic) => clinic.name).filter(Boolean).join("، ") || "—",
      },
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
                  title: row.title ?? "",
                  clinicId: row.clinics?.[0]?.id ? String(row.clinics[0].id) : "",
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
                  await apiDelete(`/diagnosis-codes/${row.id}`);
                  toast.success(t("workspace.saved"));
                  if (draft.id === row.id) setDraft(empty);
                  await qc.invalidateQueries({ queryKey: ["/diagnosis-codes"] });
                  await qc.invalidateQueries({ queryKey: ["diagnosis-codes"] });
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
      title: draft.title.trim(),
      clinic_id: Number(draft.clinicId),
    };
    setSaving(true);
    try {
      if (draft.id) await apiPatch(`/diagnosis-codes/${draft.id}`, body);
      else await apiPost("/diagnosis-codes", body);
      toast.success(t("workspace.saved"));
      setDraft(empty);
      await qc.invalidateQueries({ queryKey: ["/diagnosis-codes"] });
      await qc.invalidateQueries({ queryKey: ["diagnosis-codes"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.diagnoses")}>
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
      <form onSubmit={save} className="grid gap-2 border-b border-[var(--his-border)] bg-white p-2 md:grid-cols-[1fr_18rem_auto]">
        <Input
          required
          placeholder={t("workspace.diagnosis")}
          value={draft.title}
          onChange={(event) => setDraft((current) => ({ ...current, title: event.target.value }))}
        />
        <Select
          required
          value={draft.clinicId}
          onChange={(event) => setDraft((current) => ({ ...current, clinicId: event.target.value }))}
        >
          <option value="">{t("workspace.clinic")}</option>
          {clinics.map((clinic) => (
            <option key={clinic.id} value={clinic.id}>
              {clinicLabel(clinic)}
            </option>
          ))}
        </Select>
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
              title: row.title ?? "",
              clinicId: row.clinics?.[0]?.id ? String(row.clinics[0].id) : "",
            })
          }
        />
      </div>
    </div>
  );
}
