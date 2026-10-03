"use client";

import { Panel } from "@/components/ui/Panel";
import { PageToolbar } from "@/components/ui/PageToolbar";

export function PlaceholderPage({ title, hint }: { title: string; hint?: string }) {
  return (
    <div className="flex h-full flex-col">
      <PageToolbar title={title} />
      <div className="p-2">
        <Panel title={title}>
          <p className="m-0 text-[12px] text-[var(--his-muted)]">
            {hint ?? "هذه الشاشة جاهزة للربط مع واجهات الـ API الخاصة بالوحدة."}
          </p>
        </Panel>
      </div>
    </div>
  );
}
