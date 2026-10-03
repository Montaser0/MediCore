import { cn } from "@/lib/utils";

export function Badge({
  children,
  tone = "default",
}: {
  children: React.ReactNode;
  tone?: "default" | "success" | "warning" | "danger" | "info";
}) {
  return (
    <span
      className={cn(
        "his-badge",
        tone === "success" && "his-badge-success",
        tone === "warning" && "his-badge-warning",
        tone === "danger" && "his-badge-danger",
        tone === "info" && "his-badge-info",
      )}
    >
      {children}
    </span>
  );
}
