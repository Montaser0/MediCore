import { apiGet } from "@/lib/api/client";

export type InventoryItemRow = {
  id: number;
  code?: string;
  name?: string;
  type?: string;
  unit?: string;
  reorder_level?: number;
};

export type StockLevelRow = {
  id?: number;
  item_id?: number;
  item?: { code?: string; name?: string };
  warehouse?: { name?: string };
  quantity?: number;
  available?: number;
};

export type InventoryAlertRow = {
  id?: number;
  type?: string;
  message?: string;
  item?: { code?: string; name?: string };
  quantity?: number;
};

export const inventoryApi = {
  items: (params?: { search?: string; page?: number; per_page?: number }) =>
    apiGet<InventoryItemRow[]>("/inventory/items", { params }),

  stockLevels: (params?: { page?: number; per_page?: number }) =>
    apiGet<StockLevelRow[]>("/inventory/stock-levels", { params }),

  alerts: () => apiGet<InventoryAlertRow[]>("/inventory/alerts"),
};
