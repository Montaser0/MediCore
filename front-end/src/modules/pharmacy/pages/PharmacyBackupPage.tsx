"use client";

import { useEffect, useState, type ReactNode } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";

type BackupFile = {
  name: string;
  size: number;
  created_at: string;
  location: string;
  drive?: string | null;
};

type Drive = { letter: string; name?: string | null };

function formatSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function PharmacyBackupPage() {
  const t = useTranslations();
  const qc = useQueryClient();
  const [drive, setDrive] = useState("");
  const [pending, setPending] = useState<BackupFile | null>(null);
  const [busy, setBusy] = useState(false);

  const drives = useQuery({
    queryKey: ["hms-backup-drives"],
    queryFn: async () => (await apiGet<Drive[]>("/system/backup-drives")).data ?? [],
  });
  const connected = drives.data ?? [];
  const driveStatus = drives.isPending
    ? t("station.scanning")
    : drives.isError
      ? getErrorMessage(drives.error)
      : connected.length > 0
        ? null
        : t("station.noDrive");

  useEffect(() => {
    const first = drives.data?.[0]?.letter;
    if (drive === "" && first) setDrive(first);
  }, [drive, drives.data]);

  const local = useQuery({
    queryKey: ["hms-backups", "local"],
    queryFn: async () => (await apiGet<BackupFile[]>("/system/backups", { params: { location: "local" } })).data ?? [],
  });

  const usb = useQuery({
    queryKey: ["hms-backups", "usb", drive],
    enabled: drive !== "",
    queryFn: async () =>
      (await apiGet<BackupFile[]>("/system/backups", { params: { location: "usb", drive } })).data ?? [],
  });

  async function create(target: "local" | "usb") {
    setBusy(true);
    try {
      await apiPost("/system/backups", { target, drive: target === "usb" ? drive : undefined });
      toast.success(t("station.createdOk"));
      await qc.invalidateQueries({ queryKey: ["hms-backups"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setBusy(false);
    }
  }

  async function restore() {
    if (!pending) return;
    setBusy(true);
    try {
      await apiPost("/system/backups/restore", {
        file: pending.name,
        location: pending.location,
        drive: pending.drive,
        confirm: true,
      });
      toast.success(t("station.restoredOk"));
      setPending(null);
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("station.backupTitle")} />
      <div className="grid gap-4 p-3 lg:grid-cols-2">
        <BackupList
          title={t("station.local")}
          rows={local.data ?? []}
          loading={local.isLoading}
          empty={t("station.empty")}
          fileLabel={t("station.file")}
          sizeLabel={t("station.size")}
          createdLabel={t("station.created")}
          restoreLabel={t("station.restore")}
          onRestore={setPending}
        >
          <Button variant="primary" disabled={busy} onClick={() => void create("local")}>
            {t("station.create")}
          </Button>
        </BackupList>
        <BackupList
          title={t("station.usb")}
          rows={drive ? (usb.data ?? []) : []}
          loading={usb.isLoading}
          empty={drive ? t("station.empty") : (driveStatus ?? t("station.chooseDrive"))}
          fileLabel={t("station.file")}
          sizeLabel={t("station.size")}
          createdLabel={t("station.created")}
          restoreLabel={t("station.restore")}
          onRestore={setPending}
        >
          <select
            className="his-input"
            style={{ width: 180 }}
            value={drive}
            onChange={(event) => setDrive(event.target.value)}
          >
            <option value="">{driveStatus ?? t("station.chooseDrive")}</option>
            {connected.map((item) => (
              <option key={item.letter} value={item.letter}>
                {item.name ? `${item.letter}: ${item.name}` : `${item.letter}:`}
              </option>
            ))}
          </select>
          <Button variant="primary" disabled={busy || drive === ""} onClick={() => void create("usb")}>
            {t("station.create")}
          </Button>
        </BackupList>
      </div>
      {pending ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md border border-[#8fa0b0] bg-white p-4 shadow-lg">
            <h2 className="m-0 text-[14px] font-bold">{t("station.confirmTitle")}</h2>
            <p className="text-[12px]">{t("station.confirmBody")}</p>
            <p className="text-[12px] font-semibold" dir="ltr">
              {pending.name}
            </p>
            <div className="mt-3 flex justify-end gap-2">
              <Button disabled={busy} onClick={() => setPending(null)}>
                {t("app.cancel")}
              </Button>
              <Button variant="danger" disabled={busy} onClick={() => void restore()}>
                {t("station.confirmAction")}
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}

function BackupList({
  title,
  rows,
  loading,
  empty,
  fileLabel,
  sizeLabel,
  createdLabel,
  restoreLabel,
  onRestore,
  children,
}: {
  title: string;
  rows: BackupFile[];
  loading: boolean;
  empty: string;
  fileLabel: string;
  sizeLabel: string;
  createdLabel: string;
  restoreLabel: string;
  onRestore: (file: BackupFile) => void;
  children: ReactNode;
}) {
  return (
    <section className="border border-[var(--his-border)] bg-white">
      <div className="flex flex-wrap items-center gap-2 border-b border-[var(--his-border)] px-3 py-2">
        <h2 className="m-0 me-auto text-[13px] font-bold">{title}</h2>
        {children}
      </div>
      <table className="w-full text-[12px]">
        <thead>
          <tr className="border-b border-[var(--his-border)] text-start">
            <th className="px-3 py-2 text-start">{fileLabel}</th>
            <th className="px-3 py-2 text-start">{sizeLabel}</th>
            <th className="px-3 py-2 text-start">{createdLabel}</th>
            <th className="px-3 py-2" />
          </tr>
        </thead>
        <tbody>
          {loading ? null : rows.length === 0 ? (
            <tr>
              <td className="px-3 py-3 text-[var(--his-muted)]" colSpan={4}>
                {empty}
              </td>
            </tr>
          ) : (
            rows.map((row) => (
              <tr key={row.name} className="border-b border-[var(--his-border)]">
                <td className="px-3 py-2" dir="ltr">
                  {row.name}
                </td>
                <td className="px-3 py-2">{formatSize(row.size)}</td>
                <td className="px-3 py-2" dir="ltr">
                  {row.created_at.replace("T", " ").slice(0, 16)}
                </td>
                <td className="px-3 py-2 text-end">
                  <Button onClick={() => onRestore(row)}>{restoreLabel}</Button>
                </td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </section>
  );
}
