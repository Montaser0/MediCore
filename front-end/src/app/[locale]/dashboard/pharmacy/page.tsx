"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { CrudResourcePage } from "@/components/shared/CrudResourcePage";
import { ModuleTabs } from "@/components/shared/ModuleTabs";
import { OrderResultDialog, uploadResultFiles } from "@/components/shared/OrderResultDialog";
import { ResourceListPage, dateCell, statusBadge } from "@/components/shared/ResourceListPage";
import { deskHrefs } from "@/config/desk";
import { useAuthStore } from "@/stores/auth-store";
import { useEditionStore } from "@/stores/edition-store";
import { formatMoney } from "@/lib/utils";
import { dailyNo } from "@/lib/arabicText";
import { PharmacyCatalogPage } from "@/modules/pharmacy/pages/PharmacyCatalogPage";

type Row = Record<string, unknown>;
type RxItem = {
  id: number;
  quantity?: number;
  remaining_quantity?: number;
  medication?: { name?: string; price?: number };
};
type Warehouse = { id: number; name?: string; is_pharmacy?: boolean };

function prescriptionItems(row: Row): RxItem[] {
  const raw = row.items;
  if (Array.isArray(raw)) return raw as RxItem[];
  if (raw && typeof raw === "object" && Array.isArray((raw as { data?: unknown }).data)) {
    return (raw as { data: RxItem[] }).data;
  }
  return [];
}

export default function Page() {
  const ready = useEditionStore((state) => state.ready);
  const pos = useEditionStore((state) => state.hasFeature("pharmacy.pos"));
  if (!ready) return null;
  if (pos) return <PharmacyCatalogPage />;
  return <PharmacyDeskPage />;
}

