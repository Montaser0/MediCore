const ARABIC_DIGITS = "٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹";

function toWesternDigits(value: string): string {
  return value.replace(/[٠-٩۰-۹]/g, (digit) => String(ARABIC_DIGITS.indexOf(digit) % 10));
}

/** Normalizes typed or barcode-scanned national ids, including Arabic digits. */
export function readNationalId(raw: string): string {
  const cleaned = toWesternDigits(raw.replace(/[\u0000-\u001F]/g, "")).trim();
  const compact = cleaned.replace(/\s+/g, "");
  if (/^\d+$/.test(compact)) return compact;
  const embedded = compact.match(/\d{11}/);
  if (embedded && /\D/.test(compact)) return embedded[0];
  return cleaned;
}
