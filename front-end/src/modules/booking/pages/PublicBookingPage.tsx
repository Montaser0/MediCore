"use client";

import { FormEvent, useEffect, useState, type ReactNode } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { env } from "@/config/environment";
import { getErrorMessage } from "@/lib/api/client";
import { dailyNo } from "@/lib/arabicText";
import { formatDate } from "@/lib/utils";
import {
  publicBookingApi,
  type PublicAvailability,
  type PublicBookingResult,
} from "../api/booking.api";

const emptyForm = {
  first_name: "",
  father_name: "",
  last_name: "",
  gender: "",
  date_of_birth: "",
  phone: "",
  national_id: "",
  clinic_id: "",
  doctor_id: "",
  slot: "",
  reason: "",
};

function todayInput() {
  const now = new Date();
  const pad = (value: number) => String(value).padStart(2, "0");
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

export function PublicBookingPage() {
  const t = useTranslations();
  const [visitDate, setVisitDate] = useState(todayInput);
  const [availability, setAvailability] = useState<PublicAvailability | null>(null);
  const [clinicsError, setClinicsError] = useState("");
  const [loadingClinics, setLoadingClinics] = useState(true);
  const [form, setForm] = useState(emptyForm);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [result, setResult] = useState<PublicBookingResult | null>(null);

  useEffect(() => {
    let active = true;
    setLoadingClinics(true);
    setClinicsError("");
    publicBookingApi
      .availability(visitDate)
      .then((data) => {
        if (active) setAvailability(data);
      })
      .catch((err) => {
        if (active) setClinicsError(getErrorMessage(err, t("booking.clinicsFailed")));
      })
      .finally(() => {
        if (active) setLoadingClinics(false);
      });
    return () => {
      active = false;
    };
  }, [visitDate, t]);

  function setField(key: keyof typeof emptyForm, value: string) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  const clinics = availability?.clinics ?? [];
  const selectedClinic = clinics.find((clinic) => String(clinic.id) === form.clinic_id);
  const selectedSlot = selectedClinic?.times.find((slot) => slot.time === form.slot);
  const doctors = (selectedClinic?.doctors ?? []).filter((doctor) =>
    selectedSlot ? selectedSlot.doctor_ids.includes(doctor.id) : false,
  );
  const linkClosed = availability !== null && !availability.open;
  const closedNotice =
    availability?.opens_at && availability?.closes_at
      ? t("booking.closedWindow", {
          from: availability.opens_at,
          to: availability.closes_at,
        })
      : availability?.message || t("booking.closed");

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError("");
    setSubmitting(true);
    try {
      const booked = await publicBookingApi.book({
        first_name: form.first_name.trim(),
        father_name: form.father_name.trim(),
        last_name: form.last_name.trim(),
        gender: form.gender,
        date_of_birth: form.date_of_birth,
        phone: form.phone.trim(),
        national_id: form.national_id.trim() || undefined,
        clinic_id: Number(form.clinic_id),
        doctor_id: Number(form.doctor_id),
        scheduled_at: `${visitDate} ${form.slot}:00`,
        reason: form.reason.trim() || undefined,
      });
      setResult(booked);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div
      className="flex min-h-screen flex-col items-center justify-center gap-5 px-4 py-8"
      style={{
        background: "linear-gradient(135deg, #143a68 0%, #1b4f8c 45%, #2a6fbb 100%)",
      }}
    >
      <img src="/logo.png?v=3" alt={env.appName} className="h-24 w-auto" />
      <div className="w-full max-w-2xl overflow-hidden border border-[#8fa0b0] bg-[#f3f6f9] shadow-lg">
        <div
          className="px-4 py-2 text-center text-[13px] font-semibold text-white"
          style={{ background: "var(--his-header)" }}
        >
          {t("booking.title")}
        </div>

        {loadingClinics && availability === null ? (
          <p className="m-0 px-6 py-10 text-center text-[13px]">{clinicsError || t("app.loading")}</p>
        ) : linkClosed ? (
          <p className="m-0 px-6 py-10 text-center text-[16px] font-semibold leading-8">
            {closedNotice}
          </p>
        ) : result ? (
          <div className="space-y-3 p-5 text-[13px]">
            <p className="m-0 text-[15px] font-semibold">{t("booking.successTitle")}</p>
            <p className="m-0 text-[var(--his-muted)]">{t("booking.successBody")}</p>
            <dl className="grid gap-2 border border-[#d5dee8] bg-white p-3 sm:grid-cols-2">
              <div>
                <dt className="text-[11px] text-[var(--his-muted)]">{t("booking.number")}</dt>
                <dd className="m-0 font-semibold">{dailyNo(result.appointment_number) || "—"}</dd>
              </div>
              <div>
                <dt className="text-[11px] text-[var(--his-muted)]">{t("patients.fullName")}</dt>
                <dd className="m-0">{result.patient_name || "—"}</dd>
              </div>
              <div>
                <dt className="text-[11px] text-[var(--his-muted)]">{t("appointments.clinic")}</dt>
                <dd className="m-0">{result.clinic || "—"}</dd>
              </div>
              <div>
                <dt className="text-[11px] text-[var(--his-muted)]">{t("appointments.doctor")}</dt>
                <dd className="m-0">{result.doctor || "—"}</dd>
              </div>
              <div>
                <dt className="text-[11px] text-[var(--his-muted)]">{t("appointments.scheduledAt")}</dt>
                <dd className="m-0">{formatDate(result.scheduled_at, true)}</dd>
              </div>
              <div>
                <dt className="text-[11px] text-[var(--his-muted)]">{t("patients.mrn")}</dt>
                <dd className="m-0">{dailyNo(result.mrn) || "—"}</dd>
              </div>
            </dl>
            <Button
              variant="primary"
              onClick={() => {
                setResult(null);
                setForm(emptyForm);
              }}
            >
              {t("booking.another")}
            </Button>
          </div>
        ) : (
          <form onSubmit={onSubmit} className="grid gap-3 p-5 sm:grid-cols-2">
            <Field label={t("patients.firstName")}>
              <Input
                required
                value={form.first_name}
                onChange={(e) => setField("first_name", e.target.value)}
                autoComplete="given-name"
              />
            </Field>
            <Field label={t("patients.fatherName")}>
              <Input
                required
                value={form.father_name}
                onChange={(e) => setField("father_name", e.target.value)}
              />
            </Field>
            <Field label={t("patients.lastName")}>
              <Input
                required
                value={form.last_name}
                onChange={(e) => setField("last_name", e.target.value)}
                autoComplete="family-name"
              />
            </Field>
            <Field label={t("patients.phone")}>
              <Input
                required
                type="tel"
                dir="ltr"
                value={form.phone}
                onChange={(e) => setField("phone", e.target.value)}
                autoComplete="tel"
              />
            </Field>
            <Field label={t("patients.gender")}>
              <Select
                required
                value={form.gender}
                onChange={(e) => setField("gender", e.target.value)}
              >
                <option value="">{t("booking.choose")}</option>
                <option value="male">{t("patients.male")}</option>
                <option value="female">{t("patients.female")}</option>
              </Select>
            </Field>
            <Field label={t("patients.dob")}>
              <Input
                required
                type="date"
                value={form.date_of_birth}
                onChange={(e) => setField("date_of_birth", e.target.value)}
              />
            </Field>
            <Field label={t("patients.nationalId")}>
              <Input
                dir="ltr"
                value={form.national_id}
                onChange={(e) => setField("national_id", e.target.value)}
              />
            </Field>
            <Field label={t("booking.visitDate")}>
              <Input
                required
                type="date"
                min={todayInput()}
                value={visitDate}
                onChange={(e) => {
                  setVisitDate(e.target.value);
                  setField("clinic_id", "");
                  setField("slot", "");
                  setField("doctor_id", "");
                }}
              />
            </Field>
                <Field label={t("appointments.clinic")} className="sm:col-span-2">
                  <Select
                    required
                    value={form.clinic_id}
                    disabled={loadingClinics || clinics.length === 0}
                    onChange={(e) => {
                      setField("clinic_id", e.target.value);
                      setField("slot", "");
                      setField("doctor_id", "");
                    }}
                  >
                    <option value="">
                      {loadingClinics ? t("app.loading") : t("booking.choose")}
                    </option>
                    {clinics.map((clinic) => (
                      <option key={clinic.id} value={clinic.id} disabled={clinic.full}>
                        {clinic.name}
                        {clinic.department ? ` — ${clinic.department}` : ""}
                        {clinic.full
                          ? ` — ${t("booking.full")}`
                          : ` — ${t("booking.remaining")} ${clinic.remaining}`}
                      </option>
                    ))}
                  </Select>
                </Field>
                <Field label={t("booking.slot")}>
                  <Select
                    required
                    value={form.slot}
                    disabled={!selectedClinic || selectedClinic.full}
                    onChange={(e) => {
                      setField("slot", e.target.value);
                      setField("doctor_id", "");
                    }}
                  >
                    <option value="">{t("booking.choose")}</option>
                    {(selectedClinic?.times ?? []).map((slot) => (
                      <option key={slot.time} value={slot.time}>
                        {slot.time}
                      </option>
                    ))}
                  </Select>
                </Field>
                <Field label={t("appointments.doctor")}>
                  <Select
                    required
                    value={form.doctor_id}
                    disabled={!selectedSlot}
                    onChange={(e) => setField("doctor_id", e.target.value)}
                  >
                    <option value="">{t("booking.choose")}</option>
                    {doctors.map((doctor) => (
                      <option key={doctor.id} value={doctor.id}>
                        {doctor.name}
                      </option>
                    ))}
                  </Select>
                </Field>
            <Field label={t("booking.reason")} className="sm:col-span-2">
              <textarea
                className="his-input min-h-20"
                value={form.reason}
                placeholder={t("booking.reasonPlaceholder")}
                onChange={(e) => setField("reason", e.target.value)}
                maxLength={500}
              />
            </Field>
            {clinicsError ? (
              <p className="m-0 text-[12px] text-red-700 sm:col-span-2">{clinicsError}</p>
            ) : null}
            {availability?.message && availability.open ? (
              <p className="m-0 text-[12px] text-red-700 sm:col-span-2">{availability.message}</p>
            ) : null}
            {!loadingClinics && !clinicsError && availability?.open && clinics.length === 0 ? (
              <p className="m-0 text-[12px] text-red-700 sm:col-span-2">{t("booking.clinicsEmpty")}</p>
            ) : null}
            {error ? <p className="m-0 text-[12px] text-red-700 sm:col-span-2">{error}</p> : null}
            <div className="sm:col-span-2">
              <Button
                type="submit"
                variant="primary"
                className="h-9! w-full"
                disabled={submitting || clinics.length === 0 || clinics.every((clinic) => clinic.full)}
              >
                {submitting ? t("app.loading") : t("booking.submit")}
              </Button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}

function Field({
  label,
  children,
  className,
}: {
  label: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <label className={className}>
      <span className="mb-1 block text-[12px]">{label}</span>
      {children}
    </label>
  );
}
