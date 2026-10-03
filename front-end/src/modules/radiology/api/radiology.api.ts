import { apiGet } from "@/lib/api/client";

export type RadiologyWorklistRow = {
  id: number;
  status?: string;
  service_name?: string;
  modality?: string;
  priority?: string;
  scheduled_at?: string;
  patient?: { mrn?: string; full_name?: string };
};

export const radiologyApi = {
  worklist: (params?: { status?: string; page?: number; per_page?: number }) =>
    apiGet<RadiologyWorklistRow[]>("/radiology/worklist", { params }),
};
