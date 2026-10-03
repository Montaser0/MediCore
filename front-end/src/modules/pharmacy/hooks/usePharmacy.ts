"use client";

import { useQuery } from "@tanstack/react-query";
import { pharmacyApi } from "../api/pharmacy.api";

export function usePrescriptions() {
  return useQuery({
    queryKey: ["pharmacy", "prescriptions"],
    queryFn: async () => {
      try {
        const res = await pharmacyApi.prescriptions({ per_page: 100 });
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}
