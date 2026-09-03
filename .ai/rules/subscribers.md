---
paths:
  - resources/js/pages/audiences/subscribers/show.tsx
---

# Subscribers

## Subscriber profile is a single-column section page
audiences/subscribers/show is a max-w-3xl single column, not tabs and not a four-up metric grid. Header is avatar + name + status badge + email, with Edit / Unsubscribe in the header (Delete in overflow). Sections are plain headings (Activity, Details, Received emails, Automations) above one Card each. Activity is label/value rows with a muted footer; Details uses the same rows plus nested muted panels for custom attributes and consent. Keep the hover-card preview on lists.
