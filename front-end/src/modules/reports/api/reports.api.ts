import { apiGet } from "@/lib/api/client";

export type PharmacyReport = {
  sales_today?: number;
  items_today?: number;
  revenue_today?: number;
  revenue_week?: number;
  revenue_month?: number;
  discount_today?: number;
  discount_week?: number;
  discount_month?: number;
  payments_today?: number;
  payments_week?: number;
  payments_month?: number;
  profit_today?: number;
  profit_week?: number;
  profit_month?: number;
  item_profit_month?: Array<{ name: string; quantity: number; net: number; cost: number; profit: number }>;
  returns_today?: number;
  cash_today?: number;
  debts_today?: number;
  outstanding_debt?: number;
};

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
  sales?: { today?: number; revenue_today?: number; items_today?: number };
  inventory_alerts?: Record<string, number>;
};

export const reportsApi = {
  dashboard: () => apiGet<DashboardMetrics>("/reports/dashboard"),
  pharmacy: () => apiGet<PharmacyReport>("/reports/pharmacy"),
};
