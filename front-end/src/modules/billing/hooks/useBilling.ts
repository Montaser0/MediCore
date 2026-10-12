"use client";

import { useQuery } from "@tanstack/react-query";
import { billingApi } from "../api/billing.api";

export function useInvoices() {
  return useQuery({
    queryKey: ["billing", "invoices"],
    queryFn: async () => {
      try {
        const res = await billingApi.invoices({ per_page: 25 });
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}

export function usePayments() {
  return useQuery({
    queryKey: ["billing", "payments"],
    queryFn: async () => {
      try {
        const res = await billingApi.payments({ per_page: 50 });
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}
