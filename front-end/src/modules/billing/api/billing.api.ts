import { apiGet } from "@/lib/api/client";

export type InvoiceRow = {
  id: number;
  invoice_number?: string;
  status?: string;
  total?: number;
  balance?: number;
  issued_at?: string;
  patient?: { mrn?: string; full_name?: string };
};

export type PaymentRow = {
  id: number;
  amount?: number;
  method?: string;
  paid_at?: string;
  invoice?: { invoice_number?: string };
};

export const billingApi = {
  invoices: (params?: { status?: string; page?: number; per_page?: number }) =>
    apiGet<InvoiceRow[]>("/billing/invoices", { params }),

  payments: (params?: { page?: number; per_page?: number }) =>
    apiGet<PaymentRow[]>("/billing/payments", { params }),
};
