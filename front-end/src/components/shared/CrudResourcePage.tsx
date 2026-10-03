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
import { Select } from "@/components/ui/Select";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { useAuthStore } from "@/stores/auth-store";

type Row = Record<string, unknown>;

export type CrudField = {
  key: string;
  label: string;
  type?: "text" | "number" | "select" | "password";
  required?: boolean;
  options?: Array<{ value: string; label: string }>;
  optionsSource?: "departments";
  inject?: "hospital_id";
  createOnly?: boolean;
  boolean?: boolean;
  when?: { key: string; value: string };
};

function readField(row: Row, field: CrudField): string {
  if (field.boolean && field.key === "is_doctor") {
    return row.doctor ? "1" : "0";
  }
  if (field.key.endsWith("_id")) {
    const nested = row[field.key.slice(0, -3)];
    if (nested && typeof nested === "object" && nested !== null && "id" in nested) {
      return String((nested as { id: unknown }).id);
    }
  }
  const raw = row[field.key];
  if (raw == null) return "";
  if (typeof raw === "boolean") return raw ? "1" : "0";
  return String(raw);
}

export function CrudResourcePage({
  title,
  endpoint,
  columns,
  fields,
  updateMethod = "patch",
  removeRequest,
}: {
  title: string;
  endpoint: string;
  columns: Array<{
    key: string;
    header: string;
    width?: number | string;
    render?: (row: Row) => React.ReactNode;
  }>;
  fields: CrudField[];
  updateMethod?: "patch" | "put";
  removeRequest?: (id: number) => Promise<unknown>;
}) {
  const t = useTranslations();
  const hospitalId = useAuthStore((state) => state.user?.hospital_id);
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Row | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const needsDepartments = fields.some((field) => field.optionsSource === "departments");

  const { data, isLoading, refetch, isFetching } = useQuery({
    queryKey: [endpoint, q],
    queryFn: async () => {
      const res = await apiGet<Row[]>(endpoint, {
        params: q ? { search: q, per_page: 50 } : { per_page: 50 },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const { data: departments = [] } = useQuery({
    queryKey: ["/departments", "crud-options"],
    enabled: needsDepartments,
    queryFn: async () => {
      const res = await apiGet<Row[]>("/departments");
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  function optionsFor(field: CrudField) {
    if (field.optionsSource === "departments") {
      return departments.map((row) => ({
        value: String(row.id),
        label: String(row.name ?? row.id),
      }));
    }
    return field.options ?? [];
  }

  function openCreate() {
    setEditing(null);
    setForm({});
    setOpen(true);
  }

  function openEdit(row: Row) {
    const next: Record<string, string> = {};
    for (const field of fields) {
      if (field.inject || field.createOnly) continue;
      next[field.key] = readField(row, field);
    }
    setEditing(row);
    setForm(next);
    setOpen(true);
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    const payload: Record<string, unknown> = {};
    for (const field of fields) {
      if (field.inject === "hospital_id") {
        if (!editing) payload.hospital_id = hospitalId;
        continue;
      }
      if (field.createOnly && editing) continue;
      const raw = form[field.key] ?? "";
      if (raw === "") continue;
      if (field.type === "number") payload[field.key] = Number(raw);
      else if (field.boolean) payload[field.key] = raw === "1";
      else payload[field.key] = raw;
    }
    if (!editing && fields.some((field) => field.inject === "hospital_id") && !hospitalId) {
      toast.error(t("crud.hospitalMissing"));
      return;
    }
    setSaving(true);
    try {
      if (editing) {
        const id = Number(editing.id);
        if (updateMethod === "put") await apiPut(`${endpoint}/${id}`, payload);
        else await apiPatch(`${endpoint}/${id}`, payload);
      } else {
        await apiPost(endpoint, payload);
      }
      toast.success(t("workspace.saved"));
      setOpen(false);
      await qc.invalidateQueries({ queryKey: [endpoint] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function remove(row: Row) {
    if (!window.confirm(t("crud.confirmDelete"))) return;
    try {
      const id = Number(row.id);
      if (removeRequest) await removeRequest(id);
      else await apiDelete(`${endpoint}/${id}`);
      toast.success(t("workspace.saved"));
      await qc.invalidateQueries({ queryKey: [endpoint] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  const tableColumns = useMemo<Column<Row>[]>(
    () => [
      ...columns.map((col) => ({
        key: col.key,
        header: col.header,
        width: col.width,
        cell: (row: Row) => (col.render ? col.render(row) : String(row[col.key] ?? "—")),
      })),
      {
        key: "actions",
        header: t("app.actions"),
        width: 150,
        cell: (row: Row) => (
          <div className="flex gap-1">
            <Button onClick={() => openEdit(row)}>{t("app.edit")}</Button>
            <Button variant="danger" onClick={() => remove(row)}>
              {t("app.delete")}
            </Button>
          </div>
        ),
      },
    ],
    [columns, t],
  );

  const visibleFields = fields.filter(
    (field) => !field.inject && !(field.createOnly && editing) && (!field.when || form[field.when.key] === field.when.value),
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={title}>
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
        <Button variant="primary" onClick={openCreate}>
          {t("app.add")}
        </Button>
      </PageToolbar>
      <div className="flex-1 p-2">
        <DataTable
          columns={tableColumns}
          rows={data ?? []}
          rowKey={(row, index) => Number(row.id ?? index)}
          loading={isLoading}
          emptyText={t("app.noData")}
        />
      </div>
      {open ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
          <form onSubmit={save} className="his-panel max-h-[90vh] w-full max-w-lg space-y-2 overflow-y-auto p-3">
            <div className="text-sm font-semibold">{editing ? t("app.edit") : t("app.add")}</div>
            {visibleFields.map((field) => (
              <label key={field.key} className="grid gap-1 text-[12px]">
                <span>{field.label}</span>
                {field.type === "select" || field.optionsSource || field.boolean ? (
                  <Select
                    required={field.required}
                    value={form[field.key] ?? ""}
                    onChange={(event) => setForm((current) => ({ ...current, [field.key]: event.target.value }))}
                  >
                    <option value="">{field.label}</option>
                    {(field.boolean
                      ? [
                          { value: "1", label: t("app.yes") },
                          { value: "0", label: t("app.no") },
                        ]
                      : optionsFor(field)
                    ).map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </Select>
                ) : (
                  <Input
                    required={field.required}
                    type={field.type === "number" ? "number" : field.type === "password" ? "password" : "text"}
                    value={form[field.key] ?? ""}
                    onChange={(event) => setForm((current) => ({ ...current, [field.key]: event.target.value }))}
                  />
                )}
              </label>
            ))}
            <div className="flex justify-end gap-1">
              <Button type="button" onClick={() => setOpen(false)}>
                {t("app.cancel")}
              </Button>
              <Button type="submit" variant="primary" disabled={saving}>
                {t("app.save")}
              </Button>
            </div>
          </form>
        </div>
      ) : null}
    </div>
  );
}
