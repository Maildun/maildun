---
paths:
  - 'resources/js/components/ui/dialog.tsx,resources/js/components/ui/command.tsx'
---

# Components Ui

## DialogContent width is w-96 and overrides use w-*, never max-w-*
DialogContent's base class sets a real width — `w-96` (384px) — plus `max-w-[calc(100%-2rem)]` as a viewport clamp. The old `w-full ... sm:max-w-md` pairing is gone.

Override the width by passing a single `w-*` class in className (`w-lg`, `w-2xl`, `w-4xl`, `w-[720px]`). tailwind-merge drops `w-96` for any `w-*` in the same group, and the `max-w-[calc(100%-2rem)]` clamp survives — so a plain `w-2xl` is already mobile-safe and needs no `sm:` prefix.

Never override with `max-w-*`: it cannot widen the dialog past the fixed `w-96`, and because it collides with the clamp in tailwind-merge's `max-w` group it also removes the mobile gutter. This is why every wide dialog was migrated from `sm:max-w-lg/2xl/4xl` to `w-lg/2xl/4xl`. Enforced by tests/Unit/DialogWidthUiTest.php, which scans every `<DialogContent>` tag under resources/js.

CommandDialog carries its own `w-lg` so the palette does not shrink to the 384px default. AlertDialogContent is separate — it keeps its own `data-[size=*]:max-w-*` system. shadcn add/apply overwrites dialog.tsx and command.tsx; re-apply after any regenerate.
