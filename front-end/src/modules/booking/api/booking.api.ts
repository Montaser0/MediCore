import axios from "axios";
import { resolveApiUrl } from "@/config/environment";

const publicClient = axios.create({
  baseURL: resolveApiUrl(),
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
});

publicClient.interceptors.request.use((config) => {
  config.baseURL = resolveApiUrl();
  return config;
});

export type PublicDoctor = {
  id: number;
  name: string;
};

export type PublicSlot = {
  time: string;
  doctor_ids: number[];
};

export type PublicClinic = {
  id: number;
  name: string;
  department?: string | null;
  capacity: number;
  booked: number;
  remaining: number;
  full: boolean;
  doctors: PublicDoctor[];
  times: PublicSlot[];
};

export type PublicAvailability = {
  open: boolean;
  opens_at?: string | null;
  closes_at?: string | null;
  message?: string | null;
  clinics: PublicClinic[];
};

export type PublicBookingResult = {
  appointment_number?: string;
  scheduled_at?: string;
  clinic?: string;
  doctor?: string;
  patient_name?: string;
  mrn?: string;
};

export type PublicBookingInput = {
  first_name: string;
  father_name: string;
  last_name: string;
  gender: string;
  date_of_birth: string;
  phone: string;
  national_id?: string;
  clinic_id: number;
  doctor_id: number;
  scheduled_at: string;
  reason?: string;
};

export const publicBookingApi = {
  availability: async (date: string) => {
    const res = await publicClient.get<{ data: PublicAvailability }>("/public/clinics", {
      params: { date },
    });
    return res.data.data;
  },
  book: async (body: PublicBookingInput) => {
    const res = await publicClient.post<{ data: PublicBookingResult }>(
      "/public/appointments",
      body,
    );
    return res.data.data;
  },
};
