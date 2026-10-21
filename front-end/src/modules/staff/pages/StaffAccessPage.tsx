"use client";

import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { apiGet, apiPatch, getErrorMessage } from "@/lib/api/client";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { cn } from "@/lib/utils";

const ROLE_LABELS = {
  admin: "staffRoles.admin",
  reception: "staffRoles.reception",
  doctor: "staffRoles.doctor",
  data_entry: "staffRoles.data_entry",
  nurse: "staffRoles.nurse",
  lab_technician: "staffRoles.lab_technician",
  lab_supervisor: "staffRoles.lab_supervisor",
  radiology_technician: "staffRoles.radiology_technician",
  radiologist: "staffRoles.radiologist",
  pharmacist: "staffRoles.pharmacist",
  cashier: "staffRoles.cashier",
  warehouse: "staffRoles.warehouse",
  technician: "staffRoles.technician",
} as const;

type StaffRecord = {
  id: number;
  full_name?: string;
  screens?: string[];
  user?: { username?: string; roles?: string[] } | null;
};

type AccessCatalogue = {
  groups: Array<{
    id: string;
    label_key: string;
    screens: Array<{ id: string; label_key: string }>;
  }>;
};

const ALWAYS_ON = [
  { id: "home", label_key: "nav.home" },
  { id: "notifications", label_key: "nav.notifications" },
] as const;

export function StaffAccessPage({ staffId }: { staffId: string }) {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const qc = useQueryClient();
  const [screens, setScreens] = useState<string[] | null>(null);
  const [saving, setSaving] = useState(false);

  const { data: staff, isLoading } = useQuery({
    queryKey: ["/staff", staffId],
    queryFn: async () => {
      const res = await apiGet<StaffRecord>(`/staff/${staffId}`);
      return res.data;
    },
  });

  const { data: catalogue } = useQuery({
    queryKey: ["/staff/access"],
    queryFn: async () => {
      const res = await apiGet<AccessCatalogue>("/staff/access");
      return res.data;
    },
  });

  useEffect(() => {
    if (staff && screens === null) setScreens(staff.screens ?? []);
  }, [staff, screens]);

  const selected = screens ?? [];
  const groups = catalogue?.groups ?? [];
  const allIds = groups.flatMap((group) => group.screens.map((screen) => screen.id));
  const role = staff?.user?.roles?.[0] ?? "";
  const roleName = role in ROLE_LABELS ? t(ROLE_LABELS[role as keyof typeof ROLE_LABELS]) : role;

  function labelOf(key: string) {
    return t(key as "nav.patients");
  }

  function toggleScreen(id: string) {
    setScreens((current) => {
      const list = current ?? [];
      return list.includes(id) ? list.filter((item) => item !== id) : [...list, id];
    });
  }

  function setGroup(ids: string[], on: boolean) {
    setScreens((current) => {
      const list = current ?? [];
      const rest = list.filter((id) => !ids.includes(id));
      return on ? [...rest, ...ids] : rest;
    });
  }

  async function save() {
    if (!staff?.user) return;
    setSaving(true);
    try {
      await apiPatch(`/staff/${staffId}`, { screens: selected });
      toast.success(t("workspace.saved"));
      await qc.invalidateQueries({ queryKey: ["/staff"] });
      router.push(`/${locale}/dashboard/staff`);
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("access.title")}>
        <Button onClick={() => router.push(`/${locale}/dashboard/staff`)}>{t("app.back")}</Button>
        <Button variant="primary" onClick={save} disabled={saving || !staff?.user || screens === null}>
          {t("app.save")}
        </Button>
      </PageToolbar>
      <div className="flex-1 space-y-3 overflow-auto p-3">
        <div>
          <div className="text-sm font-semibold">{staff?.full_name ?? (isLoading ? t("app.loading") : "—")}</div>
          <p className="text-[12px] text-[#3a4a5a]">
            {[roleName, staff?.user?.username].filter(Boolean).join(" — ") || t("access.noAccount")}
          </p>
          <p className="mt-1 text-[12px] text-[#3a4a5a]">{t("access.hint")}</p>
        </div>

        {staff && !staff.user ? <p className="text-[12px]">{t("access.noAccount")}</p> : null}

        <section className="space-y-2 border border-[var(--his-border)] bg-white p-2">
          <div className="text-[12px] font-semibold">{t("nav.home")}</div>
          <div className="grid gap-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {ALWAYS_ON.map((item) => (
              <label key={item.id} className="flex items-center gap-2 border border-[#1b4f8c] bg-[#e7f1fb] px-2 py-1.5 text-[12px]">
                <input type="checkbox" checked disabled />
                <span>{labelOf(item.label_key)}</span>
                <span className="text-[10px] text-[#3a4a5a]">{t("access.always")}</span>
              </label>
            ))}
          </div>
        </section>

        <div className="flex gap-1">
          <Button type="button" onClick={() => setGroup(allIds, true)} disabled={!staff?.user}>
            {t("access.all")}
          </Button>
          <Button type="button" onClick={() => setScreens([])} disabled={!staff?.user}>
            {t("access.none")}
          </Button>
        </div>

        {groups.map((group) => {
          const ids = group.screens.map((screen) => screen.id);
          const allOn = ids.length > 0 && ids.every((id) => selected.includes(id));
          return (
            <section key={group.id} className="space-y-2 border border-[var(--his-border)] bg-white p-2">
              <div className="flex items-center justify-between gap-2">
                <span className="text-[13px] font-semibold">{labelOf(group.label_key)}</span>
                <button
                  type="button"
                  className="text-[11px] text-[#1b4f8c] disabled:opacity-40"
                  disabled={!staff?.user}
                  onClick={() => setGroup(ids, !allOn)}
                >
                  {allOn ? t("access.none") : t("access.all")}
                </button>
              </div>
              <div className="grid gap-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {group.screens.map((screen) => {
                  const checked = selected.includes(screen.id);
                  return (
                    <label
                      key={screen.id}
                      className={cn(
                        "flex cursor-pointer items-center gap-2 border px-2 py-1.5 text-[12px]",
                        checked ? "border-[#1b4f8c] bg-[#e7f1fb]" : "border-[#d7e0e8] bg-white",
                        !staff?.user && "cursor-not-allowed opacity-60",
                      )}
                    >
                      <input
                        type="checkbox"
                        checked={checked}
                        disabled={!staff?.user}
                        onChange={() => toggleScreen(screen.id)}
                      />
                      <span>{labelOf(screen.label_key)}</span>
                    </label>
                  );
                })}
              </div>
            </section>
          );
        })}
      </div>
    </div>
  );
}
