export type BulletinRow = {
  barcode?: string;
  source_record_id?: string;
  name?: string;
  company?: string;
  official_price: number;
  composition?: string;
  generic_name?: string;
};

const HEADERS: Record<string, keyof BulletinRow | "official_price"> = {
  barcode: "barcode",
  الباركود: "barcode",
  official_price: "official_price",
  price: "official_price",
  السعر: "official_price",
  "السعر الرسمي": "official_price",
  composition: "composition",
  التركيب: "composition",
  generic_name: "generic_name",
  generic: "generic_name",
  المادة: "generic_name",
  "المادة الفعالة": "generic_name",
  source_record_id: "source_record_id",
  name: "name",
  الاسم: "name",
  "الاسم التجاري": "name",
  company: "company",
  الشركة: "company",
};

export function parseBulletin(text: string): BulletinRow[] {
  const lines = text
    .replace(/^\uFEFF/, "")
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);
  if (lines.length === 0) return [];

  const delimiter = lines[0].includes(";") ? ";" : lines[0].includes("\t") ? "\t" : ",";
  const headerCells = splitCsv(lines[0], delimiter).map((cell) => HEADERS[cell.trim()] ?? HEADERS[cell.trim().toLowerCase()]);
  const hasHeader = headerCells.some(Boolean);
  const body = hasHeader ? lines.slice(1) : lines;
  const rows: BulletinRow[] = [];

  for (const line of body) {
    const values = splitCsv(line, delimiter).map((cell) => cell.trim());
    const record: Partial<BulletinRow> = {};
    if (hasHeader) {
      headerCells.forEach((key, index) => {
        if (!key) return;
        const value = values[index] ?? "";
        if (key === "official_price") record.official_price = Number(value);
        else record[key] = value;
      });
    } else {
      record.barcode = values[0];
      record.official_price = Number(values[1]);
      record.composition = values[2];
      record.generic_name = values[3];
      record.name = values[4];
      record.company = values[5];
      record.source_record_id = values[6];
    }

    if (!Number.isFinite(record.official_price)) continue;
    const row: BulletinRow = { official_price: Number(record.official_price) };
    for (const key of ["barcode", "source_record_id", "name", "company", "composition", "generic_name"] as const) {
      const value = (record[key] ?? "").trim();
      if (value) row[key] = value;
    }
    if (!row.barcode && !row.source_record_id && !row.name) continue;
    rows.push(row);
  }

  return rows;
}

function splitCsv(line: string, delimiter: string): string[] {
  const cells: string[] = [];
  let current = "";
  let quoted = false;
  for (let index = 0; index < line.length; index += 1) {
    const char = line[index];
    if (char === '"') {
      if (quoted && line[index + 1] === '"') {
        current += '"';
        index += 1;
      } else {
        quoted = !quoted;
      }
      continue;
    }
    if (char === delimiter && !quoted) {
      cells.push(current);
      current = "";
      continue;
    }
    current += char;
  }
  cells.push(current);
  return cells;
}
