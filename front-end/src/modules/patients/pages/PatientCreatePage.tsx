"use client";

import { FormEvent, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { differenceInYears, format, isValid, parse, subYears } from "date-fns";
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
import { dailyNo } from "@/lib/arabicText";
import { readNationalId } from "@/lib/nationalId";
import { latinDigits } from "@/lib/utils";

type RegisterDepartment = NamedRow & {
  type?: string;
  clinics?: NamedRow[];
};

export function PatientCreatePage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const create = useCreatePatient();
  const [saving, setSaving] = useState(false);
  const [lookupId, setLookupId] = useState("");
  const nationalIdRef = useRef<HTMLInputElement>(null);

  const [form, setForm] = useState({
    first_name: "",
    father_name: "",
    last_name: "",
    mother_name: "",
    gender: "male",
    date_of_birth: "",
    age: "",
    national_id: "",
    phone: "",
    city: "",
    blood_type: "",
    address: "",
    notes: "",
    department_id: "",
    clinic_id: "",
    doctor_id: "",
  });

  const nationalId = form.national_id.trim();
  const lookup = [nationalId, form.phone, `${form.first_name} ${form.last_name}`]
    .map((value) => value.trim())
    .find((value) => value.length >= 3);
  const { data: matches, isFetching: lookingUp, isFetched: lookupDone } = usePatients(
    lookup ? { search: lookup, per_page: 5 } : undefined,
    Boolean(lookup),
  );
  const existing = (matches?.rows ?? []).filter((patient) => {
    if (nationalId && readNationalId(patient.national_id ?? "") === nationalId) return true;
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
      const res = await apiGet<RegisterDepartment[]>("/departments", { params: { per_page: 200 } });
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
  const departmentOptions = departments.filter((row) => isVisitDepartment(row, clinics));
  const selectedDepartment = departmentOptions.find((row) => String(row.id) === form.department_id);
  const clinicOptions = form.department_id
    ? clinics.filter((row) => String(row.department_id ?? "") === form.department_id)
    : [];
  const { data: doctors = [] } = useQuery({
    queryKey: ["/doctors", "register", form.department_id, form.clinic_id],
    enabled: Boolean(form.department_id),
    queryFn: async () => {
      const res = await apiGet<NamedRow[]>("/doctors", {
        params: {
          per_page: 200,
          department_id: form.department_id,
          clinic_id: form.clinic_id || undefined,
        },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  function set<K extends keyof typeof form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  function setDateOfBirth(value: string) {
    if (value && value > format(startOfToday(), "yyyy-MM-dd")) return;
    setForm((current) => ({
      ...current,
      date_of_birth: value,
      age: ageFromDateOfBirth(value),
    }));
  }

  function setAge(raw: string) {
    const digits = latinDigits(raw).replace(/\D/g, "").slice(0, 3);
    if (digits !== "" && Number(digits) > MAX_PATIENT_AGE) return;
    setForm((current) => ({
      ...current,
      age: digits,
      date_of_birth: dateOfBirthFromAge(digits),
    }));
  }

  function searchNationalId(raw: string) {
    const value = readNationalId(raw);
    set("national_id", value);
    setLookupId(value);
  }

  useEffect(() => {
    nationalIdRef.current?.focus();
  }, []);

  useEffect(() => {
    let buffer = "";
    let last = 0;

    function onKeyDown(event: KeyboardEvent) {
      const target = event.target as HTMLElement | null;
      const tag = target?.tagName;
      const typingElsewhere =
        tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || Boolean(target?.isContentEditable);
      if (typingElsewhere) return;

      const now = performance.now();
      if (event.key === "Enter") {
        if (buffer.length >= 3 && now - last < 50) {
          event.preventDefault();
          const value = readNationalId(buffer);
          setForm((current) => ({ ...current, national_id: value }));
          setLookupId(value);
          nationalIdRef.current?.focus();
        }
        buffer = "";
        return;
      }

      if (event.key.length !== 1 || event.ctrlKey || event.altKey || event.metaKey) return;
      if (now - last > 40) buffer = "";
      buffer += event.key;
      last = now;
    }

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, []);

  const nationalMiss =
    lookupId.length >= 3 &&
    lookupId === nationalId &&
    lookup === nationalId &&
    lookupDone &&
    !lookingUp &&
    existing.length === 0;

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (existing[0]) {
      toast.error(t("patients.existingBody"));
      router.push(`/${locale}/dashboard/patients/${existing[0].id}`);
      return;
    }
    const departmentId = num(form.department_id);
    const clinicId = num(form.clinic_id);
    const doctorId = num(form.doctor_id);
    if (!departmentId || !clinicId) {
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
          type: selectedDepartment?.type === "emergency" ? "emergency" : "outpatient",
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
                      {patient.national_id ? ` — ${patient.national_id}` : ""}
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
            <Field label={t("patients.nationalId")}>
              <div className="flex gap-1">
                <Input
                  ref={nationalIdRef}
                  dir="ltr"
                  lang="en"
                  inputMode="numeric"
                  autoComplete="off"
                  spellCheck={false}
                  className="his-latin-num w-36!"
                  placeholder={t("patients.nationalId")}
                  value={form.national_id}
                  onChange={(e) => set("national_id", readNationalId(e.target.value))}
                  onKeyDown={(e) => {
                    if (e.key !== "Enter") return;
                    e.preventDefault();
                    searchNationalId(e.currentTarget.value);
                  }}
                />
                <Button type="button" onClick={() => searchNationalId(nationalIdRef.current?.value ?? form.national_id)}>
                  {t("app.search")}
                </Button>
              </div>
              {nationalMiss ? (
                <p className="mt-1 text-[12px]">{t("patients.nationalIdNotFound")}</p>
              ) : null}
            </Field>
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
              <Input
                type="date"
                max={format(new Date(), "yyyy-MM-dd")}
                value={form.date_of_birth}
                onChange={(e) => setDateOfBirth(e.target.value)}
              />
            </Field>
            <Field label={t("patients.age")}>
              <Input
                type="number"
                min={0}
                max={MAX_PATIENT_AGE}
                step={1}
                inputMode="numeric"
                value={form.age}
                onChange={(e) => setAge(e.target.value)}
              />
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
            <Field label={t("workspace.department")}>
              <Select
                required
                value={form.department_id}
                onChange={(e) =>
                  setForm((current) => ({ ...current, department_id: e.target.value, clinic_id: "", doctor_id: "" }))
                }
              >
                <option value="">{t("workspace.department")}</option>
                {departmentOptions.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("appointments.clinic")}>
              <Select
                required
                value={form.clinic_id}
                disabled={!form.department_id}
                onChange={(e) =>
                  setForm((current) => ({ ...current, clinic_id: e.target.value, doctor_id: "" }))
                }
              >
                <option value="">{t("appointments.clinic")}</option>
                {clinicOptions.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("appointments.doctor")}>
              <Select
                required
                value={form.doctor_id}
                disabled={!form.department_id}
                onChange={(e) => set("doctor_id", e.target.value)}
              >
                <option value="">{t("appointments.doctor")}</option>
                {doctors.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.name || row.display_name || row.id}
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

const MAX_PATIENT_AGE = 150;

function ageFromDateOfBirth(iso: string) {
  if (!iso) return "";
  const birth = parse(iso, "yyyy-MM-dd", new Date());
  if (!isValid(birth)) return "";
  const today = startOfToday();
  birth.setHours(0, 0, 0, 0);
  if (birth > today) return "";
  const age = differenceInYears(today, birth);
  if (age < 0 || age > MAX_PATIENT_AGE) return "";
  return String(age);
}

function dateOfBirthFromAge(ageText: string) {
  if (ageText === "") return "";
  const age = Number(ageText);
  if (!Number.isInteger(age) || age < 0 || age > MAX_PATIENT_AGE) return "";
  return format(subYears(startOfToday(), age), "yyyy-MM-dd");
}

function startOfToday() {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return today;
}

function isVisitDepartment(row: RegisterDepartment, clinics: NamedRow[]) {
  if (row.type === "emergency") return true;
  const hasClinic =
    (row.clinics?.length ?? 0) > 0 ||
    clinics.some((clinic) => String(clinic.department_id ?? "") === String(row.id));
  return (row.type === "medical" || row.type === "surgery") && hasClinic;
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
