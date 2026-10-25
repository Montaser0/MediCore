"use client";

import { useState, type ReactNode } from "react";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { Button } from "@/components/ui/Button";
import { apiClient, apiGet } from "@/lib/api/client";
import { DocumentGallery, type StoredFile } from "@/components/shared/DocumentGallery";

const IMAGING_NAME = /\.(dcm|dicom|jpe?g|png|pdf|zip)$/i;
const IGNORED_NAME = /^(dicomdir|\.ds_store|thumbs\.db|desktop\.ini)$/i;

function stagedFile(file: File): File {
  const relative = file.webkitRelativePath?.replaceAll("\\", "/").replace(/^\/+/, "") ?? "";
  if (!relative.includes("/")) return file;
  const safe = relative
    .split("/")
    .filter((part) => part && part !== "." && part !== "..")
    .join("__");
  if (!safe || safe === file.name) return file;
  return new File([file], safe, { type: file.type, lastModified: file.lastModified });
}

function leafName(name: string): string {
  const parts = name.split("__");
  return parts[parts.length - 1] ?? name;
}

async function acceptImagingFile(file: File): Promise<File | null> {
  const named = stagedFile(file);
  const leaf = leafName(named.name);
  if (!leaf || leaf.startsWith(".") || IGNORED_NAME.test(leaf)) return null;
  if (IMAGING_NAME.test(leaf)) return named;
  if (leaf.includes(".")) return null;
  return (await looksLikeDicom(named)) ? named : null;
}

async function looksLikeDicom(file: File): Promise<boolean> {
  if (file.size < 132) return false;
  const bytes = new Uint8Array(await file.slice(128, 132).arrayBuffer());
  return bytes.length === 4 && String.fromCharCode(bytes[0], bytes[1], bytes[2], bytes[3]) === "DICM";
}

export type { StoredFile };
export { openStoredFile, storedFileCaption } from "@/components/shared/DocumentGallery";

export async function uploadResultFiles(
  files: File[],
  meta: {
    entityType: string;
    entityId: number;
    patientId?: number;
    encounterId?: number;
    category: string;
  },
) {
  for (const file of files) {
    const body = new FormData();
    body.append("file", file);
    body.append("entity_type", meta.entityType);
    body.append("entity_id", String(meta.entityId));
    body.append("category", meta.category);
    if (meta.patientId) body.append("patient_id", String(meta.patientId));
    if (meta.encounterId) body.append("encounter_id", String(meta.encounterId));
    await apiClient.post("/files", body, { headers: { "Content-Type": undefined } });
  }
}

export function OrderResultDialog({
  title,
  subtitle,
  busy,
  extra,
  patientId,
  accept = "image/*,.pdf,.dcm",
  allowFolder = false,
  hint,
  resultAction = true,
  onClose,
  onSave,
}: {
  title: string;
  subtitle: string;
  busy: boolean;
  extra?: ReactNode;
  patientId?: number;
  accept?: string;
  allowFolder?: boolean;
  hint?: string;
  resultAction?: boolean;
  onClose: () => void;
  onSave: (input: { text: string; files: File[]; done: boolean }) => Promise<void>;
}) {
  const t = useTranslations();
  const [text, setText] = useState("");
  const [files, setFiles] = useState<File[]>([]);
  const { data: stored = [] } = useQuery({
    queryKey: ["patient-documents", patientId],
    enabled: Boolean(patientId),
    queryFn: async () => {
      const response = await apiGet<StoredFile[]>(`/patients/${patientId}/documents`);
      return response.data ?? [];
    },
  });

  async function addFiles(list: FileList | null) {
    const chosen = Array.from(list ?? []);
    const next: File[] = [];
    for (const file of chosen) {
      const kept = allowFolder ? await acceptImagingFile(file) : file;
      if (kept) next.push(kept);
    }
    if (allowFolder && chosen.length > 0 && next.length === 0) {
      toast.error(t("radiology.noImagingFiles"));
    }
    setFiles((current) => {
      const seen = new Set(current.map((file) => `${file.name}:${file.size}:${file.lastModified}`));
      const merged = [...current];
      for (const file of next) {
        const key = `${file.name}:${file.size}:${file.lastModified}`;
        if (seen.has(key)) continue;
        seen.add(key);
        merged.push(file);
      }
      return merged;
    });
  }

  async function submit(event: { preventDefault(): void }, done: boolean) {
    event.preventDefault();
    await onSave({ text, files, done });
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
      <form className="his-panel flex max-h-[90vh] w-full max-w-3xl flex-col" onSubmit={(event) => submit(event, true)}>
        <div className="his-panel-header">{title}</div>
        <div className="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
        <div className="text-[12px]">{subtitle}</div>
        <textarea
          className="his-input min-h-20 w-full"
          placeholder={t("laboratory.resultValue")}
          value={text}
          onChange={(event) => setText(event.target.value)}
        />
        <label className="grid gap-1 text-[12px]">
          <span>{t("radiology.upload")}</span>
          {hint ? <span className="text-[11px] text-[var(--his-muted)]">{hint}</span> : null}
          <input
            type="file"
            accept={accept}
            multiple
            onChange={(event) => {
              void addFiles(event.target.files);
              event.target.value = "";
            }}
          />
          {allowFolder ? (
            <>
              <span>{t("radiology.uploadFolder")}</span>
              <input
                type="file"
                multiple
                {...{ webkitdirectory: "", directory: "" }}
                onChange={(event) => {
                  void addFiles(event.target.files);
                  event.target.value = "";
                }}
              />
            </>
          ) : null}
          {files.length > 0 ? (
            <span className="text-[11px] text-[var(--his-muted)]">{t("radiology.uploadCount", { count: files.length })}</span>
          ) : null}
        </label>
        {stored.length > 0 ? (
          <div className="border border-[var(--his-border)] bg-[var(--his-bg)] p-2">
            <div className="mb-2 text-[12px] font-semibold text-[var(--his-header)]">{t("patients.docs")}</div>
            <DocumentGallery files={stored} />
          </div>
        ) : null}
        {extra}
        </div>
        <div className="flex justify-end gap-1 border-t border-[var(--his-border)] bg-[var(--his-panel-alt)] p-2">
          <Button type="button" onClick={onClose}>
            {t("app.cancel")}
          </Button>
          {resultAction ? (
            <Button type="button" disabled={busy} onClick={(event) => submit(event, false)}>
              {t("laboratory.result")}
            </Button>
          ) : null}
          <Button type="submit" variant="success" disabled={busy}>
            {t("app.done")}
          </Button>
        </div>
      </form>
    </div>
  );
}
