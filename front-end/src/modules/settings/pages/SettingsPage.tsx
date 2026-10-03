"use client";

import { FormEvent, useMemo, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { RefreshCw } from "lucide-react";
import { apiDelete, apiPost, apiPut, getErrorMessage } from "@/lib/api/client";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";
import { ModuleTabs } from "@/components/shared/ModuleTabs";
import { useSettings } from "../hooks/useSettings";
import type { SettingRow } from "../api/settings.api";

function SettingsEditor() {
  const t = useTranslations();
  const qc = useQueryClient();
  const { data: rows = [], isLoading, refetch, isFetching } = useSettings();
  const [draft, setDraft] = useState({ key: "", value: "" });
  const [editing, setEditing] = useState<SettingRow | null>(null);
  const group = rows[0]?.group || "general";

  const columns = useMemo<Column<SettingRow>[]>(
    () => [
      { key: "key", header: t("crud.key"), cell: (row) => row.key ?? "—" },
      {
        key: "value",
        header: t("crud.value"),
        cell: (row) =>
          row.value != null && typeof row.value === "object"
            ? JSON.stringify(row.value)
            : String(row.value ?? "—"),
      },
      {
        key: "actions",
        header: t("app.actions"),
        width: 150,
        cell: (row) => (
          <div className="flex gap-1">
            <Button
              onClick={() =>
                setEditing({
                  ...row,
                  value:
                    row.value != null && typeof row.value === "object"
                      ? JSON.stringify(row.value)
                      : String(row.value ?? ""),
                })
              }
            >
              {t("app.edit")}
            </Button>
            <Button
              variant="danger"
              onClick={async () => {
                if (!row.key || !window.confirm(t("crud.confirmDelete"))) return;
                try {
                  await apiDelete("/settings", { params: { group: row.group || group, key: row.key } });
                  toast.success(t("workspace.saved"));
                  await qc.invalidateQueries({ queryKey: ["settings"] });
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
    [t, group, qc],
  );

  async function save(event: FormEvent) {
    event.preventDefault();
    const key = editing?.key || draft.key;
    const value = editing ? String(editing.value ?? "") : draft.value;
    if (!key) return;
    try {
      await apiPut("/settings", { group, values: { [key]: value } });
      toast.success(t("workspace.saved"));
      setEditing(null);
      setDraft({ key: "", value: "" });
      await qc.invalidateQueries({ queryKey: ["settings"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("settings.general")}>
        <Button onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
      </PageToolbar>
      <form onSubmit={save} className="grid gap-2 border-b border-[var(--his-border)] p-2 md:grid-cols-3">
        <Input
          required
          placeholder={t("crud.key")}
          value={editing?.key ?? draft.key}
          disabled={Boolean(editing)}
          onChange={(event) => setDraft((current) => ({ ...current, key: event.target.value }))}
        />
        <Input
          required
          placeholder={t("crud.value")}
          value={editing ? String(editing.value ?? "") : draft.value}
          onChange={(event) => {
            const value = event.target.value;
            if (editing) setEditing({ ...editing, value });
            else setDraft((current) => ({ ...current, value }));
          }}
        />
        <div className="flex gap-1">
          <Button type="submit" variant="primary">
            {t("app.save")}
          </Button>
          {editing ? (
            <Button type="button" onClick={() => setEditing(null)}>
              {t("app.cancel")}
            </Button>
          ) : null}
        </div>
      </form>
      <div className="flex-1 p-2">
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row, index) => row.key ?? index}
          loading={isLoading}
          emptyText={t("app.noData")}
        />
      </div>
    </div>
  );
}

export function SettingsPage() {
  const t = useTranslations();
  return (
    <ModuleTabs
      tabs={[
        { id: "general", label: t("settings.general"), content: <SettingsEditor /> },
        {
          id: "users",
          label: t("settings.users"),
          content: (
            <CrudResourcePage
              title={t("settings.users")}
              endpoint="/users"
              removeRequest={(id) => apiPost(`/users/${id}/disable`)}
              columns={[
                { key: "id", header: "#", width: 60 },
                { key: "username", header: t("crud.username"), render: (row) => String(row.username ?? "—") },
                { key: "email", header: t("crud.email"), render: (row) => String(row.email ?? "—") },
              ]}
              fields={[
                { key: "username", label: t("crud.username"), required: true },
                { key: "email", label: t("crud.email") },
                { key: "password", label: t("crud.password"), type: "password", required: true, createOnly: true },
              ]}
            />
          ),
        },
      ]}
    />
  );
}
