---
paths:
  - '{resources/js/components/getting-started-checklist.tsx,app/Actions/Teams/BuildOnboardingChecklist.php,app/Http/Middleware/HandleInertiaRequests.php,resources/js/types/onboarding.ts}'
---

# Types

## Getting started follows the current sending workflow
Keep the checklist derived from live workspace state. Track tested email delivery and a verified default sender as separate steps; sender completion must use the current integration proof, not merely email_from_address. The remaining steps are audience, subscribed contact, sent campaign, and automation, with frontend destinations kept in STEP_COPY.
