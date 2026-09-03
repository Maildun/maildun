---
paths:
  - 'resources/js/pages/subscribe-forms/**,resources/js/components/subscribe-form-view.tsx,resources/js/layouts/subscribe-forms/**'
---

# Subscribe Forms

## Subscribe form editor is a Figma-style canvas
The subscribe form editor uses SubscribeFormEditorLayout (full-bleed, no max-w-7xl/p-10): live preview canvas on the left, inspector sidebar on the right. Styles are Classic (card), Split (image left/right), Minimal (no card), and Cover (card on a full-bleed image). Preview and the public page share SubscribeFormView so they stay in sync. Do not go back to a stacked title + two-column padded page.

## Subscribe form logos follow the layout
Subscribe forms can show an optional uploaded logo. Editors choose shape (default keeps the original orientation; square crops to a tile) and size (small/medium/large). Placement is not user-configurable: Classic and Cover center it above the card title, Split left-aligns it above the heading, Minimal centers it above the heading. Preview and the hosted page share SubscribeFormView so they stay in sync.
