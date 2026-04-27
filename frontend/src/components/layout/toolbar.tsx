import type { ComponentPropsWithoutRef } from "react"

import { cn } from "@/lib/cn"

export function Toolbar({
  className,
  ...props
}: ComponentPropsWithoutRef<"div">) {
  return (
    <div
      className={cn(
        "flex flex-wrap items-center justify-between gap-3 rounded-base border-2 border-border bg-secondary-background p-3 shadow-shadow",
        className,
      )}
      {...props}
    />
  )
}
