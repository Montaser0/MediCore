"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatDate, formatMoney } from "@/lib/utils";
import { arabicLabel } from "@/i18n/arabicLabels";
import { localize } from "@/lib/arabicText";
import { useInvoices } from "../hooks/useBilling";
import type { InvoiceRow } from "../api/billing.api";

export function BillingPage() {
  const t = useTranslations();
  const { data: rows = [], isLoading, refetch, isFetching } = useInvoices();

  const columns = useMemo<Column<InvoiceRow>[]>(
    () => [
      {
        key: "num",
        header: "#",
        width: 100,
        cell: (r) => localize(r.invoice_number ?? r.id),
      },
      {
        key: "patient",
        header: t("patients.fullName"),
        cell: (r) => r.patient?.full_name ?? "—",
      },
      {
        key: "total",
        header: t("billing.charges"),
        width: 90,
        cell: (r) => formatMoney(r.total),
      },
      {
        key: "balance",
        header: t("billing.payments"),
        width: 90,
        cell: (r) =>
          formatMoney(r.balance),
      },
      {
        key: "status",
        header: t("app.status"),
        width: 100,
        cell: (r) => <Badge tone="warning">{arabicLabel(r.status)}</Badge>,
      },
      {
        key: "issued",
        header: t("app.date"),
        width: 120,
        cell: (r) => formatDate(r.issued_at, true),
      },
    ],
    [t],
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("billing.title")}>
        <span className="text-[12px] text-[var(--his-muted)]">
          {t("billing.invoices")}
        </span>
        <Button onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
      </PageToolbar>
      <div className="flex-1 p-2">
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(r) => r.id}
          loading={isLoading}
          emptyText={t("app.noData")}
        />
      </div>
    </div>
  );
}
