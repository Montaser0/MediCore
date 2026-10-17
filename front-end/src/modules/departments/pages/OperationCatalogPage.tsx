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
import { formatMoney } from "@/lib/utils";

type ClinicOption = { id: number; name?: string; department_id?: number; department?: { name?: string } };
type DepartmentOption = { id: number; name?: string };

type OperationTypeRow = {
  id: number;
  name?: string;
  price?: number;
  department_id?: number;
  clinics?: Array<{ id: number; name?: string; department_id?: number }>;
};

const empty = { id: 0, name: "", price: "", departmentId: "", clinicId: "" };

export function OperationCatalogPage() {
  const t = useTranslations();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [draft, setDraft] = useState(empty);
  const [saving, setSaving] = useState(false);

  const { data: rows = [], isLoading, refetch, isFetching } = useQuery({
    queryKey: ["/operations/types", "catalog", q],
    queryFn: async () => {
      const res = await apiGet<OperationTypeRow[]>("/operations/types", {
        params: q ? { search: q, per_page: 200 } : { per_page: 200 },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const { data: departments = [] } = useQuery({
    queryKey: ["/departments", "operation-catalog"],
    queryFn: async () => {
      const res = await apiGet<DepartmentOption[]>("/departments");
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const { data: clinics = [] } = useQuery({
    queryKey: ["/clinics", "operation-catalog"],
    queryFn: async () => {
      const res = await apiGet<ClinicOption[]>("/clinics", { params: { per_page: 200 } });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const clinicOptions = draft.departmentId
    ? clinics.filter((clinic) => String(clinic.department_id ?? "") === draft.departmentId)
    : [];

  const columns = useMemo<Column<OperationTypeRow>[]>(
    () => [
      { key: "name", header: t("nav.operations"), cell: (row) => row.name ?? "—" },
      { key: "price", header: t("crud.price"), width: 120, cell: (row) => formatMoney(row.price) },
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
                  name: row.name ?? "",
                  price: row.price == null ? "" : String(row.price),
                  departmentId: row.department_id ? String(row.department_id) : String(row.clinics?.[0]?.department_id ?? ""),
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
                  await apiDelete(`/operations/types/${row.id}`);
                  toast.success(t("workspace.saved"));
                  if (draft.id === row.id) setDraft(empty);
                  await qc.invalidateQueries({ queryKey: ["/operations/types"] });
                  await qc.invalidateQueries({ queryKey: ["operation-types"] });
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
      clinic_id: Number(draft.clinicId),
    };
    setSaving(true);
    try {
      if (draft.id) await apiPatch(`/operations/types/${draft.id}`, body);
      else await apiPost("/operations/types", body);
      toast.success(t("workspace.saved"));
      setDraft(empty);
      await qc.invalidateQueries({ queryKey: ["/operations/types"] });
      await qc.invalidateQueries({ queryKey: ["operation-types"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.operationTypes")}>
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
        className="grid gap-2 border-b border-[var(--his-border)] bg-white p-2 md:grid-cols-[1fr_8rem_14rem_14rem_auto]"
      >
        <Input
          required
          placeholder={t("nav.operations")}
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
        <Select
          required
          value={draft.departmentId}
          onChange={(event) =>
            setDraft((current) => ({ ...current, departmentId: event.target.value, clinicId: "" }))
          }
        >
          <option value="">{t("workspace.department")}</option>
          {departments.map((department) => (
            <option key={department.id} value={department.id}>
              {department.name}
            </option>
          ))}
        </Select>
        <Select
          required
          disabled={!draft.departmentId}
          value={draft.clinicId}
          onChange={(event) => setDraft((current) => ({ ...current, clinicId: event.target.value }))}
        >
          <option value="">{t("workspace.clinic")}</option>
          {clinicOptions.map((clinic) => (
            <option key={clinic.id} value={clinic.id}>
              {clinic.name}
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
              name: row.name ?? "",
              price: row.price == null ? "" : String(row.price),
              departmentId: row.department_id ? String(row.department_id) : String(row.clinics?.[0]?.department_id ?? ""),
              clinicId: row.clinics?.[0]?.id ? String(row.clinics[0].id) : "",
            })
          }
        />
      </div>
    </div>
  );
}
