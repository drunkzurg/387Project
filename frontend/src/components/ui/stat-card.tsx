import { Card, CardContent } from "@/components/ui/card"
import { cn } from "@/lib/cn"

type StatCardProps = {
  label: string
  value: string | number
  detail?: string
  className?: string
}

export function StatCard({ label, value, detail, className }: StatCardProps) {
  return (
    <Card className={cn("gap-3 bg-main", className)}>
      <CardContent className="gap-1 p-0">
        <p className="m-0 text-sm font-heading uppercase tracking-wide text-main-foreground">
          {label}
        </p>
        <p className="m-0 font-heading text-4xl leading-none text-main-foreground">
          {value}
        </p>
        {detail ? <p className="m-0 text-sm text-main-foreground">{detail}</p> : null}
      </CardContent>
    </Card>
  )
}
