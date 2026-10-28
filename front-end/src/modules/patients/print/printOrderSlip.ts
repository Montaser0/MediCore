import { printWhenReady, systemLogoUrl } from "@/lib/brand";
import { formatDate } from "@/lib/utils";

export type OrderSlipLine = {
  name: string;
  price: number;
};

export type OrderSlip = {
  kind: "laboratory" | "radiology";
  title?: string;
  orderNumber?: string;
  hospitalName: string;
  patientName?: string;
  mrn?: string;
  age?: string;
  gender?: string;
  phone?: string;
  doctor?: string;
  clinic?: string;
  notes?: string;
  lines: OrderSlipLine[];
  injection?: number;
};

export function printOrderSlip(slip: OrderSlip, preview?: Window | null) {
  const target = preview ?? window.open("", "_blank", "width=900,height=720");
  if (!target) {
    throw new Error("اسمح بالنوافذ المنبثقة لطباعة الوصفة");
  }
  target.document.open();
  target.document.write(render(slip));
  target.document.close();
  printWhenReady(target);
  return target;
}

function render(slip: OrderSlip) {
  const title = slip.title ?? (slip.kind === "laboratory" ? "وصفة مخبر" : "وصفة أشعة");
  const withInjection = (slip.injection ?? 0) > 0;
  const rows = slip.lines
    .map((line, index) => `<tr><td>${index + 1}</td><td>${esc(line.name)}</td></tr>`)
    .join("");

  return `<!DOCTYPE html>
<html lang="ar-u-nu-latn" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>${esc(title)}</title>
  <style>
    @page { size: A5; margin: 10mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #111; background: #fff; font-family: Tahoma, "Segoe UI", Arial, sans-serif; font-size: 13px; }
    .logo { display: block; height: 72px; width: auto; margin: 0 auto 6px; background: transparent; }
    h1 { margin: 0; text-align: center; font-size: 20px; }
    h2 { margin: 4px 0 10px; text-align: center; font-size: 16px; }
    .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px; margin-bottom: 10px; }
    .meta div { display: flex; justify-content: space-between; gap: 8px; border-bottom: 1px dotted #bbb; padding: 2px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #222; padding: 4px 6px; text-align: right; }
    th { background: #f3f3f3; }
    td:first-child, th:first-child { width: 36px; text-align: center; }
    .notes { margin-top: 8px; }
    .sign { margin-top: 28px; display: flex; justify-content: space-between; }
    .sign span { min-width: 140px; border-top: 1px solid #222; padding-top: 4px; text-align: center; }
  </style>
</head>
<body>
  <img class="logo" src="${esc(systemLogoUrl())}" alt="" />
  <h1>${esc(slip.hospitalName || "المستشفى")}</h1>
  <h2>${esc(title)}</h2>
  <div class="meta">
    ${field("الطبيب", slip.doctor)}
    ${field("تاريخ الوصفة", formatDate(new Date().toISOString(), true))}
    ${field("عمر المريض", slip.age)}
    ${field("العيادة", slip.clinic)}
  </div>
  <table>
    <thead><tr><th>#</th><th>البيان</th></tr></thead>
    <tbody>
      ${rows}
      ${withInjection ? `<tr><td></td><td>مع حقن</td></tr>` : ""}
    </tbody>
  </table>
  ${slip.notes ? `<p class="notes">ملاحظات: ${esc(slip.notes)}</p>` : ""}
  <div class="sign"><span>توقيع الطبيب</span><span>ختم المركز</span></div>
</body>
</html>`;
}

function field(label: string, value?: string | null) {
  return `<div><span>${esc(label)}</span><strong>${esc(value || "—")}</strong></div>`;
}

function esc(value: unknown) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
