"use client";

import { useEffect, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { apiGet, getErrorMessage } from "@/lib/api/client";
import { dailyNo } from "@/lib/arabicText";
import { BarcodeImage } from "@/modules/inventory/components/BarcodeImage";
import { printLabBarcode } from "../print/printLabBarcode";

export type LabTicketTest = {
  id: number | null;
  name?: string | null;
  sample_type?: string | null;
  price?: number | null;
  unit?: string | null;
  status?: string;
  status_label?: string;
};

export type LabTicket = {
  id: number;
  barcode: string;
  ordered_at?: string | null;
  clinical_notes?: string | null;
  hospital_name?: string | null;
  clinic?: string | null;
  doctor?: string | null;
  patient?: {
    id?: number;
    mrn?: string;
    full_name?: string;
    gender?: string;
    age?: number | null;
    encounter_id?: number;
  } | null;
  tests: LabTicketTest[];
};

function barcodeDigits(value: string) {
  return value
    .replace(/[٠-٩]/g, (digit) => String("٠١٢٣٤٥٦٧٨٩".indexOf(digit)))
    .replace(/[۰-۹]/g, (digit) => String("۰۱۲۳۴۵۶۷۸۹".indexOf(digit)))
    .replace(/\D/g, "");
}

export function LabBarcodePanel({
  onOpen,
}: {
  onOpen?: (row: Record<string, unknown>) => void;
}) {
  const t = useTranslations();
  const inputRef = useRef<HTMLInputElement>(null);
  const seen = useRef("");
  const [code, setCode] = useState("");
  const [ticket, setTicket] = useState<LabTicket | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  async function lookup(raw: string, manual = false) {
    const digits = barcodeDigits(raw);
    if (digits.length < 8) {
      if (manual) toast.error(t("laboratory.barcodeMissing"));
      return;
    }
    if (!manual && seen.current === digits) return;
    setBusy(true);
    try {
      const res = await apiGet<LabTicket>(`/laboratory/orders/barcode/${digits}`);
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
    if (!ticket) return;
    try {
      printLabBarcode({
        barcode: ticket.barcode,
        clinic: ticket.clinic,
        patientName: ticket.patient?.full_name,
      });
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  }

  return (
    <section className="border-b border-[var(--his-border)] bg-white px-2 py-2">
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
          {t("laboratory.showOrder")}
        </Button>
        <span className="text-[12px] text-[var(--his-muted)]">{t("laboratory.manualHint")}</span>
      </form>

      {ticket ? (
        <div className="mt-2 border border-[var(--his-border)]">
          <div className="flex flex-wrap items-start justify-between gap-3 p-2">
            <div className="min-w-0 space-y-1 text-[13px]">
              <div>
                <span className="text-[var(--his-muted)]">{t("workspace.clinic")}: </span>
                <strong>{ticket.clinic || "—"}</strong>
                <span className="mx-2 text-[var(--his-muted)]">{t("workspace.doctor")}: </span>
                <strong>{ticket.doctor || "—"}</strong>
              </div>
              <div>
                <span className="text-[var(--his-muted)]">{t("patients.fullName")}: </span>
                <strong>{ticket.patient?.full_name || "—"}</strong>
                {ticket.patient?.mrn ? ` — ${dailyNo(ticket.patient.mrn)}` : ""}
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
            </div>
          </div>
          <div className="his-panel-header">{t("laboratory.requestedTests")}</div>
          <ul className="m-0 max-h-48 overflow-auto p-0">
            {ticket.tests.map((test, index) => (
              <li
                key={test.id ?? `${test.name}-${index}`}
                className="flex items-center gap-2 border-b border-[var(--his-border)] px-2 py-1 text-[12px] last:border-b-0"
              >
                <span className="min-w-0 flex-1">
                  {test.name || "—"}
                  {test.sample_type ? (
                    <span className="text-[var(--his-muted)]"> — {test.sample_type}</span>
                  ) : null}
                </span>
                <Badge tone="info">{test.status_label || test.status || "—"}</Badge>
                {test.id && onOpen ? (
                  <Button
                    type="button"
                    onClick={() =>
                      onOpen({
                        id: test.id,
                        status: test.status,
                        test: { name: test.name, price: test.price, unit: test.unit },
                        patient: ticket.patient,
                      })
                    }
                  >
                    {t("laboratory.result")}
                  </Button>
                ) : null}
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </section>
  );
}
