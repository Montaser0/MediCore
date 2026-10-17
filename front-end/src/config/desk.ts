import type { AuthUser } from "@/types/common";
import { modules, type NavModule } from "@/config/navigation";

const desks: Record<string, string[]> = {
  doctor: ["/dashboard/patients", "/dashboard/encounters"],
  data_entry: ["/dashboard/patients", "/dashboard/encounters"],
  lab_technician: ["/dashboard/laboratory"],
  lab_supervisor: ["/dashboard/laboratory", "/dashboard/lab-tests", "/dashboard/blood-products"],
  radiology_technician: ["/dashboard/radiology"],
  radiologist: ["/dashboard/radiology", "/dashboard/radiology-services"],
  pharmacist: [
    "/dashboard/pharmacy",
    "/dashboard/pharmacy/stock",
    "/dashboard/pharmacy/sale",
    "/dashboard/pharmacy/returns",
    "/dashboard/pharmacy/debts",
    "/dashboard/pharmacy/warehouses",
    "/dashboard/pharmacy/stocktake",
    "/dashboard/pharmacy/backups",
  ],
};

export type EditionMenu = {
  ready: boolean;
  edition: string;
  navigation: string[];
};

const ACCOUNT_EDITIONS = new Set(["clinic", "laboratory", "radiology"]);
const POS_ONLY = new Set(["pharmacy-catalog", "pharmacy-sale", "pharmacy-return", "pharmacy-debts", "pharmacy-warehouses", "pharmacy-stocktake"]);

function hrefOf(item: NavModule) {
  return item.href.split("?")[0];
}

function editionAllows(item: NavModule, edition: EditionMenu) {
  if (edition.navigation.includes("*")) return !POS_ONLY.has(item.id);
  return edition.navigation.includes(item.id);
}

export function roleNames(user: AuthUser | null): string[] {
  return (user?.roles ?? []).map((role) => (typeof role === "string" ? role : role.name));
}

/** Specialist desks see only their own pages. Admins and mixed roles keep the full menu. */
export function deskHrefs(user: AuthUser | null): string[] | null {
  const roles = roleNames(user);
  if (roles.length === 0 || roles.includes("admin")) return null;
  const matched = roles.filter((role) => role in desks);
  const others = roles.filter((role) => !(role in desks));
  if (matched.length === 0 || others.length > 0) return null;
  return [...new Set(matched.flatMap((role) => desks[role]))];
}

function hasPermission(user: AuthUser | null, permission: string): boolean {
  const perms = user?.permissions ?? [];
  if (perms.includes("*") || perms.includes("admin")) return true;
  return perms.includes(permission);
}

/** Invoices stay with whoever holds the billing permissions: reception in a clinic, the accountant in a hospital. */
export function canSeeBilling(user: AuthUser | null): boolean {
  return hasPermission(user, "billing.invoices.view") || hasPermission(user, "billing.charges.view");
}

function mayOpen(user: AuthUser | null, item: NavModule): boolean {
  if (!item.permission) return true;
  const required = Array.isArray(item.permission) ? item.permission : [item.permission];
  return required.some((permission) => hasPermission(user, permission));
}

function usesCustomScreens(user: AuthUser | null): boolean {
  return user?.custom_permissions === true;
}

function screenGranted(user: AuthUser | null, item: NavModule): boolean {
  if (item.id === "home" || item.id === "notifications") return true;
  return (user?.screens ?? []).includes(item.id);
}

export function moduleAllowedByEdition(id: string, edition?: EditionMenu | null): boolean {
  const item = modules.find((entry) => entry.id === id);
  if (!item || !edition?.ready) return true;
  return editionAllows(item, edition);
}

function modulesForPath(path: string): NavModule[] {
  return modules.filter((item) => {
    const href = hrefOf(item);
    return href !== "/dashboard" && (path === href || path.startsWith(`${href}/`));
  });
}

function longestMatch(matches: NavModule[]): NavModule {
  const top = Math.max(...matches.map((item) => hrefOf(item).length));
  return matches.find((item) => hrefOf(item).length === top) ?? matches[0];
}

function editionBlocks(path: string, edition?: EditionMenu | null): boolean {
  if (!edition?.ready || path === "/dashboard") return false;
  const sale = path === "/dashboard/pharmacy/sale" || path.startsWith("/dashboard/pharmacy/sale/");
  if (edition.navigation.includes("*") && sale) return true;
  if (!edition.navigation.includes("*")) {
    const matches = modulesForPath(path);
    if (matches.length === 0) return true;
    const top = Math.max(...matches.map((item) => hrefOf(item).length));
    const winners = matches.filter((item) => hrefOf(item).length === top);
    const winner = winners.find((item) => editionAllows(item, edition)) ?? winners[0];
    if (!editionAllows(winner, edition)) return true;
  }
  return false;
}

function applyEditionLabels(items: NavModule[], edition?: EditionMenu | null): NavModule[] {
  if (!edition || !ACCOUNT_EDITIONS.has(edition.edition)) return items;
  return items.map((item) => (item.id === "billing" ? { ...item, labelKey: "nav.account" } : item));
}

export function visibleModules(user: AuthUser | null, edition?: EditionMenu | null): NavModule[] {
  const hrefs = usesCustomScreens(user) ? null : deskHrefs(user);
  const byDesk = !hrefs
    ? modules
    : modules.filter((item) => item.href === "/dashboard" || hrefs.includes(hrefOf(item)));
  const byAccess = usesCustomScreens(user) ? byDesk.filter((item) => screenGranted(user, item)) : byDesk;

  if (!edition?.ready) return byAccess.filter((item) => item.id === "home");

  const allowed = byAccess.filter(
    (item) => editionAllows(item, edition) && (usesCustomScreens(user) || mayOpen(user, item)),
  );

  return applyEditionLabels(allowed, edition);
}

export function isDoctorDesk(user: AuthUser | null): boolean {
  const roles = roleNames(user);
  return roles.length === 1 && (roles[0] === "doctor" || roles[0] === "data_entry");
}

export function canOpenPath(user: AuthUser | null, path: string, edition?: EditionMenu | null): boolean {
  if (editionBlocks(path, edition)) return false;

  if (usesCustomScreens(user)) {
    if (path === "/dashboard") return true;
    if (path === "/dashboard/notifications" || path.startsWith("/dashboard/notifications/")) return true;
    const matches = modulesForPath(path);
    if (matches.length === 0) return false;
    return screenGranted(user, longestMatch(matches));
  }

  const hrefs = deskHrefs(user);
  const matches = modulesForPath(path);
  if (matches.length > 0 && !mayOpen(user, longestMatch(matches))) return false;
  if (!hrefs) return true;
  if (path === "/dashboard") return true;
  if (isDoctorDesk(user) && (path === "/dashboard/patients/new" || path.startsWith("/dashboard/patients/new/"))) {
    return false;
  }
  return hrefs.some((href) => path === href || path.startsWith(`${href}/`));
}
