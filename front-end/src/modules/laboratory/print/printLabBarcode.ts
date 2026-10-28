import { barcodeSvg } from "@/modules/inventory/barcode";
import { printWhenReady } from "@/lib/brand";

export type LabBarcodeSlip = {
  barcode: string;
  clinic?: string | null;
  patientName?: string | null;
};

export function printLabBarcode(slip: LabBarcodeSlip, preview?: Window | null) {
  const target = preview ?? window.open("", "_blank", "width=420,height=320");
  if (!target) {
    throw new Error("اسمح بالنوافذ المنبثقة لطباعة الباركود");
  }
  target.document.open();
  target.document.write(render(slip));
  target.document.close();
  printWhenReady(target);
  return target;
}

function render(slip: LabBarcodeSlip) {
  const svg = barcodeSvg(slip.barcode, 70);
  const clinic = slip.clinic?.trim();
  const patient = slip.patientName?.trim();

  return `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>${esc(slip.barcode)}</title>
  <style>
    @page { size: 80mm 48mm; margin: 3mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #000; background: #fff; font-family: Tahoma, Arial, sans-serif; text-align: center; }
    .clinic { font-size: 15px; font-weight: 700; line-height: 1.3; }
    .patient { font-size: 13px; font-weight: 700; line-height: 1.3; margin-top: 0.5mm; }
    .bars { width: 72mm; height: 18mm; margin: 1.5mm auto 1mm; background: #fff; }
    .bars svg { display: block; width: 72mm; height: 18mm; }
    .number { font: 700 16px/1 Consolas, "Courier New", monospace; letter-spacing: 1px; direction: ltr; }
  </style>
</head>
<body>
  ${clinic ? `<div class="clinic">${esc(clinic)}</div>` : ""}
  ${patient ? `<div class="patient">${esc(patient)}</div>` : ""}
  <div class="bars" dir="ltr">${svg ?? ""}</div>
  <div class="number">${esc(slip.barcode)}</div>
</body>
</html>`;
}

function esc(value: unknown) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
