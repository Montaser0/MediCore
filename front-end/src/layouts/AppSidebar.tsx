"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { ChevronDown, ChevronLeft, Star } from "lucide-react";
import { modules, navTree, type NavModule, type NavNode } from "@/config/navigation";
import { roleNames, visibleModules } from "@/config/desk";
import { useAuthStore } from "@/stores/auth-store";
import { useEditionStore } from "@/stores/edition-store";
import { useUiStore } from "@/stores/ui-store";
import { cn } from "@/lib/utils";

function isItemActive(item: NavModule, locale: string, pathname: string) {
  const href = `/${locale}${item.href.split("?")[0]}`;
  const longerMatch = modules.some((other) => {
    if (other.id === item.id) return false;
    const otherHref = `/${locale}${other.href.split("?")[0]}`;
    return otherHref.length > href.length && pathname.startsWith(otherHref);
  });
  return item.href === "/dashboard"
    ? pathname === href
    : !longerMatch && (pathname === href || pathname.startsWith(`${href}/`));
}

function prune(node: NavNode, allowed: Set<string>): NavNode | null {
  if (node.kind === "link") return allowed.has(node.id) ? node : null;
  const children = node.children
    .map((child) => prune(child, allowed))
    .filter((child): child is NavNode => child !== null);
  if (children.length === 0) return null;
  return { ...node, children };
}

function containsActive(
  node: NavNode,
  byId: Map<string, NavModule>,
  locale: string,
  pathname: string,
): boolean {
  if (node.kind === "link") {
    const item = byId.get(node.id);
    return item ? isItemActive(item, locale, pathname) : false;
  }
  return node.children.some((child) => containsActive(child, byId, locale, pathname));
}

function SidebarItem({
  item,
  locale,
  depth,
}: {
  item: NavModule;
  locale: string;
  depth: number;
}) {
  const t = useTranslations();
  const pathname = usePathname();
  const href = `/${locale}${item.href.split("?")[0]}`;
  const active = isItemActive(item, locale, pathname);
  const Icon = item.icon;

  return (
    <Link
      href={href}
      className={cn(
        "flex items-center gap-2 py-[7px] text-[12px] border-b border-[#d7e0e8]",
        active ? "bg-[#4a90d9] text-white" : "text-[#1a1a1a] hover:bg-[#dce8f3]",
      )}
      style={{ paddingInlineStart: 12 + depth * 14, paddingInlineEnd: 12 }}
    >
      <Icon size={15} style={{ color: active ? "#fff" : item.color ?? "#1b4f8c" }} />
      <span className="truncate">{t(item.labelKey)}</span>
    </Link>
  );
}

function NavBranch({
  node,
  depth,
  byId,
  locale,
  pathname,
  open,
  toggle,
}: {
  node: NavNode;
  depth: number;
  byId: Map<string, NavModule>;
  locale: string;
  pathname: string;
  open: Record<string, boolean>;
  toggle: (id: string, active: boolean) => void;
}) {
  const t = useTranslations();

  if (node.kind === "link") {
    const item = byId.get(node.id);
    if (!item) return null;
    return <SidebarItem item={item} locale={locale} depth={depth} />;
  }

  const active = containsActive(node, byId, locale, pathname);
  const expanded = node.id in open ? open[node.id] : active;

  return (
    <div>
      <button
        type="button"
        onClick={() => toggle(node.id, active)}
        className={cn(
          "flex w-full items-center gap-1 border-y border-[#c5d0db] py-2 text-start text-[11px] font-bold text-[#1b4f8c]",
          depth === 0 ? "bg-[#dce6ef]" : "bg-[#e7eef5]",
        )}
        style={{ paddingInlineStart: 10 + depth * 12, paddingInlineEnd: 8 }}
      >
        {expanded ? <ChevronDown size={13} /> : <ChevronLeft size={13} />}
        <span className="truncate">{t(node.labelKey)}</span>
      </button>
      {expanded
        ? node.children.map((child) => (
            <NavBranch
              key={child.kind === "link" ? child.id : child.id}
              node={child}
              depth={depth + 1}
              byId={byId}
              locale={locale}
              pathname={pathname}
              open={open}
              toggle={toggle}
            />
          ))
        : null}
    </div>
  );
}

export function AppSidebar() {
  const t = useTranslations("app");
  const tStation = useTranslations("station");
  const locale = useLocale();
  const pathname = usePathname();
  const openSidebar = useUiStore((s) => s.sidebarOpen);
  const favorites = useUiStore((s) => s.favorites);
  const [open, setOpen] = useState<Record<string, boolean>>({});

  const user = useAuthStore((state) => state.user);
  const ready = useEditionStore((state) => state.ready);
  const editionName = useEditionStore((state) => state.edition);
  const navigation = useEditionStore((state) => state.navigation);
  const editionMenu = useMemo(
    () => ({ ready, edition: editionName, navigation }),
    [ready, editionName, navigation],
  );
  const menu = visibleModules(user, editionMenu);
  const byId = new Map(menu.map((item) => [item.id, item]));
  const allowed = new Set(menu.map((item) => item.id));
  const tree = navTree
    .map((node) => prune(node, allowed))
    .filter((node): node is NavNode => node !== null);
  const favoriteItems = menu.filter((item) => favorites.includes(item.id) && item.id !== "home");
  const favoritesActive = favoriteItems.some((item) => isItemActive(item, locale, pathname));
  const favoritesOpen = "favorites" in open ? open.favorites : favoritesActive;

  function toggle(id: string, active: boolean) {
    setOpen((current) => ({ ...current, [id]: !(id in current ? current[id] : active) }));
  }

  if (!openSidebar) return null;

  return (
    <aside
      className="flex w-[232px] shrink-0 flex-col border-e"
      style={{
        background: "var(--his-sidebar)",
        borderColor: "var(--his-sidebar-border)",
      }}
    >
      <div className="overflow-y-auto flex-1">
        {favoriteItems.length > 0 ? (
          <div>
            <button
              type="button"
              onClick={() => toggle("favorites", favoritesActive)}
              className="flex w-full items-center gap-2 border-b border-[#c5d0db] bg-[#dce6ef] px-3 py-2 text-start text-[11px] font-bold text-[#1b4f8c]"
            >
              {favoritesOpen ? <ChevronDown size={13} /> : <ChevronLeft size={13} />}
              <Star size={12} className="text-[#c9a227]" fill="#c9a227" />
              {t("favorites")}
            </button>
            {favoritesOpen
              ? favoriteItems.map((item) => (
                  <SidebarItem key={`fav-${item.id}`} item={item} locale={locale} depth={1} />
                ))
              : null}
          </div>
        ) : null}

        {tree.map((node) => (
          <NavBranch
            key={node.kind === "link" ? node.id : node.id}
            node={node}
            depth={0}
            byId={byId}
            locale={locale}
            pathname={pathname}
            open={open}
            toggle={toggle}
          />
        ))}
        {ready && editionName === "pharmacy" && roleNames(user).includes("admin") ? (
          <Link
            href={`/${locale}/dashboard/pharmacy/backups`}
            className={cn(
              "flex items-center gap-2 border-b border-[#d7e0e8] px-3 py-[7px] text-[12px]",
              pathname.includes("/pharmacy/backups")
                ? "bg-[#4a90d9] text-white"
                : "text-[#1a1a1a] hover:bg-[#dce8f3]",
            )}
          >
            {tStation("backup")}
          </Link>
        ) : null}
      </div>
    </aside>
  );
}
