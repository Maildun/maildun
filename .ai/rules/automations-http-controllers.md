---
paths:
  - 'app/Actions/Automations/**,app/Http/Controllers/Automation*.php'
---

# Automations Http Controllers

## Known gaps to close before automations ship
Found during the Aug 2026 security pass and deliberately left unfixed while the feature is in flight. 1) FIXED (Aug 2026): AutomationController@update runs ValidateAutomationGraph when the automation is Active, so an Active automation cannot be edited into a graph that would fail activation. Draft and Paused still save any shape UpdateAutomationRequest accepts. 2) FIXED (Aug 2026): AdvanceAutomationRun no longer wraps the step in a transaction. It claims the run with a conditional update and claims the step row before the send, so a retry skips instead of re-sending. See .ai/rules/jobs.md. 3) FIXED (Aug 2026): a partial unique index over (automation_id, subscriber_id) where status is open backs the check-then-insert, and StartAutomationRun wraps the run plus its trigger step in a transaction and returns null on UniqueConstraintViolationException. 4) FIXED (Aug 2026): AuthenticateAutomationTrigger rejects missing, null, wrong, or undecryptable tokens with 401 before request validation. 5) FIXED (Aug 2026): AutomationEmail sets List-Unsubscribe and List-Unsubscribe-Post via a subscriber-keyed signed route, and offers {{ unsubscribe_url }} as a merge tag. See .ai/rules/unsubscribe.md.
