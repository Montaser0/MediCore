export const LAB_CATEGORIES = [
  { id: "hematology", labelKey: "laboratory.hematology" },
  { id: "chemistry", labelKey: "laboratory.chemistry" },
  { id: "hormones", labelKey: "laboratory.hormones" },
  { id: "tumors", labelKey: "laboratory.tumors" },
] as const;

export type LabCategoryId = (typeof LAB_CATEGORIES)[number]["id"];
