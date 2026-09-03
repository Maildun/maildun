---
paths:
  - 'resources/js/components/subscribe-form-view.tsx,resources/css/app.css'
---

# Js Components Css

## Cover art cannot steal form clicks
Cover layout's FormArtPanel is pointer-events-none absolute inset-0 z-0; the form card is relative z-10. The placeholder's inner z-10 would otherwise sit above the card and block inputs. Brand colors are CSS variables switched by .dark, not useAppearance, so SSR and the client paint the same inline styles.
