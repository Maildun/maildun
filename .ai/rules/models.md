---
paths:
  - 'resources/js/pages/audiences/**,app/Http/Controllers/*Audience*.php,app/Models/Audience.php,app/Models/AudienceAttribute.php'
---

# Models

## Audience settings pages and attribute keys
Audience settings are dedicated pages, not sliding tabs: General (name, description, overview), Sender, Email notification, Attributes (nested audiences.attributes CRUD), Landing pages (subscribed/already_subscribed/unsubscribed URLs only), Danger zone. Sender is an optional override that resolves email → audience → team → config. PATCH audiences.update is partial — only sent fields are validated and written, so a sender save must not require or clear the name. Attribute keys are slugified, unique per audience, and cannot reuse reserved subscriber columns (email, first_name, last_name, status, source, tags, …). Public subscribe stays generic — do not branch the public JSON/redirect on already-subscribed, or you leak whether an address exists.
