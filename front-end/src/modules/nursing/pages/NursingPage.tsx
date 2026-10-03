"use client";

import { useMemo } from "react";
import Link from "next/link";
import { useLocale, useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatDate } from "@/lib/utils";
import { dailyNo } from "@/lib/arabicText";
import { useNursingWorklist, type NursingDose } from "../hooks/useNursing";

export function NursingPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { data, isLoading, refetch, isFetching } = useNursingWorklist();
  const rows = data?.doses ?? [];

  const columns = useMemo<Column<NursingDose>[]>(
    () => [
      {
        key: "patient",
        header: t("patients.fullName"),
        cell: (row) =>
          row.patient_id ? (
            <Link href={`/${locale}/dashboard/patients/${row.patient_id}`}>
              {row.patient_name ?? (dailyNo(row.mrn) || row.patient_id)}
            </Link>
          ) : (
            "—"
          ),
      },
      {
        key: "medication",
        header: t("workspace.medAction"),
        cell: (row) => row.medication ?? "—",
      },
      {
        key: "dose",
        header: t("workspace.dose"),
        width: 100,
        cell: (row) => row.dose ?? "—",
      },
      {
        key: "when",
        header: t("app.time"),
        width: 140,
        cell: (row) => formatDate(row.scheduled_at, true),
      },
      {
        key: "status",
        header: t("app.status"),
        width: 100,
        cell: (row) => (
          <Badge tone={row.is_overdue ? "danger" : "info"}>
            {row.is_overdue ? "overdue" : "due"}
          </Badge>
        ),
      },
    ],
    [locale, t],
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.nursing")}>
        <span className="text-[12px] text-[var(--his-muted)]">
          {(data?.counts?.overdue ?? 0)} / {(data?.counts?.upcoming ?? 0)}
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
          rowKey={(row) => row.id}
          loading={isLoading}
          emptyText={t("app.noData")}
        />
      </div>
    </div>
  );
}
