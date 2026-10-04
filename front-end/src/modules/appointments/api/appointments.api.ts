import { apiGet, apiPatch, apiPost } from "@/lib/api/client";

export type AppointmentRow = {
  id: number;
  appointment_number?: string;
  status?: string;
  status_label?: string;
  scheduled_at?: string;
  reason?: string;
  patient?: { id?: number; mrn?: string; full_name?: string; phone?: string };
  doctor?: { id?: number; name?: string };
  clinic?: { id?: number; name?: string };
  department?: { id?: number; name?: string };
};

export type AppointmentsListParams = {
  status?: string;
  date?: string;
  page?: number;
  per_page?: number;
};

export const appointmentsApi = {
  list: (params?: AppointmentsListParams) =>
    apiGet<AppointmentRow[]>("/appointments", { params }),

  checkIn: (id: number | string) =>
    apiPost<AppointmentRow>(`/appointments/${id}/check-in`),

  confirm: (id: number | string) =>
    apiPost<AppointmentRow>(`/appointments/${id}/confirm`),

  cancel: (id: number | string) =>
    apiPost<AppointmentRow>(`/appointments/${id}/cancel`),

  noShow: (id: number | string) =>
    apiPost<AppointmentRow>(`/appointments/${id}/no-show`),

  update: (
    id: number | string,
    data: {
      department_id?: number;
      doctor_id?: number;
      scheduled_at?: string;
      reason?: string;
    },
  ) => apiPatch<AppointmentRow>(`/appointments/${id}`, data),

  create: (data: {
    patient_id: number;
    department_id?: number;
    doctor_id?: number;
    clinic_id?: number;
    scheduled_at: string;
    reason?: string;
  }) => apiPost<AppointmentRow>("/appointments", data),
};
