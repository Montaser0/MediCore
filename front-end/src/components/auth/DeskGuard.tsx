"use client";

import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useLocale } from "next-intl";
import { useAuthStore } from "@/stores/auth-store";
import { canOpenPath, deskHrefs } from "@/config/desk";

export function DeskGuard({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const locale = useLocale();
  const user = useAuthStore((state) => state.user);
  const hrefs = deskHrefs(user);

  useEffect(() => {
    if (!hrefs) return;
    const path = pathname.replace(new RegExp(`^/${locale}`), "") || "/dashboard";
    if (!canOpenPath(user, path)) {
      router.replace(`/${locale}${hrefs[0]}`);
    }
  }, [hrefs, pathname, locale, router, user]);

  return <>{children}</>;
}
