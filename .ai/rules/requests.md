---
paths:
  - 'app/Models/Tag.php app/Models/Subscriber.php app/Http/Controllers/SubscriberController.php app/Http/Controllers/TagController.php app/Http/Requests/SaveSubscriberRequest.php'
---

# Requests

## Tags are team-scoped; subscriber tagging goes through SaveSubscriberRequest, not a separate endpoint
Tag belongs to Team (not Audience) so the same tag set is reusable across every audience in a team; Subscriber belongsToMany Tag via subscriber_tag. TagController (index/store/update/destroy under settings/teams/{team}/tags, registered in routes/settings.php with scoped bindings) manages the team's tag list directly (create/rename/recolor/delete) from the team settings area. Assigning tags to a subscriber is NOT a separate sync endpoint — it's folded into SubscriberController::store/update via SaveSubscriberRequest's `tags` field (array of tag names). SubscriberController::syncTags() (private) trims/dedupes case-insensitively and does `$team->tags()->firstOrCreate(['name' => $name])` per name so typing a new tag name in the combobox creates it inline, then `$subscriber->tags()->sync($ids)`. The frontend TagsCombobox (resources/js/components/tags-combobox.tsx) lives inside the Add/Edit subscriber dialog (useForm-based, not the declarative Inertia <Form> component, since array fields need JS-driven state to reliably submit an empty array when all tags are cleared).
