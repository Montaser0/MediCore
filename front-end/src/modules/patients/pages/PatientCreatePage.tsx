"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import axios from "axios";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Panel } from "@/components/ui/Panel";
import { useCreatePatient, usePatients } from "../hooks/usePatients";
import { journeyApi, type NamedRow } from "../api/journey.api";
import { apiGet, getErrorMessage } from "@/lib/api/client";
import { arabicLabel } from "@/i18n/arabicLabels";
import { dailyNo } from "@/lib/arabicText";

const VISIT_TYPES = ["outpatient", "emergency", "follow_up"] as const;

export function PatientCreatePage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const create = useCreatePatient();
  const [saving, setSaving] = useState(false);

  const [form, setForm] = useState({
    first_name: "",
    father_name: "",
    last_name: "",
    mother_name: "",
    gender: "male",
    date_of_birth: "",
    national_id: "",
    phone: "",
    city: "",
    blood_type: "",
    address: "",
    notes: "",
    visit_type: "outpatient",
    department_id: "",
    clinic_id: "",
    doctor_id: "",
  });

  const lookup = [form.national_id, form.phone, `${form.first_name} ${form.last_name}`]
    .map((value) => value.trim())
    .find((value) => value.length >= 3);
  const { data: matches } = usePatients(
    lookup ? { search: lookup, per_page: 5 } : undefined,
    Boolean(lookup),
  );
  const existing = (matches?.rows ?? []).filter((patient) => {
    if (form.national_id.trim() && patient.national_id === form.national_id.trim()) return true;
    if (
      form.phone.trim() &&
      patient.phone === form.phone.trim() &&
      patient.first_name === form.first_name.trim() &&
      patient.last_name === form.last_name.trim()
    ) {
      return true;
    }
    return false;
  });
  const { data: departments = [] } = useQuery({
    queryKey: ["/departments", "register"],
    queryFn: async () => {
      const res = await apiGet<NamedRow[]>("/departments", { params: { per_page: 200 } });
      return Array.isArray(res.data) ? res.data : [];
    },
  });
  const { data: clinics = [] } = useQuery({
    queryKey: ["/clinics", "register"],
    queryFn: async () => {
      const res = await apiGet<NamedRow[]>("/clinics", { params: { per_page: 200 } });
      return Array.isArray(res.data) ? res.data : [];
    },
  });
  const clinicOptions = clinics.filter((row) => !form.department_id || String(row.department_id ?? "") === form.department_id);
  const { data: doctors = [] } = useQuery({
    queryKey: ["/doctors", "register", form.department_id],
    queryFn: async () => {
      const res = await apiGet<NamedRow[]>("/doctors", {
        params: form.department_id ? { per_page: 200, department_id: form.department_id } : { per_page: 200 },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  function set<K extends keyof typeof form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (existing[0]) {
      toast.error(t("patients.existingBody"));
      router.push(`/${locale}/dashboard/patients/${existing[0].id}`);
      return;
    }
    const clinic = clinics.find((row) => String(row.id) === form.clinic_id);
    const departmentId = num(form.department_id) ?? clinic?.department_id;
    const clinicId = num(form.clinic_id);
    const doctorId = num(form.doctor_id);
    if (!departmentId && !clinicId) {
      toast.error(t("patients.placeRequired"));
      return;
    }
    if (!doctorId) {
      toast.error(t("patients.doctorRequired"));
      return;
    }
    setSaving(true);
    try {
      const res = await create.mutateAsync({
        first_name: form.first_name,
        father_name: form.father_name,
        last_name: form.last_name,
        mother_name: form.mother_name,
        gender: form.gender as "male" | "female",
        date_of_birth: form.date_of_birth,
        national_id: form.national_id,
        phone: form.phone,
        city: form.city,
        blood_type: form.blood_type,
        address: form.address,
        notes: form.notes,
      });
      const patientId = res.data.id;
      try {
        await journeyApi.openVisit({
          patient_id: patientId,
          type: form.visit_type,
          department_id: departmentId,
          clinic_id: clinicId,
          doctor_id: doctorId,
        });
        toast.success("المريض بانتظار المعاينة");
      } catch (error) {
        toast.error(getErrorMessage(error));
      }
      router.push(`/${locale}/dashboard/patients/${patientId}`);
    } catch (error) {
      const existingId = existingPatientId(error);
      if (existingId) {
        toast.error(getErrorMessage(error));
        router.push(`/${locale}/dashboard/patients/${existingId}`);
        return;
      }
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("patients.new")}>
        <Button onClick={() => router.back()}>{t("app.back")}</Button>
        <Button variant="primary" onClick={onSubmit as unknown as () => void} disabled={saving || existing.length > 0}>
          {t("app.save")}
        </Button>
      </PageToolbar>

      <form onSubmit={onSubmit} className="p-2 max-w-4xl">
        {existing.length > 0 ? (
          <div className="mb-2">
            <Panel title={t("patients.existingTitle")}>
              <p className="mb-2 text-[12px]">{t("patients.existingBody")}</p>
              <div className="space-y-1">
                {existing.map((patient) => (
                  <div key={patient.id} className="flex items-center justify-between gap-2">
                    <span className="text-[12px]">
                      {patient.full_name} — {dailyNo(patient.mrn)}
                    </span>
                    <Button
                      variant="primary"
                      onClick={() => router.push(`/${locale}/dashboard/patients/${patient.id}`)}
                    >
                      {t("patients.openExisting")}
                    </Button>
                  </div>
                ))}
              </div>
            </Panel>
          </div>
        ) : null}
        <Panel title="البيانات الأساسية">
          <div className="his-field-grid">
            <Field label={t("patients.firstName")}>
              <Input required value={form.first_name} onChange={(e) => set("first_name", e.target.value)} />
            </Field>
            <Field label={t("patients.fatherName")}>
              <Input value={form.father_name} onChange={(e) => set("father_name", e.target.value)} />
            </Field>
            <Field label={t("patients.lastName")}>
              <Input required value={form.last_name} onChange={(e) => set("last_name", e.target.value)} />
            </Field>
            <Field label={t("patients.motherName")}>
              <Input value={form.mother_name} onChange={(e) => set("mother_name", e.target.value)} />
            </Field>
            <Field label={t("patients.gender")}>
              <Select value={form.gender} onChange={(e) => set("gender", e.target.value)}>
                <option value="male">{t("patients.male")}</option>
                <option value="female">{t("patients.female")}</option>
              </Select>
            </Field>
            <Field label={t("patients.dob")}>
              <Input type="date" value={form.date_of_birth} onChange={(e) => set("date_of_birth", e.target.value)} />
            </Field>
            <Field label={t("patients.nationalId")}>
              <Input value={form.national_id} onChange={(e) => set("national_id", e.target.value)} />
            </Field>
            <Field label={t("patients.phone")}>
              <Input value={form.phone} onChange={(e) => set("phone", e.target.value)} />
            </Field>
            <Field label={t("patients.city")}>
              <Input value={form.city} onChange={(e) => set("city", e.target.value)} />
            </Field>
            <Field label={t("patients.bloodType")}>
              <Select value={form.blood_type} onChange={(e) => set("blood_type", e.target.value)}>
                <option value="">—</option>
                {["A+","A-","B+","B-","AB+","AB-","O+","O-"].map((b) => (
                  <option key={b} value={b}>{b}</option>
                ))}
              </Select>
            </Field>
            <Field label={t("patients.address")}>
              <Input value={form.address} onChange={(e) => set("address", e.target.value)} />
            </Field>
            <Field label={t("app.notes")}>
              <Input value={form.notes} onChange={(e) => set("notes", e.target.value)} />
            </Field>
            <Field label={t("encounters.type")}>
              <Select value={form.visit_type} onChange={(e) => set("visit_type", e.target.value)}>
                {VISIT_TYPES.map((type) => (
                  <option key={type} value={type}>
                    {arabicLabel(type)}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("workspace.department")}>
              <Select
                value={form.department_id}
                onChange={(e) =>
                  setForm((current) => ({ ...current, department_id: e.target.value, clinic_id: "", doctor_id: "" }))
                }
              >
                <option value="">{t("workspace.department")}</option>
                {departments.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("appointments.clinic")}>
              <Select value={form.clinic_id} onChange={(e) => set("clinic_id", e.target.value)}>
                <option value="">{t("appointments.clinic")}</option>
                {clinicOptions.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("appointments.doctor")}>
              <Select required value={form.doctor_id} onChange={(e) => set("doctor_id", e.target.value)}>
                <option value="">{t("appointments.doctor")}</option>
                {doctors.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.name ?? row.display_name}
                  </option>
                ))}
              </Select>
            </Field>
          </div>
        </Panel>
      </form>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="his-field" style={{ gridTemplateColumns: "100px 1fr" }}>
      <label>{label}</label>
      <div>{children}</div>
    </div>
  );
}

function existingPatientId(error: unknown): number | null {
  if (!axios.isAxiosError(error)) return null;
  const id = (error.response?.data as { context?: { patient_id?: number } } | undefined)?.context
    ?.patient_id;
  return typeof id === "number" ? id : null;
}

function num(value?: string | number | null) {
  if (value === undefined || value === null || value === "") return undefined;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : undefined;
}
