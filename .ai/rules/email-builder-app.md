---
paths:
  - 'resources/js/email-builder/App/**'
---

# Email Builder App

## Do not mount the EmailBuilder.js samples drawer
Compose does not use the upstream samples sidebar (Waypoint templates and marketing). App/index.tsx must not render SamplesDrawer, and TemplatePanel must not render ToggleSamplesPanelButton. Keep the inspect drawer on the right.
