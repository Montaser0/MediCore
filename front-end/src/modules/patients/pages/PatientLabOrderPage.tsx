"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { PageToolbar } from "@/components/ui/PageToolbar";
import { Button } from "@/components/ui/Button";
import { apiGet, getErrorMessage } from "@/lib/api/client";
import { env } from "@/config/environment";
import { dailyNo } from "@/lib/arabicText";
import { formatMoney } from "@/lib/utils";
import { LAB_CATEGORIES, type LabCategoryId } from "@/modules/laboratory/categories";
import { printOrderSlip } from "../print/printOrderSlip";
import { usePatient } from "../hooks/usePatients";
import { useJourneyAction, usePatientEncounters } from "../hooks/useJourney";
import { journeyApi, type NamedRow } from "../api/journey.api";

function categoryTitle(
  t: (key: "laboratory.hematology" | "laboratory.chemistry" | "laboratory.hormones" | "laboratory.tumors") => string,
  id: LabCategoryId,
) {
  switch (id) {
    case "hematology":
      return t("laboratory.hematology");
    case "chemistry":
      return t("laboratory.chemistry");
    case "hormones":
      return t("laboratory.hormones");
    case "tumors":
      return t("laboratory.tumors");
  }
}

export function PatientLabOrderPage({ patientId }: { patientId: string }) {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const run = useJourneyAction(patientId);
  const { data: patient } = usePatient(patientId);
  const { data: encounters = [] } = usePatientEncounters(patientId);
  const [picked, setPicked] = useState<number[]>([]);
  const [notes, setNotes] = useState("");

  const { data: tests = [], isLoading } = useQuery({
    queryKey: ["/laboratory/tests", "order-sheet"],
    queryFn: async () => {
      const res = await apiGet<NamedRow[]>("/laboratory/tests", { params: { per_page: 200 } });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const visit = useMemo(() => {
    const open = encounters.filter((row) => row.is_open);
    return open.sort((a, b) => b.id - a.id)[0] ?? null;
  }, [encounters]);

  const columns = useMemo(
    () =>
      LAB_CATEGORIES.map((category) => ({
        ...category,
        tests: tests
          .filter((row) => row.category === category.id)
          .sort((a, b) => (a.name ?? "").localeCompare(b.name ?? "", "ar")),
      })),
    [tests],
  );

  const selected = tests.filter((row) => picked.includes(row.id));
  const total = selected.reduce((sum, row) => sum + (Number(row.price) || 0), 0);
  const backHref = `/${locale}/dashboard/patients/${patientId}`;

  function toggle(id: number) {
    setPicked((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));
  }

  function toggleColumn(ids: number[], on: boolean) {
    setPicked((current) => {
      const next = new Set(current);
      ids.forEach((id) => (on ? next.add(id) : next.delete(id)));
      return [...next];
    });
  }

  async function submit(printSlip = false) {
    if (!visit) {
      toast.error(t("workspace.needVisit"));
      return;
    }
    if (selected.length === 0) {
      toast.error(t("laboratory.pickTests"));
      return;
    }
    const preview = printSlip ? window.open("", "_blank", "width=900,height=720") : null;
    if (printSlip && !preview) {
      toast.error("اسمح بالنوافذ المنبثقة لطباعة الوصفة");
      return;
    }
    try {
      const created = (await run.mutateAsync(() =>
        journeyApi.order({
          encounter_id: visit.id,
          type: "laboratory",
          priority: "routine",
          clinical_notes: notes.trim(),
          items: selected.map((row) => ({
            service_type: "lab_test",
            service_id: row.id,
            name: row.name,
          })),
        }),
      )) as { data?: { order_number?: string } };
      if (printSlip) {
        printOrderSlip(
          {
            kind: "laboratory",
            orderNumber: created.data?.order_number,
            hospitalName: env.appName,
            patientName: patient?.full_name,
            mrn: patient?.mrn,
            age: patient?.age == null ? undefined : String(patient.age),
            gender: patient?.gender === "male" ? "ذكر" : patient?.gender === "female" ? "أنثى" : undefined,
            phone: patient?.phone ?? undefined,
            doctor: visit.doctor?.name,
            clinic: visit.clinic?.name,
            notes: notes.trim(),
            lines: selected.map((row) => ({ name: row.name ?? "—", price: Number(row.price) || 0 })),
          },
          preview,
        );
      }
      toast.success(t("workspace.saved"));
      router.push(backHref);
    } catch (error) {
      preview?.close();
      toast.error(getErrorMessage(error));
    }
  }

  return (
    <div className="flex h-full min-h-0 flex-col">
      <PageToolbar title={t("laboratory.orderSheet")}>
        <span className="text-[12px]">
          {patient?.full_name ?? "—"}
          {patient?.mrn ? ` — ${dailyNo(patient.mrn)}` : ""}
        </span>
        {visit?.clinic?.name || visit?.doctor?.name ? (
          <span className="text-[12px] text-[var(--his-muted)]">
            {[visit.clinic?.name, visit.doctor?.name].filter(Boolean).join(" — ")}
          </span>
        ) : null}
        <Button onClick={() => router.push(backHref)}>{t("app.back")}</Button>
      </PageToolbar>

      {!visit ? (
        <div className="border-b border-[var(--his-border)] bg-[#fef5e7] px-3 py-2 text-[12px] text-[#af601a]">
          {t("workspace.needVisit")}
        </div>
      ) : null}

      <div className="grid min-h-0 flex-1 grid-cols-1 gap-2 p-2 md:grid-cols-2 xl:grid-cols-4">
        {columns.map((column) => {
          const ids = column.tests.map((row) => row.id);
          const allOn = ids.length > 0 && ids.every((id) => picked.includes(id));
          return (
            <section key={column.id} className="his-panel flex min-h-48 flex-col">
              <header className="his-panel-header flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={allOn}
                  disabled={ids.length === 0}
                  ref={(input) => {
                    if (input) input.indeterminate = !allOn && ids.some((id) => picked.includes(id));
                  }}
                  onChange={(event) => toggleColumn(ids, event.target.checked)}
                  aria-label={t("laboratory.allInColumn")}
                />
                <span>{categoryTitle(t, column.id)}</span>
                <span className="ms-auto font-normal text-[var(--his-muted)]">{column.tests.length}</span>
              </header>
              <div className="min-h-0 flex-1 overflow-auto">
                {isLoading ? (
                  <p className="p-2 text-[12px] text-[var(--his-muted)]">{t("app.loading")}</p>
                ) : column.tests.length === 0 ? (
                  <p className="p-2 text-[12px] text-[var(--his-muted)]">{t("app.noData")}</p>
                ) : (
                  column.tests.map((row) => {
                    const on = picked.includes(row.id);
                    return (
                      <label
                        key={row.id}
                        className="flex cursor-pointer items-center gap-2 border-b border-[var(--his-border)] px-2 py-1 text-[12px]"
                        style={{ background: on ? "var(--his-accent-soft)" : "transparent" }}
                      >
                        <input type="checkbox" checked={on} onChange={() => toggle(row.id)} />
                        <span className="min-w-0 flex-1">{row.name}</span>
                      </label>
                    );
                  })
                )}
              </div>
            </section>
          );
        })}
      </div>

      <form
        className="his-action-bar items-center"
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        <span className="text-[12px]">
          {t("laboratory.selected")}: {selected.length}
          {selected.length > 0 ? ` — ${formatMoney(total, "0")}` : ""}
        </span>
        <input
          className="his-input max-w-md"
          placeholder={t("laboratory.notes")}
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
        />
        <Button type="button" onClick={() => router.push(backHref)}>
          {t("app.cancel")}
        </Button>
        <Button type="button" disabled={run.isPending || !visit} onClick={() => void submit(true)}>
          {t("laboratory.prescription")}
        </Button>
        <Button type="submit" variant="primary" disabled={run.isPending || !visit}>
          {t("app.save")}
        </Button>
      </form>
    </div>
  );
}
