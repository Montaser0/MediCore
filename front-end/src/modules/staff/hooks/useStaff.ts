"use client";

import { useQuery } from "@tanstack/react-query";
import { staffApi } from "../api/staff.api";

export function useStaffList() {
  return useQuery({
    queryKey: ["staff"],
    queryFn: async () => {
      try {
        const res = await staffApi.list();
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}
