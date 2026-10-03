import { apiGet } from "@/lib/api/client";

export type DepartmentRow = {
  id: number;
  name?: string;
  code?: string;
  type?: string;
};

export const departmentsApi = {
  list: () => apiGet<DepartmentRow[]>("/departments", { params: { per_page: 100 } }),
};
