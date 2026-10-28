"use client";

import { FormEvent, useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { apiPost, getErrorMessage } from "@/lib/api/client";
import { parseBulletin, type BulletinRow } from "../bulletin";

type Result = { matched: number; shelf_updated: number; missed: number };

export function PharmacyBulletinPanel() {
  const t = useTranslations();
  const [label, setLabel] = useState("");
  const [updateShelf, setUpdateShelf] = useState(true);
  const [fileName, setFileName] = useState("");
  const [rows, setRows] = useState<BulletinRow[]>([]);
  const [saving, setSaving] = useState(false);

  async function onFile(file: File | undefined) {
    setRows([]);
    setFileName(file?.name ?? "");
    if (!file) return;
    const text = await file.text();
    const parsed = parseBulletin(text);
    setRows(parsed);
    if (parsed.length === 0) toast.error(t("pharmacy.bulletinEmpty"));
  }

  async function apply(event: FormEvent) {
    event.preventDefault();
    if (rows.length === 0 || label.trim() === "") return;
    setSaving(true);
    const totals = { matched: 0, shelf_updated: 0, missed: 0 };
    try {
      for (let index = 0; index < rows.length; index += 400) {
        const res = await apiPost<Result>("/pharmacy/catalogue/bulletin", {
          label: label.trim(),
          update_shelf: updateShelf,
          rows: rows.slice(index, index + 400),
        });
        totals.matched += res.data?.matched ?? 0;
        totals.shelf_updated += res.data?.shelf_updated ?? 0;
        totals.missed += res.data?.missed ?? 0;
      }
      toast.success(t("pharmacy.bulletinDone", totals));
      setRows([]);
      setFileName("");
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={apply} className="grid gap-2 border-b border-[var(--his-border)] bg-white p-2 md:grid-cols-[1fr_1fr_auto_auto]">
      <div className="md:col-span-4 text-[12px] text-[var(--his-muted)]">{t("pharmacy.bulletinHint")}</div>
      <Input value={label} onChange={(event) => setLabel(event.target.value)} placeholder={t("pharmacy.bulletinLabel")} required />
      <label className="flex items-center gap-2 text-[12px]">
        <input type="file" accept=".csv,.txt" onChange={(event) => void onFile(event.target.files?.[0])} />
        <span>{fileName ? `${fileName} · ${rows.length}` : t("pharmacy.bulletinFile")}</span>
      </label>
      <label className="flex items-center gap-2 text-[12px]">
        <input type="checkbox" checked={updateShelf} onChange={(event) => setUpdateShelf(event.target.checked)} />
        {t("pharmacy.bulletinShelf")}
      </label>
      <Button type="submit" variant="primary" disabled={saving || rows.length === 0}>
        {t("pharmacy.bulletinApply")}
      </Button>
    </form>
  );
}
