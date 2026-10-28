export type SaleUnit = "علبة" | "شريط" | "حبة";

export const SALE_UNITS: SaleUnit[] = ["شريط", "علبة", "حبة"];
export const PURCHASE_UNITS: SaleUnit[] = ["علبة", "شريط"];

type PackLabels = { box: string; strip: string; tablet: string };

export function unitsPer(unit: SaleUnit, stripsPerBox = 1, tabletsPerStrip = 1): number {
  const strips = Math.max(1, stripsPerBox);
  const tablets = Math.max(1, tabletsPerStrip);
  if (unit === "شريط") return tablets;
  if (unit === "حبة") return 1;
  return strips * tablets;
}

export function toBase(quantity: number, unit: SaleUnit, stripsPerBox = 1, tabletsPerStrip = 1): number {
  return Math.round(quantity * unitsPer(unit, stripsPerBox, tabletsPerStrip) * 100) / 100;
}

export function baseOnHand(drug: {
  quantity?: number;
  stock_ready?: boolean;
  stock?: { quantity_on_hand?: number } | null;
}): number {
  if (drug.stock_ready) return Number(drug.stock?.quantity_on_hand ?? 0);
  return Number(drug.quantity ?? 0);
}

export function availableIn(
  base: number,
  unit: SaleUnit,
  stripsPerBox = 1,
  tabletsPerStrip = 1,
): number {
  const per = unitsPer(unit, stripsPerBox, tabletsPerStrip);
  if (per <= 1) return Math.round(base * 100) / 100;
  return Math.floor((base + 1e-6) / per);
}

export function unitPrice(
  drug: { price?: number; price_strip?: number; price_tablet?: number; strips_per_box?: number; tablets_per_strip?: number },
  unit: SaleUnit,
): number {
  const strips = Math.max(1, Number(drug.strips_per_box ?? 1));
  const tablets = Math.max(1, Number(drug.tablets_per_strip ?? 1));
  const box = Number(drug.price ?? 0);
  const strip = Number(drug.price_strip ?? 0) > 0 ? Number(drug.price_strip) : Math.round((box / strips) * 100) / 100;
  const tablet = Number(drug.price_tablet ?? 0) > 0 ? Number(drug.price_tablet) : Math.round((strip / tablets) * 100) / 100;
  if (unit === "شريط") return strip;
  if (unit === "حبة") return tablet;
  return box;
}

export function preferredSaleUnit(
  base: number,
  stripsPerBox = 1,
  tabletsPerStrip = 1,
): SaleUnit {
  if (availableIn(base, "شريط", stripsPerBox, tabletsPerStrip) > 0) return "شريط";
  if (availableIn(base, "علبة", stripsPerBox, tabletsPerStrip) > 0) return "علبة";
  return "حبة";
}

export function formatPack(base: number, stripsPerBox = 1, tabletsPerStrip = 1, labels: PackLabels): string {
  const strips = Math.max(1, stripsPerBox);
  const tablets = Math.max(1, tabletsPerStrip);
  const perBox = strips * tablets;
  if (perBox === 1 && tablets === 1) return String(Math.round(base * 100) / 100);
  let left = Math.round(base * 100) / 100;
  const boxes = Math.floor((left + 1e-6) / perBox);
  left = Math.round((left - boxes * perBox) * 100) / 100;
  const stripCount = Math.floor((left + 1e-6) / tablets);
  left = Math.round((left - stripCount * tablets) * 100) / 100;
  const parts = [
    boxes ? `${boxes} ${labels.box}` : "",
    stripCount ? `${stripCount} ${labels.strip}` : "",
    left ? `${left} ${labels.tablet}` : "",
  ].filter(Boolean);
  return parts.join(" + ") || "0";
}
