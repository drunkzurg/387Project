import type { ComponentPropsWithoutRef } from "react"

import { cn } from "@/lib/cn"

export function Card({ className, ...props }: ComponentPropsWithoutRef<"div">) {
  return (
    <div
      className={cn(
        "flex flex-col gap-5 rounded-base border-2 border-border bg-secondary-background p-6 text-foreground shadow-shadow",
        className,
      )}
      {...props}
    />
  )
}

export function CardHeader({
  className,
  ...props
}: ComponentPropsWithoutRef<"div">) {
  return (
    <div
      className={cn(
        "grid gap-1.5 border-b-2 border-border pb-4 has-[.card-action]:grid-cols-[1fr_auto]",
        className,
      )}
      {...props}
    />
  )
}

export function CardTitle({
  className,
  ...props
}: ComponentPropsWithoutRef<"h3">) {
  return (
    <h3
      className={cn("m-0 font-heading text-xl leading-none", className)}
      {...props}
    />
  )
}

export function CardDescription({
  className,
  ...props
}: ComponentPropsWithoutRef<"p">) {
  return (
    <p
      className={cn("m-0 text-sm text-muted-foreground", className)}
      {...props}
    />
  )
}

export function CardAction({
  className,
  ...props
}: ComponentPropsWithoutRef<"div">) {
  return (
    <div
      className={cn("card-action row-span-2 self-start justify-self-end", className)}
      {...props}
    />
  )
}

export function CardContent({
  className,
  ...props
}: ComponentPropsWithoutRef<"div">) {
  return <div className={cn("grid gap-4", className)} {...props} />
}

export function CardFooter({
  className,
  ...props
}: ComponentPropsWithoutRef<"div">) {
  return (
    <div
      className={cn("flex flex-wrap items-center gap-3 border-t-2 border-border pt-4", className)}
      {...props}
    />
  )
}
