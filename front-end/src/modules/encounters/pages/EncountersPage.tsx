"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { Panel } from "@/components/ui/Panel";
import { Select } from "@/components/ui/Select";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatDate } from "@/lib/utils";
import { arabicLabel } from "@/i18n/arabicLabels";
import { dailyNo } from "@/lib/arabicText";
import { apiGet, getErrorMessage } from "@/lib/api/client";
import { useAuthStore } from "@/stores/auth-store";
import { isDoctorDesk, roleNames } from "@/config/desk";
import type { NamedRow } from "@/modules/patients/api/journey.api";
import {
  useEncounters,
  useEncounterQueue,
  useEncounterActions,
} from "../hooks/useEncounters";
import type { EncounterRow } from "../api/encounters.api";

const TYPES = ["", "outpatient", "emergency", "inpatient"] as const;

function localToday() {
  const date = new Date();
  const pad = (value: number) => String(value).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function placeName(row: EncounterRow) {
  return row.clinic?.name ?? row.department?.name ?? "—";
}

function statusTone(status?: string) {
  if (status === "completed") return "success" as const;
  if (status === "cancelled") return "danger" as const;
  if (status === "in_progress") return "warning" as const;
  return "info" as const;
}

export function EncountersPage() {
  const t = useTranslations();
  const locale = useLocale();
  const user = useAuthStore((state) => state.user);
  const roles = roleNames(user);
  const isAdmin = roles.includes("admin");
  const isDataEntry = roles.length === 1 && roles[0] === "data_entry";
  const isDoctor = roles.length === 1 && roles[0] === "doctor";
  const doctorDesk = isDoctorDesk(user);
  const searchParams = useSearchParams();
  const lockedType = searchParams.get("type") ?? "";
  const [kind, setKind] = useState(lockedType);
  const [doctorId, setDoctorId] = useState("");
  const [clinicId, setClinicId] = useState("");
  const type = lockedType || kind || undefined;
  const today = localToday();
  const unitName = user?.clinic?.name ?? user?.department?.name ?? "";
  const listFilter = {
    date: today,
    doctor_id: (isDataEntry || isAdmin) && doctorId ? Number(doctorId) : undefined,
    clinic_id: isAdmin && clinicId ? Number(clinicId) : undefined,
  };

  const { data: doctors = [] } = useQuery({
    queryKey: ["/doctors", "encounters", isAdmin ? clinicId : user?.clinic?.id, user?.department?.id],
    enabled: isDataEntry || isAdmin,
    queryFn: async () => {
      const res = await apiGet<NamedRow[]>("/doctors", {
        params: {
          per_page: 200,
          clinic_id: isAdmin ? clinicId || undefined : user?.clinic?.id,
          department_id: isAdmin || user?.clinic?.id ? undefined : user?.department?.id,
        },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });
  const { data: clinics = [] } = useQuery({
    queryKey: ["/clinics", "encounters"],
    enabled: isAdmin,
    queryFn: async () => {
      const res = await apiGet<NamedRow[]>("/clinics", { params: { per_page: 200 } });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const { data: rows = [], isLoading, refetch, isFetching } = useEncounters({
    per_page: 100,
    ...listFilter,
  });
  const { data: queue = [], isLoading: queueLoading, refetch: refetchQueue } = useEncounterQueue(listFilter);
  const { start, close, cancel } = useEncounterActions();

  const waiting = useMemo(
    () => queue.filter((row) => !type || row.type === type),
    [queue, type],
  );
  const examining = useMemo(
    () => rows.filter((row) => row.status === "in_progress" && (!type || row.type === type)),
    [rows, type],
  );
  const closed = useMemo(
    () => rows.filter((row) => row.is_open === false && (!type || row.type === type)),
    [rows, type],
  );

  function patientName(row: EncounterRow) {
    const name = row.patient?.full_name ?? "—";
    if (!row.patient?.id) return name;
    return (
      <Link
        href={`/${locale}/dashboard/patients/${row.patient.id}`}
        className="font-semibold text-[var(--his-accent)] underline-offset-2 hover:underline"
      >
        {name}
      </Link>
    );
  }

  function fail(error: unknown) {
    toast.error(getErrorMessage(error));
  }

  function openFile(row: EncounterRow) {
    if (!row.patient?.id) return null;
    return (
      <Link href={`/${locale}/dashboard/patients/${row.patient.id}`}>
        <Button variant="info">{t("encounters.openFile")}</Button>
      </Link>
    );
  }

  const waitingColumns = useMemo<Column<EncounterRow>[]>(
    () => [
      { key: "num", header: "#", width: 80, cell: (row) => dailyNo(row.encounter_number ?? row.id) },
      { key: "patient", header: t("patients.fullName"), cell: patientName },
      { key: "mrn", header: t("patients.mrn"), width: 90, cell: (row) => dailyNo(row.patient?.mrn) || "—" },
      { key: "complaint", header: t("workspace.chiefComplaint"), cell: (row) => row.chief_complaint || "—" },
      { key: "place", header: t("workspace.unit"), width: 140, cell: (row) => placeName(row) },
      { key: "doctor", header: t("workspace.doctor"), width: 130, cell: (row) => row.doctor?.name ?? "—" },
      {
        key: "actions",
        header: t("app.actions"),
        width: 180,
        cell: (row) => (
          <div className="flex gap-1">
            {openFile(row)}
            <Button
              variant="primary"
              disabled={start.isPending}
              onClick={() => start.mutate(row.id, { onError: fail })}
            >
              {t("encounters.start")}
            </Button>
            {doctorDesk ? null : (
              <Button
                variant="danger"
                disabled={cancel.isPending}
                onClick={() => cancel.mutate(row.id, { onError: fail })}
              >
                {t("encounters.cancel")}
              </Button>
            )}
          </div>
        ),
      },
    ],
    [t, locale, start, cancel, doctorDesk],
  );

  const examiningColumns = useMemo<Column<EncounterRow>[]>(
    () => [
      { key: "num", header: "#", width: 80, cell: (row) => dailyNo(row.encounter_number ?? row.id) },
      { key: "patient", header: t("patients.fullName"), cell: patientName },
      { key: "type", header: t("encounters.type"), width: 110, cell: (row) => arabicLabel(row.type) },
      { key: "place", header: t("workspace.unit"), width: 140, cell: (row) => placeName(row) },
      { key: "doctor", header: t("workspace.doctor"), width: 130, cell: (row) => row.doctor?.name ?? "—" },
      { key: "started", header: t("app.time"), width: 130, cell: (row) => formatDate(row.started_at, true) },
      {
        key: "actions",
        header: t("app.actions"),
        width: 220,
        cell: (row) => (
          <div className="flex gap-1">
            {openFile(row)}
            {doctorDesk ? null : (
              <>
                <Button
                  variant="success"
                  disabled={close.isPending}
                  onClick={() => close.mutate(row.id, { onError: fail })}
                >
                  {t("encounters.close")}
                </Button>
                <Button
                  variant="danger"
                  disabled={cancel.isPending}
                  onClick={() => cancel.mutate(row.id, { onError: fail })}
                >
                  {t("encounters.cancel")}
                </Button>
              </>
            )}
          </div>
        ),
      },
    ],
    [t, locale, close, cancel, doctorDesk],
  );

  const closedColumns = useMemo<Column<EncounterRow>[]>(
    () => [
      { key: "num", header: "#", width: 80, cell: (row) => dailyNo(row.encounter_number ?? row.id) },
      { key: "patient", header: t("patients.fullName"), cell: patientName },
      { key: "type", header: t("encounters.type"), width: 110, cell: (row) => arabicLabel(row.type) },
      { key: "place", header: t("workspace.unit"), width: 140, cell: (row) => placeName(row) },
      { key: "doctor", header: t("workspace.doctor"), width: 130, cell: (row) => row.doctor?.name ?? "—" },
      {
        key: "status",
        header: t("app.status"),
        width: 110,
        cell: (row) => <Badge tone={statusTone(row.status)}>{arabicLabel(row.status)}</Badge>,
      },
      { key: "ended", header: t("app.time"), width: 130, cell: (row) => formatDate(row.ended_at ?? row.started_at, true) },
    ],
    [t, locale],
  );

  const title = lockedType === "emergency" ? t("nav.emergency") : t("encounters.title");
  const identity = isDataEntry
    ? unitName
    : isDoctor
      ? [unitName, user?.display_name].filter(Boolean).join(" — ")
      : "";

  return (
    <div className="flex h-full flex-col">
      <PageToolbar
        title={
          identity ? (
            <span className="inline-flex items-center gap-2">
              <span>{title}</span>
              <span className="font-normal text-[var(--his-muted)]">{identity}</span>
            </span>
          ) : (
            title
          )
        }
      >
        {isAdmin ? (
          <Select
            value={clinicId}
            onChange={(event) => {
              setClinicId(event.target.value);
              setDoctorId("");
            }}
            className="w-44"
          >
            <option value="">{t("patients.allClinics")}</option>
            {clinics.map((row) => (
              <option key={row.id} value={row.id}>
                {row.name ?? row.id}
              </option>
            ))}
          </Select>
        ) : null}
        {isDataEntry || isAdmin ? (
          <Select value={doctorId} onChange={(event) => setDoctorId(event.target.value)} className="w-44">
            <option value="">{t("workspace.doctor")}</option>
            {doctors.map((row) => (
              <option key={row.id} value={row.id}>
                {row.name ?? row.display_name ?? row.id}
              </option>
            ))}
          </Select>
        ) : null}
        {lockedType || doctorDesk ? null : (
          <div className="flex gap-1">
            {TYPES.map((value) => (
              <Button
                key={value || "all"}
                variant={(kind || "") === value ? "primary" : "default"}
                onClick={() => {
                  setKind(value);
                  setClinicId("");
                  setDoctorId("");
                }}
              >
                {value ? t(`encounters.${value}`) : t("encounters.allTypes")}
              </Button>
            ))}
          </div>
        )}
        <Button
          onClick={() => {
            void refetch();
            void refetchQueue();
          }}
          disabled={isFetching}
        >
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
      </PageToolbar>

      <div className="min-h-0 flex-1 space-y-2 overflow-auto p-2">
        <Panel title={`${t("encounters.waiting")} (${waiting.length})`}>
          <DataTable
            columns={waitingColumns}
            rows={waiting}
            rowKey={(row) => row.id}
            loading={queueLoading}
            emptyText={t("app.noData")}
          />
        </Panel>
        <Panel title={`${t("encounters.examining")} (${examining.length})`}>
          <DataTable
            columns={examiningColumns}
            rows={examining}
            rowKey={(row) => row.id}
            loading={isLoading}
            emptyText={t("app.noData")}
          />
        </Panel>
        <Panel title={`${t("encounters.closed")} (${closed.length})`}>
          <DataTable
            columns={closedColumns}
            rows={closed}
            rowKey={(row) => row.id}
            loading={isLoading}
            emptyText={t("app.noData")}
          />
        </Panel>
      </div>
    </div>
  );
}
