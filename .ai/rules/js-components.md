---
paths:
  - 'resources/js/pages/audiences/**,resources/js/pages/segments/show.tsx,resources/js/components/subscriber-hover-card.tsx'
---

# Js Components

## Subscriber names open a hover card
The audience subscribers table and the segment matching-subscribers table wrap the Micah avatar + name in SubscriberHoverCard. Hovering previews status, source, tags, and subscribed date. Managers get an Edit button that opens the existing subscriber dialog — do not replace this with a tooltip or a click-only popover. The hover-card Edit button is label-only (no Edit03Icon). Subscriber name and email truncate in the table trigger (`min-w-0 max-w-full` on the trigger, `max-w-0` on the table cell) and in the hover card body.
