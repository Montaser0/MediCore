"use client";

import type { ButtonHTMLAttributes, ReactNode } from "react";
import { cn } from "@/lib/utils";

type Variant =
  | "default"
  | "primary"
  | "success"
  | "danger"
  | "info"
  | "teal"
  | "warning";

const variantClass: Record<Variant, string> = {
  default: "his-btn",
  primary: "his-btn his-btn-primary",
  success: "his-btn his-btn-success",
  danger: "his-btn his-btn-danger",
  info: "his-btn his-btn-info",
  teal: "his-btn his-btn-teal",
  warning: "his-btn his-btn-warning",
};

export function Button({
  variant = "default",
  className,
  children,
  type = "button",
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: Variant;
  children: ReactNode;
}) {
  return (
    <button type={type} className={cn(variantClass[variant], className)} {...props}>
      {children}
    </button>
  );
}
