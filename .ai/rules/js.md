---
paths:
  - 'resources/js/**/*.tsx'
  - resources/js/app.tsx
---

# Js

## Recharts Pie needs isAnimationActive={false}
In this app's dev environment, a shadcn/recharts `<Pie>` inside `<ChartContainer>`/`<ResponsiveContainer>` renders zero sectors (empty ring, only the center `<Label>` shows) unless `isAnimationActive={false}` is set on `<Pie>`. Reproduced on recharts 3.8.0 and 3.10.1 with correct container sizing — the mount-triggered sector animation never starts. Always pass `isAnimationActive={false}` on `<Pie>` (and consider the same for other animated recharts primitives if they show the same symptom).

Separately: this app's Vite dev+SSR setup is prone to "Hydration failed" / duplicate `#app` root mounts under heavy concurrent file-edit/HMR churn (multiple agents editing the same file). Any client-only lib that measures its container (recharts' ResponsiveContainer, maps, etc.) should be gated with a mount-check hook (see `resources/js/hooks/use-mounted.ts`, built on `useSyncExternalStore` — not a `useEffect`+`setState`, which trips the `react-hooks/set-state-in-effect` lint rule) so SSR and first client paint render identical placeholder markup.

## Edit actions use Edit03Icon
All edit pencils are Hugeicons Edit03Icon — not Edit02Icon, PencilEdit01Icon, or PencilEdit02Icon. The audience table overflow Manage item uses UserGroupIcon next to the label.

## Form inputs require placeholders
Every user-facing Input, Textarea, InputGroupInput, ComboboxChipsInput, and PasswordInput must include a placeholder. Use an example value (email@example.com, Product newsletter) or a short action hint (Search audiences, Type or paste the audience name). A FieldLabel does not replace a placeholder. Skip native color pickers, OTP slots, hidden/file/range inputs, and wrappers that only forward props (PasswordInput).

## Password fields use PasswordInput
Password form fields use the PasswordInput component (visibility toggle), not Input type="password" or a raw password input. Pass placeholder and the rest of the input props through to PasswordInput.

## Combobox and Dialog both use Base UI
Dialog and Combobox are both Base UI, so they compose natively. Do not re-apply the old Radix Dialog patches (modal={false}, onInteractOutside preventDefault, manual backdrop) when embedding TagsCombobox or any Combobox in a Dialog. Use a normal modal Dialog.

## Recharts Line needs isAnimationActive={false}
Same as Pie: pass isAnimationActive={false} on Recharts Line (and other animated primitives) inside ChartContainer so the series actually draws in this app's Vite/SSR setup.

## Keep the Inertia entry free of components
Never declare a React component in resources/js/app.tsx. @vitejs/plugin-react only adds the Fast Refresh HMR wrapper to files that register a component ($RefreshReg$), and that wrapper makes the entry a hot-update boundary: an update to any dependency (hooks, toast, layouts) re-executes app.tsx, createInertiaApp() runs a second time, and a second React root mounts on #app. Symptoms are "createRoot() on a container that has already been passed to createRoot()", "Hydration failed…" (e.g. Base UI Avatar showing img vs fallback), and recharts "width(0) and height(0)". Providers live in resources/js/components/app-providers.tsx for this reason.

createInertiaApp() must also stay a top-level expression statement: @inertiajs/vite parses that statement and rewrites it into the SSR entry. Wrapping the call in a function or an if-block silently disables SSR (the served #app comes back empty, no data-server-rendered attribute).

## Submit buttons need an explicit type="submit"
Base UI's Button injects `type="button"` whenever no type is passed, so a Button inside a form does NOT submit it — the HTML default of `type="submit"` never applies. Verified: renderToString of `<Button>` gives `<button type="button">`, `<Button type="submit">` gives `<button type="submit">`.

Every Button that submits a `<Form>` must set `type="submit"` explicitly. This broke silently in the Radix → Base UI migration (Save changes on settings/profile, Update password on settings/security, and the confirm-password / forgot-password / verify-email auth forms all stopped working). The same applies to any other Base UI part built on useButton.

## Inertia Form inside a Dialog must own its own spacing
`DialogContent` is `grid gap-6`, so that gap only separates its *direct* children. When an Inertia `<Form>` is the single direct child of `<DialogContent>` and wraps `<DialogHeader>`, the fields, and `<DialogFooter>`, the form becomes the only grid item and those sections collapse to zero vertical spacing. Give that `<Form>` `className="space-y-6"` (see invite-member-modal.tsx, delete-team-modal.tsx, tag-dialog.tsx), and tighten any `<FieldGroup>` inside it to `className="gap-5"` — the `gap-7` default is meant for full-page settings forms and otherwise spaces fields wider than the sections containing them.

Dialogs that keep `<DialogHeader>` outside the form and put `mt-6` on `<DialogFooter>` (contact-dialog.tsx, company-dialog.tsx, audiences/index.tsx) already own their spacing — leave them alone.
