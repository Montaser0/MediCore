"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { apiPost, getErrorMessage } from "@/lib/api/client";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";
import { ModuleTabs } from "@/components/shared/ModuleTabs";
import { ResourceListPage, dateCell, statusBadge } from "@/components/shared/ResourceListPage";
import { Button } from "@/components/ui/Button";
import { Select } from "@/components/ui/Select";
import { printInvoice } from "@/modules/billing/print/printInvoice";
import { formatMoney } from "@/lib/utils";
import { localize } from "@/lib/arabicText";

export default function Page() {
  const t = useTranslations();
  const [payFilter, setPayFilter] = useState("");
  return (
    <ModuleTabs
      tabs={[
        {
          id: "invoices",
          label: t("billing.invoices"),
          content: (
            <ResourceListPage
              title={t("billing.title")}
              endpoint="/billing/invoices"
              params={payFilter ? { status: payFilter } : undefined}
              toolbarExtra={
                <Select className="w-40" value={payFilter} onChange={(event) => setPayFilter(event.target.value)}>
                  <option value="">{t("billing.allPayments")}</option>
                  <option value="issued">{t("billing.unpaid")}</option>
                  <option value="partially_paid">{t("billing.partial")}</option>
                </Select>
              }
              removeLabel={t("app.delete")}
              onRemove={(row) => apiPost(`/billing/invoices/${row.id}/cancel`, { reason: "cancelled" })}
              extraActions={(row) => (
                <>
                  <Button
                    onClick={() =>
                      void printInvoice(Number(row.id), "thermal").catch((error) =>
                        toast.error(getErrorMessage(error)),
                      )
                    }
                  >
                    {t("billing.printThermal")}
                  </Button>
                  <Button
                    onClick={() =>
                      void printInvoice(Number(row.id), "a4").catch((error) =>
                        toast.error(getErrorMessage(error)),
                      )
                    }
                  >
                    {t("billing.printStandard")}
                  </Button>
                </>
              )}
              columns={[
                { key: "id", header: "#", width: 60 },
                {
                  key: "number",
                  header: t("crud.code"),
                  render: (row) => localize(row.number ?? row.invoice_number ?? row.id) || "—",
                },
                {
                  key: "patient",
                  header: t("patients.fullName"),
                  render: (row) =>
                    String(
                      (row.patient as { full_name?: string } | undefined)?.full_name ??
                        row.patient_name ??
                        "—",
                    ),
                },
                {
                  key: "total",
                  header: t("crud.price"),
                  width: 100,
                  render: (row) => formatMoney(row.total ?? row.grand_total),
                },
                {
                  key: "issued_at",
                  header: t("app.date"),
                  width: 140,
                  render: (row) => dateCell(row.issued_at ?? row.created_at, true),
                },
                {
                  key: "status",
                  header: t("app.status"),
                  width: 110,
                  render: (row) => statusBadge(row.status),
                },
              ]}
            />
          ),
        },
        {
          id: "services",
          label: t("billing.services"),
          content: (
            <CrudResourcePage
              title={t("billing.services")}
              endpoint="/billing/services"
              columns={[
                { key: "id", header: "#", width: 60 },
                { key: "name", header: t("crud.name"), render: (row) => String(row.name ?? "—") },
                { key: "code", header: t("crud.code"), width: 100, render: (row) => String(row.code ?? "—") },
                { key: "price", header: t("crud.price"), width: 110, render: (row) => formatMoney(row.price) },
              ]}
              fields={[
                { key: "name", label: t("crud.name"), required: true },
                { key: "code", label: t("crud.code"), required: true },
                { key: "category", label: t("crud.type") },
                { key: "price", label: t("crud.price"), type: "number", required: true },
              ]}
            />
          ),
        },
      ]}
    />
  );
}
