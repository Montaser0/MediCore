"use client";

import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { Input } from "@/components/ui/Input";
import { apiGet } from "@/lib/api/client";

export type CatalogueHit = {
  id: number;
  name: string;
  display_name: string;
  generic_name?: string | null;
  strength?: string | null;
  dosage_form?: string | null;
  pack?: string | null;
  company?: string | null;
  company_ar?: string | null;
  company_label?: string | null;
  product_type?: string | null;
  registration_status?: string | null;
  stock?: {
    medication_id: number;
    quantity?: number;
    price?: number;
    barcode?: string | null;
  } | null;
};

export function catalogueDetail(item: CatalogueHit) {
  return [item.generic_name, item.strength, item.dosage_form, item.pack, item.company_label || item.company_ar || item.company]
    .map((part) => (part ?? "").trim())
    .filter(Boolean)
    .join(" · ");
}

export function CatalogueSearch({
  productType = "",
  selectedLabel,
  onSelect,
  onQueryChange,
  nameOnly = false,
  placeholder,
  emptyText,
}: {
  productType?: string;
  selectedLabel?: string;
  onSelect: (item: CatalogueHit) => void;
  onQueryChange?: (value: string) => void;
  nameOnly?: boolean;
  placeholder?: string;
  emptyText?: string;
}) {
  const t = useTranslations();
  const [q, setQ] = useState("");
  const [term, setTerm] = useState("");

  useEffect(() => {
    const timer = window.setTimeout(() => setTerm(q.trim()), 250);
    return () => window.clearTimeout(timer);
  }, [q]);

  const { data: hits = [], isFetching } = useQuery({
    queryKey: ["pharmacy-catalogue", term, productType, nameOnly],
    enabled: term.length >= 2,
    queryFn: async () => {
      const res = await apiGet<CatalogueHit[]>("/pharmacy/catalogue", {
        params: {
          search: term,
          per_page: 20,
          ...(productType ? { product_type: productType } : {}),
          ...(nameOnly ? { name_only: 1 } : {}),
        },
      });
      return res.data ?? [];
    },
  });

  return (
    <div>
      <Input
        value={q}
        onChange={(event) => {
          setQ(event.target.value);
          onQueryChange?.(event.target.value);
        }}
        placeholder={placeholder ?? (nameOnly ? t("pharmacy.searchByName") : t("pharmacy.searchCatalogue"))}
      />
      {selectedLabel ? <p className="mt-1 text-[12px]">{selectedLabel}</p> : null}
      {term.length > 0 && term.length < 2 ? (
        <p className="mt-1 text-[12px] text-[var(--his-muted)]">{t("pharmacy.minSearch")}</p>
      ) : null}
      {term.length >= 2 ? (
        <div className="mt-1 max-h-72 overflow-auto border border-[var(--his-border)] bg-white">
          {isFetching && hits.length === 0 ? (
            <p className="px-2 py-2 text-[12px] text-[var(--his-muted)]">{t("app.loading")}</p>
          ) : hits.length === 0 ? (
            <p className="px-2 py-2 text-[12px] text-[var(--his-muted)]">{emptyText ?? t("app.noData")}</p>
          ) : (
            hits.map((item) => (
              <button
                key={item.id}
                type="button"
                className="block w-full border-b border-[var(--his-border)] px-2 py-1.5 text-start last:border-b-0 hover:bg-[var(--his-surface)]"
                onClick={() => {
                  onSelect(item);
                  setQ("");
                  setTerm("");
                  onQueryChange?.("");
                }}
              >
                <span className="block text-[13px]">{nameOnly ? item.name : item.display_name}</span>
                {nameOnly ? null : (
                  <span className="block text-[11px] text-[var(--his-muted)]">
                    {catalogueDetail(item)}
                    {item.product_type ? ` · ${item.product_type}` : ""}
                    {item.stock ? ` · ${t("pharmacy.onShelf")} ${item.stock.quantity ?? 0}` : ""}
                  </span>
                )}
              </button>
            ))
          )}
        </div>
      ) : null}
    </div>
  );
}
