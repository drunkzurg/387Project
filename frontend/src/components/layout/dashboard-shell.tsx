import type { ReactNode } from "react"

import { PageShell } from "@/components/layout/page-shell"

type DashboardShellProps = {
  title: string
  description?: string
  roleLabel?: string
  actions?: ReactNode
  sidecar?: ReactNode
  children: ReactNode
}

export function DashboardShell({
  title,
  description,
  roleLabel,
  actions,
  sidecar,
  children,
}: DashboardShellProps) {
  if (sidecar) {
    return (
      <main className="mx-auto grid min-h-screen w-full max-w-container gap-8 px-4 py-8 sm:px-6 lg:px-8">
        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.45fr)] lg:items-start">
          <div className="rounded-base border-2 border-border bg-secondary-background p-6 shadow-smash">
            <div className="grid gap-4">
              <p className="m-0 font-heading text-sm uppercase tracking-[0.22em] text-muted-foreground">
                {roleLabel ?? "Arcade Management"}
              </p>
              <h1 className="m-0 max-w-4xl text-4xl leading-[0.95] sm:text-6xl">
                {title}
              </h1>
              {description ? (
                <p className="m-0 max-w-2xl text-base text-muted-foreground sm:text-lg">
                  {description}
                </p>
              ) : null}
              {actions ? <div className="flex flex-wrap gap-3">{actions}</div> : null}
            </div>
          </div>
          {sidecar}
        </div>
        {children}
      </main>
    )
  }

  return (
    <PageShell
      actions={actions}
      description={description}
      eyebrow={roleLabel ?? "Arcade Management"}
      title={title}
    >
      {children}
    </PageShell>
  )
}
