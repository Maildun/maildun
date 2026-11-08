---
paths:
  - 'app/Console/Commands/ResumeAutomationRunsCommand.php,app/Actions/Automations/**,app/Jobs/ProcessAutomationRun.php'
---

# Automations Jobs

## A delayed run needs a sweeper, not just a delayed job
A delay node parks the run at Waiting and leans on one delayed Redis job to wake it. Redis is not durable, so a flush, eviction, or queue reset on deploy strands the run forever — no worker touches it and no failure is recorded. `automations:resume` (scheduled every 5 minutes, withoutOverlapping) re-dispatches ProcessAutomationRun for automation_run_steps that are pending, or waiting with scheduled_at passed (AutomationRunStep::dueForDispatch).

The sweep only re-queues a step that has stalled (AutomationRunStep::stalledSince): updated_at, and scheduled_at for a delay, at least `delivery.recovery.stalled_after_minutes` (min 5, default 15) old. It touches each step it re-queues, mirroring contacts:resume-imports, so a branch still waiting in a busy queue is not pushed again on every sweep. The ['status','updated_at'] index on automation_run_steps backs it. Do not put the staleness filter into dueForDispatch itself: activating a paused automation must re-dispatch every due step at once.

The sweep also skips steps whose workspace (team) is soft-deleted, cancels Queued automation deliveries whose step is no longer running (or was deleted with its subscriber) once past the lost window, and synchronizes open runs with no open step left, so a run never stays open forever and blocks re-entry through the open-run unique index.

Re-dispatching is safe because AdvanceAutomationRun::claim() flips Pending|Waiting to Running with a conditional update, so a duplicate job loses the claim and returns.

Known and NOT covered: a run stranded at Running (worker SIGKILLed after claiming). claim() only accepts Pending|Waiting, so re-dispatching such a run no-ops. Recovering it needs a staleness heuristic and a reset to Pending — decide deliberately before adding it.
