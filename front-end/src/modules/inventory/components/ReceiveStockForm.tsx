"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { useAuthStore } from "@/stores/auth-store";

type Item = { id: number; name?: string; item_type?: string; is_batch_tracked?: boolean };
type Warehouse = { id: number; name?: string; is_pharmacy?: boolean };

export function ReceiveStockForm({ pharmacyOnly = false }: { pharmacyOnly?: boolean }) {
  const t = useTranslations();
  const qc = useQueryClient();
  const canReceive = useAuthStore((state) => state.hasPermission("inventory.stock.receive"));
  const [itemId, setItemId] = useState("");
  const [warehouseId, setWarehouseId] = useState("");
  const [quantity, setQuantity] = useState("");
  const [expiry, setExpiry] = useState("");
  const [batch, setBatch] = useState("");
  const [saving, setSaving] = useState(false);

  const { data: items = [] } = useQuery({
    queryKey: ["/inventory/items", "receive", pharmacyOnly],
    enabled: canReceive,
    queryFn: async () => {
      const res = await apiGet<Item[]>("/inventory/items", {
        params: { per_page: 200, ...(pharmacyOnly ? { item_type: "medicine" } : {}) },
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const { data: warehouses = [] } = useQuery({
    queryKey: ["/inventory/warehouses", "receive", pharmacyOnly],
    enabled: canReceive,
    queryFn: async () => {
      const res = await apiGet<Warehouse[]>("/inventory/warehouses", {
        params: pharmacyOnly ? { pharmacy_only: true } : undefined,
      });
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  useEffect(() => {
    if (warehouseId || warehouses.length === 0) return;
    const pharmacy = warehouses.find((row) => row.is_pharmacy) ?? warehouses[0];
    if (pharmacy) setWarehouseId(String(pharmacy.id));
  }, [warehouses, warehouseId]);

  const selected = items.find((row) => String(row.id) === itemId);
  const needsExpiry = selected?.item_type === "medicine" || selected?.item_type === "medical_supply";

  if (!canReceive) return null;

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (!itemId || !warehouseId || !quantity) return;
    if (needsExpiry && !expiry) {
      toast.error(t("inventory.expiryRequired"));
      return;
    }
    setSaving(true);
    try {
      await apiPost("/inventory/stock/receive", {
        inventory_item_id: Number(itemId),
        warehouse_id: Number(warehouseId),
        quantity: Number(quantity),
        expiry_date: expiry || undefined,
        batch_number: batch || undefined,
      });
      toast.success(t("inventory.received"));
      setQuantity("");
      setBatch("");
      await qc.invalidateQueries({ queryKey: ["/inventory/stock-levels"] });
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <form className="his-panel mb-2" onSubmit={submit}>
      <div className="his-panel-header">{t("inventory.receive")}</div>
      <div className="his-field-grid p-2">
        <div className="his-field">
          <label>{t("inventory.items")}</label>
          <Select value={itemId} onChange={(event) => setItemId(event.target.value)} required>
            <option value="">{t("inventory.items")}</option>
            {items.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </Select>
        </div>
        <div className="his-field">
          <label>{t("inventory.warehouses")}</label>
          <Select value={warehouseId} onChange={(event) => setWarehouseId(event.target.value)} required>
            <option value="">{t("inventory.warehouses")}</option>
            {warehouses.map((warehouse) => (
              <option key={warehouse.id} value={warehouse.id}>
                {warehouse.name}
              </option>
            ))}
          </Select>
        </div>
        <div className="his-field">
          <label>{t("workspace.quantity")}</label>
          <Input type="number" min="0.01" step="0.01" value={quantity} onChange={(event) => setQuantity(event.target.value)} required />
        </div>
        <div className="his-field">
          <label>{t("inventory.expiry")}</label>
          <Input type="date" value={expiry} onChange={(event) => setExpiry(event.target.value)} required={needsExpiry} />
        </div>
        <div className="his-field">
          <label>{t("inventory.batch")}</label>
          <Input value={batch} onChange={(event) => setBatch(event.target.value)} />
        </div>
        <div className="flex items-center">
          <Button type="submit" variant="primary" disabled={saving}>
            {t("inventory.receive")}
          </Button>
        </div>
      </div>
    </form>
  );
}
