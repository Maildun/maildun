import { mergeProps } from "@base-ui/react/merge-props"
import { useRender } from "@base-ui/react/use-render"
import { cva } from "class-variance-authority"
import type { VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "group/badge inline-flex w-fit shrink-0 items-center justify-center gap-1.5 overflow-hidden rounded-md px-2 py-1 text-sm font-medium whitespace-nowrap [print-color-adjust:exact] transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring/50 has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 aria-invalid:text-destructive [&_button]:text-inherit! [&>svg]:pointer-events-none [&>svg]:size-3!",
  {
    variants: {
      variant: {
        slate:
          "bg-slate-400/15 text-slate-700 [&:is(a,button)]:hover:bg-slate-400/25 dark:bg-slate-400/40 dark:text-slate-200 dark:[&:is(a,button)]:hover:bg-slate-400/50",
        default:
          "bg-zinc-400/15 text-zinc-700 [&:is(a,button)]:hover:bg-zinc-400/25 dark:bg-zinc-400/40 dark:text-zinc-200 dark:[&:is(a,button)]:hover:bg-zinc-400/50",
        gray:
          "bg-gray-400/15 text-gray-700 [&:is(a,button)]:hover:bg-gray-400/25 dark:bg-gray-400/40 dark:text-gray-200 dark:[&:is(a,button)]:hover:bg-gray-400/50",
        zinc:
          "bg-zinc-400/15 text-zinc-700 [&:is(a,button)]:hover:bg-zinc-400/25 dark:bg-zinc-400/40 dark:text-zinc-200 dark:[&:is(a,button)]:hover:bg-zinc-400/50",
        neutral:
          "bg-neutral-400/15 text-neutral-700 [&:is(a,button)]:hover:bg-neutral-400/25 dark:bg-neutral-400/40 dark:text-neutral-200 dark:[&:is(a,button)]:hover:bg-neutral-400/50",
        stone:
          "bg-stone-400/15 text-stone-700 [&:is(a,button)]:hover:bg-stone-400/25 dark:bg-stone-400/40 dark:text-stone-200 dark:[&:is(a,button)]:hover:bg-stone-400/50",
        secondary:
          "bg-yellow-400/25 text-yellow-800 [&:is(a,button)]:hover:bg-yellow-400/40 dark:bg-yellow-400/40 dark:text-yellow-200 dark:[&:is(a,button)]:hover:bg-yellow-400/50",
        destructive:
          "bg-red-400/20 text-red-700 [&:is(a,button)]:hover:bg-red-400/30 dark:bg-red-400/40 dark:text-red-200 dark:[&:is(a,button)]:hover:bg-red-400/50 focus-visible:ring-destructive/20",
        outline:
          "bg-blue-400/20 text-blue-800 [&:is(a,button)]:hover:bg-blue-400/30 dark:bg-blue-400/40 dark:text-blue-200 dark:[&:is(a,button)]:hover:bg-blue-400/50",
        success:
          "bg-green-400/20 text-green-800 [&:is(a,button)]:hover:bg-green-400/30 dark:bg-green-400/40 dark:text-green-200 dark:[&:is(a,button)]:hover:bg-green-400/50",
        red:
          "bg-red-400/20 text-red-700 [&:is(a,button)]:hover:bg-red-400/30 dark:bg-red-400/40 dark:text-red-200 dark:[&:is(a,button)]:hover:bg-red-400/50",
        orange:
          "bg-orange-400/20 text-orange-700 [&:is(a,button)]:hover:bg-orange-400/30 dark:bg-orange-400/40 dark:text-orange-200 dark:[&:is(a,button)]:hover:bg-orange-400/50",
        amber:
          "bg-amber-400/25 text-amber-700 [&:is(a,button)]:hover:bg-amber-400/40 dark:bg-amber-400/40 dark:text-amber-200 dark:[&:is(a,button)]:hover:bg-amber-400/50",
        yellow:
          "bg-yellow-400/25 text-yellow-800 [&:is(a,button)]:hover:bg-yellow-400/40 dark:bg-yellow-400/40 dark:text-yellow-200 dark:[&:is(a,button)]:hover:bg-yellow-400/50",
        lime:
          "bg-lime-400/25 text-lime-800 [&:is(a,button)]:hover:bg-lime-400/35 dark:bg-lime-400/40 dark:text-lime-200 dark:[&:is(a,button)]:hover:bg-lime-400/50",
        green:
          "bg-green-400/20 text-green-800 [&:is(a,button)]:hover:bg-green-400/30 dark:bg-green-400/40 dark:text-green-200 dark:[&:is(a,button)]:hover:bg-green-400/50",
        emerald:
          "bg-emerald-400/20 text-emerald-800 [&:is(a,button)]:hover:bg-emerald-400/30 dark:bg-emerald-400/40 dark:text-emerald-200 dark:[&:is(a,button)]:hover:bg-emerald-400/50",
        info:
          "bg-indigo-400/20 text-indigo-700 [&:is(a,button)]:hover:bg-indigo-400/30 dark:bg-indigo-400/40 dark:text-indigo-200 dark:[&:is(a,button)]:hover:bg-indigo-400/50",
        blue:
          "bg-blue-400/20 text-blue-800 [&:is(a,button)]:hover:bg-blue-400/30 dark:bg-blue-400/40 dark:text-blue-200 dark:[&:is(a,button)]:hover:bg-blue-400/50",
        indigo:
          "bg-indigo-400/20 text-indigo-700 [&:is(a,button)]:hover:bg-indigo-400/30 dark:bg-indigo-400/40 dark:text-indigo-200 dark:[&:is(a,button)]:hover:bg-indigo-400/50",
        violet:
          "bg-violet-400/20 text-violet-700 [&:is(a,button)]:hover:bg-violet-400/30 dark:bg-violet-400/40 dark:text-violet-200 dark:[&:is(a,button)]:hover:bg-violet-400/50",
        purple:
          "bg-purple-400/20 text-purple-700 [&:is(a,button)]:hover:bg-purple-400/30 dark:bg-purple-400/40 dark:text-purple-200 dark:[&:is(a,button)]:hover:bg-purple-400/50",
        fuchsia:
          "bg-fuchsia-400/20 text-fuchsia-700 [&:is(a,button)]:hover:bg-fuchsia-400/30 dark:bg-fuchsia-400/40 dark:text-fuchsia-200 dark:[&:is(a,button)]:hover:bg-fuchsia-400/50",
        pink:
          "bg-pink-400/20 text-pink-700 [&:is(a,button)]:hover:bg-pink-400/30 dark:bg-pink-400/40 dark:text-pink-200 dark:[&:is(a,button)]:hover:bg-pink-400/50",
        rose:
          "bg-rose-400/20 text-rose-700 [&:is(a,button)]:hover:bg-rose-400/30 dark:bg-rose-400/40 dark:text-rose-200 dark:[&:is(a,button)]:hover:bg-rose-400/50",
        teal:
          "bg-teal-400/20 text-teal-800 [&:is(a,button)]:hover:bg-teal-400/30 dark:bg-teal-400/40 dark:text-teal-200 dark:[&:is(a,button)]:hover:bg-teal-400/50",
        cyan:
          "bg-cyan-400/20 text-cyan-800 [&:is(a,button)]:hover:bg-cyan-400/30 dark:bg-cyan-400/40 dark:text-cyan-200 dark:[&:is(a,button)]:hover:bg-cyan-400/50",
        sky:
          "bg-sky-400/20 text-sky-800 [&:is(a,button)]:hover:bg-sky-400/30 dark:bg-sky-400/40 dark:text-sky-200 dark:[&:is(a,button)]:hover:bg-sky-400/50",
        taupe:
          "bg-taupe-400/15 text-taupe-700 [&:is(a,button)]:hover:bg-taupe-400/25 dark:bg-taupe-400/40 dark:text-taupe-200 dark:[&:is(a,button)]:hover:bg-taupe-400/50",
        mauve:
          "bg-mauve-400/15 text-mauve-700 [&:is(a,button)]:hover:bg-mauve-400/25 dark:bg-mauve-400/40 dark:text-mauve-200 dark:[&:is(a,button)]:hover:bg-mauve-400/50",
        mist:
          "bg-mist-400/15 text-mist-700 [&:is(a,button)]:hover:bg-mist-400/25 dark:bg-mist-400/40 dark:text-mist-200 dark:[&:is(a,button)]:hover:bg-mist-400/50",
        olive:
          "bg-olive-400/15 text-olive-700 [&:is(a,button)]:hover:bg-olive-400/25 dark:bg-olive-400/40 dark:text-olive-200 dark:[&:is(a,button)]:hover:bg-olive-400/50",
        ghost:
          "bg-gray-400/15 text-gray-700 hover:bg-gray-400/25 dark:bg-gray-400/40 dark:text-gray-200 dark:hover:bg-gray-400/50",
        link:
          "bg-indigo-400/20 text-indigo-700 hover:bg-indigo-400/30 dark:bg-indigo-400/40 dark:text-indigo-200 dark:hover:bg-indigo-400/50",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant = "default",
  render,
  ...props
}: useRender.ComponentProps<"span"> & VariantProps<typeof badgeVariants>) {
  return useRender({
    defaultTagName: "span",
    props: mergeProps<"span">(
      {
        className: cn(badgeVariants({ variant }), className),
      },
      props
    ),
    render,
    state: {
      slot: "badge",
      variant,
    },
  })
}

export { Badge, badgeVariants }
