"use client";

import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { arabicLabel } from "@/i18n/arabicLabels";
import { localize } from "@/lib/arabicText";
import { Button } from "@/components/ui/Button";
import { getErrorMessage } from "@/lib/api/client";
import { formatDate, formatMoney } from "@/lib/utils";
import { printInvoice } from "@/modules/billing/print/printInvoice";

type Rec = Record<string, unknown>;

export function PatientAccountPanel({
  billing,
  onPay,
  onInstallments,
}: {
  billing: Rec;
  onPay?: (invoice: Rec) => void;
  onInstallments?: (invoice: Rec) => void;
}) {
  const t = useTranslations();
  const charges = arr(billing.recent_charges);
  const invoices = arr(billing.recent_invoices);
  const installments = invoices.flatMap((invoice) =>
    arr(invoice.installments).map((row): Rec => ({ ...row, invoice_number: invoice.invoice_number })),
  );

  return (
    <div className="his-panel">
      <div className="his-panel-header">{t("patients.billing")}</div>
      <div className="his-field-grid p-2">
        <Field label={t("workspace.charged")} value={money(billing.charged_total)} />
        <Field label={t("workspace.paid")} value={money(billing.paid_total)} />
        <Field label={t("workspace.balance")} value={money(billing.outstanding_balance)} />
      </div>

      <div className="his-panel-header">{t("workspace.chargeLines")}</div>
      <Table
        headers={[t("workspace.description"), t("patients.actor"), t("workspace.quantity"), t("workspace.amount"), t("billing.lineTotal")]}
        empty={t("app.noData")}
        rows={charges.map((row) => [
          localize(row.description) || "—",
          text(row.by),
          money(row.quantity),
          money(row.unit_price),
          money(row.total),
        ])}
      />

      <div className="his-panel-header">{t("billing.invoices")}</div>
      <div className="his-table-wrap" style={{ border: "none" }}>
        <table className="his-table">
          <thead>
            <tr>
              <th>{t("crud.code")}</th>
              <th>{t("app.status")}</th>
              <th>{t("app.date")}</th>
              <th>{t("patients.actor")}</th>
              <th>{t("billing.lineTotal")}</th>
              <th>{t("workspace.paid")}</th>
              <th>{t("workspace.balance")}</th>
              <th>{t("app.actions")}</th>
            </tr>
          </thead>
          <tbody>
            {invoices.length === 0 ? (
                <tr>
                  <td colSpan={8}>{t("app.noData")}</td>
                </tr>
            ) : (
              invoices.map((invoice) => {
                const balance = Number(invoice.balance ?? 0);
                const collecting = arr(invoice.installments).some((row) => Number(row.paid_amount ?? 0) > 0);
                const payable = balance > 0 && ["issued", "partially_paid"].includes(String(invoice.status ?? ""));
                return (
                  <tr key={text(invoice.id)}>
                    <td>{localize(invoice.invoice_number) || "—"}</td>
                    <td>{arabicLabel(invoice.status)}</td>
                    <td>{formatDate(String(invoice.issued_at ?? ""), true)}</td>
                    <td>{text(invoice.by)}</td>
                    <td>{money(invoice.total)}</td>
                    <td>{money(invoice.paid_amount)}</td>
                    <td>{money(invoice.balance)}</td>
                    <td>
                      <span className="flex flex-wrap gap-1">
                        {payable && onPay ? <Button onClick={() => onPay(invoice)}>{t("billing.pay")}</Button> : null}
                        {payable && !collecting && onInstallments ? (
                          <Button onClick={() => onInstallments(invoice)}>{t("billing.installments")}</Button>
                        ) : null}
                        <Button onClick={() => print(invoice, "thermal")}>{t("billing.printThermal")}</Button>
                        <Button onClick={() => print(invoice, "a4")}>{t("billing.printStandard")}</Button>
                      </span>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {installments.length > 0 ? (
        <>
          <div className="his-panel-header">{t("billing.installments")}</div>
          <Table
            headers={[
              t("crud.code"),
              t("workspace.installmentLine"),
              t("billing.firstDue"),
              t("billing.lineTotal"),
              t("workspace.balance"),
              t("app.status"),
            ]}
            empty={t("app.noData")}
            rows={installments.map((row) => [
              localize(row.invoice_number) || "—",
              text(row.sequence),
              formatDate(String(row.due_date ?? "")),
              money(row.amount),
              money(row.remaining),
              arabicLabel(row.status),
            ])}
          />
        </>
      ) : null}
    </div>
  );
}

function print(invoice: Rec, kind: "thermal" | "a4") {
  void printInvoice(Number(invoice.id), kind).catch((error) => toast.error(getErrorMessage(error)));
}

function Table({ headers, rows, empty }: { headers: string[]; rows: string[][]; empty: string }) {
  return (
    <div className="his-table-wrap" style={{ border: "none" }}>
      <table className="his-table">
        <thead>
          <tr>
            {headers.map((header) => (
              <th key={header}>{header}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td colSpan={headers.length}>{empty}</td>
            </tr>
          ) : (
            rows.map((row, index) => (
              <tr key={index}>
                {row.map((cell, cellIndex) => (
                  <td key={cellIndex}>{cell}</td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div className="his-field">
      <label>{label}</label>
      <div className="value">{value}</div>
    </div>
  );
}

function money(value: unknown) {
  return formatMoney(value);
}

function text(value: unknown, empty = "—") {
  if (value === undefined || value === null || value === "") return empty;
  return String(value);
}

function arr(value: unknown): Rec[] {
  return Array.isArray(value) ? (value as Rec[]) : [];
}
