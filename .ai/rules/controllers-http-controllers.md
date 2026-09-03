---
paths:
  - 'app/Models/EmailTemplate.php app/Http/Controllers/EmailTemplateController.php app/Http/Controllers/EmailController.php'
---

# Controllers Http Controllers

## Starter email templates are team-less rows seeded by a migration
`email_templates.team_id` is nullable: NULL means a starter that ships with the app, visible to every team and editable by none (`EmailTemplatePolicy::update` returns false for them, and scoped route bindings resolve `{emailTemplate}` through `$team->emailTemplates()` so starters 404 on update/destroy anyway). Teams get their own copy via `email_templates.duplicate`, which is registered OUTSIDE `scopeBindings()` for exactly that reason and checks availability by hand.

Starter content lives in `EmailTemplate::starters()` with hard-coded uuids so the seeding migration stays idempotent; `EmailTemplate::blankBodyFor()` reads the two blank starters for "compose from scratch". Query with `scopeAvailableTo($team)` + `scopeOrderedForPicker()` — never a bare `$team->emailTemplates()` — anywhere a picker or gallery is shown.
