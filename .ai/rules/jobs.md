---
paths:
  - 'app/Actions/Automations/**,app/Jobs/ProcessAutomationRun.php'
---

# Jobs

## Automation runs claim, never lock across a send
AdvanceAutomationRun must not wrap a step in DB::transaction. The queue is redis with after_commit false, so dispatching inside an open transaction lets a worker read the run before commit, and a rollback after Mail::send re-sends on retry.

Instead follow SendEmailDelivery: claim() flips Pending|Waiting to Running with a conditional update and bails if it did not win, and claimStep() inserts the step row before handing the message to the transport, relying on the unique (automation_run_id, node_id) index so a retry skips instead of sending twice. releaseStep() hands it back when the transport throws.

A raw query-builder claim leaves the model's original attributes stale, so re-baseline with forceFill(...)->syncOriginal() or a later update() back to Pending looks unchanged and never persists.

nextNodeId must only follow the branch the condition took; falling back to unhandled edges makes an unwired "no" branch follow the "yes" edge.

Tests must Queue::fake(). Under the sync queue used by phpunit, dispatching the next step recurses into handle() and a delay node loops until memory is exhausted.

## Every automation node leaves a step row
An automation run is auditable only if each node it reaches has a row in automation_run_steps. StartAutomationRun writes the trigger step, a delay writes a 'waiting' step when it parks (recordStep is updateOrCreate, so resuming rewrites it to 'completed'), a missing node writes 'failed', and ProcessAutomationRun@failed writes 'failed' on current_node_id before flipping the run to Failed.

recordStep must stay updateOrCreate: automation_run_steps is unique on (automation_run_id, node_id), so a plain create on the second visit to a node throws.

Do not assert that a parked delay has no step row; the waiting step is the tracking.
