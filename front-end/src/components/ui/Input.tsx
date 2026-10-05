import { forwardRef, type InputHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

const WESTERN_TYPES = new Set(["number", "date", "time", "datetime-local", "month", "week"]);

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Input(
  { className, type, lang, dir, ...props },
  ref,
) {
  const western = typeof type === "string" && WESTERN_TYPES.has(type);
  return (
    <input
      type={type}
      lang={lang ?? (western ? "en-GB" : undefined)}
      dir={dir ?? (western ? "ltr" : undefined)}
      {...props}
      ref={ref}
      className={cn("his-input", western && "his-latin-num", className)}
    />
  );
});
