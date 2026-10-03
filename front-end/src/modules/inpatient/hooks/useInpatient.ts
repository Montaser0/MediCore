"use client";

import { useQuery } from "@tanstack/react-query";
import { inpatientApi } from "../api/inpatient.api";

export function useAdmissions() {
  return useQuery({
    queryKey: ["inpatient", "admissions"],
    queryFn: async () => {
      try {
        const res = await inpatientApi.admissions({ per_page: 100 });
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}

export function useBedOccupancy() {
  return useQuery({
    queryKey: ["inpatient", "occupancy"],
    queryFn: async () => {
      try {
        const res = await inpatientApi.occupancy();
        return res.data ?? null;
      } catch {
        return null;
      }
    },
  });
}

export function useWards() {
  return useQuery({
    queryKey: ["inpatient", "wards"],
    queryFn: async () => {
      try {
        const res = await inpatientApi.wards();
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}
