"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatDate } from "@/lib/utils";
import { arabicLabel } from "@/i18n/arabicLabels";
import { localize } from "@/lib/arabicText";
import { usePrescriptions } from "../hooks/usePharmacy";
import type { PrescriptionRow } from "../api/pharmacy.api";

export function PharmacyPage() {
  const t = useTranslations();
  const { data: rows = [], isLoading, refetch, isFetching } = usePrescriptions();

  const columns = useMemo<Column<PrescriptionRow>[]>(
    () => [
      {
        key: "num",
        header: "#",
        width: 90,
        cell: (r) => localize(r.prescription_number ?? r.id),
      },
      {
        key: "patient",
        header: t("patients.fullName"),
        cell: (r) => r.patient?.full_name ?? "—",
      },
      {
        key: "doctor",
        header: t("appointments.doctor"),
        width: 130,
        cell: (r) => r.doctor?.name ?? "—",
      },
      {
        key: "status",
        header: t("app.status"),
        width: 110,
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
      <PageToolbar title={t("pharmacy.title")}>
        <span className="text-[12px] text-[var(--his-muted)]">
          {t("pharmacy.prescriptions")}
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
