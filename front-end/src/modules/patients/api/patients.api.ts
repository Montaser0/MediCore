import { apiDelete, apiGet, apiPost, apiPut } from "@/lib/api/client";
import type { Patient } from "@/types/common";
import type { ApiMeta } from "@/types/api";

export type PatientsListParams = {
  search?: string;
  status?: string;
  gender?: string;
  department_id?: number;
  clinic_id?: number;
  page?: number;
  per_page?: number;
};

export const patientsApi = {
  list: (params?: PatientsListParams) =>
    apiGet<Patient[]>("/patients", { params }),

  get: (id: number | string) => apiGet<Patient>(`/patients/${id}`),

  header: (id: number | string) => apiGet<Patient>(`/patients/${id}/header`),

  file: (id: number | string, encounterId?: number) =>
    apiGet<{ patient: Patient; sections: Record<string, unknown> }>(
      `/patients/${id}/file`,
      { params: encounterId ? { encounter_id: encounterId } : undefined },
    ),

  create: (data: Partial<Patient>) => apiPost<Patient>("/patients", data),

  update: (id: number | string, data: Partial<Patient>) =>
    apiPut<Patient>(`/patients/${id}`, data),

  remove: (id: number | string) => apiDelete(`/patients/${id}`),

  timeline: (id: number | string) =>
    apiGet<{ events: unknown[]; grouped_by_day?: unknown[] }>(`/patients/${id}/timeline`),

  encounters: (id: number | string) =>
    apiGet<unknown[]>(`/patients/${id}/encounters`),

  appointments: (id: number | string) =>
    apiGet<unknown[]>(`/patients/${id}/appointments`),

  account: (id: number | string) =>
    apiGet<unknown>(`/patients/${id}/account`),
};

export type ListResult<T> = { data: T; meta?: ApiMeta };
