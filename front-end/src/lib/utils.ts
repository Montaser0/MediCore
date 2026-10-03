import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatMoney(value: unknown, empty = "—") {
  if (value === undefined || value === null || value === "") return empty;
  const amount = Number(value);
  if (!Number.isFinite(amount)) return empty;
  return latinDigits(
    new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD",
      numberingSystem: "latn",
      minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
      maximumFractionDigits: 2,
    }).format(amount),
  );
}

const ARABIC_DIGITS = "٠١٢٣٤٥٦٧٨٩";
const PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹";

export function latinDigits(value: string) {
  return value
    .replace(/[٠-٩]/g, (digit) => String(ARABIC_DIGITS.indexOf(digit)))
    .replace(/[۰-۹]/g, (digit) => String(PERSIAN_DIGITS.indexOf(digit)));
}

export function formatDate(value?: string | null, withTime = false) {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return latinDigits(value);
  const day = latinDigits(
    new Intl.DateTimeFormat("en-GB", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      numberingSystem: "latn",
    }).format(date),
  );
  if (!withTime) return day;
  const time = latinDigits(
    new Intl.DateTimeFormat("en-GB", {
      hour: "2-digit",
      minute: "2-digit",
      hourCycle: "h23",
      numberingSystem: "latn",
    }).format(date),
  );
  return `${day} ${time}`;
}

export function fullName(parts: {
  first_name?: string | null;
  father_name?: string | null;
  last_name?: string | null;
  full_name?: string | null;
}) {
  if (parts.full_name) return parts.full_name;
  return [parts.first_name, parts.father_name, parts.last_name]
    .filter(Boolean)
    .join(" ");
}
