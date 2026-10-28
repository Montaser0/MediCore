import { printWhenReady, systemLogoUrl } from "@/lib/brand";
import { formatDate, formatMoney, latinDigits } from "@/lib/utils";

export type PharmacyReceipt = {
  sale_number?: string;
  subtotal?: number;
  discount?: number;
  total?: number;
  cash_amount?: number;
  due_amount?: number;
  sold_at?: string;
  customer?: { name?: string; phone?: string | null } | null;
  hospital?: { name?: string; phone?: string | null; address?: string | null; city?: string | null } | null;
  items?: Array<{ name?: string; quantity?: number; unit?: string; unit_price?: number; line_total?: number }>;
};

export function printPharmacyReceipt(sale: PharmacyReceipt) {
  const preview = window.open("", "_blank", "width=420,height=720");
  if (!preview) {
    throw new Error("اسمح بالنوافذ المنبثقة لطباعة الفاتورة");
  }

  const hospital = sale.hospital;
  const lines = sale.items ?? [];
  const title = latinDigits(sale.sale_number || "فاتورة");

  preview.document.open();
  preview.document.write(`<!DOCTYPE html>
<html lang="ar-u-nu-latn" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>فاتورة ${esc(title)}</title>
  <style>
    @page { size: 80mm auto; margin: 4mm; }
    body { font-family: Tahoma, sans-serif; font-size: 12px; margin: 0; color: #111; }
    h1 { font-size: 16px; text-align: center; margin: 4px 0; }
    p { margin: 2px 0; }
    .center { text-align: center; }
    .logo { display: block; width: 42px; height: 42px; object-fit: contain; margin: 0 auto 4px; }
    .dash { border-top: 1px dashed #111; margin: 6px 0; }
    .row { display: flex; justify-content: space-between; gap: 8px; }
    .item { margin: 4px 0; }
    .name { font-weight: 700; }
    .strong { font-weight: 700; }
    .thanks { margin-top: 8px; }
  </style>
</head>
<body>
  <img class="logo" src="${esc(systemLogoUrl())}" alt="" />
  <h1>${esc(hospital?.name || "الصيدلية")}</h1>
  <p class="center">${esc([hospital?.address, hospital?.city, hospital?.phone].filter(Boolean).join(" · "))}</p>
  <p class="center strong">فاتورة مبيع</p>
  <div class="dash"></div>
  ${meta("الرقم", title)}
  ${meta("التاريخ", formatDate(sale.sold_at, true))}
  ${sale.customer?.name ? meta("الزبون", sale.customer.name) : ""}
  <div class="dash"></div>
  ${lines
    .map(
      (line) => `<div class="item">
        <div class="name">${esc(line.name)}</div>
        <div class="row"><span>${latinDigits(String(line.quantity ?? 0))} ${esc(line.unit || "")} × ${esc(formatMoney(line.unit_price))}</span><span>${esc(formatMoney(line.line_total))}</span></div>
      </div>`,
    )
    .join("")}
  <div class="dash"></div>
  ${meta("المجموع", formatMoney(sale.subtotal, "0"))}
  ${Number(sale.discount) > 0 ? meta("الخصم", formatMoney(sale.discount, "0")) : ""}
  ${meta("الصافي", formatMoney(sale.total, "0"))}
  ${meta("المدفوع", formatMoney(sale.cash_amount, "0"))}
  ${Number(sale.due_amount) > 0 ? meta("الدين", formatMoney(sale.due_amount, "0")) : ""}
  <p class="center thanks">شكراً لثقتكم</p>
</body>
</html>`);
  preview.document.close();
  printWhenReady(preview);
}

function meta(label: string, value?: string | null) {
  if (!value) return "";
  return `<div class="row"><span>${esc(label)}</span><span>${esc(value)}</span></div>`;
}

function esc(value?: string | null) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
