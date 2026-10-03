"use client";

import { useState } from "react";
import Link from "next/link";
import { useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { apiPost, getErrorMessage } from "@/lib/api/client";
import { Button } from "@/components/ui/Button";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";
import { ModuleTabs } from "@/components/shared/ModuleTabs";
import { OrderResultDialog, uploadResultFiles } from "@/components/shared/OrderResultDialog";
import { ResourceListPage, dateCell, statusBadge } from "@/components/shared/ResourceListPage";
import { deskHrefs } from "@/config/desk";
import { useAuthStore } from "@/stores/auth-store";
import { formatMoney } from "@/lib/utils";
import { dailyNo } from "@/lib/arabicText";

type Row = Record<string, unknown>;

export default function Page() {
  const t = useTranslations();
  const locale = useLocale();
  const qc = useQueryClient();
  const focused = (deskHrefs(useAuthStore((state) => state.user)) ?? []).includes("/dashboard/laboratory");
  const [item, setItem] = useState<Row | null>(null);
  const [saving, setSaving] = useState(false);

  async function saveResult(input: { text: string; files: File[]; done: boolean }) {
    if (!item) return;
    const person = item.patient as { id?: number; encounter_id?: number } | undefined;
    setSaving(true);
    try {
      await apiPost(`/laboratory/items/${item.id}/samples`, {}).catch(() => undefined);
      const created = await apiPost<{ id: number }>(`/laboratory/items/${item.id}/result`, {
        result_value: input.text || (input.files.length ? "مرفق صور" : "تم"),
      });
      if (input.files.length) {
        await uploadResultFiles(input.files, {
          entityType: "lab_order_item",
          entityId: Number(item.id),
          patientId: person?.id,
          encounterId: person?.encounter_id,
          category: "lab_attachment",
        });
      }
      if (input.done && created.data?.id) {
        await apiPost(`/laboratory/results/${created.data.id}/verify`);
      }
      toast.success(t("laboratory.savedOnFile"));
      setItem(null);
      await qc.invalidateQueries({ queryKey: ["/laboratory/worklist"] });
      await qc.invalidateQueries({ queryKey: ["patients"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  const test = item?.test as { name?: string; price?: number; unit?: string } | undefined;
  const patient = item?.patient as { id?: number; full_name?: string; mrn?: string } | undefined;

  return (
    <>
      <ModuleTabs
        tabs={[
          {
            id: "worklist",
            label: t("laboratory.worklist"),
            content: (
              <ResourceListPage
                title={t("laboratory.title")}
                endpoint="/laboratory/worklist"
                actionsHeader={t("app.operations")}
                removeLabel={t("app.delete")}
                onRemove={focused ? undefined : (row) => apiPost(`/laboratory/items/${row.id}/cancel`, {})}
                extraActions={(row) => (
                  <>
                    <Button variant="success" onClick={() => setItem(row)}>
                      {t("app.done")}
                    </Button>
                    <Button onClick={() => setItem(row)}>{t("laboratory.result")}</Button>
                  </>
                )}
                columns={[
                  { key: "id", header: "#", width: 60 },
                  {
                    key: "patient",
                    header: t("patients.fullName"),
                    render: (row) => {
                      const person = row.patient as { id?: number; full_name?: string; mrn?: string } | undefined;
                      const label = person?.full_name ?? String(row.patient_name ?? "—");
                      return person?.id ? (
                        <Link className="text-[var(--his-accent)] underline" href={`/${locale}/dashboard/patients/${person.id}`}>
                          {label} {person.mrn ? `(${dailyNo(person.mrn)})` : ""}
                        </Link>
                      ) : label;
                    },
                  },
                  {
                    key: "test",
                    header: t("laboratory.tests"),
                    render: (row) => String((row.test as { name?: string } | undefined)?.name ?? row.test_name ?? "—"),
                  },
                  {
                    key: "price",
                    header: t("crud.price"),
                    width: 90,
                    render: (row) => formatMoney((row.test as { price?: number } | undefined)?.price),
                  },
                  {
                    key: "ordered_at",
                    header: t("app.date"),
                    width: 140,
                    render: (row) => dateCell((row.order as { ordered_at?: string } | undefined)?.ordered_at ?? row.created_at, true),
                  },
                  {
                    key: "status",
                    header: t("app.status"),
                    width: 110,
                    render: (row) => statusBadge(row.status),
                  },
                ]}
              />
            ),
          },
          ...(!focused ? [{
            id: "tests",
            label: t("laboratory.tests"),
            content: (
              <CrudResourcePage
                title={t("laboratory.tests")}
                endpoint="/laboratory/tests"
                columns={[
                  { key: "id", header: "#", width: 60 },
                  { key: "name", header: t("crud.name"), render: (row) => String(row.name ?? "—") },
                  { key: "code", header: t("crud.code"), width: 100, render: (row) => String(row.code ?? "—") },
                  { key: "price", header: t("crud.price"), width: 110, render: (row) => formatMoney(row.price) },
                ]}
                fields={[
                  { key: "name", label: t("crud.name"), required: true },
                  { key: "code", label: t("crud.code"), required: true },
                  { key: "category", label: t("crud.type") },
                  { key: "sample_type", label: t("laboratory.sample") },
                  { key: "unit", label: t("laboratory.resultValue") },
                  { key: "price", label: t("crud.price"), type: "number" },
                ]}
              />
            ),
          }] : []),
        ]}
      />
      {item ? (
        <OrderResultDialog
          title={t("app.operations")}
          subtitle={`${patient?.full_name ?? ""} ${patient?.mrn ? `— ${dailyNo(patient.mrn)}` : ""} — ${test?.name ?? ""} — ${t("crud.price")}: ${formatMoney(test?.price, "0")}`}
          busy={saving}
          patientId={patient?.id}
          onClose={() => setItem(null)}
          onSave={saveResult}
        />
      ) : null}
    </>
  );
}
