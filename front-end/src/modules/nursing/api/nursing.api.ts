import { apiGet } from "@/lib/api/client";

export type NursingDose = {
  id: number;
  patient_id?: number;
  patient_name?: string;
  mrn?: string;
  medication?: string;
  dose?: string;
  route?: string;
  scheduled_at?: string;
  is_overdue?: boolean;
};

export type NursingWorklist = {
  counts?: { overdue?: number; upcoming?: number };
  doses?: NursingDose[];
};

export const nursingApi = {
  worklist: () => apiGet<NursingWorklist>("/nursing/worklist"),
};
