import type { AuthUser } from "@/types/common";
import { modules, type NavModule } from "@/config/navigation";

const desks: Record<string, string[]> = {
  doctor: ["/dashboard/patients", "/dashboard/encounters"],
  lab_technician: ["/dashboard/laboratory"],
  lab_supervisor: ["/dashboard/laboratory"],
  radiology_technician: ["/dashboard/radiology"],
  radiologist: ["/dashboard/radiology"],
  pharmacist: [
    "/dashboard/pharmacy",
    "/dashboard/pharmacy/stock",
    "/dashboard/pharmacy/sale",
    "/dashboard/pharmacy/returns",
    "/dashboard/pharmacy/debts",
    "/dashboard/pharmacy/warehouses",
    "/dashboard/pharmacy/stocktake",
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

export function visibleModules(user: AuthUser | null, edition?: EditionMenu | null): NavModule[] {
  const hrefs = deskHrefs(user);
  const byDesk = !hrefs
    ? modules
    : modules.filter((item) => item.href === "/dashboard" || hrefs.includes(hrefOf(item)));

  if (!edition?.ready) return byDesk.filter((item) => item.id === "home");

  const allowed = byDesk.filter((item) => editionAllows(item, edition));

  if (!ACCOUNT_EDITIONS.has(edition.edition)) return allowed;
  return allowed.map((item) => (item.id === "billing" ? { ...item, labelKey: "nav.account" } : item));
}

export function isDoctorDesk(user: AuthUser | null): boolean {
  const roles = roleNames(user);
  return roles.length === 1 && roles[0] === "doctor";
}

export function canOpenPath(user: AuthUser | null, path: string, edition?: EditionMenu | null): boolean {
  if (edition?.ready && path !== "/dashboard") {
    const sale = path === "/dashboard/pharmacy/sale" || path.startsWith("/dashboard/pharmacy/sale/");
    if (edition.navigation.includes("*") && sale) return false;
    if (!edition.navigation.includes("*")) {
      const matches = modules.filter((item) => {
        const href = hrefOf(item);
        return href !== "/dashboard" && (path === href || path.startsWith(`${href}/`));
      });
      if (matches.length === 0) return false;
      const top = Math.max(...matches.map((item) => hrefOf(item).length));
      const winners = matches.filter((item) => hrefOf(item).length === top);
      const winner = winners.find((item) => editionAllows(item, edition)) ?? winners[0];
      if (!editionAllows(winner, edition)) return false;
    }
  }

  const hrefs = deskHrefs(user);
  if (!hrefs) return true;
  if (path === "/dashboard") return true;
  if (isDoctorDesk(user) && (path === "/dashboard/patients/new" || path.startsWith("/dashboard/patients/new/"))) {
    return false;
  }
  return hrefs.some((href) => path === href || path.startsWith(`${href}/`));
}
