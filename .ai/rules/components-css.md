---
paths:
  - 'resources/js/components/subscribe-form-view.tsx,resources/js/pages/subscribe-forms/**,resources/js/pages/teams/theme.tsx,resources/js/app.tsx,resources/js/components/app-providers.tsx,resources/css/app.css'
---

# Components Css

## Team branding is subscribe-form scoped
Team brand color, font, and input style customize subscribe forms only. Apply their CSS variables and data attributes on SubscribeFormView or its explicit preview surface; never write them to document.documentElement or wrap the global app providers with team branding.
