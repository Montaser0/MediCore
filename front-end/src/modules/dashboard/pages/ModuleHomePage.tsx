"use client";

import Link from "next/link";
import { useLocale, useTranslations } from "next-intl";
import { Search, Star } from "lucide-react";
import { useMemo, useState } from "react";
import { homeTiles } from "@/config/navigation";
import { visibleModules } from "@/config/desk";
import { useAuthStore } from "@/stores/auth-store";
import { useUiStore } from "@/stores/ui-store";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/Input";

export function ModuleHomePage() {
  const t = useTranslations();
  const locale = useLocale();
  const [q, setQ] = useState("");
  const favorites = useUiStore((s) => s.favorites);
  const toggleFavorite = useUiStore((s) => s.toggleFavorite);

  const user = useAuthStore((state) => state.user);
  const tiles = useMemo(() => {
    const allowed = visibleModules(user);
    const list = allowed.filter((m) =>
      homeTiles.includes(m.id as (typeof homeTiles)[number]) || m.id === "pharmacy-stock",
    );
    if (!q.trim()) return list;
    return list.filter((m) => t(m.labelKey).includes(q.trim()));
  }, [q, t, user]);

  return (
    <div className="p-3">
      <div className="mb-3 flex items-center gap-2 border border-[var(--his-border)] bg-white px-2 py-1.5 max-w-xl">
        <Search size={14} className="text-[var(--his-muted)]" />
        <Input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder={t("app.searchList")}
          className="border-0! shadow-none! h-6!"
        />
      </div>

      <div className="flex flex-wrap gap-3">
        {tiles.map((mod) => {
          const Icon = mod.icon;
          const fav = favorites.includes(mod.id);
          return (
            <Link
              key={mod.id}
              href={`/${locale}${mod.href}`}
              className={cn("his-module-card", fav && "active")}
            >
              <button
                type="button"
                className="star"
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  toggleFavorite(mod.id);
                }}
              >
                <Star size={14} fill={fav ? "#c9a227" : "none"} />
              </button>
              <Icon size={42} strokeWidth={1.25} color={mod.color ?? "#1a1a1a"} />
              <span className="px-1 text-center text-[12px] leading-tight">
                {t(mod.labelKey)}
              </span>
            </Link>
          );
        })}
      </div>
    </div>
  );
}
