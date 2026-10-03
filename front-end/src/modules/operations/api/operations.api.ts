import { apiGet } from "@/lib/api/client";

export type OperationRow = {
  id: number;
  operation_number?: string;
  status?: string;
  scheduled_at?: string;
  patient?: { mrn?: string; full_name?: string };
  type?: { name?: string };
  theatre?: { name?: string };
};

export const operationsApi = {
  list: (params?: { status?: string; page?: number; per_page?: number }) =>
    apiGet<OperationRow[]>("/operations", { params }),
};
