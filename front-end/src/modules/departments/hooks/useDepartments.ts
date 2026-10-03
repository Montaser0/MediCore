"use client";

import { useQuery } from "@tanstack/react-query";
import { departmentsApi } from "../api/departments.api";

export function useDepartments() {
  return useQuery({
    queryKey: ["departments"],
    queryFn: async () => {
      try {
        const res = await departmentsApi.list();
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}
