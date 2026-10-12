import { apiGet, apiPost } from "@/lib/api/client";

export type EncounterRow = {
  id: number;
  encounter_number?: string;
  type?: string;
  status?: string;
  is_open?: boolean;
  chief_complaint?: string;
  started_at?: string;
  ended_at?: string;
  patient?: { id?: number; mrn?: string; full_name?: string };
  doctor?: { id?: number; name?: string };
  clinic?: { id?: number; name?: string };
  department?: { id?: number; name?: string };
};

export type EncountersListParams = {
  type?: string;
  status?: string;
  date?: string;
  page?: number;
  per_page?: number;
  doctor_id?: number;
  clinic_id?: number;
};

export const encountersApi = {
  list: (params?: EncountersListParams) =>
    apiGet<EncounterRow[]>("/encounters", { params }),

  queue: (params?: Pick<EncountersListParams, "date" | "doctor_id" | "clinic_id">) =>
    apiGet<EncounterRow[]>("/encounters/queue", { params: { ...params, per_page: 100 } }),

  start: (id: number | string) =>
    apiPost<EncounterRow>(`/encounters/${id}/start`),

  close: (id: number | string) =>
    apiPost<EncounterRow>(`/encounters/${id}/close`),

  cancel: (id: number | string) =>
    apiPost<EncounterRow>(`/encounters/${id}/close`, { cancel: true }),
};
