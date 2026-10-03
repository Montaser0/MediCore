"use client";

import { useQuery } from "@tanstack/react-query";
import { settingsApi, type SettingRow } from "../api/settings.api";

function normalizeSettings(data: unknown): SettingRow[] {
  if (Array.isArray(data)) return data as SettingRow[];
  if (data && typeof data === "object") {
    const record = data as Record<string, unknown>;
    const group = typeof record.group === "string" ? record.group : "general";
    const values =
      record.values && typeof record.values === "object" && !Array.isArray(record.values)
        ? (record.values as Record<string, unknown>)
        : record;
    return Object.entries(values)
      .filter(([key]) => key !== "group" && key !== "values")
      .map(([key, value]) => ({ key, value, group }));
  }
  return [];
}

export function useSettings() {
  return useQuery({
    queryKey: ["settings"],
    queryFn: async () => {
      try {
        const res = await settingsApi.list();
        return normalizeSettings(res.data);
      } catch {
        return [];
      }
    },
  });
}
