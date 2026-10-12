import { apiGet, apiPost } from "@/lib/api/client";
import type { EncounterRow } from "@/modules/encounters/api/encounters.api";

export type NamedRow = {
  id: number;
  name?: string;
  display_name?: string;
  label?: string;
  code?: string;
  category?: string;
  price?: number;
  specialty?: string;
  department_id?: number;
  clinic_id?: number;
  clinic?: { id?: number; name?: string };
  clinics?: Array<{ id?: number; name?: string }>;
  status?: string;
  is_assignable?: boolean;
};

export type Catalogs = {
  departments: NamedRow[];
  clinics: NamedRow[];
  doctors: NamedRow[];
  tests: NamedRow[];
  radiology: NamedRow[];
  medications: NamedRow[];
  beds: NamedRow[];
  operationTypes: NamedRow[];
  services: NamedRow[];
};

export type NoteRow = {
  id: number;
  note_type?: string;
  content?: string;
  created_at?: string;
};

function clean(data: Record<string, unknown>) {
  const out: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(data)) {
    if (value === "" || value === undefined || value === null) continue;
    out[key] = value;
  }
  return out;
}

async function list<T>(path: string, params?: Record<string, unknown>): Promise<T[]> {
  try {
    const res = await apiGet<T[]>(path, { params: { per_page: 100, ...params } });
    return Array.isArray(res.data) ? res.data : [];
  } catch {
    return [];
  }
}

export const journeyApi = {
  catalogs: async (): Promise<Catalogs> => {
    const [departments, clinics, doctors, tests, radiology, medications, beds, operationTypes, services] =
      await Promise.all([
        list<NamedRow>("/departments"),
        list<NamedRow>("/clinics"),
        list<NamedRow>("/doctors"),
        list<NamedRow>("/laboratory/tests"),
        list<NamedRow>("/radiology/services"),
        list<NamedRow>("/pharmacy/medications"),
        list<NamedRow>("/inpatient/beds", { available_only: 1, per_page: 200 }),
        list<NamedRow>("/operations/types"),
        list<NamedRow>("/billing/services"),
      ]);
    return { departments, clinics, doctors, tests, radiology, medications, beds, operationTypes, services };
  },

  encounters: (patientId: number | string) =>
    apiGet<EncounterRow[]>(`/patients/${patientId}/encounters`, { params: { per_page: 50 } }),

  notes: (encounterId: number) => apiGet<NoteRow[]>(`/encounters/${encounterId}/notes`),

  openVisit: (body: Record<string, unknown>) => apiPost<EncounterRow>("/encounters", clean(body)),

  startVisit: (id: number, doctorId?: number) =>
    apiPost<EncounterRow>(`/encounters/${id}/start`, clean({ doctor_id: doctorId })),

  transferVisit: (id: number, body: Record<string, unknown>) =>
    apiPost<EncounterRow>(`/encounters/${id}/transfer`, clean(body)),

  closeVisit: (id: number, body: Record<string, unknown>) =>
    apiPost<EncounterRow>(`/encounters/${id}/close`, clean(body)),

  vitals: (encounterId: number, body: Record<string, unknown>) =>
    apiPost(`/encounters/${encounterId}/vitals`, clean(body)),

  note: (encounterId: number, body: Record<string, unknown>) =>
    apiPost(`/encounters/${encounterId}/notes`, clean(body)),

  diagnosis: (encounterId: number, body: Record<string, unknown>) =>
    apiPost(`/encounters/${encounterId}/diagnoses`, clean(body)),

  diagnosisCodes: async (filter: { doctorId?: number; clinicId?: number }): Promise<NamedRow[]> => {
    const params: Record<string, unknown> = { per_page: 200 };
    if (filter.doctorId) params.doctor_id = filter.doctorId;
    if (filter.clinicId) params.clinic_id = filter.clinicId;
    const rows = await list<{ id: number; code?: string; title?: string; category?: string }>(
      "/diagnosis-codes",
      params,
    );
    return rows.map((row) => ({
      id: row.id,
      code: row.code,
      category: row.category,
      name: [row.code, row.title].filter(Boolean).join(" — "),
    }));
  },

  procedure: (encounterId: number, body: Record<string, unknown>) =>
    apiPost(`/encounters/${encounterId}/procedures`, clean(body)),

  order: (body: Record<string, unknown>) => apiPost("/orders", body),

  prescription: (body: Record<string, unknown>) => apiPost("/pharmacy/prescriptions", body),

  nursingNote: (encounterId: number, body: Record<string, unknown>) =>
    apiPost(`/encounters/${encounterId}/nursing-notes`, clean(body)),

  admit: (body: Record<string, unknown>) => apiPost("/inpatient/admissions", clean(body)),

  dischargeStay: (admissionId: number, body: Record<string, unknown>) =>
    apiPost(`/inpatient/admissions/${admissionId}/discharge`, clean(body)),

  scheduleOperation: (body: Record<string, unknown>) => apiPost("/operations", body),

  charge: (body: Record<string, unknown>) => apiPost("/billing/charges", clean(body)),

  pay: (invoiceId: number, body: Record<string, unknown>) =>
    apiPost(`/billing/invoices/${invoiceId}/payments`, clean(body)),

  installments: (invoiceId: number, body: Record<string, unknown>) =>
    apiPost(`/billing/invoices/${invoiceId}/installments`, clean(body)),

  allergy: (patientId: number | string, body: Record<string, unknown>) =>
    apiPost(`/patients/${patientId}/allergies`, clean(body)),

  condition: (patientId: number | string, body: Record<string, unknown>) =>
    apiPost(`/patients/${patientId}/conditions`, clean(body)),

  contact: (patientId: number | string, body: Record<string, unknown>) =>
    apiPost(`/patients/${patientId}/contacts`, clean(body)),

  appointment: (body: Record<string, unknown>) => apiPost("/appointments", clean(body)),
};
