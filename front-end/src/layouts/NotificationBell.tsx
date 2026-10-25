"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { Bell } from "lucide-react";
import { toast } from "sonner";
import { notificationsApi, type NotificationRow } from "@/modules/notifications/api/notifications.api";

function isUnread(row: NotificationRow) {
  return !row.read_at;
}

export function NotificationBell() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const seen = useRef<Set<string> | null>(null);
  const box = useRef<HTMLDivElement>(null);

  const { data: rows = [] } = useQuery({
    queryKey: ["notifications", "live"],
    queryFn: async () => {
      try {
        const res = await notificationsApi.list();
        return res.data ?? [];
      } catch {
        return [];
      }
    },
    refetchInterval: 4000,
    refetchIntervalInBackground: true,
  });

  useEffect(() => {
    const ids = rows.map((row) => String(row.id));
    if (seen.current === null) {
      seen.current = new Set(ids);
      return;
    }
    const fresh = rows.filter((row) => isUnread(row) && !seen.current?.has(String(row.id)));
    if (fresh.length === 0) return;
    fresh.forEach((row) => seen.current?.add(String(row.id)));
    fresh.forEach((row) => {
      toast(row.title ?? t("nav.notifications"), { description: row.message });
    });
    void qc.invalidateQueries({ queryKey: ["patients"] });
    void qc.invalidateQueries({ queryKey: ["/laboratory/worklist"] });
    void qc.invalidateQueries({ queryKey: ["/radiology/worklist"] });
    void qc.invalidateQueries({ queryKey: ["/pharmacy/prescriptions"] });
  }, [rows, qc, t]);

  useEffect(() => {
    function close(event: MouseEvent) {
      if (!box.current?.contains(event.target as Node)) setOpen(false);
    }
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, []);

  const unread = rows.filter(isUnread).length;

  async function openRow(row: NotificationRow) {
    setOpen(false);
    if (isUnread(row)) {
      await notificationsApi.markRead(row.id).catch(() => undefined);
      void qc.invalidateQueries({ queryKey: ["notifications", "live"] });
    }
    const patientId = row.payload?.patient_id;
    if (patientId) router.push(`/${locale}/dashboard/patients/${patientId}`);
  }

  return (
    <div className="relative" ref={box}>
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        className="relative flex h-8 w-8 items-center justify-center hover:bg-white/10"
        aria-label={t("nav.notifications")}
      >
        <Bell size={16} />
        {unread > 0 ? (
          <span className="absolute -top-0.5 -start-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#c0392b] px-1 text-[10px] leading-none text-white">
            {unread > 9 ? "9+" : unread}
          </span>
        ) : null}
      </button>
      {open ? (
        <div className="absolute end-0 top-9 z-50 w-72 border border-[var(--his-border)] bg-white text-[var(--his-text)] shadow">
          <div className="border-b border-[var(--his-border)] px-2 py-1 text-[12px] font-semibold">
            {t("nav.notifications")}
          </div>
          <div className="max-h-80 overflow-auto">
            {rows.length === 0 ? (
              <p className="px-2 py-3 text-[12px] text-[var(--his-muted)]">{t("app.noData")}</p>
            ) : (
              rows.slice(0, 12).map((row) => (
                <button
                  key={String(row.id)}
                  type="button"
                  onClick={() => void openRow(row)}
                  className="block w-full border-b border-[var(--his-border)] px-2 py-2 text-start hover:bg-[var(--his-accent-soft)]"
                  style={{ background: isUnread(row) ? "#fff8e8" : "transparent" }}
                >
                  <div className="text-[12px] font-semibold">{row.title}</div>
                  {row.message ? <div className="text-[11px] text-[var(--his-muted)]">{row.message}</div> : null}
                </button>
              ))
            )}
          </div>
          <button
            type="button"
            onClick={() => {
              setOpen(false);
              router.push(`/${locale}/dashboard/notifications`);
            }}
            className="block w-full border-t border-[var(--his-border)] px-2 py-2 text-center text-[12px] font-semibold text-[var(--his-accent)] hover:bg-[var(--his-accent-soft)]"
          >
            {t("nav.viewAllNotifications")}
          </button>
        </div>
      ) : null}
    </div>
  );
}
