"use client";

import { FormEvent, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Panel } from "@/components/ui/Panel";
import { Select } from "@/components/ui/Select";
import { getErrorMessage } from "@/lib/api/client";
import { useCatalogs } from "@/modules/patients/hooks/useJourney";
import type { NamedRow } from "@/modules/patients/api/journey.api";
import {
  bookingSettingsApi,
  type BookingClinicRule,
  type BookingSettings,
} from "../api/bookingSettings.api";

const emptyRule = (): BookingClinicRule => ({
  clinic_id: 0,
  capacity: 10,
  doctor_ids: [],
  times: [],
});

export function BookingLinkManager() {
  const t = useTranslations();
  const { data: catalogs } = useCatalogs();
  const [settings, setSettings] = useState<BookingSettings | null>(null);
  const [draft, setDraft] = useState({
    open: false,
    opens_at: "",
    closes_at: "",
    clinics: [] as BookingClinicRule[],
  });
  const [timeDrafts, setTimeDrafts] = useState<Record<number, string>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    let active = true;
    bookingSettingsApi
      .get()
      .then((data) => {
        if (!active) return;
        setSettings(data);
        setDraft({
          open: data.open,
          opens_at: data.opens_at ?? "",
          closes_at: data.closes_at ?? "",
          clinics: data.clinics ?? [],
        });
      })
      .catch((error) => toast.error(getErrorMessage(error)))
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, []);

  function updateClinic(index: number, patch: Partial<BookingClinicRule>) {
    setDraft((current) => ({
      ...current,
      clinics: current.clinics.map((clinic, clinicIndex) =>
        clinicIndex === index ? { ...clinic, ...patch } : clinic,
      ),
    }));
  }

  function addTime(index: number) {
    const time = timeDrafts[index];
    if (!time) return;
    const current = draft.clinics[index];
    updateClinic(index, {
      times: current.times.includes(time) ? current.times : [...current.times, time].sort(),
    });
    setTimeDrafts((value) => ({ ...value, [index]: "" }));
  }

  function removeClinic(index: number) {
    setDraft((current) => ({
      ...current,
      clinics: current.clinics.filter((_, clinicIndex) => clinicIndex !== index),
    }));
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    try {
      const saved = await bookingSettingsApi.save({
        open: draft.open,
        opens_at: draft.opens_at || null,
        closes_at: draft.closes_at || null,
        clinics: draft.clinics
          .filter((clinic) => clinic.clinic_id > 0)
          .map((clinic) => ({
            clinic_id: Number(clinic.clinic_id),
            capacity: Number(clinic.capacity),
            doctor_ids: clinic.doctor_ids.map(Number),
            times: clinic.times,
          })),
      });
      setSettings(saved);
      toast.success(t("workspace.saved"));
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <div className="flex h-full flex-col">
        <PageToolbar title={t("bookingManage.title")} />
        <p className="m-0 p-3 text-[12px]">{t("app.loading")}</p>
      </div>
    );
  }

  return (
    <form onSubmit={save} className="flex h-full flex-col">
      <PageToolbar title={t("bookingManage.title")}>
        <Button type="submit" variant="primary" disabled={saving}>
          {saving ? t("app.loading") : t("app.save")}
        </Button>
      </PageToolbar>
      <div className="flex-1 overflow-auto p-3">
        <div className="mx-auto flex max-w-4xl flex-col gap-3">
          <Panel title={t("bookingManage.status")}>
            <div className="flex flex-wrap items-center gap-2">
              <Button
                type="button"
                variant={draft.open ? "success" : "default"}
                onClick={() => setDraft((current) => ({ ...current, open: true }))}
              >
                {t("bookingManage.open")}
              </Button>
              <Button
                type="button"
                variant={draft.open ? "default" : "danger"}
                onClick={() => setDraft((current) => ({ ...current, open: false }))}
              >
                {t("bookingManage.closed")}
              </Button>
              {settings ? (
                <span className="text-[12px] text-[var(--his-muted)]">
                  {settings.accepting ? t("bookingManage.accepting") : t("bookingManage.notAccepting")}
                </span>
              ) : null}
            </div>
            <p className="m-0 mt-2 text-[12px] text-[var(--his-muted)]">{t("bookingManage.hint")}</p>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
              <label>
                <span className="mb-1 block text-[12px]">{t("bookingManage.opensAt")}</span>
                <Input
                  type="time"
                  value={draft.opens_at}
                  onChange={(event) => setDraft((current) => ({ ...current, opens_at: event.target.value }))}
                />
              </label>
              <label>
                <span className="mb-1 block text-[12px]">{t("bookingManage.closesAt")}</span>
                <Input
                  type="time"
                  value={draft.closes_at}
                  onChange={(event) => setDraft((current) => ({ ...current, closes_at: event.target.value }))}
                />
              </label>
            </div>
          </Panel>

          <Panel
            title={t("bookingManage.clinics")}
            actions={
              <Button
                type="button"
                onClick={() =>
                  setDraft((current) => ({ ...current, clinics: [...current.clinics, emptyRule()] }))
                }
              >
                {t("bookingManage.addClinic")}
              </Button>
            }
          >
            {draft.clinics.length === 0 ? (
              <p className="m-0 text-[12px] text-[var(--his-muted)]">{t("app.noData")}</p>
            ) : (
              <div className="flex flex-col gap-3">
                {draft.clinics.map((clinic, index) => (
                  <ClinicCard
                    key={`${clinic.clinic_id}-${index}`}
                    clinic={clinic}
                    clinics={catalogs?.clinics ?? []}
                    doctors={catalogs?.doctors ?? []}
                    timeDraft={timeDrafts[index] ?? ""}
                    onTimeDraft={(value) => setTimeDrafts((current) => ({ ...current, [index]: value }))}
                    onChange={(patch) => updateClinic(index, patch)}
                    onAddTime={() => addTime(index)}
                    onRemove={() => removeClinic(index)}
                  />
                ))}
              </div>
            )}
          </Panel>
        </div>
      </div>
    </form>
  );
}

