"use client";

import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

export type Column<T> = {
  key: string;
  header: ReactNode;
  width?: string | number;
  className?: string;
  cell: (row: T, index: number) => ReactNode;
};

export function DataTable<T>({
  columns,
  rows,
  rowKey,
  selectedKey,
  onSelect,
  emptyText = "لا توجد بيانات",
  loading,
}: {
  columns: Column<T>[];
  rows: T[];
  rowKey: (row: T, index: number) => string | number;
  selectedKey?: string | number | null;
  onSelect?: (row: T) => void;
  emptyText?: string;
  loading?: boolean;
}) {
  return (
    <div className="his-table-wrap">
      <table className="his-table">
        <thead>
          <tr>
            {columns.map((col) => (
              <th key={col.key} style={{ width: col.width }} className={col.className}>
                {col.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {loading ? (
            <tr>
              <td colSpan={columns.length} className="py-8 text-center text-[var(--his-muted)]">
                جاري التحميل...
              </td>
            </tr>
          ) : rows.length === 0 ? (
            <tr>
              <td colSpan={columns.length} className="py-8 text-center text-[var(--his-muted)]">
                {emptyText}
              </td>
            </tr>
          ) : (
            rows.map((row, index) => {
              const key = rowKey(row, index);
              const selected = selectedKey != null && selectedKey === key;
              return (
                <tr
                  key={key}
                  className={cn(selected && "selected", onSelect && "cursor-pointer")}
                  onClick={() => onSelect?.(row)}
                >
                  {columns.map((col) => (
                    <td key={col.key} className={col.className}>
                      {col.cell(row, index)}
                    </td>
                  ))}
                </tr>
              );
            })
          )}
        </tbody>
      </table>
    </div>
  );
}
