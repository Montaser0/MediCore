"use client";

import { FormEvent, useMemo, useState } from "react";
import Link from "next/link";
import { useLocale, useTranslations } from "next-intl";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Plus, RefreshCw, Search } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { ExcelButtons } from "@/components/shared/ExcelButtons";
import { useDeletePatient, usePatients, useUpdatePatient } from "../hooks/usePatients";
import type { Patient } from "@/types/common";
import { formatDate } from "@/lib/utils";
import { arabicLabel } from "@/i18n/arabicLabels";
import { dailyNo } from "@/lib/arabicText";
import { readNationalId } from "@/lib/nationalId";
import { apiGet, getErrorMessage } from "@/lib/api/client";
import { isDoctorDesk } from "@/config/desk";
import { useAuthStore } from "@/stores/auth-store";
import type { NamedRow } from "../api/journey.api";

export function PatientsPage() {
  const t = useTranslations();
  const qc = useQueryClient();
  const locale = useLocale();
  const canCreatePatient = useAuthStore((state) => state.hasPermission("patients.create"));
  const canUpdatePatient = useAuthStore((state) => state.hasPermission("patients.update"));
  const canDeletePatient = useAuthStore((state) => state.hasPermission("patients.delete"));
  const doctorDesk = isDoctorDesk(useAuthStore((state) => state.user));
  const canRegister = canCreatePatient && !doctorDesk;
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [departmentId, setDepartmentId] = useState("");
  const [clinicId, setClinicId] = useState("");
  const [editing, setEditing] = useState<Patient | null>(null);
  const [form, setForm] = useState({ phone: "", city: "", national_id: "" });
  const updatePatient = useUpdatePatient();
  const deletePatient = useDeletePatient();
  const { data, isLoading, refetch, isFetching } = usePatients({
    search: q || undefined,
    department_id: departmentId ? Number(departmentId) : undefined,
    clinic_id: clinicId ? Number(clinicId) : undefined,
    per_page: 50,
  });
  const { data: departments = [] } = useQuery({
    queryKey: ["/departments", "patients"],
    queryFn: async () => {
      const res = await apiGet<NamedRow[]>("/departments", { params: { per_page: 200 } });
      return Array.isArray(res.data) ? res.data : [];
    },
  });
  const { data: clinics = [] } = useQuery({
    queryKey: ["/clinics", "patients"],
    queryFn: async () => {
      const res = await apiGet<NamedRow[]>("/clinics", { params: { per_page: 200 } });
      return Array.isArray(res.data) ? res.data : [];
    },
  });
  const clinicOptions = clinics.filter((row) => !departmentId || String(row.department_id ?? "") === departmentId);

  const columns = useMemo<Column<Patient>[]>(
    () => [
      {
        key: "mrn",
        header: t("patients.mrn"),
        width: 110,
        cell: (r: Patient) => (
          <Link
            href={`/${locale}/dashboard/patients/${r.id}`}
            className="text-[var(--his-accent)] underline-offset-2 hover:underline font-semibold"
          >
            {dailyNo(r.mrn)}
          </Link>
        ),
      },
      {
        key: "national_id",
        header: t("patients.nationalId"),
        width: 140,
        cell: (r: Patient) => r.national_id ?? "—",
      },
      {
        key: "name",
        header: t("patients.fullName"),
        cell: (r: Patient) => r.full_name,
      },
      {
        key: "department",
        header: t("workspace.department"),
        width: 140,
        cell: (r: Patient) => r.department?.name ?? "—",
      },
      {
        key: "clinic",
        header: t("appointments.clinic"),
        width: 140,
        cell: (r: Patient) => r.clinic?.name ?? "—",
      },
      {
        key: "gender",
        header: t("patients.gender"),
        width: 70,
        cell: (r: Patient) =>
          r.gender === "male"
            ? t("patients.male")
            : r.gender === "female"
              ? t("patients.female")
              : arabicLabel(r.gender),
      },
      {
        key: "age",
        header: t("patients.age"),
        width: 60,
        cell: (r: Patient) => r.age ?? "—",
      },
      {
        key: "phone",
        header: t("patients.phone"),
        width: 120,
        cell: (r: Patient) => r.phone ?? "—",
      },
      {
        key: "city",
        header: t("patients.city"),
        width: 100,
        cell: (r: Patient) => r.city ?? "—",
      },
      {
        key: "blood",
        header: t("patients.bloodType"),
        width: 80,
        cell: (r: Patient) => r.blood_type ?? "—",
      },
      {
        key: "status",
        header: t("app.status"),
        width: 90,
        cell: (r: Patient) => (
          <Badge tone={r.status === "active" ? "success" : "default"}>
            {arabicLabel(r.status)}
          </Badge>
        ),
      },
      {
        key: "created",
        header: t("app.date"),
        width: 110,
        cell: (r: Patient) => formatDate(r.created_at),
      },
      {
        key: "actions",
        header: t("app.actions"),
        width: 160,
        cell: (r: Patient) => (
          <div className="flex gap-1">
            {canUpdatePatient ? (
              <Button
                onClick={() => {
                  setEditing(r);
                  setForm({
                    phone: r.phone ?? "",
                    city: r.city ?? "",
                    national_id: r.national_id ?? "",
                  });
                }}
              >
                {t("app.edit")}
              </Button>
            ) : null}
            {canDeletePatient ? (
              <Button
                variant="danger"
                onClick={() => {
                  if (!window.confirm(t("crud.confirmDelete"))) return;
                  deletePatient.mutate(r.id, {
                    onError: (error) => toast.error(getErrorMessage(error)),
                  });
                }}
              >
                {t("app.delete")}
              </Button>
            ) : null}
          </div>
        ),
      },
    ].filter((column) => column.key !== "actions" || canUpdatePatient || canDeletePatient),
    [t, locale, deletePatient, canUpdatePatient, canDeletePatient],
  );

  function runSearch(value = search) {
    const next = readNationalId(value);
    setSearch(next);
    setQ(next);
  }

  async function saveEdit(event: FormEvent) {
    event.preventDefault();
    if (!editing) return;
    try {
      await updatePatient.mutateAsync({ id: editing.id, data: form });
      toast.success(t("workspace.saved"));
      setEditing(null);
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("patients.title")}>
        <ExcelButtons resource="patients" onImported={() => qc.invalidateQueries({ queryKey: ["patients"] })} />
        <div className="flex items-center gap-1 border border-[var(--his-border)] bg-white px-1">
          <Search size={13} className="text-[var(--his-muted)]" />
          <Input
            autoComplete="off"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                runSearch(e.currentTarget.value);
              }
            }}
            placeholder={t("patients.searchPlaceholder")}
            className="border-0! w-64 h-6!"
          />
          <Button onClick={() => runSearch()}>{t("app.search")}</Button>
        </div>
        <Select
          className="w-40"
          value={departmentId}
          onChange={(event) => {
            setDepartmentId(event.target.value);
            setClinicId("");
          }}
        >
          <option value="">{t("patients.allDepartments")}</option>
          {departments.map((row) => (
            <option key={row.id} value={row.id}>
              {row.name}
            </option>
          ))}
        </Select>
        <Select className="w-40" value={clinicId} onChange={(event) => setClinicId(event.target.value)}>
          <option value="">{t("patients.allClinics")}</option>
          {clinicOptions.map((row) => (
            <option key={row.id} value={row.id}>
              {row.name}
            </option>
          ))}
        </Select>
        <Button onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
        {canRegister ? (
          <Link href={`/${locale}/dashboard/patients/new`}>
            <Button variant="primary">
              <Plus size={13} />
              {t("patients.new")}
            </Button>
          </Link>
        ) : null}
      </PageToolbar>

      <div className="flex-1 p-2">
        <DataTable
          columns={columns}
          rows={data?.rows ?? []}
          rowKey={(r) => r.id}
          loading={isLoading}
          emptyText={t("app.noData")}
        />
      </div>
      {editing ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
          <form onSubmit={saveEdit} className="his-panel w-full max-w-md space-y-2 p-3">
            <div className="text-sm font-semibold">
              {editing.full_name} — {dailyNo(editing.mrn)}
            </div>
            <Input
              dir="ltr"
              lang="en"
              inputMode="numeric"
              autoComplete="off"
              className="his-latin-num"
              placeholder={t("patients.nationalIdScan")}
              value={form.national_id}
              onChange={(event) =>
                setForm((current) => ({ ...current, national_id: readNationalId(event.target.value) }))
              }
              onKeyDown={(event) => {
                if (event.key !== "Enter") return;
                event.preventDefault();
                setForm((current) => ({ ...current, national_id: readNationalId(event.currentTarget.value) }));
              }}
            />
            <Input
              placeholder={t("patients.phone")}
              value={form.phone}
              onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))}
            />
            <Input
              placeholder={t("patients.city")}
              value={form.city}
              onChange={(event) => setForm((current) => ({ ...current, city: event.target.value }))}
            />
            <div className="flex justify-end gap-1">
              <Button type="button" onClick={() => setEditing(null)}>
                {t("app.cancel")}
              </Button>
              <Button type="submit" variant="primary" disabled={updatePatient.isPending}>
                {t("app.save")}
              </Button>
            </div>
          </form>
        </div>
      ) : null}
    </div>
  );
}
