import type { InputHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

const WESTERN_TYPES = new Set(["number", "date", "time", "datetime-local", "month", "week"]);

export function Input({
  className,
  type,
  lang,
  dir,
  ...props
}: InputHTMLAttributes<HTMLInputElement>) {
  const western = typeof type === "string" && WESTERN_TYPES.has(type);
  return (
    <input
      type={type}
      lang={lang ?? (western ? "en-GB" : undefined)}
      dir={dir ?? (western ? "ltr" : undefined)}
      className={cn("his-input", western && "his-latin-num", className)}
      {...props}
    />
  );
}
