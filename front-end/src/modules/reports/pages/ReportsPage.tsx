"use client";

import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Panel } from "@/components/ui/Panel";
import { formatMoney } from "@/lib/utils";
import { useDashboardReport } from "../hooks/useReports";

export function ReportsPage() {
  const t = useTranslations();
  const { data, isLoading, refetch, isFetching } = useDashboardReport();

  const cards: { label: string; value: string | number }[] = [
    {
      label: t("dashboard.patientsToday"),
      value: data?.patients?.registered_today ?? "—",
    },
    {
      label: t("dashboard.appointmentsToday"),
      value: data?.appointments?.today ?? "—",
    },
    {
      label: t("dashboard.openEncounters"),
      value: data?.encounters?.open ?? "—",
    },
    {
      label: t("dashboard.labPending"),
      value: data?.orders?.pending_lab ?? "—",
    },
    {
      label: t("dashboard.radPending"),
      value: data?.orders?.pending_radiology ?? "—",
    },
    {
      label: t("dashboard.pharmacyPending"),
      value: data?.finance?.unpaid_invoices ?? "—",
    },
    {
      label: t("dashboard.bedOccupancy"),
      value:
        data?.inpatient?.occupancy_rate != null
          ? `${data.inpatient.occupancy_rate}%`
          : "—",
    },
    {
      label: t("dashboard.revenueToday"),
      value:
        formatMoney(data?.finance?.revenue_today),
    },
  ];

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("reports.title")}>
        <span className="text-[12px] text-[var(--his-muted)]">
          {t("reports.dashboard")}
        </span>
        <Button onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
      </PageToolbar>

      <div className="p-2">
        {isLoading ? (
          <p className="text-[12px] text-[var(--his-muted)]">{t("app.loading")}</p>
        ) : !data ? (
          <p className="text-[12px] text-[var(--his-muted)]">{t("app.noData")}</p>
        ) : (
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {cards.map((c) => (
              <Panel key={c.label} title={c.label}>
                <div className="text-2xl font-semibold tabular-nums py-2">
                  {c.value}
                </div>
              </Panel>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
