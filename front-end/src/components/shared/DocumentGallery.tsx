"use client";

import { useEffect, useState } from "react";
import { Download, FileImage, FileText, ScanLine } from "lucide-react";
import { useTranslations } from "next-intl";
import { apiClient } from "@/lib/api/client";
import { formatDate } from "@/lib/utils";

export type StoredFile = {
  uuid?: string;
  original_name?: string;
  mime_type?: string;
  category?: string;
  exam_name?: string;
  by?: string;
  created_at?: string;
};

export function storedFileCaption(file: { exam_name?: string; original_name?: string }) {
  const exam = file.exam_name?.trim();
  const name = file.original_name?.trim();
  if (exam && name) return `${exam} — ${name}`;
  return exam || name || "";
}

export async function openStoredFile(uuid: string) {
  const response = await apiClient.get(`/files/${uuid}`, {
    params: { inline: 1 },
    responseType: "blob",
  });
  const url = URL.createObjectURL(response.data);
  window.open(url, "_blank", "noopener");
}

export async function downloadStoredFile(uuid: string, filename?: string) {
  const response = await apiClient.get(`/files/${uuid}`, { responseType: "blob" });
  const url = URL.createObjectURL(response.data);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename?.trim() || "file";
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

function examKind(exam?: string) {
  const name = exam?.trim() ?? "";
  if (name.startsWith("تحليل")) return "lab";
  if (name.startsWith("صورة")) return "image";
  if (name.startsWith("دواء")) return "med";
  return "file";
}

function kindClass(kind: string) {
  if (kind === "lab") return "bg-[var(--his-info)]";
  if (kind === "image") return "bg-[var(--his-teal)]";
  if (kind === "med") return "bg-[var(--his-warning)]";
  return "bg-[var(--his-accent)]";
}

function kindLabel(exam?: string) {
  const kind = examKind(exam);
  if (kind === "lab") return "تحليل";
  if (kind === "image") return "صورة";
  if (kind === "med") return "دواء";
  return "ملف";
}

function isImage(mime?: string) {
  return Boolean(mime?.startsWith("image/"));
}

function useImagePreview(file: StoredFile) {
  const [url, setUrl] = useState<string | null>(null);
  const uuid = file.uuid;
  const mime = file.mime_type;

  useEffect(() => {
    if (!uuid || !isImage(mime)) return;
    let active = true;
    let objectUrl = "";
    apiClient
      .get(`/files/${uuid}`, { params: { inline: 1 }, responseType: "blob" })
      .then((response) => {
        objectUrl = URL.createObjectURL(response.data);
        if (active) setUrl(objectUrl);
        else URL.revokeObjectURL(objectUrl);
      })
      .catch(() => undefined);
    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      setUrl(null);
    };
  }, [uuid, mime]);

  return url;
}

function FileGlyph({ mime }: { mime?: string }) {
  const className = "text-[var(--his-accent)]";
  if (mime?.includes("pdf")) return <FileText size={36} className={className} />;
  if (mime?.includes("dicom") || mime === "application/dicom") return <ScanLine size={36} className={className} />;
  return <FileImage size={36} className={className} />;
}

function shortDate(value?: string) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return formatDate(value);
}

function DocumentCard({ file }: { file: StoredFile }) {
  const t = useTranslations();
  const preview = useImagePreview(file);
  const kind = examKind(file.exam_name);
  const title = file.exam_name?.trim() || file.original_name || "ملف";

  return (
    <div className="overflow-hidden border border-[var(--his-border)] bg-white transition hover:border-[var(--his-accent)] hover:shadow-sm">
      <button
        type="button"
        onClick={() => file.uuid && openStoredFile(file.uuid)}
        className="block w-full text-start"
      >
        <div className="relative flex h-32 items-center justify-center bg-[var(--his-panel-alt)]">
          {preview ? (
            // Authenticated preview; the file name is the accessible label below.
            // eslint-disable-next-line @next/next/no-img-element
            <img src={preview} alt="" className="h-full w-full object-cover" />
          ) : (
            <FileGlyph mime={file.mime_type} />
          )}
          <span className={`absolute start-1.5 top-1.5 px-1.5 py-0.5 text-[10px] font-semibold text-white ${kindClass(kind)}`}>
            {kindLabel(file.exam_name)}
          </span>
        </div>
        <div className="space-y-0.5 px-2 py-1.5">
          <div className="truncate text-[12px] font-semibold text-[var(--his-header)]">{title}</div>
          <div className="truncate text-[11px] text-[var(--his-muted)]">{file.original_name}</div>
          {file.by ? <div className="truncate text-[11px]">{file.by}</div> : null}
          {shortDate(file.created_at) ? (
            <div className="text-[10px] text-[var(--his-muted)]">{shortDate(file.created_at)}</div>
          ) : null}
        </div>
      </button>
      <div className="px-2 pb-2">
        <button
          type="button"
          className="his-btn h-6 w-full px-2 text-[11px]"
          onClick={() => file.uuid && downloadStoredFile(file.uuid, file.original_name)}
        >
          <Download size={12} />
          {t("app.download")}
        </button>
      </div>
    </div>
  );
}

export function DocumentGallery({ files, empty }: { files: StoredFile[]; empty?: string }) {
  const visible = files.filter((file) => file.uuid);
  if (visible.length === 0) {
    return empty ? <div className="text-[var(--his-muted)]">{empty}</div> : null;
  }

  return (
    <div className="grid grid-cols-[repeat(auto-fill,minmax(168px,1fr))] gap-2">
      {visible.map((file) => (
        <DocumentCard key={file.uuid} file={file} />
      ))}
    </div>
  );
}
