import type { ComponentPropsWithoutRef } from "react"

import { cn } from "@/lib/cn"

type AlertVariant = "default" | "success" | "danger" | "accent"

const variants: Record<AlertVariant, string> = {
  default: "bg-secondary-background",
  success: "bg-success",
  danger: "bg-danger",
  accent: "bg-accent",
}

export type AlertProps = ComponentPropsWithoutRef<"div"> & {
  variant?: AlertVariant
}

export function Alert({ className, variant = "default", ...props }: AlertProps) {
  return (
    <div
      className={cn(
        "rounded-base border-2 border-border p-4 text-foreground shadow-shadow",
        variants[variant],
        className,
      )}
      role="status"
      {...props}
    />
  )
}
