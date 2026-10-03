"use client";

import { useQuery } from "@tanstack/react-query";
import { documentsApi } from "../api/documents.api";

export function useDocuments() {
  return useQuery({
    queryKey: ["documents"],
    queryFn: async () => {
      const res = await documentsApi.list();
      return res.data ?? [];
    },
  });
}
