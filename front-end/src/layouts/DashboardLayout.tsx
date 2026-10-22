"use client";

import type { ReactNode } from "react";
import { env } from "@/config/environment";
import { formatDate } from "@/lib/utils";
import { AppHeader } from "./AppHeader";
import { AppSidebar } from "./AppSidebar";

export function DashboardLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex h-screen flex-col overflow-hidden">
      <AppHeader />
      <div className="flex min-h-0 flex-1">
        <AppSidebar />
        <main className="min-h-0 min-w-0 flex-1 overflow-auto bg-[var(--his-bg)]">
          {children}
        </main>
      </div>
      <footer
        className="flex h-6 shrink-0 items-center justify-between px-3 text-[11px] text-[#3a4a5a] border-t"
        style={{ background: "var(--his-footer)", borderColor: "var(--his-border)" }}
      >
        <span>{env.appName}</span>
        <span>{formatDate(new Date().toISOString())}</span>
      </footer>
    </div>
  );
}
