---
paths:
  - 'app/Console/Commands/ResumeAutomationRunsCommand.php,app/Actions/Automations/**,app/Jobs/ProcessAutomationRun.php'
---

# Automations Jobs

## A delayed run needs a sweeper, not just a delayed job
A delay node parks the run at Waiting and leans on one delayed Redis job to wake it. Redis is not durable, so a flush, eviction, or queue reset on deploy strands the run forever — no worker touches it and no failure is recorded. `automations:resume` (scheduled every 5 minutes, withoutOverlapping) re-dispatches ProcessAutomationRun for runs where status is waiting and scheduled_at has passed. The ['status','scheduled_at'] index on automation_runs backs that query.

Re-dispatching is safe because AdvanceAutomationRun::claim() flips Pending|Waiting to Running with a conditional update, so a duplicate job loses the claim and returns.

Known and NOT covered: a run stranded at Running (worker SIGKILLed after claiming). claim() only accepts Pending|Waiting, so re-dispatching such a run no-ops. Recovering it needs a staleness heuristic and a reset to Pending — decide deliberately before adding it.
