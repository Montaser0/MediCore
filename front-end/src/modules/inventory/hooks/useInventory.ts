"use client";

import { useQuery } from "@tanstack/react-query";
import { inventoryApi } from "../api/inventory.api";

export function useInventoryItems() {
  return useQuery({
    queryKey: ["inventory", "items"],
    queryFn: async () => {
      try {
        const res = await inventoryApi.items({ per_page: 100 });
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}

export function useStockLevels() {
  return useQuery({
    queryKey: ["inventory", "stock-levels"],
    queryFn: async () => {
      try {
        const res = await inventoryApi.stockLevels({ per_page: 100 });
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}

export function useInventoryAlerts() {
  return useQuery({
    queryKey: ["inventory", "alerts"],
    queryFn: async () => {
      try {
        const res = await inventoryApi.alerts();
        return res.data ?? [];
      } catch {
        return [];
      }
    },
  });
}
