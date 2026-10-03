"use client";

import { useMemo } from "react";
import { arabicLabel } from "@/i18n/arabicLabels";
import { useTranslations } from "next-intl";
import { RefreshCw } from "lucide-react";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type Column } from "@/components/data-table/DataTable";
import { formatDate } from "@/lib/utils";
import { useNotifications } from "../hooks/useNotifications";
import type { NotificationRow } from "../api/notifications.api";

export function NotificationsPage() {
  const t = useTranslations();
  const { data: rows = [], isLoading, refetch, isFetching } = useNotifications();

  const columns = useMemo<Column<NotificationRow>[]>(
    () => [
      {
        key: "title",
        header: t("nav.notifications"),
        cell: (r) => arabicLabel(r.title ?? r.message ?? r.type),
      },
      {
        key: "status",
        header: t("app.status"),
        width: 90,
        cell: (r) => (
          <Badge tone={r.read_at ? "default" : "warning"}>
            {r.read_at ? t("app.close") : t("app.confirm")}
          </Badge>
        ),
      },
      {
        key: "date",
        header: t("app.date"),
        width: 120,
        cell: (r) => formatDate(r.created_at, true),
      },
    ],
    [t],
  );

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.notifications")}>
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
