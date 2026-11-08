---
paths:
  - 'resources/js/pages/audiences/**,app/Http/Controllers/*Audience*.php,app/Models/Audience.php,app/Models/AudienceAttribute.php'
---

# Models

## Audience settings pages and attribute keys
Audience settings are dedicated pages, not sliding tabs: General (name, description, overview), Sender, Email notification, Attributes (nested audiences.attributes CRUD), Landing pages (subscribed/already_subscribed/unsubscribed URLs only), Danger zone. Sender is an optional override that resolves email → audience → team → config. PATCH audiences.update is partial — only sent fields are validated and written, so a sender save must not require or clear the name. Attribute keys are slugified, unique per audience, and cannot reuse reserved subscriber columns (email, first_name, last_name, status, source, tags, …). Renaming or deleting a field records an AudienceAttributeMove before queueing MoveAudienceAttributeValues; until the job finishes (or its 24h retry window passes) no other field may take the old key, because the move would carry off or delete the new field's values. A field may be renamed back to the key its own values are moving off. The job rewrites one chunk per set-based JSON UPDATE (SQLite json_set/json_remove, Postgres via jsonb) and uses retryUntil, not a try count. Public subscribe stays generic — do not branch the public JSON/redirect on already-subscribed, or you leak whether an address exists.
