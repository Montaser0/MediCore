"use client";

import { useQuery } from "@tanstack/react-query";
import { laboratoryApi } from "../api/laboratory.api";

export function useLabWorklist() {
  return useQuery({
    queryKey: ["laboratory", "worklist"],
    queryFn: async () => {
      try {
        const res = await laboratoryApi.worklist({ per_page: 100 });
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}

export function useLabTests() {
  return useQuery({
    queryKey: ["laboratory", "tests"],
    queryFn: async () => {
      try {
        const res = await laboratoryApi.tests({ per_page: 100 });
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}
