import type { ComponentPropsWithoutRef } from "react"

import { cn } from "@/lib/cn"

export function ResponsiveGrid({
  className,
  ...props
}: ComponentPropsWithoutRef<"div">) {
  return (
    <div
      className={cn("grid gap-5 md:grid-cols-2 xl:grid-cols-3", className)}
      {...props}
    />
  )
}
