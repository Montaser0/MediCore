"use client";

import { useState } from "react";
import Link from "next/link";
import { useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { apiClient, apiPost, getErrorMessage } from "@/lib/api/client";
import { Button } from "@/components/ui/Button";
import { ModuleTabs } from "@/components/shared/ModuleTabs";
import { RadiologyCatalogPage } from "@/modules/radiology/pages/RadiologyCatalogPage";
import { OrderResultDialog, uploadResultFiles } from "@/components/shared/OrderResultDialog";
import { ResourceListPage, dateCell, statusBadge } from "@/components/shared/ResourceListPage";
import { deskHrefs } from "@/config/desk";
import { formatMoney } from "@/lib/utils";
import { dailyNo } from "@/lib/arabicText";
import { useAuthStore } from "@/stores/auth-store";

type Row = Record<string, unknown>;

export default function Page() {
  const t = useTranslations();
  const locale = useLocale();
  const qc = useQueryClient();
  const user = useAuthStore((state) => state.user);
  const focused = (deskHrefs(user) ?? []).includes("/dashboard/radiology");
  const permissions = user?.permissions ?? [];
  const canManageServices =
    permissions.includes("*") || permissions.includes("admin") || permissions.includes("radiology.services.manage");
  const [item, setItem] = useState<Row | null>(null);
  const [saving, setSaving] = useState(false);

  async function saveReport(input: { text: string; files: File[]; done: boolean }) {
    if (!item) return;
    const person = item.patient as { id?: number; encounter_id?: number } | undefined;
    setSaving(true);
    try {
      const study = await apiPost<{ id: number }>(`/radiology/items/${item.id}/studies`, {
        technician_notes: input.text || undefined,
      });
      const studyId = study.data?.id;
      if (studyId && input.files.length > 0) {
        const body = new FormData();
        input.files.forEach((file) => body.append("images[]", file));
        await apiClient.post(`/radiology/studies/${studyId}/images`, body, {
          headers: { "Content-Type": undefined },
        });
      }
      if (studyId && (input.text || input.done)) {
        await apiPost(`/radiology/studies/${studyId}/report`, {
          findings: input.text || undefined,
          impression: input.text || "مرفق صور",
          finalise: input.done,
        });
      }
      if (input.files.length) {
        await uploadResultFiles(input.files, {
          entityType: "radiology_order_item",
          entityId: Number(item.id),
          patientId: person?.id,
          encounterId: person?.encounter_id,
          category: "radiology_image",
        }).catch(() => undefined);
      }
      toast.success(t("radiology.savedOnFile"));
      setItem(null);
      await qc.invalidateQueries({ queryKey: ["/radiology/worklist"] });
      await qc.invalidateQueries({ queryKey: ["patients"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  const service = item?.service as { name?: string; price?: number } | undefined;
  const patient = item?.patient as { id?: number; full_name?: string; mrn?: string } | undefined;

  return (
    <>
      <ModuleTabs
        tabs={[
          {
            id: "worklist",
            label: t("radiology.worklist"),
            content: (
              <ResourceListPage
                title={t("radiology.title")}
                endpoint="/radiology/worklist"
                actionsHeader={t("app.operations")}
                removeLabel={t("app.delete")}
                onRemove={focused ? undefined : (row) => apiPost(`/radiology/items/${row.id}/cancel`, {})}
                canRemove={(row) => !["cancelled", "reported"].includes(String(row.status ?? ""))}
                extraActions={(row) => (
                  <>
                    <Button variant="success" onClick={() => setItem(row)}>{t("app.done")}</Button>
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
                      const label = person?.full_name ?? "—";
                      return person?.id ? (
                        <Link className="text-[var(--his-accent)] underline" href={`/${locale}/dashboard/patients/${person.id}`}>
                          {label} {person.mrn ? `(${dailyNo(person.mrn)})` : ""}
                        </Link>
                      ) : label;
                    },
                  },
                  {
                    key: "service",
                    header: t("radiology.study"),
                    render: (row) => {
                      const name = String((row.service as { name?: string } | undefined)?.name ?? row.service_name ?? "—");
                      return row.with_injection ? `${name} — ${t("radiology.withInjection")}` : name;
                    },
                  },
                  {
                    key: "price",
                    header: t("crud.price"),
                    width: 90,
                    render: (row) => formatMoney((row.service as { price?: number } | undefined)?.price),
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
          ...(canManageServices ? [{
            id: "services",
            label: t("nav.radiologyServices"),
            content: <RadiologyCatalogPage />,
          }] : []),
        ]}
      />
      {item ? (
        <OrderResultDialog
          title={t("app.operations")}
          subtitle={`${patient?.full_name ?? ""} ${patient?.mrn ? `— ${dailyNo(patient.mrn)}` : ""} — ${service?.name ?? ""} — ${t("crud.price")}: ${formatMoney(service?.price, "0")}`}
          busy={saving}
          patientId={patient?.id}
          onClose={() => setItem(null)}
          onSave={saveReport}
        />
      ) : null}
    </>
  );
}
