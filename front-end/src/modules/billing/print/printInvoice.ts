import { apiGet } from "@/lib/api/client";
import { printWhenReady, systemLogoUrl } from "@/lib/brand";
import { dailyNo, localize } from "@/lib/arabicText";
import { formatDate, formatMoney } from "@/lib/utils";

export type PrintKind = "thermal" | "a4";

type Line = {
  description?: string;
  quantity?: number;
  unit_price?: number;
  discount?: number;
  total?: number;
};

type Payment = {
  receipt_number?: string;
  amount?: number;
  method_label?: string;
  method?: string;
  paid_at?: string;
};

type Installment = {
  sequence?: number;
  due_date?: string;
  amount?: number;
  remaining?: number;
  status_label?: string;
  status?: string;
};

type InvoiceDocument = {
  invoice_number?: string;
  status_label?: string;
  subtotal?: number;
  discount?: number;
  tax?: number;
  total?: number;
  paid_amount?: number;
  balance?: number;
  notes?: string | null;
  issued_at?: string | null;
  items?: Line[];
  payments?: Payment[];
  installments?: Installment[];
  patient?: {
    full_name?: string;
    mrn?: string;
    phone?: string;
    national_id?: string;
  };
  encounter?: { encounter_number?: string };
  hospital?: {
    name?: string;
    phone?: string;
    address?: string;
    city?: string;
    currency?: string;
  };
};

export async function printInvoice(invoiceId: number, kind: PrintKind) {
  const preview = window.open("", "_blank", "width=900,height=720");
  if (!preview) {
    throw new Error("اسمح بالنوافذ المنبثقة لطباعة الفاتورة");
  }

  preview.document.write(
    "<p dir='rtl' style='font-family:Tahoma,sans-serif'>جارٍ تجهيز الفاتورة…</p>",
  );

  try {
    const res = await apiGet<InvoiceDocument>(`/billing/invoices/${invoiceId}`);
    preview.document.open();
    preview.document.write(render(res.data, kind));
    preview.document.close();
    printWhenReady(preview);
  } catch (error) {
    preview.close();
    throw error;
  }
}

function render(invoice: InvoiceDocument, kind: PrintKind) {
  const hospital = invoice.hospital?.name || "المستشفى";
  const title = esc(localize(invoice.invoice_number) || "فاتورة");
  const body = kind === "thermal" ? thermalBody(invoice) : a4Body(invoice);
  const css = kind === "thermal" ? thermalCss : a4Css;

  return `<!DOCTYPE html>
<html lang="ar-u-nu-latn" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>فاتورة ${title}</title>
  <style>${css}</style>
</head>
<body>
  ${body}
  <p class="foot">${esc(hospital)}</p>
</body>
</html>`;
}

function thermalBody(invoice: InvoiceDocument) {
  const hospital = invoice.hospital;
  const patient = invoice.patient;
  const lines = invoice.items ?? [];

  return `
    <img class="logo" src="${esc(systemLogoUrl())}" alt="" />
    <h1>${esc(hospital?.name || "المستشفى")}</h1>
    <p class="center">${esc(joinParts([hospital?.address, hospital?.city, hospital?.phone]))}</p>
    <p class="center strong">فاتورة</p>
    <div class="dash"></div>
    ${metaRow("الرقم", localize(invoice.invoice_number))}
    ${metaRow("التاريخ", when(invoice.issued_at))}
    ${metaRow("الحالة", invoice.status_label)}
    ${metaRow("المريض", patient?.full_name)}
    ${metaRow("الرقم", dailyNo(patient?.mrn))}
    ${patient?.phone ? metaRow("الهاتف", patient.phone) : ""}
    <div class="dash"></div>
    ${
      lines.length
        ? lines
            .map(
              (line) => `<div class="item">
                <div class="name">${esc(localize(line.description))}</div>
                <div class="row"><span>${qty(line.quantity)} × ${money(line.unit_price)}</span><span>${money(line.total)}</span></div>
              </div>`,
            )
            .join("")
        : `<p class="center">لا توجد بنود</p>`
    }
    <div class="dash"></div>
    ${moneyRows(invoice)}
    ${paymentsBlock(invoice.payments, true)}
    ${installmentsBlock(invoice.installments, true)}
    ${invoice.notes ? `<p class="note">${esc(invoice.notes)}</p>` : ""}
    <p class="center thanks">شكراً لثقتكم</p>
  `;
}

