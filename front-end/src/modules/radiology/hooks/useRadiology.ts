"use client";

import { useQuery } from "@tanstack/react-query";
import { radiologyApi } from "../api/radiology.api";

export function useRadiologyWorklist() {
  return useQuery({
    queryKey: ["radiology", "worklist"],
    queryFn: async () => {
      try {
        const res = await radiologyApi.worklist({ per_page: 100 });
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}
