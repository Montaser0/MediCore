"use client";

import { useQuery } from "@tanstack/react-query";
import { nursingApi, type NursingDose } from "../api/nursing.api";

export function useNursingWorklist() {
  return useQuery({
    queryKey: ["nursing", "worklist"],
    queryFn: async () => {
      const res = await nursingApi.worklist();
      return res.data ?? { doses: [] };
    },
  });
}

export type { NursingDose };
