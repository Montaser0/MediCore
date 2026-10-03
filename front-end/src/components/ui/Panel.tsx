import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

export function Panel({
  title,
  children,
  className,
  actions,
}: {
  title?: ReactNode;
  children: ReactNode;
  className?: string;
  actions?: ReactNode;
}) {
  return (
    <div className={cn("his-panel", className)}>
      {title ? (
        <div className="his-panel-header flex items-center justify-between gap-2">
          <span>{title}</span>
          {actions}
        </div>
      ) : null}
      <div className="p-2">{children}</div>
    </div>
  );
}
