import { dailyNo } from "@/lib/arabicText";
import { formatDate } from "@/lib/utils";

export type AppointmentSlip = {
  appointment_number?: string;
  scheduled_at?: string;
  reason?: string;
  patient?: { full_name?: string; mrn?: string; phone?: string };
  doctor?: { name?: string };
  clinic?: { name?: string };
  department?: { name?: string };
};

export function printAppointmentSlip(
  appointment: AppointmentSlip,
  hospitalName: string,
  preview?: Window | null,
) {
  const target = preview ?? window.open("", "_blank", "width=420,height=720");
  if (!target) {
    throw new Error("اسمح بالنوافذ المنبثقة لطباعة الوصل");
  }

  target.document.open();
  target.document.write(render(appointment, hospitalName));
  target.document.close();
  target.focus();
  window.setTimeout(() => target.print(), 250);
}

function render(appointment: AppointmentSlip, hospitalName: string) {
  const place = appointment.clinic?.name || appointment.department?.name;
  const when = formatDate(appointment.scheduled_at, true);

  return `<!DOCTYPE html>
<html lang="ar-u-nu-latn" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>وصل موعد</title>
  <style>
    @page { size: 80mm auto; margin: 3mm; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; width: 72mm; color: #000; background: #fff; }
    body { font-family: Tahoma, "Segoe UI", Arial, sans-serif; font-size: 12px; line-height: 1.4; }
    h1 { font-size: 16px; text-align: center; margin: 0 0 2px; }
    .center { text-align: center; }
    .title { font-weight: 700; font-size: 14px; margin: 4px 0; }
    .dash { border-top: 1px dashed #000; margin: 6px 0; }
    .row { display: flex; justify-content: space-between; gap: 8px; margin: 2px 0; }
    .row span:last-child { font-weight: 700; text-align: left; }
    .thanks { margin-top: 8px; }
  </style>
</head>
<body>
  <h1>${esc(hospitalName || "المستشفى")}</h1>
  <p class="center title">وصل موعد</p>
  <div class="dash"></div>
  ${row("رقم الموعد", dailyNo(appointment.appointment_number) || "—")}
  ${row("المريض", appointment.patient?.full_name)}
  ${row("رقم الملف", dailyNo(appointment.patient?.mrn))}
  ${appointment.patient?.phone ? row("الهاتف", appointment.patient.phone) : ""}
  ${row("العيادة", place)}
  ${appointment.doctor?.name ? row("الطبيب", appointment.doctor.name) : ""}
  ${row("موعد الزيارة", when)}
  ${appointment.reason ? row("السبب", appointment.reason) : ""}
  <div class="dash"></div>
  <p class="center thanks">يُرجى إبراز هذا الوصل عند الحضور</p>
</body>
</html>`;
}

function row(label: string, value?: string | null) {
  return `<div class="row"><span>${esc(label)}</span><span>${esc(value || "—")}</span></div>`;
}

function esc(value: unknown) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
