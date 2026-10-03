import { apiGet } from "@/lib/api/client";

export type SettingRow = {
  key?: string;
  value?: unknown;
  group?: string;
};

export const settingsApi = {
  list: () => apiGet<SettingRow[] | Record<string, unknown>>("/settings"),
};
