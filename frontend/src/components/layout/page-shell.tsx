import type { ReactNode } from "react"

import { cn } from "@/lib/cn"

type PageShellProps = {
  eyebrow?: string
  title: string
  description?: string
  actions?: ReactNode
  hero?: ReactNode
  children: ReactNode
  className?: string
  headerClassName?: string
  titleClassName?: string
}

export function PageShell({
  eyebrow,
  title,
  description,
  actions,
  hero,
  children,
  className,
  headerClassName,
  titleClassName,
}: PageShellProps) {
  return (
    <main className={cn("mx-auto grid min-h-screen w-full max-w-container gap-8 px-4 py-8 sm:px-6 lg:px-8", className)}>
      <header className={cn("grid gap-6 rounded-base border-2 border-border bg-secondary-background p-6 shadow-smash lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.65fr)] lg:items-center", headerClassName)}>
        <div className="grid gap-4">
          {eyebrow ? (
            <p className="m-0 font-heading text-sm uppercase tracking-[0.22em] text-muted-foreground">
              {eyebrow}
            </p>
          ) : null}
          <h1 className={cn("m-0 max-w-4xl text-4xl leading-[0.95] sm:text-6xl", titleClassName)}>
            {title}
          </h1>
          {description ? (
            <p className="m-0 max-w-2xl text-base text-muted-foreground sm:text-lg">
              {description}
            </p>
          ) : null}
          {actions ? <div className="flex flex-wrap gap-3">{actions}</div> : null}
        </div>
        {hero ? <div className="justify-self-center lg:justify-self-end">{hero}</div> : null}
      </header>
      {children}
    </main>
  )
}
