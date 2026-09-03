import * as React from "react"
import { Toast as ToastPrimitive } from "@base-ui/react/toast"
import { HugeiconsIcon } from "@hugeicons/react"
import { Cancel01Icon, Loading03Icon } from "@hugeicons/core-free-icons"

import { cn } from "@/lib/utils"
import { formatFileSize } from "@/lib/format"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Progress } from "@/components/ui/progress"
import {
  CheckmarkCircleSolidIcon,
  InformationCircleSolidIcon,
  AlertCircleSolidIcon,
  MultiplicationSignCircleSolidIcon,
} from "@/components/icons/toast-status-icons"

type ToastUploadFileStatus = "uploading" | "sent" | "done" | "failed"

type ToastUploadFile = {
  name: string
  size: number | null
  progress: number
  status: ToastUploadFileStatus
}

type ToastData = {
  kind: "upload"
  progress: number
  hint?: string
  files?: ToastUploadFile[]
}

const toast = ToastPrimitive.createToastManager<ToastData>()

function ToastProvider({ ...props }: ToastPrimitive.Provider.Props) {
  return <ToastPrimitive.Provider {...props} />
}

function ToastPortal({ ...props }: ToastPrimitive.Portal.Props) {
  return <ToastPrimitive.Portal data-slot="toast-portal" {...props} />
}

function ToastViewport({ className, ...props }: ToastPrimitive.Viewport.Props) {
  return (
    <ToastPrimitive.Viewport
      data-slot="toast-viewport"
      className={cn(
        "pointer-events-none isolate fixed inset-x-4 bottom-4 z-50 mx-auto w-auto max-w-sm overflow-visible outline-none sm:right-4 sm:left-auto sm:mx-0 sm:w-full",
        className
      )}
      {...props}
    />
  )
}

function Toast({ className, ...props }: ToastPrimitive.Root.Props) {
  return (
    <ToastPrimitive.Root
      data-slot="toast"
      className={cn(
        "group/toast pointer-events-auto absolute right-0 bottom-0 z-[calc(1000-var(--toast-index))] w-full origin-bottom overflow-hidden rounded-2xl border bg-popover text-popover-foreground shadow-lg will-change-transform outline-none select-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50",
        // Collapsed stack: every toast is clamped to the frontmost toast's height,
        // then scaled down and peeked upward by its index.
        "h-[var(--toast-frontmost-height,auto)]",
        "[transform:scale(calc(1-(var(--toast-index)*0.05)))_translateX(var(--toast-swipe-movement-x,0px))_translateY(calc(var(--toast-swipe-movement-y,0px)+(var(--toast-index)*-20%)))]",
        "[transition:transform_500ms_cubic-bezier(0.22,1,0.36,1),height_500ms_cubic-bezier(0.22,1,0.36,1),opacity_500ms]",
        // Expanded (viewport hovered/focused): natural heights, stacked upward by the
        // cumulative height of the toasts in front plus a 12px gap per position.
        "data-expanded:h-[var(--toast-height,auto)]",
        "data-expanded:[transform:translateX(var(--toast-swipe-movement-x,0px))_translateY(calc(var(--toast-swipe-movement-y,0px)-var(--toast-offset-y,0px)-(var(--toast-index)*12px)))]",
        "data-limited:hidden data-starting-style:opacity-0 data-starting-style:[transform:translateY(150%)]",
        "data-ending-style:opacity-0",
        "[&[data-ending-style]:not([data-limited]):not([data-swipe-direction])]:[transform:translateY(150%)]",
        "data-ending-style:data-[swipe-direction=down]:[transform:translateY(calc(var(--toast-swipe-movement-y,0px)+150%))]",
        "data-ending-style:data-[swipe-direction=left]:[transform:translateX(calc(var(--toast-swipe-movement-x,0px)-150%))]",
        "data-ending-style:data-[swipe-direction=right]:[transform:translateX(calc(var(--toast-swipe-movement-x,0px)+150%))]",
        "data-ending-style:data-[swipe-direction=up]:[transform:translateY(calc(var(--toast-swipe-movement-y,0px)-150%))]",
        className
      )}
      {...props}
    />
  )
}

function ToastContent({ className, ...props }: ToastPrimitive.Content.Props) {
  return (
    <ToastPrimitive.Content
      data-slot="toast-content"
      className={cn(
        "flex h-full items-center gap-3 overflow-hidden p-4 transition-opacity duration-250",
        // Toasts behind the frontmost one are clamped to its height, so hide their
        // content until the stack expands.
        "[&[data-behind]:not([data-expanded])]:opacity-0",
        className
      )}
      {...props}
    />
  )
}

