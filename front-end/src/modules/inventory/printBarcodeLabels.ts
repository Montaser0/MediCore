import { barcodeSvg, isEan13 } from "@/modules/inventory/barcode";

export type BarcodeLabel = {
  name: string;
  code: string;
  caption?: string;
};

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function digits(code: string): string {
  if (!isEan13(code)) return code;
  return `${code[0]} ${code.slice(1, 7)} ${code.slice(7)}`;
}

export function printBarcodeLabels(labels: BarcodeLabel[]): boolean {
  const cards = labels
    .map((label) => {
      const code = label.code.trim();
      const svg = barcodeSvg(code, 46);
      if (!svg) return "";
      return `<article class="label">
        <div class="name">${escapeHtml(label.name)}</div>
        ${label.caption ? `<div class="caption">${escapeHtml(label.caption)}</div>` : ""}
        <div class="bars" dir="ltr">${svg}</div>
        <div class="digits" dir="ltr">${escapeHtml(digits(code))}</div>
      </article>`;
    })
    .filter(Boolean);

  if (cards.length === 0) return false;

  const frame = document.createElement("iframe");
  frame.setAttribute("aria-hidden", "true");
  frame.style.position = "fixed";
  frame.style.width = "0";
  frame.style.height = "0";
  frame.style.border = "0";
  document.body.appendChild(frame);
  const doc = frame.contentDocument;
  if (!doc) {
    frame.remove();
    return false;
  }

  doc.open();
  doc.write(`<!DOCTYPE html>
<html dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>barcode</title>
  <style>
    @page { size: A4; margin: 8mm; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #fff; color: #000; font-family: Tahoma, Arial, sans-serif; }
    .sheet { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
    .label {
      break-inside: avoid;
      border: 1px dashed #222;
      padding: 3mm 2mm 2mm;
      text-align: center;
      background: #fff;
    }
    .name { font-size: 13px; font-weight: 700; line-height: 1.3; }
    .caption { font-size: 11px; margin-top: 1mm; }
    .bars, .digits { direction: ltr; }
    .bars { width: 52mm; height: 22mm; margin: 2mm auto 1mm; background: #fff; }
    .bars svg { display: block; width: 52mm; height: 22mm; }
    .digits { font: 14px/1 Consolas, "Courier New", monospace; letter-spacing: 1px; }
  </style>
</head>
<body><div class="sheet">${cards.join("")}</div></body>
</html>`);
  doc.close();

  window.setTimeout(() => {
    frame.contentWindow?.focus();
    frame.contentWindow?.print();
    window.setTimeout(() => frame.remove(), 1000);
  }, 200);

  return true;
}
