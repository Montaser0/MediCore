import type { ReactNode } from "react";

export function PageToolbar({
  title,
  children,
}: {
  title: ReactNode;
  children?: ReactNode;
}) {
  return (
    <div className="his-toolbar">
      <h1 className="m-0 text-[13px] font-bold text-[var(--his-header)] me-auto">
        {title}
      </h1>
      {children}
    </div>
  );
}
