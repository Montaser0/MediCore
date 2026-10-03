import { apiGet } from "@/lib/api/client";

export type DashboardMetrics = {
  generated_at?: string;
  patients?: { total?: number; registered_today?: number };
  encounters?: { open?: number; today?: number };
  appointments?: { today?: number; upcoming?: number };
  orders?: { open?: number; pending_lab?: number; pending_radiology?: number };
  inpatient?: {
    admitted?: number;
    occupancy_rate?: number;
    occupied_beds?: number;
    total_beds?: number;
    available_beds?: number;
  };
  operations?: { scheduled_today?: number; in_theatre?: number };
  finance?: {
    revenue_today?: number;
    outstanding_balance?: number;
    unpaid_invoices?: number;
  };
  inventory_alerts?: Record<string, number>;
};

export const reportsApi = {
  dashboard: () => apiGet<DashboardMetrics>("/reports/dashboard"),
};