function ToastTitle({ className, ...props }: ToastPrimitive.Title.Props) {
  return (
    <ToastPrimitive.Title
      data-slot="toast-title"
      className={cn("text-sm font-medium", className)}
      {...props}
    />
  )
}

function ToastDescription({
  className,
  ...props
}: ToastPrimitive.Description.Props) {
  return (
    <ToastPrimitive.Description
      data-slot="toast-description"
      className={cn("text-sm text-muted-foreground", className)}
      {...props}
    />
  )
}

function ToastAction({
  className,
  render = <Button variant="outline" size="sm" />,
  ...props
}: ToastPrimitive.Action.Props) {
  return (
    <ToastPrimitive.Action
      data-slot="toast-action"
      render={render}
      className={cn("shrink-0", className)}
      {...props}
    />
  )
}

function ToastClose({
  className,
  children,
  render = <Button variant="ghost" size="icon-sm" />,
  ...props
}: ToastPrimitive.Close.Props) {
  return (
    <ToastPrimitive.Close
      data-slot="toast-close"
      aria-label="Close toast"
      render={render}
      className={cn(
        "relative shrink-0 text-muted-foreground after:absolute after:-inset-2 after:content-[''] hover:text-foreground",
        className
      )}
      {...props}
    >
      {children ?? (
        <HugeiconsIcon icon={Cancel01Icon} strokeWidth={2} aria-hidden="true" />
      )}
    </ToastPrimitive.Close>
  )
}

function ToastIcon({
  type,
  className,
}: {
  type: string | undefined
  className?: string
}) {
  let icon: React.ReactNode = null

  if (type === "success") {
    icon = (
      <CheckmarkCircleSolidIcon className="text-success" aria-hidden="true" />
    )
  }

  if (type === "info") {
    icon = (
      <InformationCircleSolidIcon className="text-info" aria-hidden="true" />
    )
  }

  if (type === "warning") {
    icon = (
      <AlertCircleSolidIcon className="text-warning" aria-hidden="true" />
    )
  }

  if (type === "error") {
    icon = (
      <MultiplicationSignCircleSolidIcon
        className="text-destructive"
        aria-hidden="true"
      />
    )
  }

  if (type === "loading") {
    icon = (
      <HugeiconsIcon
        icon={Loading03Icon}
        strokeWidth={2}
        className="animate-spin"
        aria-hidden="true"
      />
    )
  }

  if (!icon) {
    return null
  }

  return (
    <span
      data-slot="toast-icon"
      className={cn(
        "shrink-0 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4",
        className
      )}
    >
      {icon}
    </span>
  )
}

const UPLOAD_FILE_ROWS = 4

const uploadStatusBadges: Record<
  ToastUploadFileStatus,
  { label: string; variant: "zinc" | "blue" | "success" | "destructive" }
> = {
  uploading: { label: "Uploading", variant: "zinc" },
  sent: { label: "Sent", variant: "blue" },
  done: { label: "Done", variant: "success" },
  failed: { label: "Failed", variant: "destructive" },
}

/**
 * A file is only "sent" while the request is in flight: its bytes are on the
 * wire, but the whole upload is one request, so nothing is stored until the
 * response settles it. Report that distinction instead of claiming success.
 */
function summarizeUploadFiles(files: ToastUploadFile[]) {
  const complete = files.filter(
    (file) => file.status === "sent" || file.status === "done"
  ).length
  const word =
    files.length > 0 && files.every((file) => file.status === "done")
      ? "done"
      : "sent"

  return { complete, word }
}

/**
 * Keep at most `limit` rows on screen, anchored on the file currently being
 * transferred, so a twenty file drop still fits in a toast without hiding the
 * one that is moving.
 */
function visibleUploadFiles(files: ToastUploadFile[], limit: number) {
  if (files.length <= limit) {
    return { rows: files, hidden: 0 }
  }

  const lastWindow = files.length - limit
  const activeIndex = files.findIndex((file) => file.status !== "done")
  const start =
    activeIndex === -1 ? lastWindow : Math.min(activeIndex, lastWindow)

  return { rows: files.slice(start, start + limit), hidden: lastWindow }
}

