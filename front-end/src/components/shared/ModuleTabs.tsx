"use client";

import { useState, type ReactNode } from "react";

export function ModuleTabs({
  tabs,
}: {
  tabs: Array<{ id: string; label: string; content: ReactNode }>;
}) {
  const [active, setActive] = useState(tabs[0]?.id ?? "");
  const current = tabs.find((tab) => tab.id === active) ?? tabs[0];

  return (
    <div className="flex h-full flex-col">
      <div className="his-tabs flex-wrap px-1">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            className={`his-tab ${current?.id === tab.id ? "active" : ""}`}
            onClick={() => setActive(tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </div>
      <div className="min-h-0 flex-1">{current?.content}</div>
    </div>
  );
}
