import type { ComponentPropsWithoutRef } from "react"

import { cn } from "@/lib/cn"

type BadgeVariant = "default" | "neutral" | "success" | "danger" | "accent" | "warning"

const variants: Record<BadgeVariant, string> = {
  default: "bg-main text-main-foreground",
  neutral: "bg-secondary-background text-foreground",
  success: "bg-success text-main-foreground",
  danger: "bg-danger text-main-foreground",
  accent: "bg-accent text-accent-foreground",
  warning: "bg-warning text-main-foreground",
}

export type BadgeProps = ComponentPropsWithoutRef<"span"> & {
  variant?: BadgeVariant
}

export function Badge({ className, variant = "default", ...props }: BadgeProps) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-base border-2 border-border px-2.5 py-1 text-xs font-heading leading-none shadow-[2px_2px_0_0_var(--border)]",
        variants[variant],
        className,
      )}
      {...props}
    />
  )
}
