"use client";

import { useQuery } from "@tanstack/react-query";
import { operationsApi } from "../api/operations.api";

export function useOperations() {
  return useQuery({
    queryKey: ["operations"],
    queryFn: async () => {
      try {
        const res = await operationsApi.list({ per_page: 100 });
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}
