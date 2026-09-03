---
paths:
  - resources/js/components/ui/tabs.tsx
  - resources/js/components/ui/badge.tsx
  - resources/js/components/ui/sonner.tsx
  - 'resources/js/components/ui/{input,textarea,select,input-otp,combobox,input-group,code-editor}.tsx'
  - resources/js/components/ui/toast.tsx
  - resources/js/components/ui/button.tsx
  - resources/js/components/ui/sidebar.tsx
---

# Ui

## Preserve sliding TabsList variant
TabsList has a local `variant="sliding"` used by audiences and the email editor. Re-applying shadcn/ui (including preset apply) overwrites tabs.tsx and drops this variant. Re-merge the sliding indicator (ref + MutationObserver/ResizeObserver pill) and keep `z-10` plus transparent active background on TabsTrigger for the sliding variant.

## Keep tag color Badge variants
Badge has local color variants used by tags and status chips: success, info, purple, pink, rose, orange, teal, sky. shadcn apply/add resets badge.tsx to default/secondary/destructive/outline/ghost/link only. Re-merge those color variants after any regenerate.

## Keep original Badge style and variants
Do not replace Badge with the Vega pill style (rounded-4xl, h-5, solid primary). Keep the local inset-ring, rounded-md, px-2 py-1 look and the full variant set: default, secondary, destructive, outline, success, info, purple, pink, rose, orange, teal, sky, ghost, link. Each color variant has a Tailwind Plus dark treatment (`dark:bg-*-400/10`, matching text and inset-ring). shadcn apply/add overwrites this file.

## Input fields use a light zinc focus ring
Focus rings on input-like fields (Input, Textarea, Select trigger, InputOTP, Combobox chips, InputGroup, CodeEditor) use ring-zinc-200 in light mode and ring-zinc-800 in dark, not the default ring-ring/50. Keep the focus border as border-ring. shadcn add/apply restores ring-ring/50 — re-apply this after regenerating those files. Buttons, checkboxes, switches, and tabs stay on ring-ring/50.

## Keep solid colored toast icons
Toaster uses the hand-built solid icons in resources/js/components/icons/toast-status-icons.tsx, not Hugeicons stroke icons. Colors: success=text-success (green), info=text-info (blue), warning=text-warning (amber), error=text-destructive (red). shadcn add/apply overwrites toast.tsx and drops these; re-merge after any regenerate.

## Toasts use the collapsed Base UI stack
Notifications use the Base UI toast in resources/js/components/ui/toast.tsx (toast.add), not sonner. ToastList renders Toast.Root items directly (not Toast.Positioner). Keep limit at 5 and keep the app Toaster from @/components/ui/toast.

The viewport is a zero-height `fixed bottom-4 right-4` box with NO flex/gap; every Toast.Root is `absolute right-0 bottom-0 w-full`. Collapsed state clamps each root to `h-[var(--toast-frontmost-height,auto)]` and applies `scale(calc(1-(var(--toast-index)*0.05)))` + `translateY(calc(var(--toast-index)*-20%))` with `origin-bottom`, so rear toasts peek ~11/9/8px above the frontmost one. ToastContent hides rear text via `[&[data-behind]:not([data-expanded])]:opacity-0`. On `data-expanded` (viewport hovered or focused) roots go to `h-[var(--toast-height,auto)]`, `scale(1)` and `translateY(calc(-1*var(--toast-offset-y) - var(--toast-index)*12px))` — `--toast-offset-y` is the cumulative natural height of the toasts in front, so the 12px term supplies the gap.

Absolute positioning alone is NOT the bug: without the per-index scale/translate, height clamping, and `data-behind` fade, absolute roots all pin to bottom: 0 and overlap perfectly. All five pieces are required together. Base UI measures natural height by temporarily setting inline `height: auto`, so the class-driven clamp does not poison `--toast-height`. Verified on @base-ui/react 1.7.0.

shadcn add/apply overwrites toast.tsx — re-apply this and the solid status icons after any regenerate.

## Never pass a reusable id to toast.add
Base UI's `addToast` dedupes on `id`: if a non-ending toast with that id already exists it is *updated in place* and no second toast is added. `use-flash-toast.ts` used to pass `id: data.message`, so repeating an action (deleting subscribers one by one, all flashing "Subscriber permanently deleted.") collapsed into one toast and could never stack. Omit `id` and let Base UI generate one, unless you deliberately want an existing toast replaced (e.g. a loading toast you later resolve).

## Keep the raised primary button
The Button `default` (primary) variant is a "button-7" style solid button, not a flat `bg-primary`. It carries `shadow-xs`, an `inset-shadow-2xs inset-shadow-white/40` top highlight, a hover that DARKENS (`hover:bg-[color-mix(in_oklch,var(--primary),black_12%)]`, `active:` the same at 22% plus `active:shadow-none active:inset-shadow-none`), and a soft `focus-visible:ring-4 focus-visible:ring-primary/15` glow with `focus-visible:border-primary`.

No always-on ring: the glow is focus-only, and the base `border border-transparent bg-clip-padding` stays so the fill keeps its 1px inset edge. Do not go back to `hover:bg-primary/80` — that lightens toward the page background instead of darkening, and washes out over non-white surfaces. Every color is token-based, so dark mode and the subscribe-form brand overrides (`--subscribe-form-primary-*` mapped onto `--primary`) recolor hover, active and the focus glow automatically. shadcn add/apply overwrites button.tsx and drops all of it — re-merge after any regenerate.

## Buttons use rounded-lg, not rounded-md
The base Button class (default/lg sizes) is `rounded-lg` (10px, this app's `--radius`). We iterated through several other radii — `rounded-full` (full pill, Manage-Billing style), `rounded-xl` (14px), and `rounded-[12px]` (arbitrary, since no token lands exactly at 12px) — and landed back on `rounded-lg` as the final choice. `xs`/`sm`/`icon-xs`/`icon-sm` stay on `rounded-md` (8px) so smaller buttons keep a slightly tighter corner than the standard size — restore that pairing (`rounded-lg` default, `rounded-md` small sizes) if it's ever lost to a shadcn regenerate. The dead `in-data-[slot=button-group]:rounded-md` classes (no ButtonGroup component exists in this app) were left as-is. shadcn add/apply resets button.tsx to `rounded-md` across the board — re-apply after any regenerate.

## Primary buttons use the grainy surface
The Button default (primary) variant must include `relative grainy`, reusing the app.css feTurbulence overlay while preserving its raised shadow and darkening hover/active states. Do not apply the grain to outline, secondary, ghost, destructive, or link variants. Covered by tests/Unit/GrainySidebarUiTest.php.

## Keep sliding indicator scroll-aware
Scrollable sliding TabsList instances must calculate the active pill's left offset with `triggerRect.left - listRect.left + list.scrollLeft`. Without the scroll offset, the pill drifts away from its trigger after horizontal scrolling on narrow screens.

## Sidebar trigger uses layout-align icons
SidebarTrigger shows Hugeicons LayoutAlignLeftIcon when the sidebar is expanded (open) and LayoutAlignRightIcon when it is collapsed (closed). Do not revert to PanelLeftIcon/PanelRightIcon.