function ClinicCard({
  clinic,
  clinics,
  doctors,
  timeDraft,
  onTimeDraft,
  onChange,
  onAddTime,
  onRemove,
}: {
  clinic: BookingClinicRule;
  clinics: NamedRow[];
  doctors: NamedRow[];
  timeDraft: string;
  onTimeDraft: (value: string) => void;
  onChange: (patch: Partial<BookingClinicRule>) => void;
  onAddTime: () => void;
  onRemove: () => void;
}) {
  const t = useTranslations();

  return (
    <section className="border border-[var(--his-border)] bg-[var(--his-panel-alt)] p-2">
      <div className="grid items-end gap-2 md:grid-cols-3">
        <label className="md:col-span-1">
          <span className="mb-1 block text-[12px]">{t("appointments.clinic")}</span>
          <Select
            required
            value={clinic.clinic_id || ""}
            onChange={(event) => onChange({ clinic_id: Number(event.target.value) })}
          >
            <option value="">{t("booking.choose")}</option>
            {clinics.map((row) => (
              <option key={row.id} value={row.id}>
                {row.name}
              </option>
            ))}
          </Select>
        </label>
        <label>
          <span className="mb-1 block text-[12px]">{t("bookingManage.capacity")}</span>
          <Input
            required
            type="number"
            min={1}
            max={500}
            value={clinic.capacity}
            onChange={(event) => onChange({ capacity: Number(event.target.value) })}
          />
        </label>
        <Button type="button" variant="danger" onClick={onRemove}>
          {t("app.delete")}
        </Button>
      </div>
      <p className="m-0 mt-1 text-[11px] text-[var(--his-muted)]">{t("bookingManage.capacityHint")}</p>
      <div className="mt-3 grid gap-3 lg:grid-cols-2">
        <div>
          <span className="mb-1 block text-[12px] font-semibold">{t("bookingManage.doctors")}</span>
          <div className="grid max-h-36 gap-1 overflow-auto border border-[var(--his-border)] bg-white p-2 sm:grid-cols-2">
            {doctors.map((doctor) => {
              const selected = clinic.doctor_ids.includes(doctor.id);
              return (
                <label
                  key={doctor.id}
                  className="flex items-center gap-2 px-1 py-0.5 text-[12px]"
                  style={{ background: selected ? "var(--his-accent-soft)" : "transparent" }}
                >
                  <input
                    type="checkbox"
                    checked={selected}
                    onChange={(event) => {
                      const doctorIds = event.target.checked
                        ? [...clinic.doctor_ids, doctor.id]
                        : clinic.doctor_ids.filter((id) => id !== doctor.id);
                      onChange({ doctor_ids: doctorIds });
                    }}
                  />
                  <span>{doctor.name}</span>
                </label>
              );
            })}
          </div>
        </div>
        <div>
          <span className="mb-1 block text-[12px] font-semibold">{t("bookingManage.times")}</span>
          <div className="flex flex-wrap items-center gap-1">
            <div className="w-36">
              <Input type="time" value={timeDraft} onChange={(event) => onTimeDraft(event.target.value)} />
            </div>
            <Button type="button" onClick={onAddTime}>
              {t("app.add")}
            </Button>
          </div>
          <div className="mt-2 flex flex-wrap gap-1">
            {clinic.times.map((time) => (
              <button
                key={time}
                type="button"
                className="his-btn"
                onClick={() => onChange({ times: clinic.times.filter((item) => item !== time) })}
              >
                {time}
              </button>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
