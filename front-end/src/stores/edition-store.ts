"use client";

import { create } from "zustand";
import { apiGet } from "@/lib/api/client";

export type EditionPayload = {
  edition: string;
  modules: string[];
  features: string[];
  navigation: string[];
};

type EditionState = EditionPayload & {
  ready: boolean;
  hasModule: (name: string) => boolean;
  hasFeature: (name: string) => boolean;
  load: () => Promise<void>;
};

const empty: EditionPayload = {
  edition: "hospital",
  modules: [],
  features: [],
  navigation: [],
};

export const useEditionStore = create<EditionState>((set, get) => ({
  ready: false,
  ...empty,
  hasModule: (name) => {
    const state = get();
    if (!state.ready) return false;
    if (state.navigation.includes("*")) return true;
    return state.modules.includes(name);
  },
  hasFeature: (name) => get().ready && get().features.includes(name),
  load: async () => {
    if (get().ready) return;
    try {
      const res = await apiGet<EditionPayload>("/system/modules");
      const payload = res.data;
      if (!payload?.edition || !Array.isArray(payload.navigation)) {
        set({ ready: true, edition: "hospital", modules: [], features: ["pharmacy.prescriptions"], navigation: ["*"] });
        return;
      }
      set({
        ready: true,
        edition: payload.edition,
        modules: payload.modules ?? [],
        features: payload.features ?? [],
        navigation: payload.navigation,
      });
    } catch {
      set({ ready: true, edition: "hospital", modules: [], features: ["pharmacy.prescriptions"], navigation: ["*"] });
    }
  },
}));
