import { apiGet } from "@/lib/api/client";

export type StaffRow = Record<string, unknown> & {
  id: number;
  full_name?: string;
  name?: string;
  job_title?: string;
  department?: { name?: string };
};

export const staffApi = {
  list: async () => {
    try {
      return await apiGet<StaffRow[]>("/staff", { params: { per_page: 100 } });
    } catch {
      return apiGet<StaffRow[]>("/doctors", { params: { per_page: 100 } });
    }
  },
};
