"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { CatalogueSearch } from "@/modules/pharmacy/components/CatalogueSearch";

export type PrescriptionLine = {
  catalogue_item_id?: number;
  name: string;
  dose: string;
  frequency: string;
  duration: string;
  quantity: number;
};

export function MedicationPrescriptionForm({
  pending,
  onCancel,
  onSubmit,
}: {
  pending: boolean;
  onCancel: () => void;
  onSubmit: (input: { fulfillment: "hospital" | "external"; items: PrescriptionLine[] }) => void;
}) {
  const t = useTranslations();
  const [fulfillment, setFulfillment] = useState<"hospital" | "external">("hospital");
  const [lines, setLines] = useState<PrescriptionLine[]>([]);
  const [picked, setPicked] = useState<{ id: number; name: string } | null>(null);
  const [typed, setTyped] = useState("");
  const [dose, setDose] = useState("");
  const [frequency, setFrequency] = useState("");
  const [duration, setDuration] = useState("");
  const [quantity, setQuantity] = useState("1");
  const [searchKey, setSearchKey] = useState(0);

  function draftLine(): PrescriptionLine | null {
    const amount = Number(quantity);
    const quantityValue = Number.isFinite(amount) && amount > 0 ? amount : 1;
    const details = {
      dose: dose.trim(),
      frequency: frequency.trim(),
      duration: duration.trim(),
      quantity: quantityValue,
    };
    if (picked) {
      return { catalogue_item_id: picked.id, name: picked.name, ...details };
    }
    const written = typed.trim();
    if (!written) return null;
    return { name: written, ...details };
  }

  function addLine() {
    const line = draftLine();
    if (!line) {
      toast.error(t("workspace.rxNeedDrug"));
      return;
    }
    setLines((current) => [...current, line]);
    setPicked(null);
    setTyped("");
    setDose("");
    setFrequency("");
    setDuration("");
    setQuantity("1");
    setSearchKey((value) => value + 1);
  }

  function submit() {
    const pendingLine = draftLine();
    const items = pendingLine ? [...lines, pendingLine] : lines;
    if (items.length === 0) {
      toast.error(t("workspace.rxNeedDrug"));
      return;
    }
    onSubmit({ fulfillment, items });
  }

  return (
    <form
      className="space-y-2 p-3"
      onSubmit={(event) => {
        event.preventDefault();
        submit();
      }}
    >
      <fieldset className="space-y-1">
        <legend className="mb-1 text-[12px] text-[var(--his-muted)]">{t("workspace.rxSource")}</legend>
        <label className="flex items-center gap-2 text-[13px]">
          <input
            type="radio"
            name="fulfillment"
            checked={fulfillment === "hospital"}
            onChange={() => setFulfillment("hospital")}
          />
          {t("workspace.rxHospital")}
        </label>
        <label className="flex items-center gap-2 text-[13px]">
          <input
            type="radio"
            name="fulfillment"
            checked={fulfillment === "external"}
            onChange={() => setFulfillment("external")}
          />
          {t("workspace.rxExternal")}
        </label>
        <p className="text-[12px] text-[var(--his-muted)]">
          {fulfillment === "hospital" ? t("workspace.rxHospitalHint") : t("workspace.rxExternalHint")}
        </p>
      </fieldset>

      <div>
        <span className="mb-1 block text-[12px] text-[var(--his-muted)]">{t("workspace.medAction")}</span>
        <CatalogueSearch
          key={searchKey}
          nameOnly
          placeholder={t("workspace.rxDrugPlaceholder")}
          emptyText={t("workspace.rxNotInList")}
          selectedLabel={picked?.name ?? ""}
          onQueryChange={(value) => {
            setTyped(value);
            if (value.trim()) setPicked(null);
          }}
          onSelect={(item) => setPicked({ id: item.id, name: item.name })}
        />
      </div>
      <div className="grid grid-cols-2 gap-2">
        <label className="block text-[12px]">
          <span className="mb-1 block text-[var(--his-muted)]">{t("workspace.dose")}</span>
          <Input value={dose} onChange={(event) => setDose(event.target.value)} />
        </label>
        <label className="block text-[12px]">
          <span className="mb-1 block text-[var(--his-muted)]">{t("workspace.frequency")}</span>
          <Input value={frequency} onChange={(event) => setFrequency(event.target.value)} />
        </label>
        <label className="block text-[12px]">
          <span className="mb-1 block text-[var(--his-muted)]">{t("workspace.duration")}</span>
          <Input value={duration} onChange={(event) => setDuration(event.target.value)} />
        </label>
        <label className="block text-[12px]">
          <span className="mb-1 block text-[var(--his-muted)]">{t("workspace.quantity")}</span>
          <Input type="number" min={1} value={quantity} onChange={(event) => setQuantity(event.target.value)} />
        </label>
      </div>
      <Button type="button" onClick={addLine}>
        {t("workspace.rxAddLine")}
      </Button>

      {lines.length > 0 ? (
        <ul className="m-0 space-y-1 border border-[var(--his-border)] p-2 text-[12px]">
          {lines.map((line, index) => (
            <li key={`${line.catalogue_item_id ?? line.name}-${index}`} className="flex items-center justify-between gap-2">
              <span>
                {line.name}
                {line.dose ? ` — ${line.dose}` : ""}
                {line.quantity ? ` × ${line.quantity}` : ""}
              </span>
              <Button
                type="button"
                variant="danger"
                onClick={() => setLines((current) => current.filter((_, item) => item !== index))}
              >
                {t("app.delete")}
              </Button>
            </li>
          ))}
        </ul>
      ) : null}

      <div className="flex flex-wrap justify-end gap-2 pt-2">
        <Button type="button" onClick={onCancel}>
          {t("app.cancel")}
        </Button>
        <Button type="submit" variant="primary" disabled={pending}>
          {t("workspace.rxPrint")}
        </Button>
      </div>
    </form>
  );
}
