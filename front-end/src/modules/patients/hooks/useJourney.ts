"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { journeyApi } from "../api/journey.api";

export function usePatientEncounters(patientId?: string) {
  return useQuery({
    queryKey: ["patients", patientId, "encounters"],
    enabled: !!patientId,
    queryFn: async () => {
      const res = await journeyApi.encounters(patientId!);
      return res.data ?? [];
    },
  });
}

export function useCatalogs() {
  return useQuery({
    queryKey: ["journey-catalogs"],
    queryFn: () => journeyApi.catalogs(),
    staleTime: 60_000,
  });
}

export function useJourneyAction(patientId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (job: () => Promise<unknown>) => job(),
    onSuccess: async () => {
      await Promise.all([
        qc.invalidateQueries({ queryKey: ["patients"] }),
        qc.invalidateQueries({ queryKey: ["encounters"] }),
        qc.invalidateQueries({ queryKey: ["appointments"] }),
        qc.invalidateQueries({ queryKey: ["laboratory"] }),
        qc.invalidateQueries({ queryKey: ["pharmacy"] }),
        qc.invalidateQueries({ queryKey: ["nursing"] }),
        qc.invalidateQueries({ queryKey: ["journey-catalogs"] }),
        qc.invalidateQueries({ queryKey: ["diagnosis-codes"] }),
        qc.invalidateQueries({ queryKey: ["/diagnosis-codes"] }),
      ]);
    },
  });
}
