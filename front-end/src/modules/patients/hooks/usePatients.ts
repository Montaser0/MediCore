"use client";

import { keepPreviousData, useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { patientsApi, type PatientsListParams } from "../api/patients.api";
import type { Patient } from "@/types/common";

export function usePatients(params?: PatientsListParams, enabled = true) {
  return useQuery({
    queryKey: ["patients", params],
    enabled,
    placeholderData: keepPreviousData,
    staleTime: 15_000,
    queryFn: async ({ signal }) => {
      const res = await patientsApi.list(params, signal);
      return { rows: res.data ?? [], meta: res.meta };
    },
  });
}

export function usePatient(id?: string | number) {
  return useQuery({
    queryKey: ["patients", id],
    enabled: !!id,
    queryFn: async () => {
      const res = await patientsApi.get(id!);
      return res.data;
    },
  });
}

export function usePatientFile(
  id?: string | number,
  encounterId?: number,
  sections?: string[],
  enabled = true,
) {
  return useQuery({
    queryKey: ["patients", id, "file", encounterId ?? "all", sections?.slice().sort().join(",") ?? "all"],
    enabled: Boolean(id) && enabled,
    queryFn: async ({ signal }) => {
      const res = await patientsApi.file(id!, encounterId, sections, signal);
      return res.data;
    },
  });
}

export function usePatientTimeline(id?: string | number, enabled = true) {
  return useQuery({
    queryKey: ["patients", id, "timeline"],
    enabled: Boolean(id) && enabled,
    queryFn: async () => {
      const res = await patientsApi.timeline(id!);
      const payload = res.data;
      if (Array.isArray(payload)) return payload;
      return payload?.events ?? [];
    },
  });
}

export function useCreatePatient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<Patient>) => patientsApi.create(data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["patients"] }),
  });
}

export function useUpdatePatient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<Patient> }) =>
      patientsApi.update(id, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["patients"] }),
  });
}

export function useDeletePatient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => patientsApi.remove(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["patients"] }),
  });
}
