---
paths:
  - 'resources/js/pages/audiences/**,app/Http/Controllers/*Audience*.php,app/Actions/Audiences/*.php'
---

# Actions Audiences

## Subscriber list uses one interactive stats chart
The audience show subscribers tab renders one shadcn Chart card (metric tabs with week-over-week change + a 28-day line vs the previous period) above the subscriber toolbar. Stats come from BuildAudienceSubscriberStats. Do not go back to five separate stat cards or wrap the subscriber table in a Card.
