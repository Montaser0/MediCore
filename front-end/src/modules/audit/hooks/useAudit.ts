"use client";

import { useQuery } from "@tanstack/react-query";
import { auditApi } from "../api/audit.api";

export function useAuditLogs() {
  return useQuery({
    queryKey: ["audit"],
    queryFn: async () => {
      try {
        const res = await auditApi.list();
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}