function a4Body(invoice: InvoiceDocument) {
  const hospital = invoice.hospital;
  const patient = invoice.patient;
  const lines = invoice.items ?? [];

  return `
    <header>
      <div class="brand">
        <img class="logo" src="${esc(systemLogoUrl())}" alt="" />
        <div>
        <h1>${esc(hospital?.name || "المستشفى")}</h1>
        <p>${esc(joinParts([hospital?.address, hospital?.city]))}</p>
        <p>${esc(hospital?.phone || "")}</p>
        </div>
      </div>
      <div class="doc">
        <strong>فاتورة</strong>
        <p>${esc(localize(invoice.invoice_number))}</p>
        <p>${esc(when(invoice.issued_at))}</p>
        <p>${esc(invoice.status_label || "")}</p>
      </div>
    </header>
    <section class="who">
      <div><span>المريض</span><strong>${esc(patient?.full_name || "—")}</strong></div>
      <div><span>الرقم</span><strong>${esc(dailyNo(patient?.mrn) || "—")}</strong></div>
      <div><span>الهاتف</span><strong>${esc(patient?.phone || "—")}</strong></div>
      <div><span>الرقم الوطني</span><strong>${esc(patient?.national_id || "—")}</strong></div>
      <div><span>الزيارة</span><strong>${esc(dailyNo(invoice.encounter?.encounter_number) || "—")}</strong></div>
    </section>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>البيان</th>
          <th>الكمية</th>
          <th>سعر الوحدة</th>
          <th>الخصم</th>
          <th>الإجمالي</th>
        </tr>
      </thead>
      <tbody>
        ${
          lines.length
            ? lines
                .map(
                  (line, index) => `<tr>
                    <td>${index + 1}</td>
                    <td>${esc(localize(line.description))}</td>
                    <td class="num">${qty(line.quantity)}</td>
                    <td class="num">${money(line.unit_price)}</td>
                    <td class="num">${money(line.discount)}</td>
                    <td class="num">${money(line.total)}</td>
                  </tr>`,
                )
                .join("")
            : `<tr><td colspan="6" class="center">لا توجد بنود</td></tr>`
        }
      </tbody>
    </table>
    <table class="totals">${moneyRows(invoice, true)}</table>
    ${paymentsBlock(invoice.payments, false)}
    ${installmentsBlock(invoice.installments, false)}
    ${invoice.notes ? `<p class="note">ملاحظات: ${esc(invoice.notes)}</p>` : ""}
    <section class="sign">
      <div>المحاسب</div>
      <div>المريض / المرافق</div>
    </section>
  `;
}

function moneyRows(invoice: InvoiceDocument, cells = false) {
  const rows: Array<[string, number | undefined]> = [
    ["المجموع", invoice.subtotal],
    ["الخصم", invoice.discount],
    ["الضريبة", invoice.tax],
    ["الإجمالي", invoice.total],
    ["المدفوع", invoice.paid_amount],
    ["المتبقي", invoice.balance],
  ];
  const visible = rows.filter(([label, value]) => {
    if (label === "الخصم" || label === "الضريبة") return Number(value ?? 0) > 0;
    return true;
  });

  if (cells) {
    return visible
      .map(([label, value]) => {
        const mark = label === "الإجمالي" || label === "المتبقي" ? "emph" : "";
        return `<tr class="${mark}"><th>${label}</th><td class="num">${money(value)}</td></tr>`;
      })
      .join("");
  }

  return visible
    .map(
      ([label, value]) =>
        `<div class="row ${label === "الإجمالي" || label === "المتبقي" ? "emph" : ""}"><span>${label}</span><span>${money(value)}</span></div>`,
    )
    .join("");
}

function paymentsBlock(payments: Payment[] | undefined, compact: boolean) {
  if (!payments?.length) return "";
  if (compact) {
    return `<div class="dash"></div><p class="strong">المدفوعات</p>${payments
      .map(
        (payment) =>
          `<div class="row"><span>${esc(payment.method_label || payment.method)} ${esc(localize(payment.receipt_number))}</span><span>${money(payment.amount)}</span></div>`,
      )
      .join("")}`;
  }

  return `<h2>المدفوعات</h2><table>
    <thead><tr><th>الإيصال</th><th>الطريقة</th><th>التاريخ</th><th>المبلغ</th></tr></thead>
    <tbody>${payments
      .map(
        (payment) => `<tr>
          <td>${esc(localize(payment.receipt_number))}</td>
          <td>${esc(payment.method_label || payment.method)}</td>
          <td>${esc(when(payment.paid_at))}</td>
          <td class="num">${money(payment.amount)}</td>
        </tr>`,
      )
      .join("")}</tbody></table>`;
}

