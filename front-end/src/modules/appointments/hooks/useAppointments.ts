"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  appointmentsApi,
  type AppointmentsListParams,
} from "../api/appointments.api";

async function listRows(params?: AppointmentsListParams) {
  try {
    const res = await appointmentsApi.list(params);
    return res.data ?? [];
  } catch {
    return [];
  }
}

export function useAppointments(params?: AppointmentsListParams) {
  return useQuery({
    queryKey: ["appointments", params],
    queryFn: () => listRows(params),
  });
}

export function useAppointmentActions() {
  const qc = useQueryClient();
  const invalidate = () =>
    qc.invalidateQueries({ queryKey: ["appointments"] });

  const checkIn = useMutation({
    mutationFn: (id: number | string) => appointmentsApi.checkIn(id),
    onSuccess: async () => {
      await invalidate();
      await qc.invalidateQueries({ queryKey: ["encounters"] });
    },
  });
  const confirm = useMutation({
    mutationFn: (id: number | string) => appointmentsApi.confirm(id),
    onSuccess: invalidate,
  });
  const cancel = useMutation({
    mutationFn: (id: number | string) => appointmentsApi.cancel(id),
    onSuccess: invalidate,
  });
  const noShow = useMutation({
    mutationFn: (id: number | string) => appointmentsApi.noShow(id),
    onSuccess: invalidate,
  });
  const create = useMutation({
    mutationFn: appointmentsApi.create,
    onSuccess: invalidate,
  });
  const update = useMutation({
    mutationFn: ({
      id,
      data,
    }: {
      id: number;
      data: {
        department_id?: number;
        doctor_id?: number;
        scheduled_at?: string;
        reason?: string;
      };
    }) => appointmentsApi.update(id, data),
    onSuccess: invalidate,
  });

  return { checkIn, confirm, cancel, noShow, create, update };
}
