"use client";

import { useEffect, useMemo } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useLocale } from "next-intl";
import { useAuthStore } from "@/stores/auth-store";
import { useEditionStore } from "@/stores/edition-store";
import { canOpenPath, visibleModules } from "@/config/desk";

export function DeskGuard({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const locale = useLocale();
  const user = useAuthStore((state) => state.user);
  const ready = useEditionStore((state) => state.ready);
  const editionName = useEditionStore((state) => state.edition);
  const navigation = useEditionStore((state) => state.navigation);
  const edition = useMemo(
    () => ({ ready, edition: editionName, navigation }),
    [ready, editionName, navigation],
  );

  useEffect(() => {
    if (!edition.ready) return;
    const path = pathname.replace(new RegExp(`^/${locale}`), "") || "/dashboard";
    if (!canOpenPath(user, path, edition)) {
      const fallback = visibleModules(user, edition).find((item) => item.id !== "home")?.href ?? "/dashboard";
      router.replace(`/${locale}${fallback}`);
    }
  }, [pathname, locale, router, user, edition]);

  return <>{children}</>;
}
