"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { RefreshCw } from "lucide-react";
import { toast } from "sonner";
import { apiDelete, apiGet, apiPatch, apiPost, getErrorMessage } from "@/lib/api/client";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { ExcelButtons } from "@/components/shared/ExcelButtons";
import { useAuthStore } from "@/stores/auth-store";

const ACCOUNT_ROLES = [
  "reception",
  "doctor",
  "data_entry",
  "nurse",
  "lab_technician",
  "lab_supervisor",
  "radiology_technician",
  "radiologist",
  "pharmacist",
  "cashier",
  "warehouse",
  "technician",
] as const;

const ROLE_LABELS = {
  admin: "staffRoles.admin",
  reception: "staffRoles.reception",
  doctor: "staffRoles.doctor",
  data_entry: "staffRoles.data_entry",
  nurse: "staffRoles.nurse",
  lab_technician: "staffRoles.lab_technician",
  lab_supervisor: "staffRoles.lab_supervisor",
  radiology_technician: "staffRoles.radiology_technician",
  radiologist: "staffRoles.radiologist",
  pharmacist: "staffRoles.pharmacist",
  cashier: "staffRoles.cashier",
  warehouse: "staffRoles.warehouse",
  technician: "staffRoles.technician",
} as const;

type ClinicOption = {
  id?: number;
  name?: string;
  department_id?: number;
  department?: { id?: number };
};

type Row = Record<string, unknown> & {
  id?: number;
  clinic_id?: number;
  screens?: string[];
  user?: { username?: string; roles?: string[] } | null;
  department?: { id?: number; name?: string };
  clinic?: { id?: number; name?: string };
  clinics?: ClinicOption[];
};

function clinicDepartmentId(clinic: ClinicOption) {
  return String(clinic.department_id ?? clinic.department?.id ?? "");
}

function clinicList(value: unknown): ClinicOption[] {
  if (Array.isArray(value)) return value as ClinicOption[];
  if (value && typeof value === "object" && Array.isArray((value as { data?: unknown }).data)) {
    return (value as { data: ClinicOption[] }).data;
  }
  return [];
}

function chosenClinicId(...values: unknown[]): number | null {
  for (const value of values) {
    const text = String(value ?? "").trim();
    if (text === "" || text === "undefined" || text === "null") continue;
    const id = Number(text);
    if (Number.isInteger(id) && id > 0) return id;
  }
  return null;
}

function savedClinicId(row: Row) {
  const id = row.clinic?.id ?? row.clinic_id;
  return id != null ? String(id) : "";
}

function rowsOf<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) return payload as T[];
  return clinicList(payload) as T[];
}

function clinicLabel(row: Row, known: ClinicOption[]) {
  if (row.clinic?.name) return String(row.clinic.name);
  const id = row.clinic?.id ?? row.clinic_id;
  if (id == null) return "—";
  const match = known.find((clinic) => String(clinic.id) === String(id));
  return match?.name ? String(match.name) : "—";
}

function accountRole(row: Row): string {
  return row.user?.roles?.[0] ?? "";
}

function roleLabel(
  role: string,
  t: (key: (typeof ROLE_LABELS)[keyof typeof ROLE_LABELS]) => string,
  jobTitle: unknown,
) {
  if (role in ROLE_LABELS) return t(ROLE_LABELS[role as keyof typeof ROLE_LABELS]);
  const title = String(jobTitle ?? "").trim();
  return title || role || "—";
}