function installmentsBlock(rows: Installment[] | undefined, compact: boolean) {
  if (!rows?.length) return "";
  if (compact) {
    return `<div class="dash"></div><p class="strong">الأقساط</p>${rows
      .map(
        (row) =>
          `<div class="row"><span>${esc(row.sequence)}. ${esc(row.due_date)} ${esc(row.status_label || row.status)}</span><span>${money(row.remaining ?? row.amount)}</span></div>`,
      )
      .join("")}`;
  }

  return `<h2>الأقساط</h2><table>
    <thead><tr><th>#</th><th>الاستحقاق</th><th>المبلغ</th><th>المتبقي</th><th>الحالة</th></tr></thead>
    <tbody>${rows
      .map(
        (row) => `<tr>
          <td>${esc(row.sequence)}</td>
          <td>${esc(row.due_date)}</td>
          <td class="num">${money(row.amount)}</td>
          <td class="num">${money(row.remaining)}</td>
          <td>${esc(row.status_label || row.status)}</td>
        </tr>`,
      )
      .join("")}</tbody></table>`;
}

function metaRow(label: string, value?: string | null) {
  return `<div class="row"><span>${label}</span><span>${esc(value || "—")}</span></div>`;
}

function joinParts(parts: Array<string | null | undefined>) {
  return parts.filter((part) => part && String(part).trim()).join(" — ");
}

function qty(value: unknown) {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount)) return "0";
  return new Intl.NumberFormat("en-US", { maximumFractionDigits: 2 }).format(amount);
}

function money(value: unknown) {
  return formatMoney(value, "0");
}

function when(value?: string | null) {
  return formatDate(value, Boolean(value?.includes("T")));
}

function esc(value: unknown) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

const thermalCss = `
  @page { size: 80mm auto; margin: 3mm; }
  * { box-sizing: border-box; font-feature-settings: "locl" 0; }
  html, body { margin: 0; padding: 0; width: 72mm; color: #000; background: #fff; }
  body { font-family: Tahoma, "Segoe UI", Arial, sans-serif; font-size: 12px; line-height: 1.35; }
  .logo { display: block; height: 52px; width: auto; margin: 0 auto 4px; background: transparent; }
  h1 { font-size: 16px; text-align: center; margin: 0 0 4px; }
  p { margin: 2px 0; }
  .center { text-align: center; }
  .strong, .emph { font-weight: 700; }
  .emph { font-size: 13px; }
  .dash { border-top: 1px dashed #000; margin: 6px 0; }
  .row { display: flex; justify-content: space-between; gap: 8px; }
  .item { margin: 4px 0; }
  .name { font-weight: 700; }
  .note { margin-top: 6px; white-space: pre-wrap; }
  .thanks { margin-top: 8px; }
  .foot { display: none; }
`;

const a4Css = `
  @page { size: A4; margin: 14mm; }
  * { box-sizing: border-box; font-feature-settings: "locl" 0; }
  body { margin: 0; color: #1a1a1a; background: #fff; font-family: Tahoma, "Segoe UI", Arial, sans-serif; font-size: 13px; }
  header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 3px solid #1b4f8c; padding-bottom: 10px; }
  .brand { display: flex; align-items: center; gap: 12px; }
  .logo { height: 72px; width: auto; background: transparent; }
  h1 { margin: 0 0 4px; font-size: 22px; color: #1b4f8c; }
  h2 { margin: 18px 0 6px; font-size: 15px; }
  header p, .doc p { margin: 2px 0; }
  .doc { text-align: left; }
  .doc strong { display: block; font-size: 20px; color: #1b4f8c; }
  .who { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin: 14px 0; }
  .who div { border: 1px solid #b8c4ce; padding: 6px 8px; }
  .who span { display: block; color: #5a6a7a; font-size: 11px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #8fa0b0; padding: 6px 8px; vertical-align: top; }
  th { background: #eef5fb; text-align: right; }
  .num { text-align: left; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .center { text-align: center; }
  .totals { width: 280px; margin-top: 12px; margin-inline-start: auto; }
  .totals .emph th, .totals .emph td { font-weight: 700; background: #f7fafc; }
  .note { margin-top: 12px; }
  .sign { display: flex; justify-content: space-between; margin-top: 56px; }
  .sign div { width: 38%; border-top: 1px solid #1a1a1a; padding-top: 6px; text-align: center; }
  .foot { display: none; }
`;
