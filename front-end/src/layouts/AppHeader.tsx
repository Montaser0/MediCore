"use client";

import Link from "next/link";
import { useLocale, useTranslations } from "next-intl";
import { Bell, LogOut, Menu, User } from "lucide-react";
import { env } from "@/config/environment";
import { useAuthStore } from "@/stores/auth-store";
import { useUiStore } from "@/stores/ui-store";

export function AppHeader() {
  const t = useTranslations("app");
  const locale = useLocale();
  const user = useAuthStore((s) => s.user);
  const logout = useAuthStore((s) => s.logout);
  const toggleSidebar = useUiStore((s) => s.toggleSidebar);

  return (
    <header
      className="flex h-16 shrink-0 items-center justify-between px-3 text-white"
      style={{ background: "var(--his-header)" }}
    >
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={toggleSidebar}
          className="flex h-8 w-8 items-center justify-center hover:bg-white/10"
          aria-label="menu"
        >
          <Menu size={20} />
        </button>
        <Link href={`/${locale}/dashboard`} className="flex items-center">
          <img src="/logo.png?v=3" alt={env.appName} className="h-11 w-auto" />
        </Link>
      </div>

      <div className="flex items-center gap-3 text-[12px]">
        <button type="button" className="flex h-8 w-8 items-center justify-center hover:bg-white/10">
          <Bell size={16} />
        </button>
        <div className="flex items-center gap-2 border-s border-white/25 ps-3">
          <User size={14} />
          <span>{user?.display_name ?? user?.name ?? user?.username ?? t("user")}</span>
        </div>
        <button
          type="button"
          onClick={() => logout()}
          className="flex items-center gap-1 hover:bg-white/10 px-2 h-8"
        >
          <LogOut size={14} />
          <span>{t("logout")}</span>
        </button>
      </div>
    </header>
  );
}
