---
paths:
  - 'resources/js/components/email-builder-editor.css,resources/js/components/email-builder-editor.tsx'
  - 'resources/js/components/automation-canvas.tsx, resources/js/components/automation-step-panel.tsx'
---

# Components Js Components

## Pin contained EmailBuilder.js drawers to their anchors
MUI persistent drawers are position:fixed to the viewport. Containing the inspect drawer in the compose card requires position:absolute plus .MuiDrawer-paperAnchorRight { right: 0 }. Absolute without that pin puts Inspect on the left. Do not mount the samples drawer.

## Condition branches show True and False
Condition nodes keep sourceHandle ids yes/no — AdvanceAutomationRun follows those ids. The canvas must label the left handle True and the right handle False (and the edges that leave them), so the next action is obviously on the matching branch. Do not rename the handle ids.
