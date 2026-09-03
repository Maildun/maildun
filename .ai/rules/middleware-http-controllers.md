---
paths:
  - 'routes/api.php,app/Http/Middleware/AuthenticateAutomationTrigger.php,app/Http/Controllers/AutomationTriggerController.php'
---

# Middleware Http Controllers

## Automation trigger API is stateless and authenticates before validation
Keep the public trigger at /api/automations/{automation}/trigger in routes/api.php. Throttle before token authentication so failed guesses are limited; authenticate the encrypted automation token in middleware before FormRequest validation, returning 401 for missing, wrong, null, or undecryptable tokens.