function ToastUploadPanel({
  progress,
  files,
  hint,
  failed,
}: {
  progress: number
  files: ToastUploadFile[]
  hint: string | undefined
  failed: boolean
}) {
  const { complete, word } = summarizeUploadFiles(files)
  const { rows, hidden } = visibleUploadFiles(files, UPLOAD_FILE_ROWS)

  return (
    <div className="flex flex-col gap-2.5 rounded-xl border bg-muted/40 p-3">
      <div className="flex items-center justify-between gap-3 text-xs">
        <span className="font-medium">
          {files.length > 0
            ? `${complete} / ${files.length} ${word}`
            : "Transferring…"}
        </span>
        <span className="text-muted-foreground tabular-nums">{progress}%</span>
      </div>
      <Progress
        value={progress}
        aria-label="Upload progress"
        className={cn(
          // The default track shares its colour with the panel, so give it a
          // neutral tint that reads in both themes.
          "[&_[data-slot=progress-track]]:bg-foreground/10",
          failed && "[&_[data-slot=progress-indicator]]:bg-destructive"
        )}
      />
      {hint ? (
        <p className="text-[11px] text-muted-foreground">{hint}</p>
      ) : null}
      {rows.length > 0 ? (
        <ul className="flex flex-col gap-2 border-t pt-2.5">
          {rows.map((file, index) => (
            <li
              key={`${file.name}-${index}`}
              className="flex items-center gap-2"
            >
              <div className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-xs font-medium">
                  {file.name}
                </span>
                <span className="text-[11px] text-muted-foreground tabular-nums">
                  {file.size === null
                    ? `${file.progress}%`
                    : `${formatFileSize(file.size)} • ${file.progress}%`}
                </span>
              </div>
              <Badge
                variant={uploadStatusBadges[file.status].variant}
                className="shrink-0"
              >
                {uploadStatusBadges[file.status].label}
              </Badge>
            </li>
          ))}
        </ul>
      ) : null}
      {hidden > 0 ? (
        <p className="text-[11px] text-muted-foreground">
          +{hidden} more {hidden === 1 ? "file" : "files"}
        </p>
      ) : null}
    </div>
  )
}

function ToastList() {
  const { toasts } = ToastPrimitive.useToastManager<ToastData>()

  return toasts.map((toastItem) => {
    const upload = toastItem.data?.kind === "upload" ? toastItem.data : null
    const uploadFiles = upload?.files ?? []
    const uploadSummary = summarizeUploadFiles(uploadFiles)

    return (
      <Toast key={toastItem.id} toast={toastItem}>
        <ToastContent
          className={cn(upload && "flex-col items-stretch gap-3")}
        >
          <div
            className={cn(
              "flex w-full min-w-0 gap-3",
              upload ? "items-start" : "items-center"
            )}
          >
            <ToastIcon
              type={toastItem.type}
              className={cn(upload && "mt-0.5")}
            />
            <div
              className={cn(
                "flex min-w-0 flex-1 flex-col",
                upload ? "gap-0.5" : "gap-1"
              )}
            >
              {upload ? (
                <span className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                  Uploads
                </span>
              ) : null}
              <ToastTitle />
              {uploadFiles.length > 0 ? (
                <span className="text-xs text-muted-foreground tabular-nums">
                  {uploadFiles.length} total • {uploadSummary.complete}{" "}
                  {uploadSummary.word}
                </span>
              ) : null}
              <ToastDescription />
            </div>
            <ToastAction />
            <ToastClose />
          </div>
          {upload ? (
            <ToastUploadPanel
              progress={upload.progress}
              files={uploadFiles}
              hint={upload.hint}
              failed={toastItem.type === "error"}
            />
          ) : null}
        </ToastContent>
      </Toast>
    )
  })
}

function Toaster({
  children,
  toastManager = toast,
  limit = 5,
  ...props
}: ToastPrimitive.Provider.Props) {
  return (
    <ToastProvider toastManager={toastManager} limit={limit} {...props}>
      {children}
      <ToastPortal>
        <ToastViewport>
          <ToastList />
        </ToastViewport>
      </ToastPortal>
    </ToastProvider>
  )
}

const createToastManager = ToastPrimitive.createToastManager
const useToastManager = ToastPrimitive.useToastManager

export type { ToastUploadFile, ToastUploadFileStatus }

export {
  Toaster,
  Toast,
  ToastAction,
  ToastClose,
  ToastContent,
  ToastDescription,
  ToastPortal,
  ToastProvider,
  ToastTitle,
  ToastViewport,
  createToastManager,
  toast,
  useToastManager,
}
