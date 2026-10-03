"use client";

import type { LucideIcon } from "lucide-react";
import {
  BedDouble,
  CalendarDays,
  FlaskConical,
  Receipt,
  Scan,
  Stethoscope,
  UserRound,
  Wallet,
} from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { apiGet, getErrorMessage } from "@/lib/api/client";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Panel } from "@/components/ui/Panel";
import { formatMoney } from "@/lib/utils";
import { arabicLabel } from "@/i18n/arabicLabels";

type Dashboard = {
  patients?: { registered_today?: number };
  encounters?: { open?: number };
  appointments?: { today?: number };
  orders?: { pending_lab?: number; pending_radiology?: number };
  inpatient?: { occupied_beds?: number; total_beds?: number };
  finance?: { revenue_today?: number; unpaid_invoices?: number };
};

type Financial = {
  totals?: {
    charged?: number;
    invoiced?: number;
    discounts?: number;
    collected?: number;
    refunded?: number;
    net_revenue?: number;
    outstanding?: number;
  };
  by_method?: { method?: string; total?: number; count?: number }[];
  by_service_source?: { source_type?: string; total?: number; count?: number }[];
};

function dayStamp(date: Date) {
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${date.getFullYear()}-${month}-${day}`;
}

function money(value: unknown) {
  return formatMoney(value, "0");
}

function StatCard({
  label,
  value,
  icon: Icon,
  color,
  loading,
}: {
  label: string;
  value: string;
  icon: LucideIcon;
  color: string;
  loading?: boolean;
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
          <div className="text-[18px] font-bold text-[var(--his-header)]">{loading ? "…" : value}</div>
        </div>
      </div>
    </Panel>
  );
}

export default function Page() {
  const t = useTranslations();
  const dashboard = useQuery({
    queryKey: ["reports", "dashboard"],
    queryFn: async () => (await apiGet<Dashboard>("/reports/dashboard")).data,
  });
  const today = new Date();
  const todayStamp = dayStamp(today);
  const monthStart = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-01`;
  const yearStart = `${today.getFullYear()}-01-01`;

  const dayReport = useQuery({
    queryKey: ["reports", "financial", todayStamp, todayStamp],
    queryFn: async () => (await apiGet<Financial>("/reports/financial", { params: { from: todayStamp, to: todayStamp } })).data,
  });
  const monthReport = useQuery({
    queryKey: ["reports", "financial", monthStart, todayStamp],
    queryFn: async () => (await apiGet<Financial>("/reports/financial", { params: { from: monthStart, to: todayStamp } })).data,
  });
  const yearReport = useQuery({
    queryKey: ["reports", "financial", yearStart, todayStamp],
    queryFn: async () => (await apiGet<Financial>("/reports/financial", { params: { from: yearStart, to: todayStamp } })).data,
  });

  const data = dashboard.data;
  const totals = monthReport.data?.totals;
  const error = dashboard.error ?? dayReport.error ?? monthReport.error ?? yearReport.error;

  const revenues = [
    { label: t("reports.revenueDay"), value: money(dayReport.data?.totals?.net_revenue), color: "#c0392b", loading: dayReport.isLoading },
    { label: t("reports.revenueMonth"), value: money(monthReport.data?.totals?.net_revenue), color: "#1b4f8c", loading: monthReport.isLoading },
    { label: t("reports.revenueYear"), value: money(yearReport.data?.totals?.net_revenue), color: "#27ae60", loading: yearReport.isLoading },
  ];

  const stats: { label: string; value: string; icon: LucideIcon; color: string }[] = [
    { label: t("dashboard.patientsToday"), value: String(data?.patients?.registered_today ?? 0), icon: UserRound, color: "#1b4f8c" },
    { label: t("dashboard.appointmentsToday"), value: String(data?.appointments?.today ?? 0), icon: CalendarDays, color: "#2980b9" },
    { label: t("dashboard.openEncounters"), value: String(data?.encounters?.open ?? 0), icon: Stethoscope, color: "#8e44ad" },
    { label: t("dashboard.labPending"), value: String(data?.orders?.pending_lab ?? 0), icon: FlaskConical, color: "#2e86c1" },
    { label: t("dashboard.radPending"), value: String(data?.orders?.pending_radiology ?? 0), icon: Scan, color: "#e67e22" },
    { label: t("reports.unpaidInvoices"), value: String(data?.finance?.unpaid_invoices ?? 0), icon: Wallet, color: "#16a085" },
    {
      label: t("dashboard.bedOccupancy"),
      value: `${data?.inpatient?.occupied_beds ?? 0}/${data?.inpatient?.total_beds ?? 0}`,
      icon: BedDouble,
      color: "#27ae60",
    },
  ];

  const month: { label: string; value: string; color: string }[] = [
    { label: t("reports.charged"), value: money(totals?.charged), color: "#1b4f8c" },
    { label: t("reports.invoiced"), value: money(totals?.invoiced), color: "#2980b9" },
    { label: t("reports.discounts"), value: money(totals?.discounts), color: "#8e44ad" },
    { label: t("reports.collected"), value: money(totals?.collected), color: "#27ae60" },
    { label: t("reports.refunded"), value: money(totals?.refunded), color: "#e67e22" },
    { label: t("reports.netRevenue"), value: money(totals?.net_revenue), color: "#c0392b" },
    { label: t("reports.outstandingNow"), value: money(totals?.outstanding), color: "#16a085" },
  ];

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("reports.dashboard")} />
      <div className="space-y-3 p-2">
        {error ? <p className="text-[12px] text-red-700">{getErrorMessage(error)}</p> : null}
        <div className="grid gap-2 sm:grid-cols-3">
          {revenues.map((card) => (
            <StatCard key={card.label} label={card.label} value={card.value} icon={Receipt} color={card.color} loading={card.loading} />
          ))}
        </div>
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
          {stats.map((card) => (
            <StatCard key={card.label} {...card} loading={dashboard.isLoading} />
          ))}
        </div>

        <div className="text-[12px] font-semibold text-[var(--his-header)]">
          {t("reports.financial")} — {t("reports.thisMonth")}
        </div>
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
          {month.map((card) => (
            <StatCard key={card.label} label={card.label} value={card.value} icon={Receipt} color={card.color} loading={monthReport.isLoading} />
          ))}
          {(monthReport.data?.by_service_source ?? []).map((row) => (
            <StatCard
              key={row.source_type}
              label={arabicLabel(row.source_type)}
              value={money(row.total)}
              icon={Receipt}
              color="#1b4f8c"
              loading={monthReport.isLoading}
            />
          ))}
          {(monthReport.data?.by_method ?? []).map((row) => (
            <StatCard
              key={row.method}
              label={arabicLabel(row.method)}
              value={money(row.total)}
              icon={Wallet}
              color="#16a085"
              loading={monthReport.isLoading}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
