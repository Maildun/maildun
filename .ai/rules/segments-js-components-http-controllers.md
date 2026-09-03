---
paths:
  - 'resources/js/pages/audiences/**,resources/js/pages/segments/show.tsx,resources/js/components/subscriber-hover-card.tsx,app/Http/Controllers/SubscriberController.php'
---

# Segments Js Components Http Controllers

## Subscriber names open a profile page
Clicking a subscriber name (audience table, segment matching list, or hover-card trigger) goes to audiences.subscribers.show. The hover card stays for preview and the label-only Edit button; do not replace it with a tooltip. The profile is a single-column section page (Activity, Details, Received emails, Automations) nested under the audience, with custom attributes, consent, matching segments, and paginated deliveries. Keep bulk subscriber routes registered before the resource so {subscriber} cannot capture bulk-unsubscribe/bulk-destroy.
