import { Suspense } from "react";
import { EncountersPage } from "@/modules/encounters";

export default function Page() {
  return (
    <Suspense>
      <EncountersPage />
    </Suspense>
  );
}
