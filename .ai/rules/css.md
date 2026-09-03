---
paths:
  - resources/css/app.css
---

# Css

## Keep custom blue primary
Light-mode --primary is oklch(0.631 0.2 253) with --primary-foreground oklch(0.985 0 0). shadcn apply resets it to near-black. Restore the blue after any preset/theme regenerate. Also keep --success, --info, --warning and the Laravel @source paths.

## Keep muted as #fafafa
Light-mode --muted is oklch(0.985 0 0) (#fafafa). shadcn apply resets it to oklch(0.97 0 0). Restore the lighter muted after any preset/theme regenerate. Dark-mode --muted stays oklch(0.269 0 0).
