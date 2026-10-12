"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  encountersApi,
  type EncountersListParams,
} from "../api/encounters.api";

export function useEncounters(params?: EncountersListParams) {
  return useQuery({
    queryKey: ["encounters", params],
    queryFn: async () => {
      try {
        const res = await encountersApi.list(params);
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}

export function useEncounterQueue(params?: Pick<EncountersListParams, "date" | "doctor_id" | "clinic_id">) {
  return useQuery({
    queryKey: ["encounters", "queue", params],
    queryFn: async () => {
      try {
        const res = await encountersApi.queue(params);
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}

export function useEncounterActions() {
  const qc = useQueryClient();
  const invalidate = () =>
    qc.invalidateQueries({ queryKey: ["encounters"] });

  const start = useMutation({
    mutationFn: (id: number | string) => encountersApi.start(id),
    onSuccess: invalidate,
  });
  const close = useMutation({
    mutationFn: (id: number | string) => encountersApi.close(id),
    onSuccess: invalidate,
  });
  const cancel = useMutation({
    mutationFn: (id: number | string) => encountersApi.cancel(id),
    onSuccess: invalidate,
  });

  return { start, close, cancel };
}
