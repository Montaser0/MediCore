"use client";

import { useState } from "react";
import Link from "next/link";
import { useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { apiPost, getErrorMessage } from "@/lib/api/client";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { ModuleTabs } from "@/components/shared/ModuleTabs";
import { BLOOD_GROUPS } from "@/modules/laboratory/blood";
import { LabCatalogPage } from "@/modules/laboratory/pages/LabCatalogPage";
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
  const user = useAuthStore((state) => state.user);
  const focused = (deskHrefs(user) ?? []).includes("/dashboard/laboratory");
  const permissions = user?.permissions ?? [];
  const canManageTests =
    permissions.includes("*") || permissions.includes("admin") || permissions.includes("laboratory.tests.manage");
  const canConfirmBlood =
    permissions.includes("*") || permissions.includes("admin") || permissions.includes("laboratory.results.create");
  const [item, setItem] = useState<Row | null>(null);
  const [blood, setBlood] = useState<Row | null>(null);
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

  async function saveBlood(input: { group: string; bag: string; date: string }) {
    if (!blood) return;
    setSaving(true);
    try {
      await apiPost(`/laboratory/blood-requests/${blood.id}/result`, {
        confirmed_blood_group: input.group,
        bag_number: input.bag,
        issued_at: input.date,
      });
      toast.success(t("laboratory.savedOnFile"));
      setBlood(null);
      await qc.invalidateQueries({ queryKey: ["/laboratory/blood-requests"] });
      await qc.invalidateQueries({ queryKey: ["patients"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  const test = item?.test as { name?: string; price?: number; unit?: string } | undefined;
  const patient = item?.patient as { id?: number; full_name?: string; mrn?: string } | undefined;
  const bloodPatient = blood?.patient as { full_name?: string; mrn?: string } | undefined;

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
          {
            id: "blood",
            label: t("laboratory.bloodRequests"),
            content: (
              <ResourceListPage
                title={t("laboratory.bloodRequests")}
                endpoint="/laboratory/blood-requests"
                actionsHeader={t("app.operations")}
                extraActions={
                  canConfirmBlood
                    ? (row) => (
                        <Button variant="success" onClick={() => setBlood(row)}>
                          {t("laboratory.confirm")}
                        </Button>
                      )
                    : undefined
                }
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
                      ) : (
                        label
                      );
                    },
                  },
                  {
                    key: "product_type",
                    header: t("crud.name"),
                    width: 130,
                    render: (row) => String(row.product_label ?? row.product_type ?? "—"),
                  },
                  { key: "blood_group", header: t("laboratory.bloodGroup"), width: 80 },
                  { key: "units", header: t("laboratory.units"), width: 90 },
                  {
                    key: "price",
                    header: t("crud.price"),
                    width: 100,
                    render: (row) => formatMoney(row.price, "0"),
                  },
                  {
                    key: "confirmed_blood_group",
                    header: t("laboratory.confirmGroup"),
                    width: 110,
                    render: (row) => String(row.confirmed_blood_group ?? "—"),
                  },
                  {
                    key: "bag_number",
                    header: t("laboratory.bagNumber"),
                    width: 120,
                    render: (row) => String(row.bag_number ?? "—"),
                  },
                  {
                    key: "issued_at",
                    header: t("laboratory.issueDate"),
                    width: 110,
                    render: (row) => dateCell(row.issued_at),
                  },
                  {
                    key: "status",
                    header: t("app.status"),
                    width: 100,
                    render: (row) => statusBadge(row.status),
                  },
                ]}
              />
            ),
          },
          ...(canManageTests ? [{
            id: "tests",
            label: t("nav.labTests"),
            content: <LabCatalogPage />,
          }] : []),
        ]}
      />
      {blood ? (
        <BloodResultDialog
          key={String(blood.id)}
          title={t("laboratory.confirm")}
          subtitle={`${bloodPatient?.full_name ?? ""} ${bloodPatient?.mrn ? `— ${dailyNo(bloodPatient.mrn)}` : ""} — ${String(blood.product_label ?? "")}`}
          group={String(blood.confirmed_blood_group || blood.blood_group || "")}
          bag={String(blood.bag_number ?? "")}
          date={String(blood.issued_at ?? "")}
          busy={saving}
          onClose={() => setBlood(null)}
          onSave={saveBlood}
        />
      ) : null}
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

function today() {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");
  return `${now.getFullYear()}-${month}-${day}`;
}

function BloodResultDialog({
  title,
  subtitle,
  group,
  bag,
  date,
  busy,
  onClose,
  onSave,
}: {
  title: string;
  subtitle: string;
  group: string;
  bag: string;
  date: string;
  busy: boolean;
  onClose: () => void;
  onSave: (input: { group: string; bag: string; date: string }) => Promise<void>;
}) {
  const t = useTranslations();
  const [confirmed, setConfirmed] = useState(group);
  const [bagNumber, setBagNumber] = useState(bag);
  const [issuedAt, setIssuedAt] = useState(date || today());

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
      <form
        className="his-panel w-full max-w-lg"
        onSubmit={(event) => {
          event.preventDefault();
          void onSave({ group: confirmed, bag: bagNumber.trim(), date: issuedAt });
        }}
      >
        <div className="his-panel-header">{title}</div>
        <div className="space-y-2 p-3">
          <div className="text-[12px]">{subtitle}</div>
          <label className="grid gap-1 text-[12px]">
            <span>{t("laboratory.confirmGroup")}</span>
            <Select required value={confirmed} onChange={(event) => setConfirmed(event.target.value)}>
              <option value="">—</option>
              {BLOOD_GROUPS.map((item) => (
                <option key={item} value={item}>
                  {item}
                </option>
              ))}
            </Select>
          </label>
          <label className="grid gap-1 text-[12px]">
            <span>{t("laboratory.bagNumber")}</span>
            <Input required value={bagNumber} onChange={(event) => setBagNumber(event.target.value)} />
          </label>
          <label className="grid gap-1 text-[12px]">
            <span>{t("laboratory.issueDate")}</span>
            <Input required type="date" value={issuedAt} onChange={(event) => setIssuedAt(event.target.value)} />
          </label>
        </div>
        <div className="flex justify-end gap-1 border-t border-[var(--his-border)] bg-[var(--his-panel-alt)] p-2">
          <Button type="button" onClick={onClose}>
            {t("app.cancel")}
          </Button>
          <Button type="submit" variant="success" disabled={busy}>
            {t("laboratory.confirm")}
          </Button>
        </div>
      </form>
    </div>
  );
}
