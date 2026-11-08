---
paths:
  - 'resources/js/pages/subscribe-forms/**,resources/js/components/subscribe-form-view.tsx,resources/js/layouts/subscribe-forms/**'
---

# Subscribe Forms

## Subscribe form editor is a Figma-style canvas
The subscribe form editor renders without an app layout (`subscribe-forms/edit` resolves to no layout in app.tsx) and builds its own full-bleed shell in edit.tsx: a `fixed inset-0 h-dvh` column with an h-14 header (back link, name, status, actions), the live preview canvas on the left, and the `lg:w-[26rem]` inspector sidebar on the right. It does not use SubscribeFormEditorLayout. Styles are Classic (card), Split (image left/right), Minimal (no card), and Cover (card on a full-bleed image). Preview and the public page share SubscribeFormView so they stay in sync. Do not go back to a stacked title + two-column padded page.

## Subscribe form logos follow the layout
Subscribe forms can show an optional uploaded logo. Editors choose shape (default keeps the original orientation; square crops to a tile) and size (small/medium/large). Placement is not user-configurable: Classic and Cover center it above the card title, Split left-aligns it above the heading, Minimal centers it above the heading. Preview and the hosted page share SubscribeFormView so they stay in sync.
