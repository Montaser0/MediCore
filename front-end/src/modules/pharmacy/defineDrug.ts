import { apiGet, apiPost } from "@/lib/api/client";

export function earliestExpiry(): string {
  const date = new Date();
  date.setDate(date.getDate() + 1);
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${date.getFullYear()}-${month}-${day}`;
}

type Warehouse = { id: number };
type SavedDrug = { id: number };

async function pharmacyWarehouseId(): Promise<number> {
  const listed = await apiGet<Warehouse[]>("/pharmacy/warehouses");
  const existing = listed.data?.[0];
  if (existing?.id) return existing.id;

  const created = await apiPost<Warehouse>("/pharmacy/warehouses", {});
  if (!created.data?.id) {
    throw new Error("لا يوجد مستودع صيدلية");
  }
  return created.data.id;
}

export async function defineDrug(input: {
  catalogueId?: number;
  medicationId?: number;
  name?: string;
  barcode?: string;
  manufacturer?: string;
  dosageForm?: string;
  strength?: string;
  price: number;
  cost: number;
  quantity: number;
  expiry: string;
}): Promise<number> {
  if (!input.expiry || !(input.quantity > 0) || Number.isNaN(input.price) || Number.isNaN(input.cost)) {
    throw new Error("أدخل تاريخ الصلاحية والسعر وسعر الشراء والكمية");
  }

  let medicationId = input.medicationId ?? 0;

  if (input.catalogueId && !medicationId) {
    const adopted = await apiPost<SavedDrug>(`/pharmacy/catalogue/${input.catalogueId}/adopt`, {
      price: input.price,
    });
    medicationId = adopted.data?.id ?? 0;
  } else if (!medicationId) {
    const name = input.name?.trim() ?? "";
    if (!name) throw new Error("اكتب اسم الدواء");
    const created = await apiPost<SavedDrug>("/pharmacy/medications", {
      name,
      manufacturer: input.manufacturer?.trim() || undefined,
      dosage_form: input.dosageForm?.trim() || undefined,
      strength: input.strength?.trim() || undefined,
      price: input.price,
    });
    medicationId = created.data?.id ?? 0;
  }

  if (!medicationId) {
    throw new Error("تعذر حفظ الدواء");
  }

  const warehouseId = await pharmacyWarehouseId();
  await apiPost(`/pharmacy/medications/${medicationId}/receive`, {
    warehouse_id: warehouseId,
    quantity: input.quantity,
    batch_number: input.expiry,
    expiry_date: input.expiry,
    unit_cost: input.cost,
    price: input.price,
    ...(input.barcode?.trim() ? { barcode: input.barcode.trim() } : {}),
  });

  return medicationId;
}
