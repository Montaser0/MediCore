"use client";

import { FormEvent, useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { ExcelButtons } from "@/components/shared/ExcelButtons";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { formatDate } from "@/lib/utils";
import { getErrorMessage } from "@/lib/api/client";
import { arabicLabel } from "@/i18n/arabicLabels";
import { dailyNo } from "@/lib/arabicText";
import { useAuthStore } from "@/stores/auth-store";
import { env } from "@/config/environment";
import { isDoctorDesk } from "@/config/desk";
import { useCatalogs } from "@/modules/patients/hooks/useJourney";
import { usePatients } from "@/modules/patients/hooks/usePatients";
import {
  useAppointments,
  useAppointmentActions,
} from "../hooks/useAppointments";
import type { AppointmentRow } from "../api/appointments.api";
import { printAppointmentSlip, type AppointmentSlip } from "../print/printAppointmentSlip";

function statusTone(status?: string) {
  if (!status) return "default" as const;
  if (["confirmed", "checked_in", "completed"].includes(status)) return "success" as const;
  if (["cancelled", "no_show"].includes(status)) return "danger" as const;
  if (["scheduled", "pending"].includes(status)) return "info" as const;
  return "warning" as const;
}

export function AppointmentsPage() {
  const t = useTranslations();
  const canCreateAppointment = useAuthStore((state) => state.hasPermission("appointments.create"));
  const canUpdateAppointment = useAuthStore((state) => state.hasPermission("appointments.update"));
  const doctorDesk = isDoctorDesk(useAuthStore((state) => state.user));
  const canCreate = canCreateAppointment && !doctorDesk;
  const [booking, setBooking] = useState(false);
  const [search, setSearch] = useState("");
  const [draft, setDraft] = useState({
    patient_id: "",
    department_id: "",
    doctor_id: "",
    scheduled_at: "",
    reason: "",
  });
  const { data: loaded = [], isLoading, refetch, isFetching } = useAppointments({
    per_page: 100,
  });
  const rows = useMemo(() => {
    const waiting = (status?: string) => status === "scheduled" || status === "confirmed";
    return [...loaded].sort((a, b) => {
      const rank = Number(waiting(b.status)) - Number(waiting(a.status));
      if (rank !== 0) return rank;
      return (a.scheduled_at ?? "").localeCompare(b.scheduled_at ?? "");
    });
  }, [loaded]);
  const { checkIn, noShow, create } = useAppointmentActions();
  const { data: catalogs } = useCatalogs();
  const { data: patientLookup } = usePatients({
    search: search || undefined,
    per_page: 8,
  });

  const columns = useMemo<Column<AppointmentRow>[]>(
    () => [
      {
        key: "num",
        header: "#",
        width: 90,
        cell: (r) => dailyNo(r.appointment_number ?? r.id),
      },
      {
        key: "patient",
        header: t("patients.fullName"),
        cell: (r) => (
          <div>
            <div>{r.patient?.full_name ?? "—"}</div>
            {r.patient?.phone ? (
              <div className="text-[11px] text-[var(--his-muted)]" dir="ltr">
                {r.patient.phone}
              </div>
            ) : null}
          </div>
        ),
      },
      {
        key: "mrn",
        header: t("patients.mrn"),
        width: 100,
        cell: (r) => dailyNo(r.patient?.mrn) || "—",
      },
      {
        key: "doctor",
        header: t("appointments.doctor"),
        width: 140,
        cell: (r) => r.doctor?.name ?? "—",
      },
      {
        key: "clinic",
        header: t("appointments.clinic"),
        width: 120,
        cell: (r) => r.clinic?.name ?? r.department?.name ?? "—",
      },
      {
        key: "scheduled",
        header: t("appointments.scheduledAt"),
        width: 130,
        cell: (r) => formatDate(r.scheduled_at, true),
      },
      {
        key: "status",
        header: t("app.status"),
        width: 110,
        cell: (r) => (
          <Badge tone={statusTone(r.status)}>
            {arabicLabel(r.status_label ?? r.status)}
          </Badge>
        ),
      },
      {
        key: "actions",
        header: t("app.actions"),
        width: 340,
        cell: (r) => {
          const waiting = r.status === "scheduled" || r.status === "confirmed";
          return (
            <div className="flex flex-wrap gap-1">
              <Button onClick={() => printSlip(r)}>{t("appointments.printSlip")}</Button>
              {canUpdateAppointment && waiting ? (
                <>
                  <Button
                    variant="success"
                    disabled={checkIn.isPending}
                    onClick={() =>
                      checkIn.mutate(r.id, {
                        onSuccess: () => toast.success(t("appointments.arrived")),
                        onError: (error) => toast.error(getErrorMessage(error)),
                      })
                    }
                  >
                    {t("appointments.checkIn")}
                  </Button>
                  <Button
                    disabled={noShow.isPending}
                    onClick={() =>
                      noShow.mutate(r.id, {
                        onError: (error) => toast.error(getErrorMessage(error)),
                      })
                    }
                  >
                    {t("appointments.noShow")}
                  </Button>
                </>
              ) : null}
            </div>
          );
        },
      },
    ],
    [t, checkIn, noShow, canUpdateAppointment],
  );

  function printSlip(appointment: AppointmentSlip) {
    try {
      printAppointmentSlip(appointment, env.appName);
    } catch (error) {
      toast.error(getErrorMessage(error, t("appointments.popupBlocked")));
    }
  }

  async function book(event: FormEvent) {
    event.preventDefault();
    const preview = window.open("", "_blank", "width=420,height=720");
    if (preview) {
      preview.document.write(
        "<p dir='rtl' style='font-family:Tahoma,sans-serif'>جارٍ تجهيز الوصل…</p>",
      );
    }
    const scheduled = draft.scheduled_at.includes("T")
      ? `${draft.scheduled_at.replace("T", " ")}:00`
      : draft.scheduled_at;
    try {
      const payload = {
        department_id: draft.department_id ? Number(draft.department_id) : undefined,
        doctor_id: draft.doctor_id ? Number(draft.doctor_id) : undefined,
        scheduled_at: scheduled,
        reason: draft.reason || undefined,
      };
      const saved = await create.mutateAsync({
        patient_id: Number(draft.patient_id),
        ...payload,
      });
      const patient = patientLookup?.rows.find((row) => String(row.id) === draft.patient_id);
      const doctor = catalogs?.doctors.find((row) => String(row.id) === draft.doctor_id);
      const department = catalogs?.departments.find(
        (row) => String(row.id) === draft.department_id,
      );
      const slip: AppointmentSlip = {
        ...saved.data,
        scheduled_at: saved.data?.scheduled_at || scheduled,
        reason: saved.data?.reason || draft.reason || undefined,
        patient: {
          full_name: saved.data?.patient?.full_name ?? patient?.full_name,
          mrn: saved.data?.patient?.mrn ?? patient?.mrn,
          phone: saved.data?.patient?.phone ?? patient?.phone ?? undefined,
        },
        doctor: saved.data?.doctor?.name
          ? saved.data.doctor
          : doctor
            ? { name: doctor.name }
            : undefined,
        department: saved.data?.department?.name
          ? saved.data.department
          : department
            ? { name: department.name }
            : undefined,
        clinic: saved.data?.clinic,
      };
      if (preview) {
        printAppointmentSlip(slip, env.appName, preview);
      } else {
        toast.error(t("appointments.popupBlocked"));
      }
      toast.success(t("workspace.saved"));
      setBooking(false);
    } catch (error) {
      preview?.close();
      toast.error(getErrorMessage(error));
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("appointments.title")}>
        <ExcelButtons
          resource="appointments"
          onImported={async () => {
            await refetch();
          }}
        />
        {canCreate ? (
          <Button
            variant="primary"
            onClick={() => setBooking((value) => !value)}
          >
            {t("appointments.new")}
          </Button>
        ) : null}
        <Button onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
      </PageToolbar>

      {booking ? (
        <form onSubmit={book} className="grid gap-2 border-b border-[var(--his-border)] p-2 md:grid-cols-6">
          <Input
            placeholder={t("patients.searchPlaceholder")}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <Select
            required
            value={draft.patient_id}
            onChange={(e) => setDraft((d) => ({ ...d, patient_id: e.target.value }))}
          >
            <option value="">{t("patients.fullName")}</option>
            {(patientLookup?.rows ?? []).map((patient) => (
              <option key={patient.id} value={patient.id}>
                {patient.full_name} — {dailyNo(patient.mrn)}
              </option>
            ))}
          </Select>
          <Select
            value={draft.department_id}
            onChange={(e) => setDraft((d) => ({ ...d, department_id: e.target.value }))}
          >
            <option value="">{t("workspace.department")}</option>
            {(catalogs?.departments ?? []).map((row) => (
              <option key={row.id} value={row.id}>
                {row.name}
              </option>
            ))}
          </Select>
          <Select
            required
            value={draft.doctor_id}
            onChange={(e) => setDraft((d) => ({ ...d, doctor_id: e.target.value }))}
          >
            <option value="">{t("appointments.doctor")}</option>
            {(catalogs?.doctors ?? []).map((row) => (
              <option key={row.id} value={row.id}>
                {row.name}
              </option>
            ))}
          </Select>
          <Input
            required
            type="datetime-local"
            value={draft.scheduled_at}
            onChange={(e) => setDraft((d) => ({ ...d, scheduled_at: e.target.value }))}
          />
          <Button type="submit" variant="primary" disabled={create.isPending}>
            {t("app.save")}
          </Button>
        </form>
      ) : null}

      <div className="flex-1 p-2">
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(r) => r.id}
          loading={isLoading}
          emptyText={t("app.noData")}
        />
      </div>
    </div>
  );
}
