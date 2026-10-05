"use client";

import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import type { LucideIcon } from "lucide-react";
import { BadgePercent, Banknote, Barcode, HandCoins, Pill, Receipt, RefreshCw, TrendingUp, Undo2, Wallet } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Panel } from "@/components/ui/Panel";
import { formatMoney } from "@/lib/utils";
import { useEditionStore } from "@/stores/edition-store";
import { reportsApi } from "../api/reports.api";
import { useDashboardReport } from "../hooks/useReports";
import type { DashboardMetrics } from "../api/reports.api";

export function ReportsPage() {
  const t = useTranslations();
  const ready = useEditionStore((state) => state.ready);
  const edition = useEditionStore((state) => state.edition);

  if (!ready) {
    return <p className="p-3 text-[12px] text-[var(--his-muted)]">{t("app.loading")}</p>;
  }

  if (edition === "pharmacy") return <PharmacyReportsPage />;
  return <ClinicalReportsPage />;
}

export function PharmacyReportsPage() {
  const t = useTranslations();
  const { data, isLoading, refetch, isFetching } = useQuery({
    queryKey: ["reports", "pharmacy"],
    queryFn: async () => {
      const res = await reportsApi.pharmacy();
      return res.data ?? null;
    },
  });

  const money = (amount: number | undefined) => (isLoading ? "…" : formatMoney(amount, "0"));
  const count = (amount: number | undefined) => (isLoading ? "…" : String(amount ?? 0));

  const revenues = [
    { label: t("dashboard.revenueToday"), value: money(data?.revenue_today), color: "#c0392b" },
    { label: t("reports.revenueWeek"), value: money(data?.revenue_week), color: "#1b4f8c" },
    { label: t("reports.revenueMonth"), value: money(data?.revenue_month), color: "#27ae60" },
  ];

  const stats: { label: string; value: string; icon: LucideIcon; color: string }[] = [
    { label: t("dashboard.salesToday"), value: count(data?.sales_today), icon: Barcode, color: "#1b4f8c" },
    { label: t("dashboard.itemsSoldToday"), value: count(data?.items_today), icon: Pill, color: "#2980b9" },
    { label: t("dashboard.returnsToday"), value: money(data?.returns_today), icon: Undo2, color: "#e67e22" },
    { label: t("dashboard.cashToday"), value: money(data?.cash_today), icon: Banknote, color: "#27ae60" },
    { label: t("dashboard.debtsToday"), value: money(data?.debts_today), icon: HandCoins, color: "#8e44ad" },
    { label: t("dashboard.outstandingDebt"), value: money(data?.outstanding_debt), icon: Wallet, color: "#16a085" },
  ];

  const discounts = [
    { label: t("dashboard.discountToday"), value: money(data?.discount_today), color: "#8e44ad" },
    { label: t("reports.discountWeek"), value: money(data?.discount_week), color: "#1b4f8c" },
    { label: t("reports.discountMonth"), value: money(data?.discount_month), color: "#16a085" },
  ];

  const payments = [
    { label: t("reports.paymentsToday"), value: money(data?.payments_today), color: "#c0392b" },
    { label: t("reports.paymentsWeek"), value: money(data?.payments_week), color: "#1b4f8c" },
    { label: t("reports.paymentsMonth"), value: money(data?.payments_month), color: "#8e44ad" },
  ];

  const profits = [
    { label: t("reports.profitToday"), value: money(data?.profit_today), color: "#27ae60" },
    { label: t("reports.profitWeek"), value: money(data?.profit_week), color: "#1b4f8c" },
    { label: t("reports.profitMonth"), value: money(data?.profit_month), color: "#16a085" },
  ];

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("reports.pharmacyTitle")}>
        <Button onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw size={13} />
          {t("app.refresh")}
        </Button>
      </PageToolbar>
      <div className="space-y-3 overflow-auto p-2">
        <div className="grid gap-2 sm:grid-cols-3">
          {revenues.map((card) => (
            <StatCard key={card.label} label={card.label} value={card.value} icon={Receipt} color={card.color} />
          ))}
        </div>
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
          {stats.map((card) => (
            <StatCard key={card.label} {...card} />
          ))}
        </div>
        <div className="text-[12px] font-semibold text-[var(--his-header)]">{t("reports.discounts")}</div>
        <div className="grid gap-2 sm:grid-cols-3">
          {discounts.map((card) => (
            <StatCard key={card.label} label={card.label} value={card.value} icon={BadgePercent} color={card.color} />
          ))}
        </div>
        <div className="text-[12px] font-semibold text-[var(--his-header)]">{t("reports.payments")}</div>
        <div className="grid gap-2 sm:grid-cols-3">
          {payments.map((card) => (
            <StatCard key={card.label} label={card.label} value={card.value} icon={Banknote} color={card.color} />
          ))}
        </div>
        <div className="text-[12px] font-semibold text-[var(--his-header)]">{t("reports.netProfit")}</div>
        <div className="grid gap-2 sm:grid-cols-3">
          {profits.map((card) => (
            <StatCard key={card.label} label={card.label} value={card.value} icon={TrendingUp} color={card.color} />
          ))}
        </div>
      </div>
    </div>
  );
}

