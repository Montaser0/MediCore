"use client";

import { useEffect, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { apiGet, getErrorMessage } from "@/lib/api/client";
import { dailyNo } from "@/lib/arabicText";
import { BarcodeImage } from "@/modules/inventory/components/BarcodeImage";
import { printLabBarcode } from "../print/printLabBarcode";

type BloodTicket = {
  id: number;
  barcode?: string;
  clinic_name?: string | null;
  product_label?: string | null;
  blood_group?: string | null;
  units?: number | null;
  status_label?: string | null;
  patient?: { id?: number; mrn?: string; full_name?: string } | null;
};

function barcodeDigits(value: string) {
  return value
    .replace(/[٠-٩]/g, (digit) => String("٠١٢٣٤٥٦٧٨٩".indexOf(digit)))
    .replace(/[۰-۹]/g, (digit) => String("۰۱۲۳۴۵۶۷۸۹".indexOf(digit)))
    .replace(/\D/g, "");
}

export function BloodBarcodePanel({
  onOpen,
}: {
  onOpen?: (row: Record<string, unknown>) => void;
}) {
  const t = useTranslations();
  const inputRef = useRef<HTMLInputElement>(null);
  const seen = useRef("");
  const [code, setCode] = useState("");
  const [ticket, setTicket] = useState<BloodTicket | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  async function lookup(raw: string, manual = false) {
    const digits = barcodeDigits(raw);
    if (digits.length < 8) {
      if (manual) toast.error(t("laboratory.bloodBarcodeMissing"));
      return;
    }
    if (!manual && seen.current === digits) return;
    setBusy(true);
    try {
      const res = await apiGet<BloodTicket>(`/laboratory/blood-requests/barcode/${digits}`);
      setTicket(res.data ?? null);
      setCode(digits);
      seen.current = digits;
      inputRef.current?.select();
    } catch (error) {
      setTicket(null);
      seen.current = "";
      toast.error(getErrorMessage(error));
    } finally {
      setBusy(false);
    }
  }

  function reprint() {
    if (!ticket?.barcode) return;
    try {
      printLabBarcode({
        barcode: ticket.barcode,
        clinic: ticket.clinic_name,
        patientName: ticket.patient?.full_name,
      });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  return (
    <section className="shrink-0 border-b border-[var(--his-border)] bg-white px-2 py-2">
      <form
        className="flex flex-wrap items-center gap-2"
        onSubmit={(event) => {
          event.preventDefault();
          void lookup(code, true);
        }}
      >
        <span className="text-[12px] font-semibold">{t("laboratory.barcode")}</span>
        <Input
          ref={inputRef}
          dir="ltr"
          lang="en"
          autoComplete="off"
          className="w-64 font-mono"
          placeholder={t("laboratory.scanBarcode")}
          value={code}
          onChange={(event) => {
            const digits = barcodeDigits(event.target.value);
            setCode(digits);
            if (digits.length === 13) void lookup(digits);
          }}
        />
        <Button type="submit" variant="primary" disabled={busy}>
          {t("laboratory.showBlood")}
        </Button>
        <span className="text-[12px] text-[var(--his-muted)]">{t("laboratory.manualHint")}</span>
      </form>

      {ticket?.barcode ? (
        <div className="mt-2 flex flex-wrap items-start justify-between gap-3 border border-[var(--his-border)] p-2">
          <div className="min-w-0 space-y-1 text-[13px]">
            <div>
              <span className="text-[var(--his-muted)]">{t("workspace.clinic")}: </span>
              <strong>{ticket.clinic_name || "—"}</strong>
            </div>
            <div>
              <span className="text-[var(--his-muted)]">{t("patients.fullName")}: </span>
              <strong>{ticket.patient?.full_name || "—"}</strong>
              {ticket.patient?.mrn ? ` — ${dailyNo(ticket.patient.mrn)}` : ""}
            </div>
            <div>
              <strong>{ticket.product_label || "—"}</strong>
              <span className="mx-2">{ticket.blood_group || ""}</span>
              <span>
                {ticket.units ?? "—"} {t("laboratory.units")}
              </span>
              {ticket.status_label ? <span className="mx-2 text-[var(--his-muted)]">{ticket.status_label}</span> : null}
            </div>
            <div dir="ltr" className="font-mono text-[18px] font-bold tracking-wider">
              {ticket.barcode}
            </div>
          </div>
          <div className="flex flex-col items-center gap-1">
            <BarcodeImage code={ticket.barcode} height={56} />
            <Button type="button" onClick={reprint}>
              {t("app.print")}
            </Button>
            {onOpen ? (
              <Button type="button" variant="success" onClick={() => onOpen(ticket as Record<string, unknown>)}>
                {t("laboratory.confirm")}
              </Button>
            ) : null}
          </div>
        </div>
      ) : null}
    </section>
  );
}
