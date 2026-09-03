---
paths:
  - 'resources/js/pages/automations/**,app/Http/Controllers/AutomationController.php'
---

# Pages Automations Http Controllers

## Automation saves use one flash toast
AutomationController owns successful save notifications through Inertia::flash. The editor must not also call toast.add on save success; keep local toast.add only for client-side failures and test/copy feedback. Disable lifecycle status changes while editor changes are unsaved so save and activate are not chained into two success flashes.

## Activity is where a run is visible
automations/activity is the only surface that shows recorded runs: a filtered runs table with an expandable step trail per row, reached from the list overflow menu and the editor navbar. It reads automation_run_steps, never replays the graph.

Step labels come from the run's own graph snapshot ($run->node($nodeId)), not the automation's current graph, so a node deleted in the editor still reads as "Removed step" instead of a raw id. Tag and transactional-email names in step details are resolved in one batched lookup per page (stepNames), not per row.

The canvas Test button walks nodes only — it evaluates no branch, sends no mail, and writes no run. Never present it as proof an automation works; the Activity page is.
