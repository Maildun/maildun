---
paths:
  - 'app/{Actions/Automations,Jobs,Models,Console/Commands}/**,database/migrations/*automation_run_steps*'
---

# Commands Migrations

## Automation fan-out uses durable node work
One automation enrollment may have multiple pending, running, or waiting automation_run_steps. ProcessAutomationRun receives the run id and node id, and AdvanceAutomationRun atomically claims that step; delays schedule only that step. Ordinary nodes enqueue every outgoing target, conditions enqueue only the matched yes/no handle, and the run status is aggregated from all open steps. Parallel branches may not merge; only mutually exclusive True/False edges from the same condition may converge.

## Fan-out supersedes run-level claims
This fan-out contract supersedes the older rules that claimed automation_runs as one cursor and parked delays on automation_runs. Exactly-once claims and scheduled_at now belong to automation_run_steps; automation_runs.current_node_id and scheduled_at are aggregate compatibility fields only.
