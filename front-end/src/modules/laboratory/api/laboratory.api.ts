import { apiGet } from "@/lib/api/client";

export type LabWorklistRow = {
  id: number;
  status?: string;
  test_name?: string;
  test_code?: string;
  priority?: string;
  ordered_at?: string;
  patient?: { mrn?: string; full_name?: string };
};

export type LabTestRow = {
  id: number;
  code?: string;
  name?: string;
  category?: string;
};

export const laboratoryApi = {
  worklist: (params?: { status?: string; page?: number; per_page?: number }) =>
    apiGet<LabWorklistRow[]>("/laboratory/worklist", { params }),

  tests: (params?: { search?: string; page?: number; per_page?: number }) =>
    apiGet<LabTestRow[]>("/laboratory/tests", { params }),
};
