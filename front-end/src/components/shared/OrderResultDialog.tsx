"use client";

import { useState, type ReactNode } from "react";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { apiClient, apiGet } from "@/lib/api/client";
import { DocumentGallery, type StoredFile } from "@/components/shared/DocumentGallery";

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
  onClose,
  onSave,
}: {
  title: string;
  subtitle: string;
  busy: boolean;
  extra?: ReactNode;
  patientId?: number;
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
          <input
            type="file"
            accept="image/*,.pdf,.dcm"
            multiple
            onChange={(event) => setFiles(Array.from(event.target.files ?? []))}
          />
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
          <Button type="button" disabled={busy} onClick={(event) => submit(event, false)}>
            {t("laboratory.result")}
          </Button>
          <Button type="submit" variant="success" disabled={busy}>
            {t("app.done")}
          </Button>
        </div>
      </form>
    </div>
  );
}
