import type { ComponentProps, CSSProperties, ReactNode } from "react"
import { createContext, useContext, useId } from "react"
import { ResponsiveContainer, Tooltip } from "recharts"

import { cn } from "@/lib/cn"

export type ChartConfig = Record<
  string,
  {
    color: string
    label: string
  }
>

type ChartContextValue = {
  config: ChartConfig
}

const ChartContext = createContext<ChartContextValue | null>(null)

export function useChartConfig() {
  const context = useContext(ChartContext)

  if (!context) {
    throw new Error("useChartConfig must be used inside ChartContainer.")
  }

  return context.config
}

export function ChartContainer({
  children,
  className,
  config,
}: {
  children: ReactNode
  className?: string
  config: ChartConfig
}) {
  const chartId = `chart-${useId().replace(/:/g, "")}`

  return (
    <ChartContext.Provider value={{ config }}>
      <div
        className={cn(
          "min-h-80 rounded-base border-2 border-border bg-secondary-background p-4 shadow-shadow",
          "[&_.recharts-cartesian-axis-tick_text]:fill-foreground [&_.recharts-cartesian-grid_line]:stroke-border/25 [&_.recharts-curve]:stroke-[3px] [&_.recharts-dot]:stroke-border",
          className,
        )}
        data-chart={chartId}
      >
        <ChartStyle chartId={chartId} config={config} />
        <ResponsiveContainer height="100%" minHeight={300} width="100%">
          {children as ComponentProps<typeof ResponsiveContainer>["children"]}
        </ResponsiveContainer>
      </div>
    </ChartContext.Provider>
  )
}

function ChartStyle({ chartId, config }: { chartId: string; config: ChartConfig }) {
  const css = Object.entries(config)
    .map(([key, item]) => `  --color-${key}: ${item.color};`)
    .join("\n")

  return (
    <style
      dangerouslySetInnerHTML={{
        __html: `[data-chart="${chartId}"] {\n${css}\n}`,
      }}
    />
  )
}

export const ChartTooltip = Tooltip

export function ChartTooltipPanel({
  active,
  label,
  payload,
}: {
  active?: boolean
  label?: string | number
  payload?: Array<{
    color?: string
    dataKey?: string | number
    name?: string | number
    value?: string | number
    payload?: Record<string, unknown>
  }>
}) {
  const config = useChartConfig()

  if (!active || !payload?.length) {
    return null
  }

  return (
    <div className="rounded-base border-2 border-border bg-secondary-background p-3 text-sm shadow-shadow">
      {label ? <p className="m-0 mb-2 font-heading">{label}</p> : null}
      <div className="grid gap-2">
        {payload.map((item) => {
          const key = String(item.dataKey ?? item.name ?? "")
          const chartItem = config[key]

          return (
            <div className="flex items-center justify-between gap-4" key={key}>
              <span className="flex items-center gap-2">
                <span
                  className="inline-block size-3 rounded-full border border-border"
                  style={{ background: item.color ?? chartItem?.color } as CSSProperties}
                />
                {chartItem?.label ?? item.name ?? key}
              </span>
              <strong>{Number(item.value ?? 0).toLocaleString()}</strong>
            </div>
          )
        })}
      </div>
    </div>
  )
}
