import { cn } from "@/lib/utils"
import { HugeiconsIcon } from "@hugeicons/react"
import { Loading03Icon } from "@hugeicons/core-free-icons"

function Spinner({ className, strokeWidth = 2, ...props }: React.ComponentProps<"svg">) {
  const resolvedStrokeWidth = typeof strokeWidth === "number" ? strokeWidth : 2

  return (
    <HugeiconsIcon icon={Loading03Icon} strokeWidth={resolvedStrokeWidth} data-slot="spinner" role="status" aria-label="Loading" className={cn("size-4 animate-spin", className)} {...props} />
  )
}

export { Spinner }
