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
import { latinDigits, formatMoney } from "@/lib/utils";
import { env } from "@/config/environment";
import { MedicationPrescriptionForm, type PrescriptionLine } from "../components/MedicationPrescriptionForm";
import { printPrescription } from "../print/printPrescription";
import { printOrderSlip } from "../print/printOrderSlip";
import { printLabBarcode } from "@/modules/laboratory/print/printLabBarcode";

type RegisterDepartment = NamedRow & {
  type?: string;
  clinics?: NamedRow[];
};

type ServiceLine = { id: number; name: string; price: number };

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
  const labPrint = useRef<"prescription" | "barcode" | null>(null);
  const rxDraft = useRef<{ fulfillment: "hospital" | "external"; items: PrescriptionLine[] }>({
    fulfillment: "hospital",
    items: [],
  });
  const [serviceLines, setServiceLines] = useState<ServiceLine[]>([]);
  const [serviceQuery, setServiceQuery] = useState("");
  const [withInjection, setWithInjection] = useState(false);

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
  const pharmacy = selectedDepartment?.type === "pharmacy";
  const serviceKind =
    selectedDepartment?.type === "laboratory" || selectedDepartment?.type === "radiology"
      ? selectedDepartment.type
      : null;
  const directDesk = pharmacy || serviceKind !== null;
  const clinicOptions = form.department_id
    ? clinics.filter((row) => String(row.department_id ?? "") === form.department_id)
    : [];
  const { data: doctors = [] } = useQuery({
    queryKey: ["/doctors", "register", form.clinic_id],
    enabled: Boolean(form.clinic_id),
    queryFn: async () => {
      const res = await apiGet<NamedRow[]>("/doctors", {
        params: { per_page: 200, clinic_id: form.clinic_id },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });
  const { data: serviceCatalog = [] } = useQuery({
    queryKey: ["/register-services", serviceKind],
    enabled: serviceKind !== null,
    queryFn: async () => {
      const path = serviceKind === "laboratory" ? "/laboratory/tests" : "/radiology/services";
      const res = await apiGet<NamedRow[]>(path, { params: { per_page: 200 } });
      return Array.isArray(res.data) ? res.data : [];
    },
  });
  const serviceTerm = serviceQuery.trim().toLowerCase();
  const serviceHits =
    serviceTerm.length < 1
      ? []
      : serviceCatalog
          .filter((row) => {
            const name = (row.name ?? "").toLowerCase();
            const code = (row.code ?? "").toLowerCase();
            return name.includes(serviceTerm) || code.includes(serviceTerm);
          })
          .slice(0, 12);

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

  function draftItems() {
    return rxDraft.current.items;
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    const labChoice = labPrint.current;
    labPrint.current = null;
    if (!nationalId) {
      toast.error(t("patients.nationalIdRequired"));
      nationalIdRef.current?.focus();
      return;
    }
    if (existing[0] && !directDesk) {
      toast.error(t("patients.existingBody"));
      router.push(`/${locale}/dashboard/patients/${existing[0].id}`);
      return;
    }
    const departmentId = num(form.department_id);
    const clinicId = num(form.clinic_id);
    const doctorId = num(form.doctor_id);
    if (!departmentId || (!directDesk && !clinicId)) {
      toast.error(t("patients.placeRequired"));
      return;
    }
    if (!directDesk && !doctorId) {
      toast.error(t("patients.doctorRequired"));
      return;
    }
    const rxItems = pharmacy ? draftItems() : [];
    if (pharmacy && rxItems.length === 0) {
      toast.error(t("workspace.rxNeedDrug"));
      return;
    }
    if (serviceKind && serviceLines.length === 0) {
      toast.error(serviceKind === "laboratory" ? t("laboratory.pickTests") : t("radiology.pickStudies"));
      return;
    }
    const shouldPrint = directDesk && (serviceKind !== "laboratory" || labChoice !== null);
    const preview = shouldPrint ? window.open("", "_blank", "width=820,height=720") : null;
    if (shouldPrint && !preview) {
      toast.error(labChoice === "barcode" ? t("laboratory.popupBlocked") : "اسمح بالنوافذ المنبثقة لطباعة الوصفة");
      return;
    }
    setSaving(true);
    try {
      let patientId = existing[0]?.id;
      let patientName = existing[0]?.full_name || [form.first_name, form.father_name, form.last_name].filter(Boolean).join(" ");
      let patientMrn = existing[0]?.mrn;
      let patientAge = form.age || (existing[0]?.age == null ? undefined : String(existing[0].age));
      if (!patientId) {
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
        patientId = res.data.id;
        patientName = res.data.full_name || patientName;
        patientMrn = res.data.mrn || patientMrn;
        if (res.data.age != null) patientAge = String(res.data.age);
      }
      try {
        const created = await journeyApi.openVisit({
          patient_id: patientId,
          type: selectedDepartment?.type === "emergency" ? "emergency" : "outpatient",
          department_id: departmentId,
          clinic_id: directDesk ? undefined : clinicId,
          doctor_id: directDesk ? undefined : doctorId,
          ...(pharmacy
            ? {
                reason: "صرف أدوية فقط",
                items: rxItems.map((line) => ({
                  ...(line.catalogue_item_id ? { catalogue_item_id: line.catalogue_item_id } : { name: line.name }),
                  dose: line.dose || undefined,
                  frequency: line.frequency || undefined,
                  duration: line.duration || undefined,
                  quantity: line.quantity,
                })),
              }
            : {}),
          ...(serviceKind
            ? {
                reason: serviceKind === "laboratory" ? "تحاليل مخبرية فقط" : "صور أشعة فقط",
                items: serviceLines.map((line) => ({
                  service_type: serviceKind === "laboratory" ? "lab_test" : "radiology_service",
                  service_id: line.id,
                  name: line.name,
                  ...(serviceKind === "radiology" ? { with_injection: withInjection } : {}),
                })),
              }
            : {}),
        });
        if (preview && pharmacy) {
          printPrescription(
            {
              prescriptionNumber: (created.data as { prescription_number?: string }).prescription_number,
              hospitalName: env.appName,
              fulfillment: "hospital",
              patientName,
              mrn: patientMrn ? dailyNo(patientMrn) : undefined,
              age: patientAge,
              gender: form.gender === "male" ? "ذكر" : form.gender === "female" ? "أنثى" : undefined,
              lines: rxItems.map((line) => ({
                name: line.name,
                dose: line.dose,
                frequency: line.frequency,
                duration: line.duration,
                quantity: String(line.quantity),
              })),
            },
            preview,
          );
        }
        const slip = created.data as {
          order_number?: string;
          barcode?: string;
          clinic_name?: string | null;
          doctor_name?: string | null;
        };
        if (preview && serviceKind === "laboratory" && labChoice === "barcode" && slip.barcode) {
          printLabBarcode(
            {
              barcode: slip.barcode,
              clinic: slip.clinic_name,
              patientName,
            },
            preview,
          );
        } else if (preview && serviceKind === "laboratory" && labChoice === "prescription") {
          printOrderSlip(
            {
              kind: "laboratory",
              orderNumber: slip.order_number,
              hospitalName: env.appName,
              patientName,
              mrn: patientMrn ? dailyNo(patientMrn) : undefined,
              age: patientAge,
              gender: form.gender === "male" ? "ذكر" : form.gender === "female" ? "أنثى" : undefined,
              phone: form.phone || existing[0]?.phone || undefined,
              doctor: slip.doctor_name ?? undefined,
              clinic: slip.clinic_name ?? undefined,
              lines: serviceLines.map((line) => ({ name: line.name, price: line.price })),
            },
            preview,
          );
        } else if (preview && serviceKind === "radiology") {
          printOrderSlip(
            {
              kind: serviceKind,
              orderNumber: slip.order_number,
              hospitalName: env.appName,
              patientName,
              mrn: patientMrn ? dailyNo(patientMrn) : undefined,
              age: patientAge,
              gender: form.gender === "male" ? "ذكر" : form.gender === "female" ? "أنثى" : undefined,
              phone: form.phone || existing[0]?.phone || undefined,
              lines: serviceLines.map((line) => ({ name: line.name, price: line.price })),
            },
            preview,
          );
        } else if (preview && serviceKind === "laboratory") {
          preview.close();
        }
        toast.success(
          pharmacy
            ? t("patients.pharmacySaved")
            : serviceKind === "laboratory"
              ? labChoice === "barcode" && slip.barcode
                ? t("laboratory.barcodeSaved", { barcode: slip.barcode })
                : t("patients.labSaved")
              : serviceKind === "radiology"
                ? t("patients.radSaved")
                : "المريض بانتظار المعاينة",
        );
      } catch (error) {
        preview?.close();
        toast.error(getErrorMessage(error));
      }
      router.push(`/${locale}/dashboard/patients/${patientId}`);
    } catch (error) {
      preview?.close();
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
        <Button variant="primary" onClick={onSubmit as unknown as () => void} disabled={saving || (existing.length > 0 && !directDesk)}>
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
                  required
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
                onChange={(e) => {
                  rxDraft.current = { fulfillment: "hospital", items: [] };
                  setServiceLines([]);
                  setServiceQuery("");
                  setWithInjection(false);
                  setForm((current) => ({ ...current, department_id: e.target.value, clinic_id: "", doctor_id: "" }));
                }}
              >
                <option value="">{t("workspace.department")}</option>
                {departmentOptions.map((row) => (
                  <option key={row.id} value={row.id}>
                    {row.name}
                  </option>
                ))}
              </Select>
            </Field>
            {pharmacy ? (
              <div className="col-span-full border border-[var(--his-border)] bg-white">
                <div className="his-panel-header">{t("patients.pharmacyRx")}</div>
                <MedicationPrescriptionForm
                  embedded
                  fulfillmentLocked="hospital"
                  pending={saving}
                  onDraftChange={(draft) => {
                    rxDraft.current = draft;
                  }}
                />
              </div>
            ) : serviceKind ? (
              <div className="col-span-full border border-[var(--his-border)] bg-white">
                <div className="his-panel-header">
                  {serviceKind === "laboratory" ? t("laboratory.orderSheet") : t("radiology.orderSheet")}
                </div>
                <div className="space-y-2 p-3">
                  <p className="text-[12px] text-[var(--his-muted)]">
                    {serviceKind === "laboratory" ? t("patients.labHint") : t("patients.radHint")}
                  </p>
                  <div className="relative max-w-md">
                    <Input
                      value={serviceQuery}
                      placeholder={t("patients.serviceSearch")}
                      onChange={(event) => setServiceQuery(event.target.value)}
                    />
                    {serviceHits.length > 0 ? (
                      <div className="absolute z-20 mt-1 max-h-64 w-full overflow-auto border border-[var(--his-border)] bg-white shadow">
                        {serviceHits.map((row) => (
                          <button
                            key={row.id}
                            type="button"
                            className="block w-full border-b border-[var(--his-border)] px-2 py-1 text-start text-[13px] last:border-b-0 hover:bg-[var(--his-surface)]"
                            onClick={() => {
                              setServiceLines((current) =>
                                current.some((line) => line.id === row.id)
                                  ? current
                                  : [...current, { id: row.id, name: row.name ?? "—", price: Number(row.price) || 0 }],
                              );
                              setServiceQuery("");
                            }}
                          >
                            {row.name}
                            {row.price != null ? ` — ${formatMoney(Number(row.price), "0")}` : ""}
                          </button>
                        ))}
                      </div>
                    ) : null}
                  </div>
                  {serviceKind === "radiology" ? (
                    <label className="flex items-center gap-2 text-[13px]">
                      <input
                        type="checkbox"
                        checked={withInjection}
                        onChange={(event) => setWithInjection(event.target.checked)}
                      />
                      {t("radiology.withInjection")}
                    </label>
                  ) : null}
                  {serviceLines.length > 0 ? (
                    <ul className="m-0 space-y-1 border border-[var(--his-border)] p-2 text-[12px]">
                      {serviceLines.map((line) => (
                        <li key={line.id} className="flex items-center justify-between gap-2">
                          <span>
                            {line.name}
                            {line.price ? ` — ${formatMoney(line.price, "0")}` : ""}
                          </span>
                          <Button
                            type="button"
                            variant="danger"
                            onClick={() => setServiceLines((current) => current.filter((item) => item.id !== line.id))}
                          >
                            {t("app.delete")}
                          </Button>
                        </li>
                      ))}
                    </ul>
                  ) : null}
                  <div className="flex justify-end gap-1">
                    {serviceKind === "laboratory" ? (
                      <>
                        <Button
                          type="button"
                          disabled={saving}
                          onClick={(event) => {
                            labPrint.current = "prescription";
                            event.currentTarget.form?.requestSubmit();
                          }}
                        >
                          {t("laboratory.prescription")}
                        </Button>
                        <Button
                          type="button"
                          variant="primary"
                          disabled={saving}
                          onClick={(event) => {
                            labPrint.current = "barcode";
                            event.currentTarget.form?.requestSubmit();
                          }}
                        >
                          {t("laboratory.barcode")}
                        </Button>
                      </>
                    ) : (
                      <Button type="submit" variant="primary" disabled={saving}>
                        {t("patients.deskPrint")}
                      </Button>
                    )}
                  </div>
                </div>
              </div>
            ) : (
              <>
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
                disabled={!form.clinic_id}
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
              </>
            )}
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
  if (row.type === "emergency" || row.type === "pharmacy" || row.type === "laboratory" || row.type === "radiology") return true;
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
