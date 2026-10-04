import { apiGet, apiPut } from "@/lib/api/client";

export type BookingClinicRule = {
  clinic_id: number;
  capacity: number;
  doctor_ids: number[];
  times: string[];
};

export type BookingSettings = {
  open: boolean;
  opens_at: string | null;
  closes_at: string | null;
  accepting: boolean;
  clinics: BookingClinicRule[];
};

export const bookingSettingsApi = {
  get: async () => {
    const res = await apiGet<BookingSettings>("/booking-settings");
    return res.data;
  },
  save: async (body: Omit<BookingSettings, "accepting">) => {
    const res = await apiPut<BookingSettings>("/booking-settings", body);
    return res.data;
  },
};