function PharmacyDeskPage() {
  const t = useTranslations();
  const locale = useLocale();
  const qc = useQueryClient();
  const focused = (deskHrefs(useAuthStore((state) => state.user)) ?? []).includes("/dashboard/pharmacy");
  const [prescription, setPrescription] = useState<{
    id: number;
    status?: string;
    patient?: string;
    patientId?: number;
    encounterId?: number;
    items: RxItem[];
  } | null>(null);
  const [warehouseId, setWarehouseId] = useState("");
  const [saving, setSaving] = useState(false);
  const { data: warehouses = [], isError: warehousesFailed } = useQuery({
    queryKey: ["/inventory/warehouses", "dispense"],
    queryFn: async () => {
      const res = await apiGet<Warehouse[]>("/inventory/warehouses", { params: { pharmacy_only: true } });
      const rows = Array.isArray(res.data) ? res.data : [];
      return rows.length > 0 ? rows : (await apiGet<Warehouse[]>("/inventory/warehouses")).data ?? [];
    },
  });

  useEffect(() => {
    if (warehouseId || warehouses.length === 0) return;
    const pharmacy = warehouses.find((row) => row.is_pharmacy) ?? warehouses[0];
    if (pharmacy) setWarehouseId(String(pharmacy.id));
  }, [warehouses, warehouseId]);

  async function dispense(input: { text: string; files: File[]; done: boolean }) {
    if (!prescription) return;
    if (input.done && !warehouseId) {
      toast.error(t(warehousesFailed ? "pharmacy.warehousesUnavailable" : "pharmacy.chooseWarehouse"));
      return;
    }
    setSaving(true);
    try {
      if (input.files.length) {
        await uploadResultFiles(input.files, {
          entityType: "prescription",
          entityId: prescription.id,
          patientId: prescription.patientId,
          encounterId: prescription.encounterId,
          category: "prescription",
        });
      }
      if (input.done) {
        if (prescription.status === "draft") {
          await apiPost(`/pharmacy/prescriptions/${prescription.id}/activate`);
        }
        const lines = prescription.items.filter((item) => Number(item.remaining_quantity ?? item.quantity ?? 0) > 0);
        for (const line of lines) {
          await apiPost(`/pharmacy/prescription-items/${line.id}/dispense`, {
            warehouse_id: Number(warehouseId),
            quantity: Number(line.remaining_quantity ?? line.quantity),
            notes: input.text || undefined,
          });
        }
      }
      toast.success(t("pharmacy.savedOnFile"));
      setPrescription(null);
      await qc.invalidateQueries({ queryKey: ["/pharmacy/prescriptions"] });
      await qc.invalidateQueries({ queryKey: ["patients"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <ModuleTabs
        tabs={[
          {
            id: "prescriptions",
            label: t("pharmacy.prescriptions"),
            content: (
              <ResourceListPage
                title={t("pharmacy.title")}
                endpoint="/pharmacy/prescriptions"
                actionsHeader={t("app.operations")}
                removeLabel={t("app.delete")}
                onRemove={focused ? undefined : (row) => apiPost(`/pharmacy/prescriptions/${row.id}/cancel`, { reason: "cancelled" })}
                extraActions={(row) => (
                  <>
                    <Button
                      variant="success"
                      onClick={() => {
                        const person = row.patient as { id?: number; full_name?: string; encounter_id?: number } | undefined;
                        setPrescription({
                          id: Number(row.id),
                          status: String(row.status ?? ""),
                          patient: person?.full_name,
                          patientId: person?.id,
                          encounterId: person?.encounter_id ?? (Number(row.encounter_id ?? 0) || undefined),
                          items: prescriptionItems(row),
                        });
                      }}
                    >
                      {t("app.done")}
                    </Button>
                    <Button
                      onClick={() => {
                        const person = row.patient as { id?: number; full_name?: string; encounter_id?: number } | undefined;
                        setPrescription({
                          id: Number(row.id),
                          status: String(row.status ?? ""),
                          patient: person?.full_name,
                          patientId: person?.id,
                          encounterId: person?.encounter_id ?? (Number(row.encounter_id ?? 0) || undefined),
                          items: prescriptionItems(row),
                        });
                      }}
                    >
                      {t("laboratory.result")}
                    </Button>
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
                    key: "items",
                    header: t("pharmacy.medications"),
                    render: (row) => {
                      const items = prescriptionItems(row);
                      return items.map((item) => item.medication?.name).filter(Boolean).join("، ") || "—";
                    },
                  },
                  {
                    key: "prescribed_at",
                    header: t("app.date"),
                    width: 140,
                    render: (row) => dateCell(row.prescribed_at ?? row.created_at, true),
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
            id: "medications",
            label: t("pharmacy.medications"),
            content: (
              <CrudResourcePage
                title={t("pharmacy.medications")}
                endpoint="/pharmacy/medications"
                columns={[
                  { key: "id", header: "#", width: 60 },
                  { key: "name", header: t("crud.name"), render: (row) => String(row.name ?? "—") },
                  { key: "strength", header: t("crud.type"), render: (row) => String(row.strength ?? "—") },
                  { key: "price", header: t("crud.price"), width: 110, render: (row) => formatMoney(row.price) },
                ]}
                fields={[
                  { key: "name", label: t("crud.name"), required: true },
                  { key: "generic_name", label: t("crud.name") },
                  { key: "strength", label: t("crud.type") },
                  { key: "dosage_form", label: t("crud.type") },
                  { key: "price", label: t("crud.price"), type: "number" },
                ]}
              />
            ),
          }] : []),
        ]}
      />
      {prescription ? (
        <OrderResultDialog
          title={t("app.operations")}
          subtitle={prescription.patient ?? ""}
          busy={saving}
          patientId={prescription.patientId}
          onClose={() => setPrescription(null)}
          onSave={dispense}
          extra={
            <>
              <ul className="m-0 space-y-1 ps-4 text-[12px]">
                {prescription.items.map((item) => (
                  <li key={item.id}>
                    {item.medication?.name ?? "—"} — {t("workspace.quantity")}: {item.remaining_quantity ?? item.quantity ?? "—"} — {t("crud.price")}: {formatMoney(item.medication?.price, "0")}
                  </li>
                ))}
              </ul>
              <label className="grid gap-1 text-[12px]">
                <span>{t("pharmacy.warehouse")}</span>
                <Select value={warehouseId} onChange={(event) => setWarehouseId(event.target.value)}>
                  <option value="">{warehousesFailed ? t("pharmacy.warehousesUnavailable") : t("pharmacy.chooseWarehouse")}</option>
                  {warehouses.map((warehouse) => (
                    <option key={warehouse.id} value={warehouse.id}>
                      {warehouse.name}
                    </option>
                  ))}
                </Select>
              </label>
            </>
          }
        />
      ) : null}
    </>
  );
}
