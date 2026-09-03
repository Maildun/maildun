import { useTheme } from "next-themes"
import { Toaster as Sonner, type ToasterProps } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { Loading03Icon } from "@hugeicons/core-free-icons"
import {
  CheckmarkCircleSolidIcon,
  InformationCircleSolidIcon,
  AlertCircleSolidIcon,
  MultiplicationSignCircleSolidIcon,
} from "@/components/icons/toast-status-icons"

const Toaster = ({ ...props }: ToasterProps) => {
  const { theme = "system" } = useTheme()

  return (
    <Sonner
      theme={theme as ToasterProps["theme"]}
      className="toaster group"
      icons={{
        success: (
          <CheckmarkCircleSolidIcon className="size-4 text-success" />
        ),
        info: (
          <InformationCircleSolidIcon className="size-4 text-info" />
        ),
        warning: (
          <AlertCircleSolidIcon className="size-4 text-warning" />
        ),
        error: (
          <MultiplicationSignCircleSolidIcon className="size-4 text-destructive" />
        ),
        loading: (
          <HugeiconsIcon icon={Loading03Icon} strokeWidth={2} className="size-4 animate-spin" />
        ),
      }}
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
          "--border-radius": "var(--radius)",
        } as React.CSSProperties
      }
      toastOptions={{
        classNames: {
          toast: "cn-toast",
        },
      }}
      {...props}
    />
  )
}

export { Toaster }
