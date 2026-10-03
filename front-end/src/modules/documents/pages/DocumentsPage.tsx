"use client";

import Link from "next/link";
import { useLocale, useTranslations } from "next-intl";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Panel } from "@/components/ui/Panel";

export function DocumentsPage() {
  const t = useTranslations();
  const locale = useLocale();

  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={t("nav.documents")} />
      <div className="p-2">
        <Panel title={t("nav.documents")}>
          <p className="mb-2 text-[12px]">
            وثائق المريض تُحفظ داخل ملفه الطبي وترتبط بالزيارة، وليست قائمة عامة منفصلة.
          </p>
          <Link
            href={`/${locale}/dashboard/patients`}
            className="text-[12px] text-[var(--his-accent)] underline-offset-2 hover:underline"
          >
            {t("patients.title")}
          </Link>
        </Panel>
      </div>
    </div>
  );
}
