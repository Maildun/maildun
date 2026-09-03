---
paths:
  - 'app/Models/Segment.php app/Http/Controllers/SegmentController.php app/Actions/Audiences/*.php app/Console/Commands/SyncSegmentsCommand.php'
---

# Commands

## Segment membership is materialized, not computed live
Segment::subscribers() is a real BelongsToMany pivot (segment_subscriber), not a live ApplySegmentRules query. SyncSegmentSubscribers::handle($segment) applies the rules and syncs the pivot + stamps rules_synced_at; it runs synchronously in SegmentController@store/update for immediate feedback, and via the `segments:sync` artisan command scheduled every 5 minutes (routes/console.php) for drift caused by subscriber changes elsewhere. Anywhere that needs "who's in this segment" (subscriber lists, counts) should read the pivot/relation, not call ApplySegmentRules directly — that action is now only the rule-matching primitive used by SyncSegmentSubscribers.
