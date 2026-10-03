"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useLocale } from "next-intl";
import { useAuthStore } from "@/stores/auth-store";
import { getToken } from "@/lib/api/client";

export function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const locale = useLocale();
  const hydrated = useAuthStore((s) => s.hydrated);
  const token = useAuthStore((s) => s.token);
  const fetchMe = useAuthStore((s) => s.fetchMe);
  const setHydrated = useAuthStore((s) => s.setHydrated);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      if (!useAuthStore.getState().hydrated) setHydrated(true);
    }, 50);
    return () => window.clearTimeout(timer);
  }, [setHydrated]);

  useEffect(() => {
    if (!hydrated) return;
    const existing = token ?? getToken();
    if (!existing) {
      router.replace(`/${locale}/login`);
      return;
    }
    let cancelled = false;
    fetchMe()
      .catch(() => {
        if (!cancelled) router.replace(`/${locale}/login`);
      })
      .finally(() => {
        if (!cancelled) setReady(true);
      });
    return () => {
      cancelled = true;
    };
  }, [hydrated, token, fetchMe, router, locale]);

  if (!hydrated || !ready) {
    return (
      <div className="flex h-screen items-center justify-center text-[var(--his-muted)]">
        جاري التحميل...
      </div>
    );
  }

  return <>{children}</>;
}
