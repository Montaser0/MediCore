export type Gender = "male" | "female" | "unknown";

export type PatientStatus = "active" | "inactive" | "deceased" | "merged";

export type Patient = {
  id: number;
  hospital_id: number;
  mrn: string;
  first_name: string;
  father_name?: string | null;
  grandfather_name?: string | null;
  last_name: string;
  mother_name?: string | null;
  full_name: string;
  gender: Gender;
  date_of_birth?: string | null;
  age?: number | null;
  national_id?: string | null;
  phone?: string | null;
  phone_secondary?: string | null;
  email?: string | null;
  address?: string | null;
  city?: string | null;
  nationality?: string | null;
  blood_type?: string | null;
  marital_status?: string | null;
  occupation?: string | null;
  status: PatientStatus | string;
  notes?: string | null;
  encounters_count?: number;
  department?: { id?: number; name?: string | null } | null;
  clinic?: { id?: number; name?: string | null } | null;
  allergies?: Array<{
    id: number;
    allergen: string;
    reaction?: string | null;
    severity?: string;
    status?: string;
  }>;
  conditions?: Array<{
    id: number;
    condition_name: string;
    status?: string;
    notes?: string | null;
  }>;
  contacts?: Array<{
    id: number;
    name: string;
    relationship?: string | null;
    phone?: string | null;
    is_emergency_contact?: boolean;
  }>;
  created_at?: string;
  updated_at?: string;
};

export type AuthUser = {
  id: number;
  display_name?: string;
  name?: string;
  email: string;
  username?: string;
  hospital_id?: number | null;
  department?: { id: number; name: string; type?: string } | null;
  permissions?: string[];
  roles?: string[] | Array<{ id: number; name: string; display_name?: string }>;
  staff_id?: number | null;
  doctor_id?: number | null;
  job_title?: string | null;
};

export type Appointment = {
  id: number;
  patient_id: number;
  doctor_id?: number | null;
  clinic_id?: number | null;
  scheduled_at: string;
  status: string;
  chief_complaint?: string | null;
  patient?: Patient;
  doctor?: { id: number; full_name?: string; name?: string };
  clinic?: { id: number; name: string };
};

export type Encounter = {
  id: number;
  patient_id: number;
  type: string;
  status: string;
  started_at?: string | null;
  closed_at?: string | null;
  chief_complaint?: string | null;
  patient?: Patient;
  department?: { id: number; name: string };
  doctor?: { id: number; full_name?: string };
};

export type DashboardStats = {
  patients_today?: number;
  appointments_today?: number;
  open_encounters?: number;
  lab_pending?: number;
  radiology_pending?: number;
  pharmacy_pending?: number;
  occupied_beds?: number;
  total_beds?: number;
  revenue_today?: number;
  [key: string]: unknown;
};
