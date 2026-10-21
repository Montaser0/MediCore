"use client";

import { useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { apiClient, getErrorMessage } from "@/lib/api/client";
import { Button } from "@/components/ui/Button";

type ImportResult = {
  created: number;
  updated: number;
  skipped: number;
  failed: number;
  errors?: Array<{ row: number; message: string }>;
};

async function blobMessage(data: unknown): Promise<string | null> {
  if (!(data instanceof Blob)) return null;
  try {
    const json = JSON.parse(await data.text()) as { message?: string };
    return json.message ?? null;
  } catch {
    return null;
  }
}

export function ExcelButtons({
  resource,
  caption,
  exportOnly = false,
  onImported,
}: {
  resource: string;
  caption?: string;
  exportOnly?: boolean;
  onImported?: () => void | Promise<void>;
}) {
  const t = useTranslations();
  const input = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);

  async function download(kind: "export" | "template") {
    setBusy(true);
    try {
      const response = await apiClient.get(`/excel/${resource}/${kind}`, { responseType: "blob" });
      const blob = response.data as Blob;
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `${resource}.xlsx`;
      link.click();
      URL.revokeObjectURL(url);
    } catch (error) {
      const message = await blobMessage(
        (error as { response?: { data?: unknown } })?.response?.data,
      );
      toast.error(message ?? getErrorMessage(error));
    } finally {
      setBusy(false);
    }
  }

  async function upload(file: File) {
    const body = new FormData();
    body.append("file", file);
    setBusy(true);
    try {
      const response = await apiClient.post<{ data: ImportResult }>(`/excel/${resource}/import`, body, {
        headers: { "Content-Type": undefined },
      });
      const result = response.data.data;
      const summary = t("excel.done", {
        created: result.created,
        updated: result.updated,
        skipped: result.skipped,
        failed: result.failed,
      });
      const details = (result.errors ?? [])
        .slice(0, 5)
        .map((error) => `${error.row}: ${error.message}`)
        .join(" — ");
      if (result.failed > 0 && result.created + result.updated === 0) {
        toast.error(summary, { description: details || undefined });
      } else {
        toast.success(summary, { description: details || undefined });
      }
      await onImported?.();
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setBusy(false);
      if (input.current) input.current.value = "";
    }
  }

  return (
    <span className="inline-flex items-center gap-1">
      {caption ? <span className="text-[11px] text-[var(--his-muted)]">{caption}</span> : null}
      <Button disabled={busy} onClick={() => download("export")}>
        {t("excel.export")}
      </Button>
      {exportOnly ? null : (
        <>
          <Button disabled={busy} onClick={() => download("template")}>
            {t("excel.template")}
          </Button>
          <Button disabled={busy} onClick={() => input.current?.click()}>
            {t("excel.import")}
          </Button>
        </>
      )}
      <input
        ref={input}
        type="file"
        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        className="hidden"
        onChange={(event) => {
          const file = event.target.files?.[0];
          if (file) void upload(file);
        }}
      />
    </span>
  );
}
