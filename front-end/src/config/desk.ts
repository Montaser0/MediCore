import type { AuthUser } from "@/types/common";
import { modules, type NavModule } from "@/config/navigation";

const desks: Record<string, string[]> = {
  doctor: ["/dashboard/patients", "/dashboard/encounters"],
  lab_technician: ["/dashboard/laboratory"],
  lab_supervisor: ["/dashboard/laboratory"],
  radiology_technician: ["/dashboard/radiology"],
  radiologist: ["/dashboard/radiology"],
  pharmacist: ["/dashboard/pharmacy", "/dashboard/pharmacy/stock"],
};

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

export function visibleModules(user: AuthUser | null): NavModule[] {
  const hrefs = deskHrefs(user);
  if (!hrefs) return modules;
  return modules.filter(
    (item) => item.href === "/dashboard" || hrefs.includes(item.href.split("?")[0]),
  );
}

export function isDoctorDesk(user: AuthUser | null): boolean {
  const roles = roleNames(user);
  return roles.length === 1 && roles[0] === "doctor";
}

export function canOpenPath(user: AuthUser | null, path: string): boolean {
  const hrefs = deskHrefs(user);
  if (!hrefs) return true;
  if (path === "/dashboard") return true;
  if (isDoctorDesk(user) && (path === "/dashboard/patients/new" || path.startsWith("/dashboard/patients/new/"))) {
    return false;
  }
  return hrefs.some((href) => path === href || path.startsWith(`${href}/`));
}
