"use client";

import { useTranslations } from "next-intl";
import {
  ResourceListPage,
  dateCell,
} from "@/components/shared/ResourceListPage";

export default function Page() {
  const t = useTranslations();
  return (
    <ResourceListPage
      title={t("nav.audit")}
      endpoint="/audit/logs"
      columns={[
        { key: "id", header: "#", width: 60 },
        {
          key: "action",
          header: "الإجراء",
          render: (r) => String(r.action ?? r.event ?? "—"),
        },
        {
          key: "user",
          header: t("app.user"),
          render: (r) =>
            String(
              (r.user as { display_name?: string } | undefined)?.display_name ??
                r.user_name ??
                "—",
            ),
        },
        {
          key: "created_at",
          header: t("app.date"),
          width: 140,
          render: (r) => dateCell(r.created_at, true),
        },
      ]}
    />
  );
}
