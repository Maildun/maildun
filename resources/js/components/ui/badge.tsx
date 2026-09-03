import { mergeProps } from "@base-ui/react/merge-props"
import { useRender } from "@base-ui/react/use-render"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "group/badge inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-md px-2 py-1 text-xs font-medium whitespace-nowrap inset-ring transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring/50 has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 aria-invalid:text-destructive aria-invalid:inset-ring-destructive/20 [&>svg]:pointer-events-none [&>svg]:size-3!",
  {
    variants: {
      variant: {
        slate:
          "bg-slate-50 text-slate-700 inset-ring-slate-700/10 [a]:hover:bg-slate-100 dark:bg-slate-400/10 dark:text-slate-400 dark:inset-ring-slate-400/20 dark:[a]:hover:bg-slate-400/20",
        default:
          "bg-gray-50 text-gray-600 inset-ring-gray-500/10 [a]:hover:bg-gray-100 dark:bg-gray-400/10 dark:text-gray-400 dark:inset-ring-gray-400/20 dark:[a]:hover:bg-gray-400/20",
        gray:
          "bg-gray-50 text-gray-600 inset-ring-gray-500/10 [a]:hover:bg-gray-100 dark:bg-gray-400/10 dark:text-gray-400 dark:inset-ring-gray-400/20 dark:[a]:hover:bg-gray-400/20",
        zinc:
          "bg-zinc-50 text-zinc-700 inset-ring-zinc-700/10 [a]:hover:bg-zinc-100 dark:bg-zinc-400/10 dark:text-zinc-400 dark:inset-ring-zinc-400/20 dark:[a]:hover:bg-zinc-400/20",
        neutral:
          "bg-neutral-50 text-neutral-700 inset-ring-neutral-700/10 [a]:hover:bg-neutral-100 dark:bg-neutral-400/10 dark:text-neutral-400 dark:inset-ring-neutral-400/20 dark:[a]:hover:bg-neutral-400/20",
        stone:
          "bg-stone-50 text-stone-700 inset-ring-stone-700/10 [a]:hover:bg-stone-100 dark:bg-stone-400/10 dark:text-stone-400 dark:inset-ring-stone-400/20 dark:[a]:hover:bg-stone-400/20",
        secondary:
          "bg-yellow-50 text-yellow-800 inset-ring-yellow-600/20 [a]:hover:bg-yellow-100 dark:bg-yellow-400/10 dark:text-yellow-500 dark:inset-ring-yellow-400/20 dark:[a]:hover:bg-yellow-400/20",
        destructive:
          "bg-red-50 text-red-700 inset-ring-red-600/10 focus-visible:ring-destructive/20 [a]:hover:bg-red-100 dark:bg-red-400/10 dark:text-red-400 dark:inset-ring-red-400/20 dark:[a]:hover:bg-red-400/20",
        outline:
          "bg-blue-50 text-blue-700 inset-ring-blue-700/10 [a]:hover:bg-blue-100 dark:bg-blue-400/10 dark:text-blue-400 dark:inset-ring-blue-400/30 dark:[a]:hover:bg-blue-400/20",
        success:
          "bg-green-50 text-green-700 inset-ring-green-600/20 [a]:hover:bg-green-100 dark:bg-green-400/10 dark:text-green-400 dark:inset-ring-green-500/20 dark:[a]:hover:bg-green-400/20",
        red:
          "bg-red-50 text-red-700 inset-ring-red-600/10 [a]:hover:bg-red-100 dark:bg-red-400/10 dark:text-red-400 dark:inset-ring-red-400/20 dark:[a]:hover:bg-red-400/20",
        orange:
          "bg-orange-50 text-orange-700 inset-ring-orange-600/20 [a]:hover:bg-orange-100 dark:bg-orange-400/10 dark:text-orange-400 dark:inset-ring-orange-400/20 dark:[a]:hover:bg-orange-400/20",
        amber:
          "bg-amber-50 text-amber-700 inset-ring-amber-600/20 [a]:hover:bg-amber-100 dark:bg-amber-400/10 dark:text-amber-400 dark:inset-ring-amber-400/20 dark:[a]:hover:bg-amber-400/20",
        yellow:
          "bg-yellow-50 text-yellow-800 inset-ring-yellow-600/20 [a]:hover:bg-yellow-100 dark:bg-yellow-400/10 dark:text-yellow-500 dark:inset-ring-yellow-400/20 dark:[a]:hover:bg-yellow-400/20",
        lime:
          "bg-lime-50 text-lime-700 inset-ring-lime-600/20 [a]:hover:bg-lime-100 dark:bg-lime-400/10 dark:text-lime-400 dark:inset-ring-lime-400/20 dark:[a]:hover:bg-lime-400/20",
        green:
          "bg-green-50 text-green-700 inset-ring-green-600/20 [a]:hover:bg-green-100 dark:bg-green-400/10 dark:text-green-400 dark:inset-ring-green-500/20 dark:[a]:hover:bg-green-400/20",
        emerald:
          "bg-emerald-50 text-emerald-700 inset-ring-emerald-600/20 [a]:hover:bg-emerald-100 dark:bg-emerald-400/10 dark:text-emerald-400 dark:inset-ring-emerald-500/20 dark:[a]:hover:bg-emerald-400/20",
        info: "bg-indigo-50 text-indigo-700 inset-ring-indigo-700/10 [a]:hover:bg-indigo-100 dark:bg-indigo-400/10 dark:text-indigo-400 dark:inset-ring-indigo-400/30 dark:[a]:hover:bg-indigo-400/20",
        blue:
          "bg-blue-50 text-blue-700 inset-ring-blue-700/10 [a]:hover:bg-blue-100 dark:bg-blue-400/10 dark:text-blue-400 dark:inset-ring-blue-400/30 dark:[a]:hover:bg-blue-400/20",
        indigo:
          "bg-indigo-50 text-indigo-700 inset-ring-indigo-700/10 [a]:hover:bg-indigo-100 dark:bg-indigo-400/10 dark:text-indigo-400 dark:inset-ring-indigo-400/30 dark:[a]:hover:bg-indigo-400/20",
        violet:
          "bg-violet-50 text-violet-700 inset-ring-violet-700/10 [a]:hover:bg-violet-100 dark:bg-violet-400/10 dark:text-violet-400 dark:inset-ring-violet-400/30 dark:[a]:hover:bg-violet-400/20",
        purple:
          "bg-purple-50 text-purple-700 inset-ring-purple-700/10 [a]:hover:bg-purple-100 dark:bg-purple-400/10 dark:text-purple-400 dark:inset-ring-purple-400/30 dark:[a]:hover:bg-purple-400/20",
        fuchsia:
          "bg-fuchsia-50 text-fuchsia-700 inset-ring-fuchsia-700/10 [a]:hover:bg-fuchsia-100 dark:bg-fuchsia-400/10 dark:text-fuchsia-400 dark:inset-ring-fuchsia-400/20 dark:[a]:hover:bg-fuchsia-400/20",
        pink: "bg-pink-50 text-pink-700 inset-ring-pink-700/10 [a]:hover:bg-pink-100 dark:bg-pink-400/10 dark:text-pink-400 dark:inset-ring-pink-400/20 dark:[a]:hover:bg-pink-400/20",
        rose: "bg-rose-50 text-rose-700 inset-ring-rose-700/10 [a]:hover:bg-rose-100 dark:bg-rose-400/10 dark:text-rose-400 dark:inset-ring-rose-400/20 dark:[a]:hover:bg-rose-400/20",
        teal: "bg-teal-50 text-teal-700 inset-ring-teal-600/20 [a]:hover:bg-teal-100 dark:bg-teal-400/10 dark:text-teal-400 dark:inset-ring-teal-400/20 dark:[a]:hover:bg-teal-400/20",
        cyan: "bg-cyan-50 text-cyan-700 inset-ring-cyan-700/10 [a]:hover:bg-cyan-100 dark:bg-cyan-400/10 dark:text-cyan-400 dark:inset-ring-cyan-400/30 dark:[a]:hover:bg-cyan-400/20",
        sky: "bg-sky-50 text-sky-700 inset-ring-sky-700/10 [a]:hover:bg-sky-100 dark:bg-sky-400/10 dark:text-sky-400 dark:inset-ring-sky-400/30 dark:[a]:hover:bg-sky-400/20",
        taupe:
          "bg-taupe-50 text-taupe-700 inset-ring-taupe-700/10 [a]:hover:bg-taupe-100 dark:bg-taupe-400/10 dark:text-taupe-400 dark:inset-ring-taupe-400/20 dark:[a]:hover:bg-taupe-400/20",
        mauve:
          "bg-mauve-50 text-mauve-700 inset-ring-mauve-700/10 [a]:hover:bg-mauve-100 dark:bg-mauve-400/10 dark:text-mauve-400 dark:inset-ring-mauve-400/20 dark:[a]:hover:bg-mauve-400/20",
        mist:
          "bg-mist-50 text-mist-700 inset-ring-mist-700/10 [a]:hover:bg-mist-100 dark:bg-mist-400/10 dark:text-mist-400 dark:inset-ring-mist-400/20 dark:[a]:hover:bg-mist-400/20",
        olive:
          "bg-olive-50 text-olive-700 inset-ring-olive-700/10 [a]:hover:bg-olive-100 dark:bg-olive-400/10 dark:text-olive-400 dark:inset-ring-olive-400/20 dark:[a]:hover:bg-olive-400/20",
        ghost:
          "bg-gray-50 text-gray-600 inset-ring-gray-500/10 hover:bg-gray-100 dark:bg-gray-400/10 dark:text-gray-400 dark:inset-ring-gray-400/20 dark:hover:bg-gray-400/20",
        link: "bg-indigo-50 text-indigo-700 inset-ring-indigo-700/10 hover:bg-indigo-100 dark:bg-indigo-400/10 dark:text-indigo-400 dark:inset-ring-indigo-400/30 dark:hover:bg-indigo-400/20",
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
