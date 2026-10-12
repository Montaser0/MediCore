"use client";

import Link from "next/link";
import { useLocale, useTranslations } from "next-intl";
import { Search, Star, UserPlus } from "lucide-react";
import { useMemo, useState } from "react";
import { homeTiles } from "@/config/navigation";
import { isDoctorDesk, visibleModules } from "@/config/desk";
import { useAuthStore } from "@/stores/auth-store";
import { useEditionStore } from "@/stores/edition-store";
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
  const canCreatePatient = useAuthStore((state) => state.hasPermission("patients.create"));
  const ready = useEditionStore((state) => state.ready);
  const editionName = useEditionStore((state) => state.edition);
  const navigation = useEditionStore((state) => state.navigation);
  const { tiles, showNewPatient } = useMemo(() => {
    const allowed = visibleModules(user, { ready, edition: editionName, navigation });
    const list =
      ready && editionName !== "hospital"
        ? allowed.filter((item) => item.id !== "home")
        : allowed.filter(
            (item) =>
              homeTiles.includes(item.id as (typeof homeTiles)[number]) || item.id === "pharmacy-stock",
          );
    const query = q.trim();
    const filtered = query ? list.filter((item) => t(item.labelKey).includes(query)) : list;
    const newPatientLabel = t("patients.new");
    const showNewPatient =
      allowed.some((item) => item.id === "patients") &&
      canCreatePatient &&
      !isDoctorDesk(user) &&
      (!query || newPatientLabel.includes(query));
    return { tiles: filtered, showNewPatient };
  }, [q, t, user, ready, editionName, navigation, canCreatePatient]);

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
        {showNewPatient ? (
          <Link
            href={`/${locale}/dashboard/patients/new`}
            className={cn("his-module-card", favorites.includes("patients-new") && "active")}
          >
            <button
              type="button"
              className="star"
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleFavorite("patients-new");
              }}
            >
              <Star size={14} fill={favorites.includes("patients-new") ? "#c9a227" : "none"} />
            </button>
            <UserPlus size={42} strokeWidth={1.25} color="#1a1a1a" />
            <span className="px-1 text-center text-[12px] leading-tight">{t("patients.new")}</span>
          </Link>
        ) : null}
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
