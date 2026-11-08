---
paths:
  - 'app/Actions/Automations/**,app/Jobs/ProcessAutomationRun.php'
---

# Jobs

## Automation runs claim, never lock across a send
AdvanceAutomationRun must not wrap a step in DB::transaction. The queue is redis with after_commit false, so dispatching inside an open transaction lets a worker read the run before commit, and a rollback after Mail::send re-sends on retry.

Instead follow SendEmailDelivery: claim() flips Pending|Waiting to Running with a conditional update and bails if it did not win, and claimStep() inserts the step row before handing the message to the transport, relying on the unique (automation_run_id, node_id) index so a retry skips instead of sending twice. releaseStep() hands it back when the transport throws.

synchronizeRun is the one exception: it locks the run row (lockForUpdate in a short transaction) while it reads the open steps and writes the aggregate status, and does nothing else inside it — never a dispatch or a send. Unlocked, a sibling branch's late write could reopen a run another branch already closed.

A raw query-builder claim leaves the model's original attributes stale, so re-baseline with forceFill(...)->syncOriginal() or a later update() back to Pending looks unchanged and never persists.

nextNodeId must only follow the branch the condition took; falling back to unhandled edges makes an unwired "no" branch follow the "yes" edge.

Tests must Queue::fake(). Under the sync queue used by phpunit, dispatching the next step recurses into handle() and a delay node loops until memory is exhausted.

## Every automation node leaves a step row
An automation run is auditable only if each node it reaches has a row in automation_run_steps. StartAutomationRun writes the trigger step, a delay writes a 'waiting' step when it parks (recordStep is updateOrCreate, so resuming rewrites it to 'completed'), a missing node writes 'failed', and ProcessAutomationRun@failed writes 'failed' on current_node_id before flipping the run to Failed.

recordStep must stay updateOrCreate: automation_run_steps is unique on (automation_run_id, node_id), so a plain create on the second visit to a node throws.

Do not assert that a parked delay has no step row; the waiting step is the tracking.

## Deleted workspaces stop their runs
A soft-deleted team keeps its rows until purged but runs nothing. AdvanceAutomationRun cancels a run whose automation's team is gone ("The workspace was deleted."), SendAutomationEmailDelivery cancels the delivery and the run, the API trigger answers 404 as for a deleted automation, and automations:resume does not re-queue their steps.

## One tagging sends each email once per contact
Tag-triggered automations without an audience filter still start one run per audience membership. DispatchTaggedTriggers stamps every run of one tagging with the same context.trigger_event_uuid, and a send step writes AutomationEmailDelivery.dedupe_key = sha256(automation_id|trigger_event_uuid|node_id). A partial unique index (status not cancelled, rejected or failed) lets only one such delivery exist, and the losing step is recorded as skipped with reason 'duplicate'. It is a DB claim, not a read-then-write check, so concurrent runs are safe. Only that insert runs in its own DB::transaction (a savepoint when nested) so a lost claim cannot abort an enclosing Postgres transaction; it never wraps the step. Runs started any other way, or by a later tagging, carry no shared key and send as before. ProcessContactImport fires tagged events itself and does not stamp a trigger_event_uuid yet.

## Automations starting one another stop after a few hops
A run started by another automation's add-tag step carries context.trigger_depth (source depth + 1). DispatchTaggedTriggers refuses past MAX_TRIGGER_DEPTH (5) and logs a warning, and the tag step's result gets trigger_chain_limit: true, which the activity page shows next to the tag name. source_automation_uuid still stops an automation from restarting itself.
