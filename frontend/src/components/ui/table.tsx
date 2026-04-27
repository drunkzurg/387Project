import type { ComponentPropsWithoutRef } from "react"

import { cn } from "@/lib/cn"

export function Table({ className, ...props }: ComponentPropsWithoutRef<"table">) {
  return (
    <div className="w-full overflow-x-auto rounded-base border-2 border-border shadow-shadow">
      <table
        className={cn("w-full caption-bottom border-collapse bg-secondary-background text-sm", className)}
        {...props}
      />
    </div>
  )
}

export function TableHeader({
  className,
  ...props
}: ComponentPropsWithoutRef<"thead">) {
  return <thead className={cn("bg-main text-main-foreground", className)} {...props} />
}

export function TableBody({
  className,
  ...props
}: ComponentPropsWithoutRef<"tbody">) {
  return <tbody className={cn("[&_tr:last-child]:border-b-0", className)} {...props} />
}

export function TableFooter({
  className,
  ...props
}: ComponentPropsWithoutRef<"tfoot">) {
  return <tfoot className={cn("bg-main font-base text-main-foreground", className)} {...props} />
}

export function TableRow({ className, ...props }: ComponentPropsWithoutRef<"tr">) {
  return (
    <tr
      className={cn("border-b-2 border-border transition-colors", className)}
      {...props}
    />
  )
}

export function TableHead({ className, ...props }: ComponentPropsWithoutRef<"th">) {
  return (
    <th
      className={cn("h-12 px-4 text-left align-middle font-heading", className)}
      {...props}
    />
  )
}

export function TableCell({ className, ...props }: ComponentPropsWithoutRef<"td">) {
  return <td className={cn("p-4 align-middle", className)} {...props} />
}

export function TableCaption({
  className,
  ...props
}: ComponentPropsWithoutRef<"caption">) {
  return (
    <caption className={cn("mt-4 text-sm text-muted-foreground", className)} {...props} />
  )
}
