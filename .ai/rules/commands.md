---
paths:
  - 'app/Models/Segment.php app/Http/Controllers/SegmentController.php app/Actions/Audiences/*.php app/Console/Commands/SyncSegmentsCommand.php'
---

# Commands

## Segment membership is materialized, not computed live
Segment::subscribers() is a real BelongsToMany pivot (segment_subscriber), not a live ApplySegmentRules query. SyncSegmentSubscribers::handle($segment) applies the rules and syncs the pivot + stamps rules_synced_at; it runs synchronously in SegmentController@store/update for immediate feedback, and via the `segments:sync` artisan command scheduled every 5 minutes (routes/console.php) for drift caused by subscriber changes elsewhere. SyncSegmentSubscribers re-reads the segment row with lockForUpdate inside its transaction and syncs those rules, never the caller's copy: segments:sync loads a chunk up front, and syncing a user's earlier rules would overwrite the members of their edit and stamp it synced. A rule whose operator the field's current type does not accept (e.g. greater_than after a custom field became Text) matches nobody, never the whole audience, and SaveAudienceAttributeRequest refuses that type change while a segment has such a rule, naming the segments. EnsureSegmentRulesCanChange compares a rule's `attribute` (case-insensitively) along with field, operator and value. Anywhere that needs "who's in this segment" (subscriber lists, counts) should read the pivot/relation, not call ApplySegmentRules directly — that action is now only the rule-matching primitive used by SyncSegmentSubscribers.
