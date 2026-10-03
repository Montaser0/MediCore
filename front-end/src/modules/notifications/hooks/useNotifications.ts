"use client";

import { useQuery } from "@tanstack/react-query";
import { notificationsApi } from "../api/notifications.api";

export function useNotifications() {
  return useQuery({
    queryKey: ["notifications"],
    queryFn: async () => {
      try {
        const res = await notificationsApi.list();
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}
