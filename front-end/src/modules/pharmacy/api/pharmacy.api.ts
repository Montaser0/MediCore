import { apiGet } from "@/lib/api/client";

export type PrescriptionRow = {
  id: number;
  prescription_number?: string;
  status?: string;
  issued_at?: string;
  patient?: { mrn?: string; full_name?: string };
  doctor?: { name?: string };
};

export const pharmacyApi = {
  prescriptions: (params?: { status?: string; page?: number; per_page?: number }) =>
    apiGet<PrescriptionRow[]>("/pharmacy/prescriptions", { params }),
};
