import type { ComponentPropsWithoutRef } from "react"

import { cn } from "@/lib/cn"

type ImageSlotProps = ComponentPropsWithoutRef<"div"> & {
  label?: string
  detail?: string
}

export function ImageSlot({
  className,
  label = "Image Placeholder",
  detail = "Drop future artwork here.",
  ...props
}: ImageSlotProps) {
  return (
    <div
      className={cn(
        "grid min-h-52 place-items-center rounded-base border-2 border-dashed border-border bg-secondary-background p-6 text-center shadow-shadow",
        className,
      )}
      {...props}
    >
      <div>
        <p className="m-0 font-heading text-xl">{label}</p>
        <p className="m-0 mt-2 text-sm text-muted-foreground">{detail}</p>
      </div>
    </div>
  )
}
