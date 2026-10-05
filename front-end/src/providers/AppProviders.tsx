"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useEffect, useState, type ReactNode } from "react";
import { Toaster } from "sonner";
import { useEditionStore } from "@/stores/edition-store";

export function AppProviders({ children }: { children: ReactNode }) {
  const [client] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30_000,
            refetchOnWindowFocus: false,
            retry: 1,
          },
        },
      }),
  );

  return (
    <QueryClientProvider client={client}>
      <EditionLoader />
      {children}
      <Toaster richColors position="top-center" dir="rtl" closeButton />
    </QueryClientProvider>
  );
}

function EditionLoader() {
  const load = useEditionStore((state) => state.load);
  useEffect(() => {
    void load();
  }, [load]);
  return null;
}