function StatCard({
  label,
  value,
  icon: Icon,
  color,
}: {
  label: string;
  value: string;
  icon: LucideIcon;
  color: string;
}) {
  return (
    <Panel>
      <div className="flex items-center gap-3">
        <div
          className="flex h-10 w-10 items-center justify-center border border-[var(--his-border)] bg-[var(--his-panel-alt)]"
          style={{ color }}
        >
          <Icon size={20} />
        </div>
        <div>
          <div className="text-[11px] text-[var(--his-muted)]">{label}</div>
          <div className="text-[18px] font-bold text-[var(--his-header)]">{value}</div>
        </div>
      </div>
    </Panel>
  );
}

function ClinicalReportsPage() {
  const t = useTranslations();
  const edition = useEditionStore((state) => state.edition);
  const { data, isLoading, refetch, isFetching } = useDashboardReport();
  const cards = reportCards(edition, data, t);

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("reports.title")}>
        <span className="text-[12px] text-[var(--his-muted)]">{t("reports.dashboard")}</span>
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
            {cards.map((card) => (
              <Panel key={card.label} title={card.label}>
                <div className="py-2 text-2xl font-semibold tabular-nums">{card.value}</div>
              </Panel>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function reportCards(
  edition: string,
  data: DashboardMetrics | null | undefined,
  t: (key: string) => string,
) {
  const clinical = [
    { label: t("dashboard.patientsToday"), value: data?.patients?.registered_today ?? "—" },
    { label: t("dashboard.appointmentsToday"), value: data?.appointments?.today ?? "—" },
    { label: t("dashboard.openEncounters"), value: data?.encounters?.open ?? "—" },
    { label: t("dashboard.revenueToday"), value: formatMoney(data?.finance?.revenue_today) },
  ];

  if (edition === "clinic") return clinical;
  if (edition === "laboratory") {
    return [...clinical, { label: t("dashboard.labPending"), value: data?.orders?.pending_lab ?? "—" }];
  }
  if (edition === "radiology") {
    return [...clinical, { label: t("dashboard.radPending"), value: data?.orders?.pending_radiology ?? "—" }];
  }

  return [
    ...clinical.slice(0, 3),
    { label: t("dashboard.labPending"), value: data?.orders?.pending_lab ?? "—" },
    { label: t("dashboard.radPending"), value: data?.orders?.pending_radiology ?? "—" },
    { label: t("dashboard.pharmacyPending"), value: data?.finance?.unpaid_invoices ?? "—" },
    {
      label: t("dashboard.bedOccupancy"),
      value: data?.inpatient?.occupancy_rate != null ? `${data.inpatient.occupancy_rate}%` : "—",
    },
    clinical[3],
  ];
}
