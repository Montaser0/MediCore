"use client";

import { useQuery } from "@tanstack/react-query";
import { reportsApi } from "../api/reports.api";

export function useDashboardReport() {
  return useQuery({
    queryKey: ["reports", "dashboard"],
    queryFn: async () => {
      try {
        const res = await reportsApi.dashboard();
        return res.data ?? null;
      } catch {
        return null;
      }
    },
  });
}