export function StaffPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const hospitalId = useAuthStore((state) => state.user?.hospital_id);
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Row | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  const { data: rows = [], isLoading, isError, error, refetch, isFetching } = useQuery({
    queryKey: ["/staff", q],
    queryFn: async () => {
      const res = await apiGet<Row[]>("/staff", {
        params: q ? { search: q, per_page: 200 } : { per_page: 200 },
      });
      if (!Array.isArray(res.data)) {
        throw new Error("استجابة قائمة الكوادر غير متوقعة");
      }
      return res.data;
    },
  });

  const { data: departments = [] } = useQuery({
    queryKey: ["/departments", "staff-options"],
    queryFn: async () => {
      const res = await apiGet<unknown>("/departments");
      return rowsOf<Row>(res.data);
    },
  });

  const { data: clinics = [] } = useQuery({
    queryKey: ["/clinics", "staff-options"],
    queryFn: async () => {
      const res = await apiGet<unknown>("/clinics");
      return rowsOf<ClinicOption>(res.data);
    },
  });
  const { data: departmentClinics = [] } = useQuery({
    queryKey: ["/clinics", "staff-form", form.department_id ?? ""],
    enabled: open && Boolean(form.department_id),
    queryFn: async () => {
      const res = await apiGet<unknown>("/clinics", {
        params: { department_id: form.department_id },
      });
      return rowsOf<ClinicOption>(res.data);
    },
  });

  function openCreate() {
    setEditing(null);
    setForm({});
    setOpen(true);
  }

  function openEdit(row: Row) {
    setEditing(row);
    setForm({
      first_name: String(row.first_name ?? ""),
      last_name: String(row.last_name ?? ""),
      employee_number: String(row.employee_number ?? ""),
      phone: String(row.phone ?? ""),
      department_id: row.department?.id != null ? String(row.department.id) : "",
      clinic_id: savedClinicId(row),
    });
    setOpen(true);
  }

  function openAccess(id: number) {
    router.push(`/${locale}/dashboard/staff/${id}/access`);
  }

  async function remove(row: Row) {
    if (row.id == null || !window.confirm(t("crud.confirmDelete"))) return;
    try {
      await apiDelete(`/staff/${row.id}`);
      toast.success(t("workspace.saved"));
      await qc.invalidateQueries({ queryKey: ["/staff"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!editing && !hospitalId) {
      toast.error(t("crud.hospitalMissing"));
      return;
    }

    const payload: Record<string, unknown> = {};
    if (!editing) payload.hospital_id = hospitalId;
    for (const key of ["first_name", "last_name", "employee_number", "phone", "department_id"] as const) {
      const value = (form[key] ?? "").trim();
      if (value !== "") payload[key] = key.endsWith("_id") ? Number(value) : value;
    }
    const submitted = new FormData(event.currentTarget);
    const clinicId = chosenClinicId(...submitted.getAll("clinic_id"), form.clinic_id);
    const role = editing ? accountRole(editing) : (form.role ?? "");
    const options = clinicsFor(form.department_id ?? "", departmentClinics, editing);
    if ((role === "doctor" || role === "data_entry") && clinicId == null && options.length > 0) {
      toast.error(t("workspace.clinicRequired"));
      return;
    }
    if (clinicId != null) payload.clinic_id = clinicId;
    else if (!editing) payload.clinic_id = null;
    if (!editing && form.role) {
      payload.role = form.role;
      payload.username = form.username ?? "";
      payload.password = form.password ?? "";
      if ((form.email ?? "").trim() !== "") payload.email = form.email.trim();
      if (form.role === "doctor" && (form.specialty ?? "").trim() !== "") payload.specialty = form.specialty.trim();
    }

    setSaving(true);
    try {
      if (editing?.id != null) {
        await apiPatch(`/staff/${editing.id}`, payload);
        toast.success(t("workspace.saved"));
        setOpen(false);
      } else {
        const created = await apiPost<{ id: number }>("/staff", payload);
        toast.success(t("workspace.saved"));
        setOpen(false);
        if (form.role && created.data?.id) {
          router.push(`/${locale}/dashboard/staff/${created.data.id}/access`);
        }
      }
      await qc.invalidateQueries({ queryKey: ["/staff"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  function clinicsFor(departmentId: string, extra: ClinicOption[] = [], current: Row | null = null) {
    if (departmentId === "") return [];
    const department = departments.find((row) => String(row.id ?? "") === departmentId);
    const embedded = clinicList(department?.clinics);
    const merged = new Map<string, ClinicOption>();
    for (const clinic of [...embedded, ...extra]) {
      if (clinic.id != null) merged.set(String(clinic.id), clinic);
    }
    for (const clinic of clinics) {
      if (clinic.id != null && clinicDepartmentId(clinic) === departmentId) {
        merged.set(String(clinic.id), clinic);
      }
    }
    const saved = current?.clinic;
    if (saved?.id != null && !merged.has(String(saved.id))) {
      merged.set(String(saved.id), saved);
    }
    return [...merged.values()];
  }

  const clinicOptions = clinicsFor(form.department_id ?? "", departmentClinics, editing);
  const accountBeingEdited = editing ? accountRole(editing) : (form.role ?? "");
  const clinicRequired = (accountBeingEdited === "doctor" || accountBeingEdited === "data_entry") && clinicOptions.length > 0;
  const knownClinics = useMemo(() => {
    const merged = new Map<string, ClinicOption>();
    for (const clinic of clinics) {
      if (clinic.id != null) merged.set(String(clinic.id), clinic);
    }
    for (const department of departments) {
      for (const clinic of clinicList(department.clinics)) {
        if (clinic.id != null) merged.set(String(clinic.id), clinic);
      }
    }
    return [...merged.values()];
  }, [clinics, departments]);

  useEffect(() => {
    if (!open || clinicOptions.length !== 1 || clinicOptions[0]?.id == null) return;
    const only = String(clinicOptions[0].id);
    setForm((current) => (current.clinic_id === only ? current : { ...current, clinic_id: only }));
  }, [open, clinicOptions]);

  const columns = useMemo<Column<Row>[]>(
    () => [
      { key: "id", header: "#", width: 60, cell: (row) => row.id },
      { key: "full_name", header: t("crud.name"), cell: (row) => String(row.full_name ?? "—") },
      {
        key: "role",
        header: t("staffRoles.label"),
        cell: (row) => {
          const role = accountRole(row);
          return role ? roleLabel(role, t, row.job_title) : String(row.job_title ?? "—");
        },
      },
      {
        key: "username",
        header: t("crud.username"),
        cell: (row) => row.user?.username ?? "—",
      },
      {
        key: "screens",
        header: t("access.count"),
        width: 80,
        cell: (row) => (row.user ? String(row.screens?.length ?? 0) : "—"),
      },
      {
        key: "department",
        header: t("workspace.department"),
        cell: (row) => row.department?.name ?? "—",
      },
      {
        key: "clinic",
        header: t("workspace.clinic"),
        cell: (row) => clinicLabel(row, knownClinics),
      },
      {
        key: "actions",
        header: t("app.actions"),
        width: 230,
        cell: (row) => (
          <div className="flex gap-1">
            <Button onClick={() => openEdit(row)}>{t("app.edit")}</Button>
            {row.user && row.id != null ? (
              <Button onClick={() => openAccess(row.id!)}>{t("access.open")}</Button>
            ) : null}
            <Button variant="danger" onClick={() => remove(row)}>
              {t("app.delete")}
            </Button>
          </div>
        ),
      },
    ],
    [t, knownClinics],
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.staff")}>
        <ExcelButtons resource="staff" onImported={() => qc.invalidateQueries({ queryKey: ["/staff"] })} />
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
        {isError ? (
          <p className="mb-2 text-[13px] text-[var(--his-danger)]">{getErrorMessage(error)}</p>
        ) : null}
        <DataTable columns={columns} rows={rows} rowKey={(row, index) => Number(row.id ?? index)} loading={isLoading} emptyText={t("app.noData")} />
      </div>
      {open ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
          <form onSubmit={save} className="his-panel max-h-[90vh] w-full max-w-lg space-y-2 overflow-y-auto p-3">
            <div className="text-sm font-semibold">{editing ? t("app.edit") : t("app.add")}</div>
            <label className="grid gap-1 text-[12px]">
              <span>{t("patients.firstName")}</span>
              <Input required value={form.first_name ?? ""} onChange={(event) => setForm((current) => ({ ...current, first_name: event.target.value }))} />
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("patients.lastName")}</span>
              <Input required value={form.last_name ?? ""} onChange={(event) => setForm((current) => ({ ...current, last_name: event.target.value }))} />
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("crud.code")}</span>
              <Input value={form.employee_number ?? ""} onChange={(event) => setForm((current) => ({ ...current, employee_number: event.target.value }))} />
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("patients.phone")}</span>
              <Input value={form.phone ?? ""} onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))} />
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("workspace.department")}</span>
              <Select
                required={accountBeingEdited === "doctor"}
                value={form.department_id ?? ""}
                name="department_id"
                onChange={(event) => {
                  const departmentId = event.target.value;
                  const nextClinics = clinicsFor(departmentId, departmentClinics, editing);
                  setForm((current) => {
                    if (current.department_id === departmentId) return current;
                    const kept = current.clinic_id ?? "";
                    const stillThere = nextClinics.some((clinic) => String(clinic.id) === kept);
                    return {
                      ...current,
                      department_id: departmentId,
                      clinic_id: stillThere ? kept : nextClinics.length === 1 ? String(nextClinics[0].id) : "",
                    };
                  });
                }}
              >
                <option value="">{t("workspace.department")}</option>
                {departments.map((row) => (
                  <option key={String(row.id)} value={String(row.id)}>
                    {String(row.name ?? row.id)}
                  </option>
                ))}
              </Select>
            </label>
            <label className="grid gap-1 text-[12px]">
              <span>{t("workspace.clinic")}</span>
              <Select
                name="clinic_id"
                required={clinicRequired}
                value={form.clinic_id ?? ""}
                onChange={(event) => setForm((current) => ({ ...current, clinic_id: event.target.value }))}
              >
                <option value="">{t("workspace.chooseClinic")}</option>
                {clinicOptions.map((row) => (
                  <option key={String(row.id)} value={String(row.id)}>
                    {String(row.name ?? row.id)}
                  </option>
                ))}
              </Select>
              <input type="hidden" name="clinic_id" value={form.clinic_id ?? ""} />
              {form.department_id && clinicOptions.length === 0 ? (
                <span className="text-[11px] text-[var(--his-danger)]">{t("workspace.noClinics")}</span>
              ) : null}
            </label>
            {editing ? null : (
              <label className="grid gap-1 text-[12px]">
                <span>{t("staffRoles.label")}</span>
                <Select value={form.role ?? ""} onChange={(event) => setForm((current) => ({ ...current, role: event.target.value }))}>
                  <option value="">{t("staffRoles.label")}</option>
                  {ACCOUNT_ROLES.map((role) => (
                    <option key={role} value={role}>
                      {t(ROLE_LABELS[role])}
                    </option>
                  ))}
                </Select>
              </label>
            )}
            {!editing && form.role ? (
              <>
                <label className="grid gap-1 text-[12px]">
                  <span>{t("crud.username")}</span>
                  <Input required value={form.username ?? ""} onChange={(event) => setForm((current) => ({ ...current, username: event.target.value }))} />
                </label>
                <label className="grid gap-1 text-[12px]">
                  <span>{t("crud.password")}</span>
                  <Input required type="password" value={form.password ?? ""} onChange={(event) => setForm((current) => ({ ...current, password: event.target.value }))} />
                </label>
                <label className="grid gap-1 text-[12px]">
                  <span>{t("crud.email")}</span>
                  <Input type="email" value={form.email ?? ""} onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))} />
                </label>
              </>
            ) : null}
            {!editing && form.role === "doctor" ? (
              <label className="grid gap-1 text-[12px]">
                <span>{t("staffRoles.specialty")}</span>
                <Input value={form.specialty ?? ""} onChange={(event) => setForm((current) => ({ ...current, specialty: event.target.value }))} />
              </label>
            ) : null}
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
