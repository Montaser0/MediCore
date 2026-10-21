"use client";

import { FormEvent, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { toast } from "sonner";
import { apiDelete, apiGet, apiPatch, apiPost, getErrorMessage } from "@/lib/api/client";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { BarcodeImage } from "@/modules/inventory/components/BarcodeImage";
import { printBarcodeLabels } from "@/modules/inventory/printBarcodeLabels";
import { ExcelButtons } from "@/components/shared/ExcelButtons";
import { useAuthStore } from "@/stores/auth-store";

type Option = { value: string; label: string };
type Named = { id: number; name?: string; full_name?: string };

type AssetRow = {
  id: number;
  asset_code: string;
  barcode?: string | null;
  category: string;
  category_label: string;
  brand?: string | null;
  model_name?: string | null;
  status: string;
  status_label: string;
  location?: string | null;
  received_by?: string | null;
  delivered_on?: string | null;
  notes?: string | null;
  department?: { id: number; name: string } | null;
};

type Movement = {
  id: number;
  type_label: string;
  note?: string | null;
  occurred_at?: string | null;
  actor?: string | null;
  from_holder?: string | null;
  to_holder?: string | null;
  from_department?: string | null;
  to_department?: string | null;
};

type Summary = {
  total: number;
  in_stock: number;
  assigned: number;
  maintenance: number;
  retired: number;
};

type NoteAction = "return" | "maintenance" | "repair" | "retire";

type Dialog =
  | { kind: "form"; asset: AssetRow | null }
  | { kind: "assign"; asset: AssetRow }
  | { kind: "note"; asset: AssetRow; action: NoteAction }
  | { kind: "history"; asset: AssetRow };

const EMPTY_FORM = {
  category: "laptop",
  asset_code: "",
  brand: "",
  model_name: "",
  department_id: "",
  location: "",
  received_by: "",
  delivered_on: "",
  notes: "",
};

function deviceName(row: AssetRow) {
  const name = [row.brand, row.model_name].filter(Boolean).join(" ");
  return name || row.category_label;
}

function statusTone(status: string): "success" | "warning" | "danger" | "info" {
  if (status === "assigned") return "success";
  if (status === "maintenance") return "warning";
  if (status === "retired") return "danger";
  return "info";
}

function blank(value: string) {
  const trimmed = value.trim();
  return trimmed === "" ? null : trimmed;
}

export function AssetsPage() {
  const t = useTranslations();
  const qc = useQueryClient();
  const hospitalId = useAuthStore((state) => state.user?.hospital_id);
  const canManage = useAuthStore((state) => state.hasPermission("assets.manage"));
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [category, setCategory] = useState("");
  const [status, setStatus] = useState("");
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [assignForm, setAssignForm] = useState({ received_by: "", department_id: "", location: "", note: "" });
  const [note, setNote] = useState("");
  const [saving, setSaving] = useState(false);

  const { data: categories = [] } = useQuery({
    queryKey: ["/assets", "categories"],
    queryFn: async () => {
      const res = await apiGet<Option[]>("/assets/categories");
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const { data: statuses = [] } = useQuery({
    queryKey: ["/assets", "statuses"],
    queryFn: async () => {
      const res = await apiGet<Option[]>("/assets/statuses");
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const { data: summary } = useQuery({
    queryKey: ["/assets", "summary"],
    queryFn: async () => {
      const res = await apiGet<Summary>("/assets/summary");
      return res.data;
    },
  });

  const { data: rows = [], isLoading, refetch, isFetching } = useQuery({
    queryKey: ["/assets", "list", q, category, status],
    queryFn: async () => {
      const collected: AssetRow[] = [];
      let page = 1;
      let last = 1;
      do {
        const res = await apiGet<AssetRow[]>("/assets", {
          params: {
            per_page: 200,
            page,
            ...(q ? { search: q } : {}),
            ...(category ? { category } : {}),
            ...(status ? { status } : {}),
          },
        });
        collected.push(...(res.data ?? []));
        last = res.meta?.last_page ?? page;
        page += 1;
      } while (page <= last && page <= 10);
      return collected;
    },
  });

  const { data: departments = [] } = useQuery({
    queryKey: ["/departments", "assets"],
    queryFn: async () => {
      const res = await apiGet<Named[]>("/departments");
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const historyId = dialog?.kind === "history" ? dialog.asset.id : null;
  const { data: movements = [], isLoading: historyLoading } = useQuery({
    queryKey: ["/assets", historyId, "movements"],
    enabled: historyId != null,
    queryFn: async () => {
      const res = await apiGet<Movement[]>(`/assets/${historyId}/movements`);
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  function statusLabel(value: string) {
    return statuses.find((item) => item.value === value)?.label ?? value;
  }

  function openCreate() {
    setForm(EMPTY_FORM);
    setDialog({ kind: "form", asset: null });
  }

  function openEdit(asset: AssetRow) {
    setForm({
      category: asset.category,
      asset_code: asset.asset_code,
      brand: asset.brand ?? "",
      model_name: asset.model_name ?? "",
      department_id: asset.department ? String(asset.department.id) : "",
      location: asset.location ?? "",
      received_by: asset.received_by ?? "",
      delivered_on: asset.delivered_on ?? "",
      notes: asset.notes ?? "",
    });
    setDialog({ kind: "form", asset });
  }

  function openAssign(asset: AssetRow) {
    setAssignForm({
      received_by: asset.received_by ?? "",
      department_id: asset.department ? String(asset.department.id) : "",
      location: asset.location ?? "",
      note: "",
    });
    setDialog({ kind: "assign", asset });
  }

  async function refresh() {
    await qc.invalidateQueries({ queryKey: ["/assets"] });
  }

  async function saveAsset(event: FormEvent) {
    event.preventDefault();
    if (dialog?.kind !== "form") return;
    if (!dialog.asset && !hospitalId) {
      toast.error(t("crud.hospitalMissing"));
      return;
    }
    const payload: Record<string, unknown> = {
      category: form.category,
      brand: blank(form.brand),
      model_name: blank(form.model_name),
      department_id: form.department_id ? Number(form.department_id) : null,
      location: blank(form.location),
      received_by: blank(form.received_by),
      delivered_on: blank(form.delivered_on),
      notes: blank(form.notes),
    };
    if (dialog.asset) payload.asset_code = form.asset_code.trim();
    else payload.hospital_id = hospitalId;
    if (!dialog.asset && form.asset_code.trim()) payload.asset_code = form.asset_code.trim();

    setSaving(true);
    try {
      if (dialog.asset) {
        await apiPatch(`/assets/${dialog.asset.id}`, payload);
      } else {
        const created = await apiPost<AssetRow>("/assets", payload);
        if (created.data) printLabel(created.data);
      }
      toast.success(t("workspace.saved"));
      setDialog(null);
      await refresh();
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function submitAssign(event: FormEvent) {
    event.preventDefault();
    if (dialog?.kind !== "assign") return;
    setSaving(true);
    try {
      await apiPost(`/assets/${dialog.asset.id}/assign`, {
        received_by: blank(assignForm.received_by),
        department_id: assignForm.department_id ? Number(assignForm.department_id) : null,
        location: blank(assignForm.location),
        note: blank(assignForm.note),
      });
      toast.success(t("workspace.saved"));
      setDialog(null);
      await refresh();
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function submitNote(event: FormEvent) {
    event.preventDefault();
    if (dialog?.kind !== "note") return;
    const path = {
      return: "return",
      maintenance: "maintenance",
      repair: "repair",
      retire: "retire",
    }[dialog.action];
    setSaving(true);
    try {
      await apiPost(`/assets/${dialog.asset.id}/${path}`, { note: blank(note) });
      toast.success(t("workspace.saved"));
      setDialog(null);
      await refresh();
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function remove(asset: AssetRow) {
    if (!window.confirm(t("crud.confirmDelete"))) return;
    try {
      await apiDelete(`/assets/${asset.id}`);
      toast.success(t("workspace.saved"));
      await refresh();
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  function printLabel(asset: AssetRow) {
    const code = String(asset.barcode ?? asset.asset_code).trim();
    if (!printBarcodeLabels([{ name: deviceName(asset), code, caption: asset.asset_code }])) {
      toast.error(t("inventory.nothingToPrint"));
    }
  }

  const placeLocked = dialog?.kind === "form" && dialog.asset != null && dialog.asset.status !== "in_stock";

  const columns: Column<AssetRow>[] = [
    { key: "code", header: t("assets.code"), width: 110, cell: (row) => row.asset_code },
    { key: "category", header: t("assets.category"), width: 110, cell: (row) => row.category_label },
    {
      key: "device",
      header: t("assets.device"),
      cell: (row) => deviceName(row),
    },
    {
      key: "barcode",
      header: t("assets.barcode"),
      width: 180,
      cell: (row) =>
        row.barcode ? <BarcodeImage code={row.barcode} height={36} /> : "—",
    },
    {
      key: "holder",
      header: t("assets.holder"),
      cell: (row) => row.received_by || "—",
    },
    {
      key: "department",
      header: t("workspace.department"),
      cell: (row) => row.department?.name || "—",
    },
    {
      key: "status",
      header: t("app.status"),
      width: 110,
      cell: (row) => <Badge tone={statusTone(row.status)}>{row.status_label}</Badge>,
    },
    {
      key: "delivered",
      header: t("assets.deliveredOn"),
      width: 120,
      cell: (row) => row.delivered_on || "—",
    },
    {
      key: "actions",
      header: t("app.actions"),
      width: 280,
      cell: (row) => (
        <div className="flex flex-wrap gap-1">
          <Button onClick={() => setDialog({ kind: "history", asset: row })}>{t("assets.history")}</Button>
          <Button onClick={() => printLabel(row)}>{t("inventory.printBarcode")}</Button>
          {canManage ? (
            <>
              <Button onClick={() => openEdit(row)}>{t("app.edit")}</Button>
              {row.status === "in_stock" || row.status === "assigned" ? (
                <Button variant="primary" onClick={() => openAssign(row)}>
                  {t("assets.assign")}
                </Button>
              ) : null}
              {row.status === "assigned" ? (
                <Button onClick={() => { setNote(""); setDialog({ kind: "note", asset: row, action: "return" }); }}>
                  {t("assets.return")}
                </Button>
              ) : null}
              {row.status === "in_stock" || row.status === "assigned" ? (
                <Button onClick={() => { setNote(""); setDialog({ kind: "note", asset: row, action: "maintenance" }); }}>
                  {t("assets.maintenance")}
                </Button>
              ) : null}
              {row.status === "maintenance" ? (
                <Button variant="success" onClick={() => { setNote(""); setDialog({ kind: "note", asset: row, action: "repair" }); }}>
                  {t("assets.repaired")}
                </Button>
              ) : null}
              {row.status !== "retired" ? (
                <Button variant="warning" onClick={() => { setNote(""); setDialog({ kind: "note", asset: row, action: "retire" }); }}>
                  {t("assets.retire")}
                </Button>
              ) : null}
              <Button variant="danger" onClick={() => void remove(row)}>
                {t("app.delete")}
              </Button>
            </>
          ) : null}
        </div>
      ),
    },
  ];

  const summaryItems = [
    { value: "", label: t("assets.total"), count: summary?.total ?? 0 },
    { value: "in_stock", label: statusLabel("in_stock"), count: summary?.in_stock ?? 0 },
    { value: "assigned", label: statusLabel("assigned"), count: summary?.assigned ?? 0 },
    { value: "maintenance", label: statusLabel("maintenance"), count: summary?.maintenance ?? 0 },
    { value: "retired", label: statusLabel("retired"), count: summary?.retired ?? 0 },
  ];

  const noteTitle = dialog?.kind === "note"
    ? {
        return: t("assets.return"),
        maintenance: t("assets.maintenance"),
        repair: t("assets.repaired"),
        retire: t("assets.retire"),
      }[dialog.action]
    : "";

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("assets.title")}>
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          onKeyDown={(event) => event.key === "Enter" && setQ(search)}
          placeholder={t("app.search")}
          className="w-56"
        />
        <Button onClick={() => setQ(search)}>{t("app.search")}</Button>
        <Select className="w-40" value={category} onChange={(event) => setCategory(event.target.value)}>
          <option value="">{t("assets.allCategories")}</option>
          {categories.map((item) => (
            <option key={item.value} value={item.value}>{item.label}</option>
          ))}
        </Select>
        <Select className="w-40" value={status} onChange={(event) => setStatus(event.target.value)}>
          <option value="">{t("assets.allStatuses")}</option>
          {statuses.map((item) => (
            <option key={item.value} value={item.value}>{item.label}</option>
          ))}
        </Select>
        <Button onClick={() => void refetch()} disabled={isFetching}>
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
        <ExcelButtons
          resource="assets"
          exportOnly={!canManage}
          onImported={() => refresh()}
        />
        {canManage ? (
          <Button variant="primary" onClick={openCreate}>{t("app.add")}</Button>
        ) : null}
      </PageToolbar>
      <div className="flex flex-wrap gap-1 border-b border-[var(--his-border)] bg-white px-2 py-1">
        {summaryItems.map((item) => (
          <Button
            key={item.value || "all"}
            variant={status === item.value ? "primary" : "default"}
            onClick={() => setStatus(item.value)}
          >
            {item.label} ({item.count})
          </Button>
        ))}
      </div>
      <div className="flex-1 p-2">
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row) => row.id}
          loading={isLoading}
          emptyText={t("app.noData")}
        />
      </div>
      {dialog?.kind === "form" ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
          <form onSubmit={(event) => void saveAsset(event)} className="his-panel max-h-[90vh] w-full max-w-2xl space-y-2 overflow-y-auto p-3">
            <div className="text-sm font-semibold">{dialog.asset ? t("app.edit") : t("app.add")}</div>
            {dialog.asset?.barcode ? (
              <div className="flex items-center justify-between gap-2 border border-[var(--his-border)] bg-white p-2">
                <BarcodeImage code={dialog.asset.barcode} />
                <Button type="button" onClick={() => dialog.asset && printLabel(dialog.asset)}>
                  {t("inventory.printBarcode")}
                </Button>
              </div>
            ) : (
              <p className="m-0 text-[12px] text-[var(--his-muted)]">{t("assets.barcodeAuto")}</p>
            )}
            <div className="grid gap-2 sm:grid-cols-2">
              <label className="grid gap-1 text-[12px]">
                <span>{t("assets.category")}</span>
                <Select required value={form.category} onChange={(event) => setForm({ ...form, category: event.target.value })}>
                  {categories.map((item) => (
                    <option key={item.value} value={item.value}>{item.label}</option>
                  ))}
                </Select>
              </label>
              <label className="grid gap-1 text-[12px]">
                <span>{t("assets.code")}</span>
                <Input value={form.asset_code} onChange={(event) => setForm({ ...form, asset_code: event.target.value })} />
              </label>
              <label className="grid gap-1 text-[12px]">
                <span>{t("assets.brand")}</span>
                <Input value={form.brand} onChange={(event) => setForm({ ...form, brand: event.target.value })} />
              </label>
              <label className="grid gap-1 text-[12px]">
                <span>{t("assets.model")}</span>
                <Input value={form.model_name} onChange={(event) => setForm({ ...form, model_name: event.target.value })} />
              </label>
              <label className="grid gap-1 text-[12px]">
                <span>{t("assets.holder")} ({t("assets.optional")})</span>
                <Input value={form.received_by} onChange={(event) => setForm({ ...form, received_by: event.target.value })} />
              </label>
              <label className="grid gap-1 text-[12px]">
                <span>{t("workspace.department")}</span>
                <Select
                  disabled={placeLocked}
                  value={form.department_id}
                  onChange={(event) => setForm({ ...form, department_id: event.target.value })}
                >
                  <option value="">{t("workspace.department")}</option>
                  {departments.map((item) => (
                    <option key={item.id} value={item.id}>{item.name}</option>
                  ))}
                </Select>
              </label>
              <label className="grid gap-1 text-[12px]">
                <span>{t("assets.place")}</span>
                <Input
                  disabled={placeLocked}
                  value={form.location}
                  onChange={(event) => setForm({ ...form, location: event.target.value })}
                />
              </label>
              <label className="grid gap-1 text-[12px]">
                <span>{t("assets.deliveredOn")}</span>
                <Input type="date" required value={form.delivered_on} onChange={(event) => setForm({ ...form, delivered_on: event.target.value })} />
              </label>
            </div>
            {placeLocked ? <p className="text-[12px] text-[var(--his-muted)]">{t("assets.lockedPlace")}</p> : null}
            <label className="grid gap-1 text-[12px]">
              <span>{t("app.notes")}</span>
              <textarea
                className="his-input min-h-16"
                value={form.notes}
                onChange={(event) => setForm({ ...form, notes: event.target.value })}
              />
            </label>
            <div className="flex justify-end gap-1">
              <Button type="button" onClick={() => setDialog(null)}>{t("app.cancel")}</Button>
              <Button type="submit" variant="primary" disabled={saving}>{t("app.save")}</Button>
            </div>
          </form>
        </div>
      ) : null}
      {dialog?.kind === "assign" ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
          <form onSubmit={(event) => void submitAssign(event)} className="his-panel w-full max-w-lg space-y-2 p-3">
            <div className="text-sm font-semibold">{t("assets.assign")} — {deviceName(dialog.asset)}</div>
            <label className="grid gap-1 text-[12px]">
              <span>{t("assets.holder")} ({t("assets.optional")})</span>
              <Input value={assignForm.received_by} onChange={(event) => setAssignForm({ ...assignForm, received_by: event.target.value })} />
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("workspace.department")}</span>
              <Select value={assignForm.department_id} onChange={(event) => setAssignForm({ ...assignForm, department_id: event.target.value })}>
                <option value="">{t("workspace.department")}</option>
                {departments.map((item) => (
                  <option key={item.id} value={item.id}>{item.name}</option>
                ))}
              </Select>
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("assets.place")}</span>
              <Input value={assignForm.location} onChange={(event) => setAssignForm({ ...assignForm, location: event.target.value })} />
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("assets.note")}</span>
              <Input value={assignForm.note} onChange={(event) => setAssignForm({ ...assignForm, note: event.target.value })} />
            </label>
            <div className="flex justify-end gap-1">
              <Button type="button" onClick={() => setDialog(null)}>{t("app.cancel")}</Button>
              <Button type="submit" variant="primary" disabled={saving}>{t("app.save")}</Button>
            </div>
          </form>
        </div>
      ) : null}
      {dialog?.kind === "note" ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
          <form onSubmit={(event) => void submitNote(event)} className="his-panel w-full max-w-lg space-y-2 p-3">
            <div className="text-sm font-semibold">{noteTitle} — {deviceName(dialog.asset)}</div>
            <label className="grid gap-1 text-[12px]">
              <span>{t("assets.note")}</span>
              <textarea className="his-input min-h-16" value={note} onChange={(event) => setNote(event.target.value)} />
            </label>
            <div className="flex justify-end gap-1">
              <Button type="button" onClick={() => setDialog(null)}>{t("app.cancel")}</Button>
              <Button type="submit" variant="primary" disabled={saving}>{t("app.confirm")}</Button>
            </div>
          </form>
        </div>
      ) : null}
      {dialog?.kind === "history" ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
          <div className="his-panel max-h-[90vh] w-full max-w-lg space-y-2 overflow-y-auto p-3">
            <div className="text-sm font-semibold">{t("assets.history")} — {dialog.asset.asset_code}</div>
            {historyLoading ? <p className="text-[12px]">{t("app.loading")}</p> : null}
            {!historyLoading && movements.length === 0 ? <p className="text-[12px]">{t("app.noData")}</p> : null}
            {movements.map((movement) => (
              <div key={movement.id} className="border-b border-[var(--his-border)] py-1 text-[12px]">
                <div className="font-semibold">{movement.type_label}</div>
                <div>{movement.occurred_at ?? "—"}{movement.actor ? ` — ${movement.actor}` : ""}</div>
                <div>
                  {(movement.from_holder || "—")} → {(movement.to_holder || "—")}
                  {" · "}
                  {(movement.from_department || "—")} → {(movement.to_department || "—")}
                </div>
                {movement.note ? <div>{movement.note}</div> : null}
              </div>
            ))}
            <div className="flex justify-end">
              <Button onClick={() => setDialog(null)}>{t("app.close")}</Button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
