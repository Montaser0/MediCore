"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { arabicLabel } from "@/i18n/arabicLabels";
import { dailyNo, localize } from "@/lib/arabicText";
import {
  Activity,
  BedDouble,
  CalendarDays,
  Check,
  Droplet,
  FlaskConical,
  NotebookPen,
  Pill,
  Scan,
  Syringe,
  UserRound,
  X,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { usePatient, usePatientFile, usePatientTimeline, usePatients } from "../hooks/usePatients";
import { useCatalogs, useJourneyAction, usePatientEncounters } from "../hooks/useJourney";
import { journeyApi, type Catalogs, type NamedRow } from "../api/journey.api";
import { PatientAccountPanel } from "../components/PatientAccountPanel";
import { formatDate, formatMoney, latinDigits } from "@/lib/utils";
import { apiDelete, apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { BLOOD_GROUPS } from "@/modules/laboratory/blood";
import { DocumentGallery } from "@/components/shared/DocumentGallery";
import { useAuthStore } from "@/stores/auth-store";
import { useEditionStore } from "@/stores/edition-store";
import { canSeeBilling, isDoctorDesk } from "@/config/desk";
import type { EncounterRow } from "@/modules/encounters/api/encounters.api";

type TabKey =
  | "file"
  | "visit"
  | "orders"
  | "pharmacy"
  | "nursing"
  | "stay"
  | "billing"
  | "docs"
  | "timeline";

type ActionKey =
  | "vitals"
  | "note"
  | "diagnosis"
  | "procedure"
  | "radiology"
  | "blood"
  | "medication"
  | "nursing"
  | "admit"
  | "surgery"
  | "charge"
  | "discharge"
  | "transfer"
  | "allergy"
  | "condition"
  | "contact"
  | "appointment"
  | "dischargeStay"
  | "pay"
  | "installment";

type Rec = Record<string, unknown>;

const VISIT_TYPES = ["outpatient", "emergency", "inpatient", "operation", "follow_up"] as const;

export function PatientWorkspacePage({ patientId }: { patientId: string }) {
  const t = useTranslations();
  const tr = (key: string) => (t as unknown as (value: string) => string)(key);
  const locale = useLocale();
  const router = useRouter();
  const userId = useAuthStore((s) => s.user?.id);
  const can = useAuthStore((s) => s.hasPermission);
  const canCreateAppointment = can("appointments.create");
  const user = useAuthStore((s) => s.user);
  const doctorDesk = isDoctorDesk(user);
  const showBilling = canSeeBilling(user);
  const canBook = canCreateAppointment && !doctorDesk;
  const canUpdatePatient = can("patients.update");
  const canOpenVisit = can("encounters.create") && !doctorDesk;
  const canTransfer = can("encounters.transfer");
  const canDischarge = can("encounters.discharge");
  const canDischargeStay = can("inpatient.discharge");
  const canVitals = can("clinical.vitals.create");
  const canNote = can("clinical.notes.create");
  const canDiagnosis = can("clinical.diagnosis.create");
  const canOrder = can("orders.create");
  const canPrescribe = can("pharmacy.prescriptions.create");
  const canSurgery = can("operations.schedule");
  const canAdmit = can("inpatient.admit");
  const canCharge = can("billing.charges.create");
  const canPay = can("billing.payments.create");
  const canInstallment = can("billing.invoices.create");
  const editionReady = useEditionStore((state) => state.ready);
  const editionModules = useEditionStore((state) => state.modules);
  const editionFeatures = useEditionStore((state) => state.features);
  const editionNavigation = useEditionStore((state) => state.navigation);
  const moduleOn = (name: string) =>
    editionReady && (editionNavigation.includes("*") || editionModules.includes(name));
  const featureOn = (name: string) => editionReady && editionFeatures.includes(name);
  const showLab = canOrder && moduleOn("Laboratory");
  const showRadiology = canOrder && moduleOn("Radiology");
  const showPrescribe = canPrescribe && featureOn("pharmacy.prescriptions");
  const showSurgery = canSurgery && moduleOn("Operations");
  const showAdmit = canAdmit && moduleOn("Inpatient");
  const showCharge = canCharge && moduleOn("Billing");
  const showStay = canDischargeStay && moduleOn("Inpatient");
  const showAccount = showBilling && !doctorDesk && moduleOn("Billing");
  const showClinical =
    can("clinical.notes.view") || can("clinical.vitals.view") || can("clinical.diagnosis.view");
  const showOrderRecords = can("orders.view") && moduleOn("Orders");
  const showLabRecords = can("laboratory.orders.view") && moduleOn("Laboratory");
  const showRadRecords = can("radiology.orders.view") && moduleOn("Radiology");
  const showOrdersTab = showOrderRecords || showLabRecords || showRadRecords;
  const showPharmacyTab = can("pharmacy.prescriptions.view") && featureOn("pharmacy.prescriptions");
  const showNursingTab = can("nursing.notes.view") && moduleOn("Nursing");
  const showStayTab = can("inpatient.view") && moduleOn("Inpatient");
  const showOperations = can("operations.view") && moduleOn("Operations");
  const showDocs = can("documents.view");
  const qc = useQueryClient();
  const [tab, setTab] = useState<TabKey>("file");
  const [action, setAction] = useState<ActionKey | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [visit, setVisit] = useState({
    type: "outpatient",
    department_id: "",
    clinic_id: "",
    doctor_id: "",
    chief_complaint: "",
  });

  const { data: listData } = usePatients({ per_page: 40 });
  const { data: patient, isLoading } = usePatient(patientId);
  const { data: file } = usePatientFile(patientId);
  const { data: timeline } = usePatientTimeline(patientId);
  const { data: encounters = [] } = usePatientEncounters(patientId);
  const { data: catalogs, refetch: refetchCatalogs } = useCatalogs();
  const run = useJourneyAction(patientId);

  async function removeFact(url: string) {
    if (!window.confirm(t("crud.confirmDelete"))) return;
    try {
      await apiDelete(url);
      toast.success(t("workspace.saved"));
      await qc.invalidateQueries({ queryKey: ["patients"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  const sections = (file?.sections ?? {}) as Rec;
  const clinical = asObj(sections.clinical);
  const orders = arr(asObj(sections.orders).recent);
  const lab = arr(asObj(sections.laboratory).recent);
  const bloodRequests = arr(asObj(sections.blood).recent);
  const radiology = arr(asObj(sections.radiology).recent);
  const pharmacy = asObj(sections.pharmacy);
  const nursing = asObj(sections.nursing);
  const admissions = asObj(sections.admissions);
  const operations = arr(asObj(sections.operations).items);
  const billing = asObj(sections.billing);
  const documents = arr(asObj(sections.documents).recent);
  const procedures = arr(clinical.procedures);

  const current = useMemo(() => {
    const open = encounters.filter((row) => row.is_open);
    return open.sort((a, b) => b.id - a.id)[0] ?? null;
  }, [encounters]);
  const diagnosisDesk = isDoctorDesk(user);
  const visitClinicId = current?.clinic?.id ?? null;
  const diagnosisReady = action === "diagnosis";
  const { data: clinicDiagnoses = [], isLoading: diagnosesLoading, isFetched: diagnosesFetched } = useQuery({
    queryKey: ["diagnosis-codes", diagnosisDesk ? "account" : "visit", diagnosisDesk ? user?.id ?? null : visitClinicId],
    enabled: diagnosisReady,
    queryFn: () =>
      journeyApi.diagnosisCodes(
        diagnosisDesk || visitClinicId == null ? {} : { clinicId: visitClinicId },
      ),
  });

  const activeStay = useMemo(() => {
    return arr(admissions.items).find((row) =>
      ["admitted", "transferred"].includes(String(row.status ?? "")),
    );
  }, [admissions]);

  const { data: notes = [] } = useQuery({
    queryKey: ["encounters", current?.id, "notes"],
    enabled: !!current?.id,
    queryFn: async () => {
      const res = await journeyApi.notes(current!.id);
      return res.data ?? [];
    },
  });

  function setField(key: string, value: string) {
    setForm((prev) => ({ ...prev, [key]: value }));
  }

  function openAction(next: ActionKey, seed?: Record<string, string>) {
    if (
      !current &&
      !["allergy", "condition", "contact", "appointment", "pay", "installment"].includes(next)
    ) {
      toast.error(t("workspace.needVisit"));
      return;
    }
    if (next === "admit") void refetchCatalogs();
    setAction(next);
    setForm(
      seed ??
        (next === "dischargeStay"
          ? { discharge_type: "routine" }
          : next === "diagnosis"
            ? { type: "primary" }
            : {}),
    );
  }

  async function openVisit() {
    const clinic = (catalogs?.clinics ?? []).find((row) => String(row.id) === visit.clinic_id);
    const departmentId = num(visit.department_id) ?? clinic?.department_id;
    const clinicId = num(visit.clinic_id);
    if (!departmentId && !clinicId) {
      toast.error(t("patients.placeRequired"));
      return;
    }
    try {
      const created = await run.mutateAsync(() =>
        journeyApi.openVisit({
          patient_id: Number(patientId),
          type: visit.type,
          department_id: departmentId,
          clinic_id: clinicId,
          doctor_id: num(visit.doctor_id),
          chief_complaint: visit.chief_complaint,
        }),
      );
      const encounter = (created as { data?: EncounterRow }).data;
      if (encounter?.id && encounter.status !== "in_progress") {
        await journeyApi.startVisit(encounter.id, num(visit.doctor_id));
        await run.mutateAsync(async () => undefined);
      }
      toast.success(t("workspace.visitOpened"));
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  async function submitAction() {
    if (!action) return;
    try {
      await run.mutateAsync(() => perform(action));
      toast.success(t("workspace.saved"));
      setAction(null);
      setForm({});
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  function perform(key: ActionKey) {
    const encounterId = current?.id;
    const doctorId = num(form.doctor_id) ?? current?.doctor?.id ?? num(visit.doctor_id);
    switch (key) {
      case "vitals": {
        const pressure = parseBloodPressure(form.blood_pressure);
        if (form.blood_pressure?.trim() && !pressure) {
          throw new Error(t("workspace.bloodPressureInvalid"));
        }
        return journeyApi.vitals(encounterId!, {
          temperature: num(form.temperature),
          systolic_bp: pressure?.systolic,
          diastolic_bp: pressure?.diastolic,
          heart_rate: num(form.heart_rate),
          respiratory_rate: num(form.respiratory_rate),
          oxygen_saturation: num(form.oxygen_saturation),
          weight: num(form.weight),
          height: num(form.height),
          notes: form.notes,
        });
      }
      case "note":
        return journeyApi.note(encounterId!, {
          note_type: form.note_type || "progress",
          content: form.content,
        });
      case "diagnosis":
        return journeyApi.diagnosis(encounterId!, {
          diagnosis_code_id: num(form.diagnosis_code_id),
          type: form.type || "primary",
          doctor_id: doctorId,
          notes: form.notes,
        });
      case "procedure":
        return journeyApi.procedure(encounterId!, {
          service_id: num(form.service_id),
          name: catalogs?.services?.find((row) => String(row.id) === form.service_id)?.name,
          doctor_id: doctorId,
          description: form.description,
        });
      case "blood":
        return apiPost("/laboratory/blood-requests", {
          patient_id: Number(patientId),
          encounter_id: encounterId,
          blood_product_id: num(form.blood_product_id),
          blood_group: form.blood_group,
          units: num(form.units),
        });
      case "radiology":
        return journeyApi.order({
          encounter_id: encounterId,
          type: "radiology",
          priority: form.priority || "routine",
          clinical_notes: form.clinical_notes,
          items: [
            {
              service_type: "radiology_service",
              service_id: num(form.service_id),
              name: selectedName(catalogs?.radiology, form.service_id),
            },
          ],
        });
      case "medication":
        return journeyApi.prescription({
          encounter_id: encounterId,
          doctor_id: doctorId,
          notes: form.notes,
          items: [
            {
              medication_id: num(form.medication_id),
              dose: form.dose,
              frequency: form.frequency,
              duration: form.duration,
              quantity: num(form.quantity) ?? 1,
              instructions: form.instructions,
            },
          ],
        });
      case "nursing":
        return journeyApi.nursingNote(encounterId!, { content: form.content, shift: form.shift });
      case "admit":
        return journeyApi.admit({
          patient_id: Number(patientId),
          encounter_id: encounterId,
          department_id: current?.department?.id ?? num(visit.department_id),
          bed_id: num(form.bed_id),
          admission_reason: form.admission_reason,
        });
      case "surgery":
        if (!userId) throw new Error("يجب تسجيل الدخول قبل حجز العملية");
        return journeyApi.scheduleOperation({
          encounter_id: encounterId,
          operation_type_id: num(form.operation_type_id),
          scheduled_at: toSql(form.scheduled_at),
          pre_op_diagnosis: form.pre_op_diagnosis,
          team: [{ user_id: userId, role: "surgeon" }],
        });
      case "charge":
        return journeyApi.charge({
          encounter_id: encounterId,
          description: form.description,
          quantity: num(form.quantity) ?? 1,
          unit_price: num(form.unit_price),
        });
      case "discharge":
        return dischargeVisit(encounterId!, doctorId);
      case "transfer":
        return journeyApi.transferVisit(encounterId!, {
          to_department_id: num(form.department_id),
          clinic_id: num(form.clinic_id),
          doctor_id: num(form.doctor_id),
          reason: form.reason,
        });
      case "allergy":
        return journeyApi.allergy(patientId, {
          allergen: form.allergen,
          reaction: form.reaction,
          severity: form.severity || "moderate",
        });
      case "condition":
        return journeyApi.condition(patientId, {
          condition_name: form.condition_name,
          notes: form.notes,
        });
      case "contact":
        return journeyApi.contact(patientId, {
          name: form.name,
          relationship: form.relationship,
          phone: form.phone,
          is_emergency_contact: true,
        });
      case "appointment":
        return journeyApi.appointment({
          patient_id: Number(patientId),
          department_id: num(form.department_id),
          doctor_id: num(form.doctor_id),
          scheduled_at: toSql(form.scheduled_at),
          reason: form.reason,
        });
      case "dischargeStay":
        return journeyApi.dischargeStay(Number(activeStay?.id), {
          discharge_type: form.discharge_type || "routine",
          discharge_summary: form.discharge_summary,
        });
      case "pay":
        return journeyApi.pay(Number(form.invoice_id), {
          amount: num(form.amount),
          method: form.method || "cash",
          reference: form.reference,
        });
      case "installment":
        return journeyApi.installments(Number(form.invoice_id), {
          count: num(form.count),
          first_due_date: form.first_due_date,
          interval_months: num(form.interval_months) || 1,
        });
    }
  }

  async function dischargeVisit(encounterId: number, doctorId?: number) {
    if (current && current.status !== "in_progress") {
      await journeyApi.startVisit(encounterId, doctorId);
    }
    return journeyApi.closeVisit(encounterId, {
      discharge_instructions: form.discharge_instructions,
      discharge_disposition: form.discharge_disposition || "home",
      follow_up_date: form.follow_up_date,
      note: form.note,
    });
  }

  const timelineColumns = useMemo<Column<Rec>[]>(
    () => [
      {
        key: "n",
        header: "#",
        width: 50,
        cell: (_row, index) => String(index + 1),
      },
      {
        key: "when",
        header: t("app.date"),
        width: 140,
        cell: (row) => timelineWhen(row.occurred_at ?? row.created_at),
      },
      {
        key: "event",
        header: t("patients.event"),
        width: 160,
        cell: (row) => timelineText(row).title,
      },
      {
        key: "actor",
        header: t("patients.actor"),
        width: 160,
        cell: (row) => actorName(row.actor),
      },
      {
        key: "detail",
        header: t("patients.detail"),
        cell: (row) => timelineText(row).detail,
      },
    ],
    [t],
  );

  if (isLoading || !patient) {
    return <div className="p-6 text-[var(--his-muted)]">{t("app.loading")}</div>;
  }

  const genderLabel =
    patient.gender === "male"
      ? t("patients.male")
      : patient.gender === "female"
        ? t("patients.female")
        : arabicLabel(patient.gender);

  const vitals = asObj(clinical.latest_vitals);
  const tabs: Array<[TabKey, string]> = [
    ["file", t("workspace.fileInfo")],
    ...(showClinical ? [["visit", t("workspace.treatment")] as [TabKey, string]] : []),
    ...(showOrdersTab ? [["orders", t("workspace.orders")] as [TabKey, string]] : []),
    ...(showPharmacyTab ? [["pharmacy", t("workspace.pharmacy")] as [TabKey, string]] : []),
    ...(showNursingTab ? [["nursing", t("workspace.nursing")] as [TabKey, string]] : []),
    ...(showStayTab ? [["stay", t("workspace.stay")] as [TabKey, string]] : []),
    ...(showAccount ? [["billing", t("patients.billing")] as [TabKey, string]] : []),
    ...(showDocs ? [["docs", t("patients.docs")] as [TabKey, string]] : []),
    ["timeline", t("patients.timeline")],
  ];

  return (
    <div className="flex h-full min-h-0">
      <aside className="flex w-[210px] shrink-0 flex-col border-e border-[var(--his-border)] bg-[#eef3f8]">
        <div className="his-panel-header">{t("patients.title")}</div>
        <div className="overflow-y-auto flex-1">
          {(listData?.rows ?? []).map((row) => (
            <Link
              key={row.id}
              href={`/${locale}/dashboard/patients/${row.id}`}
              className={`block border-b border-[#d0dae4] px-2 py-1.5 text-[11px] ${
                String(row.id) === patientId ? "bg-[#4a90d9] text-white" : "hover:bg-[#dce8f3]"
              }`}
            >
              <div className="font-semibold truncate">{row.full_name}</div>
              <div className="opacity-80">{dailyNo(row.mrn)}</div>
            </Link>
          ))}
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <div className="border-b border-[var(--his-border)] bg-[var(--his-panel-alt)] p-2">
          <div className="flex gap-3">
            <div className="flex h-[88px] w-[72px] shrink-0 items-center justify-center border border-[var(--his-border)] bg-white">
              <UserRound size={36} className="text-[var(--his-muted)]" />
            </div>
            <div className="min-w-0 flex-1">
              <div className="mb-1 flex flex-wrap items-center gap-2">
                <h2 className="m-0 text-[15px] font-bold text-[var(--his-header)]">{patient.full_name}</h2>
                <Badge tone="info">{dailyNo(patient.mrn)}</Badge>
                {patient.blood_type ? <Badge tone="danger">{patient.blood_type}</Badge> : null}
                <Badge tone={current ? "warning" : "success"}>
                  {current ? t("workspace.activeVisit") : t("workspace.noOpenVisit")}
                </Badge>
              </div>
              <div className="his-field-grid">
                <Info label={t("patients.gender")} value={genderLabel} />
                <Info label={t("patients.age")} value={patient.age ? `${patient.age}` : "—"} />
                <Info label={t("patients.dob")} value={formatDate(patient.date_of_birth)} />
                <Info label={t("patients.nationalId")} value={patient.national_id ?? "—"} />
                <Info label={t("patients.phone")} value={patient.phone ?? "—"} />
                <Info label={t("patients.city")} value={patient.city ?? "—"} />
                <Info label={t("workspace.doctor")} value={current?.doctor?.name ?? "—"} />
                <Info label={t("workspace.department")} value={current?.department?.name ?? "—"} />
              </div>
            </div>
          </div>
        </div>

        <div className="border-b border-[var(--his-border)] bg-white p-2">
          {current ? (
            <div className="flex flex-wrap items-center gap-2 text-[12px]">
              <Badge tone="info">{dailyNo(current.encounter_number ?? current.id)}</Badge>
              <span>{visitLabel(current.type, tr)}</span>
              <span>{arabicLabel(current.status)}</span>
              <span>{current.chief_complaint ?? "—"}</span>
              {current.doctor?.name ? <span>{t("patients.actor")}: {current.doctor.name}</span> : null}
              {canTransfer ? (
                <Button variant="info" onClick={() => openAction("transfer")}>
                  {t("encounters.transfer")}
                </Button>
              ) : null}
              {activeStay && showStay ? (
                <Button variant="warning" onClick={() => openAction("dischargeStay")}>
                  {t("workspace.dischargeStay")}
                </Button>
              ) : null}
              {canDischarge ? (
                <Button variant="success" onClick={() => openAction("discharge")}>
                  {t("workspace.discharge")}
                </Button>
              ) : null}
            </div>
          ) : canOpenVisit ? (
            <div className="grid gap-2 md:grid-cols-6">
              <Select value={visit.type} onChange={(e) => setVisit((v) => ({ ...v, type: e.target.value }))}>
                {VISIT_TYPES.filter((type) => {
                  if (type === "inpatient") return can("inpatient.admit");
                  if (type === "operation") return can("operations.schedule");
                  return true;
                }).map((type) => (
                  <option key={type} value={type}>
                    {visitLabel(type, tr)}
                  </option>
                ))}
              </Select>
              <Select
                value={visit.department_id}
                onChange={(e) => setVisit((v) => ({ ...v, department_id: e.target.value, clinic_id: "" }))}
              >
                <option value="">{t("workspace.department")}</option>
                {(catalogs?.departments ?? []).map((row) => (
                  <option key={row.id} value={row.id}>
                    {labelOf(row)}
                  </option>
                ))}
              </Select>
              <Select value={visit.clinic_id} onChange={(e) => setVisit((v) => ({ ...v, clinic_id: e.target.value }))}>
                <option value="">{t("appointments.clinic")}</option>
                {(catalogs?.clinics ?? [])
                  .filter((row) => !visit.department_id || String(row.department_id ?? "") === visit.department_id)
                  .map((row) => (
                  <option key={row.id} value={row.id}>
                    {labelOf(row)}
                  </option>
                ))}
              </Select>
              <Select value={visit.doctor_id} onChange={(e) => setVisit((v) => ({ ...v, doctor_id: e.target.value }))}>
                <option value="">{t("workspace.doctor")}</option>
                {(catalogs?.doctors ?? []).map((row) => (
                  <option key={row.id} value={row.id}>
                    {labelOf(row)}
                  </option>
                ))}
              </Select>
              <Input
                placeholder={t("workspace.chiefComplaint")}
                value={visit.chief_complaint}
                onChange={(e) => setVisit((v) => ({ ...v, chief_complaint: e.target.value }))}
              />
              <Button variant="primary" disabled={run.isPending} onClick={openVisit}>
                {t("workspace.openVisit")}
              </Button>
            </div>
          ) : null}
        </div>

        <div className="his-tabs flex-wrap px-1">
          {tabs.map(([key, label]) => (
            <button
              key={key}
              type="button"
              className={`his-tab ${tab === key ? "active" : ""}`}
              onClick={() => setTab(key)}
            >
              {label}
            </button>
          ))}
        </div>

        <div className="min-h-0 flex-1 overflow-auto p-2">
          {tab === "file" && (
            <div className="grid gap-2 md:grid-cols-2">
              <Section
                title={t("patients.allergies")}
                action={
                  canUpdatePatient ? <Button onClick={() => openAction("allergy")}>{t("app.add")}</Button> : null
                }
              >
                <SimpleList
                  items={patient.allergies ?? []}
                  text={(item) => `${item.allergen}${item.reaction ? ` — ${item.reaction}` : ""}`}
                  onRemove={canUpdatePatient ? (item) => removeFact(`/patient-allergies/${item.id}`) : undefined}
                />
              </Section>
              <Section
                title={t("patients.conditions")}
                action={
                  canUpdatePatient ? <Button onClick={() => openAction("condition")}>{t("app.add")}</Button> : null
                }
              >
                <SimpleList
                  items={patient.conditions ?? []}
                  text={(item) => item.condition_name}
                  onRemove={canUpdatePatient ? (item) => removeFact(`/patient-conditions/${item.id}`) : undefined}
                />
              </Section>
              <Section
                title={t("patients.contacts")}
                action={
                  canUpdatePatient ? <Button onClick={() => openAction("contact")}>{t("app.add")}</Button> : null
                }
              >
                <SimpleList
                  items={patient.contacts ?? []}
                  text={(item) => `${item.name}${item.phone ? ` — ${item.phone}` : ""}`}
                  onRemove={canUpdatePatient ? (item) => removeFact(`/patient-contacts/${item.id}`) : undefined}
                />
              </Section>
              <Section title={t("app.notes")}>
                <div className="text-[12px]">{patient.notes || t("app.noData")}</div>
              </Section>
            </div>
          )}

          {tab === "visit" && showClinical && (
            <div className="grid gap-2">
              <div className="his-panel">
                <div className="his-panel-header">{t("workspace.vitalsAction")}</div>
                <DataTable
                  columns={[
                    { key: "item", header: t("crud.name"), cell: (row) => text(row.item) },
                    { key: "detail", header: t("crud.value"), width: 160, cell: (row) => text(row.detail) },
                    { key: "actor", header: t("patients.actor"), width: 160, cell: (row) => text(row.actor) },
                    { key: "when", header: t("app.date"), width: 140, cell: (row) => text(row.when) },
                  ]}
                  rows={vitalSheet(vitals, tr)}
                  rowKey={(_row, index) => index}
                  emptyText={t("app.noData")}
                />
              </div>
              <div className="his-panel">
                <div className="his-panel-header">{t("workspace.treatment")}</div>
                <DataTable
                  columns={[
                    { key: "n", header: "#", width: 50, cell: (_row, index) => String(index + 1) },
                    { key: "section", header: t("workspace.department"), width: 130, cell: (row) => text(row.section) },
                    { key: "item", header: t("crud.name"), width: 180, cell: (row) => text(row.item) },
                    { key: "detail", header: t("patients.detail"), cell: (row) => text(row.detail) },
                    { key: "actor", header: t("patients.actor"), width: 150, cell: (row) => text(row.actor) },
                    { key: "when", header: t("app.date"), width: 140, cell: (row) => text(row.when) },
                  ]}
                  rows={visitSheet(arr(clinical.diagnoses), procedures, notes as unknown as Rec[], tr)}
                  rowKey={(_row, index) => index}
                  emptyText={t("app.noData")}
                />
              </div>
            </div>
          )}

          {tab === "orders" && showOrdersTab && (
            <div className="grid gap-2">
              {showOrderRecords ? (
              <div className="his-panel">
                <div className="his-panel-header">{t("workspace.orders")}</div>
                <DataTable
                  columns={[
                    { key: "n", header: "#", width: 50, cell: (_row, index) => String(index + 1) },
                    { key: "type", header: t("crud.type"), width: 110, cell: (row) => arabicLabel(row.type) },
                    { key: "item", header: t("crud.name"), cell: (row) => (arr(row.items).length ? arr(row.items).map((item) => text(item)).join("، ") : "—") },
                    { key: "number", header: t("crud.code"), width: 150, cell: (row) => localize(row.order_number) || "—" },
                    { key: "status", header: t("app.status"), width: 110, cell: (row) => arabicLabel(row.status) },
                    { key: "actor", header: t("patients.actor"), width: 150, cell: (row) => actorOf(row) || "—" },
                    { key: "when", header: t("app.date"), width: 140, cell: (row) => timelineWhen(row.ordered_at) },
                  ]}
                  rows={orders}
                  rowKey={(row, index) => String(row.id ?? index)}
                  emptyText={t("app.noData")}
                />
              </div>
              ) : null}
              {showLabRecords ? (
              <div className="his-panel">
                <div className="his-panel-header">{t("nav.laboratory")}</div>
                <DataTable
                  columns={[
                    { key: "n", header: "#", width: 50, cell: (_row, index) => String(index + 1) },
                    { key: "test", header: t("crud.name"), cell: (row) => text(row.test) },
                    {
                      key: "result",
                      header: t("laboratory.resultValue"),
                      width: 160,
                      cell: (row) => <ResultMark value={row.result_value} />,
                    },
                    { key: "price", header: t("crud.price"), width: 110, cell: (row) => formatMoney(row.price, "0") },
                    { key: "actor", header: t("patients.actor"), width: 140, cell: (row) => actorOf(row) || "—" },
                    { key: "verified", header: t("patients.verifiedBy"), width: 140, cell: (row) => text(row.verified_by) },
                    { key: "when", header: t("app.date"), width: 140, cell: (row) => timelineWhen(row.reported_at) },
                  ]}
                  rows={lab}
                  rowKey={(row, index) => String(row.id ?? index)}
                  emptyText={t("app.noData")}
                />
              </div>
              ) : null}
              {showLabRecords ? (
              <div className="his-panel">
                <div className="his-panel-header">{t("laboratory.bloodRequests")}</div>
                <DataTable
                  columns={[
                    { key: "n", header: "#", width: 50, cell: (_row, index) => String(index + 1) },
                    { key: "product", header: t("crud.name"), width: 160, cell: (row) => text(row.product) },
                    { key: "group", header: t("laboratory.bloodGroup"), width: 80, cell: (row) => text(row.blood_group) },
                    { key: "units", header: t("laboratory.units"), width: 90, cell: (row) => latinDigits(text(row.units, "0")) },
                    { key: "price", header: t("crud.price"), width: 110, cell: (row) => formatMoney(row.price, "0") },
                    { key: "confirmed", header: t("laboratory.confirmGroup"), width: 110, cell: (row) => text(row.confirmed_blood_group) },
                    { key: "bag", header: t("laboratory.bagNumber"), width: 120, cell: (row) => text(row.bag_number) },
                    { key: "when", header: t("laboratory.issueDate"), width: 120, cell: (row) => formatDate(text(row.issued_at, "") || null) },
                    { key: "status", header: t("app.status"), width: 100, cell: (row) => arabicLabel(row.status) },
                    { key: "actor", header: t("patients.actor"), width: 140, cell: (row) => actorOf(row) || "—" },
                  ]}
                  rows={bloodRequests}
                  rowKey={(row, index) => String(row.id ?? index)}
                  emptyText={t("app.noData")}
                />
              </div>
              ) : null}
              {showRadRecords ? (
              <div className="his-panel">
                <div className="his-panel-header">{t("nav.radiology")}</div>
                <DataTable
                  columns={[
                    { key: "n", header: "#", width: 50, cell: (_row, index) => String(index + 1) },
                    {
                      key: "service",
                      header: t("crud.name"),
                      cell: (row) =>
                        row.with_injection
                          ? `${text(row.service)} — ${t("radiology.withInjection")}`
                          : text(row.service),
                    },
                    {
                      key: "impression",
                      header: t("radiology.impression"),
                      cell: (row) => <ResultMark value={row.impression} />,
                    },
                    { key: "images", header: t("radiology.upload"), width: 90, cell: (row) => latinDigits(text(row.image_count, "0")) },
                    { key: "price", header: t("crud.price"), width: 110, cell: (row) => formatMoney(row.price, "0") },
                    { key: "actor", header: t("patients.actor"), width: 140, cell: (row) => actorOf(row) || "—" },
                    { key: "verified", header: t("patients.verifiedBy"), width: 140, cell: (row) => text(row.verified_by) },
                    { key: "when", header: t("app.date"), width: 140, cell: (row) => timelineWhen(row.reported_at) },
                  ]}
                  rows={radiology}
                  rowKey={(row, index) => String(row.id ?? index)}
                  emptyText={t("app.noData")}
                />
              </div>
              ) : null}
            </div>
          )}

          {tab === "pharmacy" && showPharmacyTab && (
            <div className="grid gap-2">
              <div className="his-panel">
                <div className="his-panel-header">{t("pharmacy.prescriptions")}</div>
                <DataTable
                  columns={[
                    { key: "n", header: "#", width: 50, cell: (_row, index) => String(index + 1) },
                    { key: "number", header: t("crud.code"), width: 150, cell: (row) => localize(row.prescription_number) || "—" },
                    { key: "name", header: t("pharmacy.medications"), cell: (row) => text(row.name) },
                    { key: "quantity", header: t("workspace.quantity"), width: 80, cell: (row) => latinDigits(text(row.quantity, "0")) },
                    { key: "price", header: t("crud.price"), width: 110, cell: (row) => formatMoney(row.price, "0") },
                    { key: "status", header: t("app.status"), width: 120, cell: (row) => arabicLabel(row.status) },
                    { key: "actor", header: t("patients.actor"), width: 150, cell: (row) => actorOf(row) || "—" },
                    { key: "when", header: t("app.date"), width: 140, cell: (row) => timelineWhen(row.prescribed_at) },
                  ]}
                  rows={prescriptionRows(arr(pharmacy.prescriptions))}
                  rowKey={(row, index) => `${text(row.prescription_number, "rx")}-${index}`}
                  emptyText={t("app.noData")}
                />
              </div>
              <div className="his-panel">
                <div className="his-panel-header">{t("pharmacy.dispense")}</div>
                <DataTable
                  columns={[
                    { key: "n", header: "#", width: 50, cell: (_row, index) => String(index + 1) },
                    { key: "number", header: t("crud.code"), width: 150, cell: (row) => localize(row.dispense_number) || "—" },
                    { key: "name", header: t("pharmacy.medications"), cell: (row) => text(row.medication) },
                    { key: "quantity", header: t("workspace.quantity"), width: 80, cell: (row) => latinDigits(text(row.quantity, "0")) },
                    { key: "price", header: t("crud.price"), width: 110, cell: (row) => formatMoney(row.total_price, "0") },
                    { key: "actor", header: t("patients.actor"), width: 150, cell: (row) => actorOf(row) || "—" },
                    { key: "when", header: t("app.date"), width: 140, cell: (row) => timelineWhen(row.dispensed_at) },
                  ]}
                  rows={arr(pharmacy.dispenses)}
                  rowKey={(row, index) => String(row.id ?? index)}
                  emptyText={t("app.noData")}
                />
              </div>
            </div>
          )}

          {tab === "nursing" && showNursingTab && (
            <Section title={t("workspace.nursing")}>
              <SimpleList items={arr(nursing.notes)} text={(item) => withActor(text(item.content), item, t("patients.actor"))} />
            </Section>
          )}

          {tab === "stay" && showStayTab && (
            <div className="grid gap-2 md:grid-cols-2">
              <Section title={t("workspace.stay")}>
                <SimpleList
                  items={arr(admissions.items)}
                  text={(item) =>
                    withActor(
                      `${localize(item.admission_number)} — ${arabicLabel(item.status)} — ${text(item.bed || item.department)}`,
                      item,
                      t("patients.actor"),
                    )
                  }
                />
              </Section>
              {showOperations ? (
              <Section title={t("nav.operations")}>
                <SimpleList
                  items={operations}
                  text={(item) => withActor(`${text(item.name)} — ${arabicLabel(item.status)} — ${formatDate(String(item.scheduled_at ?? ""), true)}`, item, t("patients.actor"))}
                />
              </Section>
              ) : null}
            </div>
          )}

          {tab === "billing" && showAccount && (
            <PatientAccountPanel
              billing={billing}
              onPay={
                canPay
                  ? (invoice) =>
                      openAction("pay", {
                        invoice_id: String(invoice.id),
                        amount: nextInstallmentAmount(invoice),
                        method: "cash",
                      })
                  : undefined
              }
              onInstallments={
                canInstallment
                  ? (invoice) =>
                      openAction("installment", {
                        invoice_id: String(invoice.id),
                        count: "3",
                        first_due_date: defaultDueDate(),
                        interval_months: "1",
                      })
                  : undefined
              }
            />
          )}

          {tab === "docs" && showDocs && (
            <Section
              title={t("patients.docs")}
              action={
                documents.length > 0 ? (
                  <span className="text-[11px] font-normal text-[var(--his-muted)]">{documents.length}</span>
                ) : null
              }
            >
              <DocumentGallery
                empty={t("app.noData")}
                files={documents.map((item) => ({
                  uuid: text(item.uuid, "") || undefined,
                  original_name: text(item.original_name, ""),
                  mime_type: text(item.mime_type, ""),
                  exam_name: text(item.exam_name, ""),
                  by: text(item.by, ""),
                  created_at: text(item.created_at, ""),
                }))}
              />
            </Section>
          )}

          {tab === "timeline" && (
            <DataTable
              columns={timelineColumns}
              rows={(Array.isArray(timeline) ? (timeline as Rec[]) : []).filter((row) =>
                timelineVisible(String(row.event_type ?? ""), can, showBilling),
              )}
              rowKey={(row, index) => String(row.id ?? index)}
              emptyText={t("app.noData")}
            />
          )}
        </div>

        {canVitals || canNote || canDiagnosis || showLab || showRadiology || showPrescribe || showSurgery || showAdmit || canBook || showCharge ? (
        <div className="his-action-bar">
          {canVitals ? (
            <Button variant="danger" onClick={() => openAction("vitals")}>
              <Activity size={13} />
              {t("workspace.vitalsAction")}
            </Button>
          ) : null}
          {canNote ? (
            <Button onClick={() => openAction("note")}>
              <NotebookPen size={13} />
              {t("workspace.noteAction")}
            </Button>
          ) : null}
          {canDiagnosis ? (
            <Button variant="primary" onClick={() => openAction("diagnosis")}>
              {t("workspace.diagnosis")}
            </Button>
          ) : null}
          {showLab ? (
            <Button variant="info" onClick={() => router.push(`/${locale}/dashboard/patients/${patientId}/laboratory`)}>
              <FlaskConical size={13} />
              {t("workspace.labAction")}
            </Button>
          ) : null}
          {showLab ? (
            <Button
              variant="danger"
              onClick={() =>
                openAction("blood", {
                  blood_product_id: "",
                  blood_group: patient?.blood_type ?? "",
                  units: "1",
                })
              }
            >
              <Droplet size={13} />
              {t("laboratory.bloodRequest")}
            </Button>
          ) : null}
          {showRadiology ? (
            <Button variant="warning" onClick={() => router.push(`/${locale}/dashboard/patients/${patientId}/radiology`)}>
              <Scan size={13} />
              {t("nav.radiology")}
            </Button>
          ) : null}
          {showPrescribe ? (
            <Button variant="teal" onClick={() => openAction("medication")}>
              <Pill size={13} />
              {t("workspace.medAction")}
            </Button>
          ) : null}
          {showSurgery ? (
            <Button variant="success" onClick={() => openAction("surgery")}>
              <Syringe size={13} />
              {t("workspace.surgeryAction")}
            </Button>
          ) : null}
          {showAdmit ? (
            <Button onClick={() => openAction("admit")}>
              <BedDouble size={13} />
              {t("workspace.admit")}
            </Button>
          ) : null}
          {canBook ? (
            <Button onClick={() => openAction("appointment")}>
              <CalendarDays size={13} />
              {t("appointments.new")}
            </Button>
          ) : null}
          {showCharge ? <Button onClick={() => openAction("charge")}>{t("workspace.charge")}</Button> : null}
        </div>
        ) : null}
      </div>

      {action ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
          <div className="his-panel w-full max-w-lg bg-white">
            <div className="his-panel-header flex items-center justify-between">
              <span>{dialogTitle(action, tr)}</span>
              <button type="button" onClick={() => setAction(null)}>
                ×
              </button>
            </div>
            <form
              className="space-y-2 p-3"
              onSubmit={(event) => {
                event.preventDefault();
                void submitAction();
              }}
            >
              <ActionFields
                action={action}
                form={form}
                setField={setField}
                catalogs={catalogs}
                clinicDiagnoses={clinicDiagnoses}
                clinicReady={diagnosisReady}
                diagnosesLoading={diagnosesLoading}
                diagnosesFetched={diagnosesFetched}
                t={tr}
              />
              <div className="flex justify-end gap-2 pt-2">
                <Button type="button" onClick={() => setAction(null)}>
                  {t("app.cancel")}
                </Button>
                <Button type="submit" variant="primary" disabled={run.isPending}>
                  {t("app.save")}
                </Button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </div>
  );
}

function ActionFields({
  action,
  form,
  setField,
  catalogs,
  clinicDiagnoses,
  clinicReady,
  diagnosesLoading,
  diagnosesFetched,
  t,
}: {
  action: ActionKey;
  form: Record<string, string>;
  setField: (key: string, value: string) => void;
  catalogs?: Catalogs;
  clinicDiagnoses: NamedRow[];
  clinicReady: boolean;
  diagnosesLoading: boolean;
  diagnosesFetched: boolean;
  t: (key: string) => string;
}) {
  const { data: bloodProducts = [], isLoading: bloodProductsLoading } = useQuery({
    queryKey: ["/laboratory/blood-products", "order"],
    enabled: action === "blood",
    queryFn: async () => {
      const res = await apiGet<Array<{ id: number; name?: string; price?: number }>>("/laboratory/blood-products", {
        params: { per_page: 100 },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  switch (action) {
    case "vitals":
      return (
        <div className="grid grid-cols-2 gap-2">
          <Field label={t("workspace.temperature")}>
            <Input value={form.temperature ?? ""} onChange={(e) => setField("temperature", e.target.value)} />
          </Field>
          <Field label={t("workspace.heartRate")}>
            <Input value={form.heart_rate ?? ""} onChange={(e) => setField("heart_rate", e.target.value)} />
          </Field>
          <Field label={t("workspace.bloodPressure")}>
            <Input
              dir="ltr"
              lang="en-GB"
              placeholder="120/80"
              value={form.blood_pressure ?? ""}
              onChange={(e) => setField("blood_pressure", e.target.value)}
            />
          </Field>
          <Field label={t("workspace.spo2")}>
            <Input value={form.oxygen_saturation ?? ""} onChange={(e) => setField("oxygen_saturation", e.target.value)} />
          </Field>
          <Field label={t("workspace.weight")}>
            <Input value={form.weight ?? ""} onChange={(e) => setField("weight", e.target.value)} />
          </Field>
          <Field label={t("workspace.height")}>
            <Input value={form.height ?? ""} onChange={(e) => setField("height", e.target.value)} />
          </Field>
        </div>
      );
    case "note":
    case "nursing":
      return (
        <Field label={t("app.notes")}>
          <textarea className="his-input min-h-24 w-full" required value={form.content ?? ""} onChange={(e) => setField("content", e.target.value)} />
        </Field>
      );
    case "diagnosis":
      return (
        <Field label={t("workspace.diagnosis")}>
          <DiagnosisSelect
            rows={clinicDiagnoses}
            value={form.diagnosis_code_id ?? ""}
            onChange={(value) => setField("diagnosis_code_id", value)}
            clinicReady={clinicReady}
            loading={diagnosesLoading}
            fetched={diagnosesFetched}
            t={t}
          />
        </Field>
      );
    case "procedure":
      return (
        <Field label={t("workspace.procedure")}>
          <NamedSelect
            required
            rows={procedureServices(catalogs?.services)}
            value={form.service_id ?? ""}
            onChange={(value) => setField("service_id", value)}
          />
        </Field>
      );
    case "blood":
      return (
        <div className="grid grid-cols-2 gap-2">
          <Field label={t("laboratory.bloodRequest")}>
            {bloodProductsLoading ? (
              <p className="text-[12px]">{t("app.loading")}</p>
            ) : bloodProducts.length === 0 ? (
              <p className="text-[12px]">{t("laboratory.noBloodProducts")}</p>
            ) : (
              <Select required value={form.blood_product_id ?? ""} onChange={(e) => setField("blood_product_id", e.target.value)}>
                <option value="">—</option>
                {bloodProducts.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                  </option>
                ))}
              </Select>
            )}
          </Field>
          <Field label={t("laboratory.bloodGroup")}>
            <Select required value={form.blood_group ?? ""} onChange={(e) => setField("blood_group", e.target.value)}>
              <option value="">—</option>
              {BLOOD_GROUPS.map((item) => (
                <option key={item} value={item}>
                  {item}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("laboratory.units")}>
            <Input required type="number" min={1} max={20} value={form.units ?? "1"} onChange={(e) => setField("units", e.target.value)} />
          </Field>
        </div>
      );
    case "radiology":
      return (
        <Field label={t("radiology.study")}>
          <NamedSelect required rows={catalogs?.radiology} value={form.service_id ?? ""} onChange={(value) => setField("service_id", value)} />
        </Field>
      );
    case "medication":
      return (
        <>
          <Field label={t("workspace.medAction")}>
            <NamedSelect required rows={catalogs?.medications} value={form.medication_id ?? ""} onChange={(value) => setField("medication_id", value)} />
          </Field>
          <div className="grid grid-cols-2 gap-2">
            <Field label={t("workspace.dose")}>
              <Input value={form.dose ?? ""} onChange={(e) => setField("dose", e.target.value)} />
            </Field>
            <Field label={t("workspace.frequency")}>
              <Input value={form.frequency ?? ""} onChange={(e) => setField("frequency", e.target.value)} />
            </Field>
            <Field label={t("workspace.duration")}>
              <Input value={form.duration ?? ""} onChange={(e) => setField("duration", e.target.value)} />
            </Field>
            <Field label={t("workspace.quantity")}>
              <Input value={form.quantity ?? "1"} onChange={(e) => setField("quantity", e.target.value)} />
            </Field>
          </div>
        </>
      );
    case "admit":
      return (
        <>
          <Field label={t("workspace.admitReason")}>
            <Input required value={form.admission_reason ?? ""} onChange={(e) => setField("admission_reason", e.target.value)} />
          </Field>
          <Field label={t("workspace.bed")}>
            <NamedSelect
              rows={(catalogs?.beds ?? []).filter((bed) => bed.status === "available")}
              value={form.bed_id ?? ""}
              onChange={(value) => setField("bed_id", value)}
            />
          </Field>
        </>
      );
    case "surgery":
      return (
        <>
          <Field label={t("workspace.operationType")}>
            <NamedSelect required rows={catalogs?.operationTypes} value={form.operation_type_id ?? ""} onChange={(value) => setField("operation_type_id", value)} />
          </Field>
          <Field label={t("appointments.scheduledAt")}>
            <Input required type="datetime-local" value={form.scheduled_at ?? ""} onChange={(e) => setField("scheduled_at", e.target.value)} />
          </Field>
          <Field label={t("workspace.diagnosis")}>
            <Input value={form.pre_op_diagnosis ?? ""} onChange={(e) => setField("pre_op_diagnosis", e.target.value)} />
          </Field>
        </>
      );
    case "charge":
      return (
        <div className="grid grid-cols-2 gap-2">
          <Field label={t("workspace.description")}>
            <Input required value={form.description ?? ""} onChange={(e) => setField("description", e.target.value)} />
          </Field>
          <Field label={t("workspace.amount")}>
            <Input required value={form.unit_price ?? ""} onChange={(e) => setField("unit_price", e.target.value)} />
          </Field>
        </div>
      );
    case "discharge":
      return (
        <>
          <Field label={t("workspace.dischargeInstructions")}>
            <textarea className="his-input min-h-20 w-full" value={form.discharge_instructions ?? ""} onChange={(e) => setField("discharge_instructions", e.target.value)} />
          </Field>
          <Field label={t("workspace.followUp")}>
            <Input type="date" value={form.follow_up_date ?? ""} onChange={(e) => setField("follow_up_date", e.target.value)} />
          </Field>
        </>
      );
    case "dischargeStay":
      return (
        <Field label={t("workspace.dischargeSummary")}>
          <textarea required className="his-input min-h-20 w-full" value={form.discharge_summary ?? ""} onChange={(e) => setField("discharge_summary", e.target.value)} />
        </Field>
      );
    case "transfer":
      return (
        <>
          <Field label={t("workspace.department")}>
            <NamedSelect required rows={catalogs?.departments} value={form.department_id ?? ""} onChange={(value) => setField("department_id", value)} />
          </Field>
          <Field label={t("workspace.doctor")}>
            <NamedSelect rows={catalogs?.doctors} value={form.doctor_id ?? ""} onChange={(value) => setField("doctor_id", value)} />
          </Field>
          <Field label={t("workspace.reason")}>
            <Input value={form.reason ?? ""} onChange={(e) => setField("reason", e.target.value)} />
          </Field>
        </>
      );
    case "allergy":
      return (
        <>
          <Field label={t("patients.allergies")}>
            <Input required value={form.allergen ?? ""} onChange={(e) => setField("allergen", e.target.value)} />
          </Field>
          <Field label={t("workspace.reaction")}>
            <Input value={form.reaction ?? ""} onChange={(e) => setField("reaction", e.target.value)} />
          </Field>
        </>
      );
    case "condition":
      return (
        <Field label={t("patients.conditions")}>
          <Input required value={form.condition_name ?? ""} onChange={(e) => setField("condition_name", e.target.value)} />
        </Field>
      );
    case "contact":
      return (
        <>
          <Field label={t("patients.fullName")}>
            <Input required value={form.name ?? ""} onChange={(e) => setField("name", e.target.value)} />
          </Field>
          <Field label={t("patients.phone")}>
            <Input value={form.phone ?? ""} onChange={(e) => setField("phone", e.target.value)} />
          </Field>
          <Field label={t("workspace.relationship")}>
            <Input value={form.relationship ?? ""} onChange={(e) => setField("relationship", e.target.value)} />
          </Field>
        </>
      );
    case "appointment":
      return (
        <>
          <Field label={t("workspace.department")}>
            <NamedSelect rows={catalogs?.departments} value={form.department_id ?? ""} onChange={(value) => setField("department_id", value)} />
          </Field>
          <Field label={t("workspace.doctor")}>
            <NamedSelect rows={catalogs?.doctors} value={form.doctor_id ?? ""} onChange={(value) => setField("doctor_id", value)} />
          </Field>
          <Field label={t("appointments.scheduledAt")}>
            <Input required type="datetime-local" value={form.scheduled_at ?? ""} onChange={(e) => setField("scheduled_at", e.target.value)} />
          </Field>
          <Field label={t("workspace.reason")}>
            <Input value={form.reason ?? ""} onChange={(e) => setField("reason", e.target.value)} />
          </Field>
        </>
      );
    case "pay":
      return (
        <div className="grid grid-cols-2 gap-2">
          <Field label={t("workspace.amount")}>
            <Input required type="number" min="0.01" step="0.01" value={form.amount ?? ""} onChange={(e) => setField("amount", e.target.value)} />
          </Field>
          <Field label={t("billing.method")}>
            <Select value={form.method ?? "cash"} onChange={(e) => setField("method", e.target.value)}>
              <option value="cash">{arabicLabel("cash")}</option>
              <option value="card">{arabicLabel("card")}</option>
              <option value="bank_transfer">{arabicLabel("bank_transfer")}</option>
              <option value="insurance">{arabicLabel("insurance")}</option>
              <option value="other">{arabicLabel("other")}</option>
            </Select>
          </Field>
          {form.method && form.method !== "cash" && form.method !== "other" ? (
            <Field label={t("billing.reference")}>
              <Input required value={form.reference ?? ""} onChange={(e) => setField("reference", e.target.value)} />
            </Field>
          ) : null}
        </div>
      );
    case "installment":
      return (
        <div className="grid grid-cols-2 gap-2">
          <Field label={t("billing.installmentCount")}>
            <Input required type="number" min={2} max={36} value={form.count ?? "3"} onChange={(e) => setField("count", e.target.value)} />
          </Field>
          <Field label={t("billing.intervalMonths")}>
            <Input required type="number" min={1} max={12} value={form.interval_months ?? "1"} onChange={(e) => setField("interval_months", e.target.value)} />
          </Field>
          <Field label={t("billing.firstDue")}>
            <Input required type="date" value={form.first_due_date ?? ""} onChange={(e) => setField("first_due_date", e.target.value)} />
          </Field>
        </div>
      );
  }
}

function DiagnosisSelect({
  rows,
  value,
  onChange,
  clinicReady,
  loading,
  fetched,
  t,
}: {
  rows: NamedRow[];
  value: string;
  onChange: (value: string) => void;
  clinicReady: boolean;
  loading: boolean;
  fetched: boolean;
  t: (key: string) => string;
}) {
  if (!clinicReady) {
    return <p className="text-[12px]">{t("workspace.pickClinicFirst")}</p>;
  }
  if (loading || !fetched) {
    return <p className="text-[12px]">{t("app.loading")}</p>;
  }
  if (rows.length === 0) {
    return <p className="text-[12px]">{t("workspace.noClinicDiagnoses")}</p>;
  }
  return <NamedSelect required rows={rows} value={value} onChange={onChange} />;
}

function NamedSelect({
  rows,
  value,
  onChange,
  required,
}: {
  rows?: NamedRow[];
  value: string;
  onChange: (value: string) => void;
  required?: boolean;
}) {
  return (
    <Select required={required} value={value} onChange={(e) => onChange(e.target.value)}>
      <option value="">—</option>
      {(rows ?? []).map((row) => (
        <option key={row.id} value={row.id}>
          {labelOf(row)}
        </option>
      ))}
    </Select>
  );
}

function labelOf(row: NamedRow) {
  return row.name || row.display_name || row.label || row.code || `#${row.id}`;
}

function procedureServices(rows?: NamedRow[]) {
  return (rows ?? [])
    .filter((row) => row.category !== "المعاينات" && row.category !== "الإقامة")
    .map((row) => ({ ...row, name: row.price != null ? `${row.name ?? ""} — ${formatMoney(row.price, "0")}` : row.name }));
}

function nextInstallmentAmount(invoice: Rec) {
  const open = arr(invoice.installments).find((row) => String(row.status) !== "paid" && Number(row.amount ?? 0) > 0);
  if (open) {
    const remaining = Number(open.remaining ?? Number(open.amount) - Number(open.paid_amount ?? 0));
    if (Number.isFinite(remaining) && remaining > 0) return String(remaining);
  }
  return String(invoice.balance ?? "");
}

function defaultDueDate() {
  const due = new Date();
  due.setMonth(due.getMonth() + 1);
  const month = String(due.getMonth() + 1).padStart(2, "0");
  const day = String(due.getDate()).padStart(2, "0");
  return `${due.getFullYear()}-${month}-${day}`;
}

function selectedName(rows: NamedRow[] | undefined, id: string) {
  return labelOf(rows?.find((row) => String(row.id) === id) ?? { id: Number(id) || 0 });
}

function num(value?: string | number | null) {
  if (value === undefined || value === null || value === "") return undefined;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : undefined;
}

function parseBloodPressure(value?: string) {
  const normalized = latinDigits(value ?? "").trim();
  if (!normalized) return undefined;
  const match = normalized.match(/^(\d{2,3})\s*[/\\-]\s*(\d{2,3})$/);
  if (!match) return null;
  return { systolic: Number(match[1]), diastolic: Number(match[2]) };
}

function localDateTime() {
  const date = new Date();
  const pad = (value: number) => String(value).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function toSql(value?: string) {
  if (!value) return undefined;
  const normalized = value.includes("T") ? value.replace("T", " ") : value;
  return normalized.length === 16 ? `${normalized}:00` : normalized;
}

function asObj(value: unknown): Rec {
  return value && typeof value === "object" && !Array.isArray(value) ? (value as Rec) : {};
}

function arr(value: unknown): Rec[] {
  return Array.isArray(value) ? (value as Rec[]) : [];
}

function text(value: unknown, empty = "—") {
  if (value === undefined || value === null || value === "") return empty;
  return String(value);
}

function ResultMark({ value }: { value: unknown }) {
  const done = value !== undefined && value !== null && String(value).trim() !== "";
  return (
    <span className="inline-flex items-center gap-1">
      {done ? (
        <Check size={14} strokeWidth={3} className="shrink-0 text-[#1e8449]" />
      ) : (
        <X size={14} strokeWidth={3} className="shrink-0 text-[#c0392b]" />
      )}
      <span>{text(value)}</span>
    </span>
  );
}

function visitLabel(type: string | undefined, t: (key: string) => string) {
  if (type === "outpatient") return t("encounters.outpatient");
  if (type === "inpatient") return t("encounters.inpatient");
  if (type === "emergency") return t("encounters.emergency");
  if (type === "operation") return t("workspace.operation");
  if (type === "follow_up") return t("workspace.followUpVisit");
  return type ?? "—";
}

function dialogTitle(action: ActionKey, t: (key: string) => string) {
  const map: Record<ActionKey, string> = {
    vitals: t("workspace.vitalsAction"),
    note: t("workspace.noteAction"),
    diagnosis: t("workspace.diagnosis"),
    procedure: t("workspace.procedure"),
    radiology: t("nav.radiology"),
    blood: t("laboratory.bloodRequest"),
    medication: t("workspace.medAction"),
    nursing: t("workspace.nursing"),
    admit: t("workspace.admit"),
    surgery: t("workspace.surgeryAction"),
    charge: t("workspace.charge"),
    discharge: t("workspace.discharge"),
    transfer: t("encounters.transfer"),
    allergy: t("patients.allergies"),
    condition: t("patients.conditions"),
    contact: t("patients.contacts"),
    appointment: t("appointments.new"),
    dischargeStay: t("workspace.dischargeStay"),
    pay: t("billing.pay"),
    installment: t("billing.installments"),
  };
  return map[action];
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div className="his-field" style={{ gridTemplateColumns: "95px 1fr" }}>
      <label>{label}</label>
      <div className="value truncate">{value}</div>
    </div>
  );
}

function Section({
  title,
  action,
  children,
}: {
  title: string;
  action?: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <div className="his-panel">
      <div className="his-panel-header flex items-center justify-between">
        <span>{title}</span>
        {action}
      </div>
      <div className="p-2 text-[12px]">{children}</div>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block text-[12px]">
      <span className="mb-1 block text-[var(--his-muted)]">{label}</span>
      {children}
    </label>
  );
}

function Muted({ children }: { children: React.ReactNode }) {
  return <div className="text-[var(--his-muted)]">{children}</div>;
}

function SimpleList<T extends { id?: number }>({
  items,
  text: render,
  onRemove,
}: {
  items: readonly T[];
  text: (item: T) => string;
  onRemove?: (item: T) => void;
}) {
  const t = useTranslations();
  if (!items.length) return <Muted>{t("app.noData")}</Muted>;
  return (
    <ul className="m-0 space-y-1">
      {items.map((item, index) => (
        <li key={String(item.id ?? index)} className="flex items-center justify-between gap-2">
          <span>{render(item)}</span>
          {onRemove && item.id ? (
            <Button variant="danger" onClick={() => onRemove(item)}>
              {t("app.delete")}
            </Button>
          ) : null}
        </li>
      ))}
    </ul>
  );
}

const TIMELINE_TITLES: Record<string, string> = {
  "patient.registered": "تسجيل المريض",
  "encounter.created": "فتح الزيارة",
  "encounter.started": "بدء المعاينة",
  "encounter.completed": "إنهاء الزيارة",
  "encounter.cancelled": "إلغاء الزيارة",
  "encounter.transferred": "نقل الزيارة",
  "order.created": "طلب جديد",
  "order.cancelled": "إلغاء طلب",
  "laboratory.result_verified": "نتيجة تحليل",
  "radiology.report_completed": "تقرير أشعة",
  "pharmacy.prescription_created": "وصفة طبية",
  "pharmacy.medication_dispensed": "صرف دواء",
  "billing.payment_received": "استلام مبلغ",
  "inpatient.admitted": "تنويم",
  "inpatient.discharged": "خروج من المستشفى",
  "inpatient.transferred": "نقل داخلي",
  "clinical.vitals_recorded": "علامات حيوية",
  "clinical.note_added": "ملاحظة سريرية",
  "clinical.diagnosis_added": "تشخيص",
  "clinical.procedure_recorded": "إجراء طبي",
  "operation.scheduled": "جدولة عملية",
  "operation.completed": "اكتمال العملية",
  "nursing.note": "ملاحظة تمريض",
};

function actorOf(item: Rec) {
  const direct = item.by ?? item.doctor ?? item.author ?? item.surgeon;
  if (typeof direct === "string") return direct.trim();
  if (direct && typeof direct === "object" && "name" in direct) {
    return String((direct as { name?: unknown }).name ?? "").trim();
  }
  return "";
}

function withActor(label: string, item: Rec, actorLabel: string) {
  const name = actorOf(item);
  return name ? `${label} — ${actorLabel}: ${name}` : label;
}

function actorName(value: unknown) {
  if (!value || typeof value !== "object" || !("name" in value)) return "—";
  const name = String((value as { name?: unknown }).name ?? "").trim();
  return name || "—";
}

function vitalSheet(vitals: Rec, label: (key: string) => string): Rec[] {
  const actor = text(vitals.by, "") || "—";
  const when = vitals.recorded_at ? timelineWhen(vitals.recorded_at) : "—";
  return (
    [
      [label("workspace.temperature"), vitals.temperature],
      [label("workspace.bloodPressure"), vitals.blood_pressure],
      [label("workspace.heartRate"), vitals.heart_rate],
      [label("workspace.respiratoryRate"), vitals.respiratory_rate],
      [label("workspace.spo2"), vitals.oxygen_saturation],
      [label("workspace.weight"), vitals.weight],
      [label("workspace.height"), vitals.height],
    ] as const
  )
    .filter(([, value]) => value !== undefined && value !== null && String(value).trim() !== "")
    .map(([item, value]) => ({
      item,
      detail: latinDigits(String(value)),
      actor,
      when,
    }));
}

function prescriptionRows(prescriptions: Rec[]): Rec[] {
  return prescriptions.flatMap((prescription) => {
    const lines = arr(prescription.medications);
    const source = lines.length > 0 ? lines : [{}];
    return source.map((line) => {
      const medication = asObj(line);
      return {
        prescription_number: prescription.prescription_number,
        status: prescription.status,
        by: prescription.by,
        doctor: prescription.doctor,
        prescribed_at: prescription.prescribed_at,
        name: medication.name,
        quantity: medication.quantity,
        price: medication.price,
      };
    });
  });
}

function visitSheet(diagnoses: Rec[], procedures: Rec[], notes: Rec[], label: (key: string) => string): Rec[] {
  const rows: Rec[] = [];
  for (const row of diagnoses) {
    rows.push({
      section: label("workspace.diagnoses"),
      item: text(row.name),
      detail: [text(row.code, ""), arabicLabel(row.type)].filter((part) => part && part !== "—").join(" — ") || "—",
      actor: actorOf(row) || "—",
      when: timelineWhen(row.diagnosed_at),
    });
  }
  for (const row of procedures) {
    rows.push({
      section: label("workspace.procedures"),
      item: text(row.name),
      detail: text(row.code),
      actor: actorOf(row) || "—",
      when: timelineWhen(row.performed_at),
    });
  }
  for (const row of notes) {
    rows.push({
      section: label("workspace.noteAction"),
      item: arabicLabel(row.note_type),
      detail: text(row.content),
      actor: actorOf(row) || "—",
      when: timelineWhen(row.created_at),
    });
  }
  return rows;
}

function timelineWhen(value: unknown) {
  if (!value) return "—";
  return formatDate(String(value), true);
}

function timelineVisible(type: string, can: (permission: string) => boolean, showBilling: boolean) {
  if (type.startsWith("billing.")) return showBilling;
  if (type.startsWith("laboratory.")) return can("laboratory.orders.view");
  if (type.startsWith("radiology.")) return can("radiology.orders.view");
  if (type.startsWith("pharmacy.")) return can("pharmacy.prescriptions.view");
  if (type.startsWith("nursing.")) return can("nursing.notes.view");
  if (type.startsWith("inpatient.")) return can("inpatient.view");
  if (type.startsWith("operation.")) return can("operations.view");
  if (type.startsWith("clinical.")) {
    return can("clinical.notes.view") || can("clinical.vitals.view") || can("clinical.diagnosis.view");
  }
  if (type.startsWith("order.")) return can("orders.view");
  return true;
}

function timelineText(item: Rec) {
  const type = String(item.event_type ?? "");
  const rawTitle = String(item.title ?? "");
  const rawDetail = String(item.description ?? "");
  const title = TIMELINE_TITLES[type] ?? (hasArabic(rawTitle) ? rawTitle : arabicLabel(type));
  return { title: localize(title), detail: latinDigits(localize(timelineDetail(type, rawTitle, rawDetail))) || "—" };
}

function timelineDetail(type: string, title: string, detail: string) {
  if (type === "encounter.created") {
    return arabicLabel(detail.split(" ")[0]);
  }
  if (type === "patient.registered") {
    const serial = detail.match(/(\d+)\s*$/)?.[1];
    return serial ? `الرقم ${Number(serial)}` : "";
  }
  if (type === "order.created" || type === "order.cancelled") {
    const kind = title.split(" order ")[0];
    return joinBits(hasArabic(kind) ? kind : arabicLabel(kind), detail, trackCode(title, "ORD"));
  }
  if (type === "billing.payment_received") {
    const amount = Number(title.split(":").pop()?.trim());
    const method = detail.split("-")[0]?.trim();
    return joinBits(Number.isFinite(amount) ? formatMoney(amount, "0") : "", method, trackCode(detail, "RCP"));
  }
  if (type === "pharmacy.prescription_created") {
    return joinBits(trackCode(title, "RX"), detail);
  }
  if (type === "pharmacy.medication_dispensed") {
    const quantity = detail.match(/^(\d+)/)?.[1];
    return joinBits(title.replace(/^صُرف\s*/, ""), quantity ? `الكمية ${quantity}` : "", trackCode(detail, "DSP"));
  }
  if (type === "laboratory.result_verified") {
    const name = title.split(":").slice(1).join(":").trim();
    const range = /reference range/i.test(detail) ? "ضمن المجال المرجعي" : detail;
    return joinBits(name, range);
  }
  if (type === "radiology.report_completed") {
    return joinBits(title.split(":").slice(1).join(":").trim(), detail);
  }
  if (type === "clinical.diagnosis_added" || type === "clinical.procedure_recorded") {
    const name = title.split(":").slice(1).join(":").trim();
    return joinBits(name, detail);
  }
  if (/^[a-z_]+$/i.test(detail)) return arabicLabel(detail);
  return detail;
}

function trackCode(value: string, prefix: string) {
  return value.match(new RegExp(`${prefix}-\\d{4}-\\d+`))?.[0] ?? "";
}

function joinBits(...parts: Array<string | undefined>) {
  return parts
    .map((part) => (part ?? "").trim())
    .filter((part) => part.length > 0 && part !== "—")
    .join(" — ");
}

function hasArabic(value: string) {
  return /[\u0600-\u06FF]/.test(value);
}
