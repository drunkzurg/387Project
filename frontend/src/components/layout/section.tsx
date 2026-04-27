import type { ComponentPropsWithoutRef, ReactNode } from "react"

import { cn } from "@/lib/cn"

type SectionProps = ComponentPropsWithoutRef<"section"> & {
  title?: string
  description?: string
  actions?: ReactNode
}

export function Section({
  title,
  description,
  actions,
  className,
  children,
  ...props
}: SectionProps) {
  return (
    <section className={cn("grid gap-4", className)} {...props}>
      {(title || description || actions) ? (
        <div className="grid gap-2 md:grid-cols-[1fr_auto] md:items-end">
          <div>
            {title ? <h2 className="m-0 text-3xl">{title}</h2> : null}
            {description ? (
              <p className="m-0 mt-1 text-muted-foreground">{description}</p>
            ) : null}
          </div>
          {actions ? <div className="flex flex-wrap gap-3">{actions}</div> : null}
        </div>
      ) : null}
      {children}
    </section>
  )
}
