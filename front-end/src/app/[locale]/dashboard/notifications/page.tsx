"use client";

import { useTranslations } from "next-intl";
import { localize } from "@/lib/arabicText";
import {
  ResourceListPage,
  dateCell,
} from "@/components/shared/ResourceListPage";

export default function Page() {
  const t = useTranslations();
  return (
    <ResourceListPage
      title={t("nav.notifications")}
      endpoint="/notifications"
      excel="notifications"
      excelExportOnly
      columns={[
        { key: "id", header: "#", width: 60 },
        {
          key: "title",
          header: "العنوان",
          render: (r) => localize(r.title ?? r.message) || "—",
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
