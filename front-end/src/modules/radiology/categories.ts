export const RADIOLOGY_CATEGORIES = [
  { id: "ct", labelKey: "radiology.ct" },
  { id: "echo", labelKey: "radiology.echo" },
  { id: "panorama", labelKey: "radiology.panorama" },
  { id: "mri", labelKey: "radiology.mri" },
  { id: "xray", labelKey: "radiology.xray" },
] as const;

export type RadiologyCategoryId = (typeof RADIOLOGY_CATEGORIES)[number]["id"];
