import { apiGet } from "@/lib/api/client";

export type AdmissionRow = {
  id: number;
  admission_number?: string;
  status?: string;
  admitted_at?: string;
  patient?: { mrn?: string; full_name?: string };
  ward?: { name?: string };
  bed?: { code?: string; room?: string };
};

export type BedOccupancy = {
  total_beds?: number;
  occupied_beds?: number;
  available_beds?: number;
  occupancy_rate?: number;
};

export type WardRow = {
  id: number;
  name?: string;
  code?: string;
};

export const inpatientApi = {
  admissions: (params?: { status?: string; page?: number; per_page?: number }) =>
    apiGet<AdmissionRow[]>("/inpatient/admissions", { params }),

  occupancy: () => apiGet<BedOccupancy>("/inpatient/beds/occupancy"),

  wards: () => apiGet<WardRow[]>("/inpatient/wards"),
};
