---
paths:
  - '{app/Models/Team.php,app/Services/TeamMailer.php,app/Http/Controllers/Teams/TeamEmailIntegrationController.php,resources/js/pages/teams/email-provider.tsx}'
---

# Teams Js Pages Teams

## Connected providers require one explicit active sender
A workspace may store one encrypted connection per EmailProvider, but normal delivery must use only teams.active_email_integration_id. The one-provider-per-type and first-ever auto-selection parts are superseded below. If the active connection is removed while others remain, keep sending paused until a user explicitly activates another provider. Testing a saved provider must not change the active selection.

## Multiple named email connections
Supersedes the earlier one-connection-per-EmailProvider rule. The overview may list multiple named connections for one provider and must link/manage/mark active by integration UUID, not provider value; only teams.active_email_integration_id selects the sender.
