import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { AuthUser } from "@/types/common";
import { apiGet, apiPost, setToken, getToken } from "@/lib/api/client";

type AuthState = {
  user: AuthUser | null;
  token: string | null;
  hydrated: boolean;
  setHydrated: (value: boolean) => void;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  fetchMe: () => Promise<void>;
  hasPermission: (permission: string) => boolean;
};

type LoginPayload = {
  token: string;
  token_type: string;
  user: AuthUser;
};

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      token: null,
      hydrated: false,
      setHydrated: (value) => set({ hydrated: value }),
      login: async (username, password) => {
        const res = await apiPost<LoginPayload>("/auth/login", {
          username,
          password,
          device_name: "web",
        });
        setToken(res.data.token);
        set({ token: res.data.token, user: res.data.user });
      },
      logout: async () => {
        try {
          if (getToken()) await apiPost("/auth/logout");
        } finally {
          setToken(null);
          set({ token: null, user: null });
        }
      },
      fetchMe: async () => {
        if (!getToken()) {
          set({ user: null, token: null });
          return;
        }
        const res = await apiGet<AuthUser>("/auth/me");
        set({ user: res.data, token: getToken() });
      },
      hasPermission: (permission) => {
        const perms = get().user?.permissions ?? [];
        if (perms.includes("*") || perms.includes("admin")) return true;
        return perms.includes(permission);
      },
    }),
    {
      name: "hms-auth",
      partialize: (state) => ({ token: state.token, user: state.user }),
      onRehydrateStorage: () => (state) => {
        if (state?.token) setToken(state.token);
        state?.setHydrated(true);
      },
    },
  ),
);
