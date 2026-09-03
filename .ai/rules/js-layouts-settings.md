---
paths:
  - 'app/Http/Controllers/Teams/*Email*SettingsController.php,app/Http/Controllers/Teams/TeamSenderSettingsController.php,app/Http/Requests/Teams/SaveTeam*SettingsRequest.php,resources/js/pages/teams/{email,sender,email-provider,email-provider-show}.tsx,resources/js/layouts/settings/layout.tsx,routes/settings.php'
---

# Js Layouts Settings

## Sender identity is separate from Email Builder
Workspace Email Builder only selects the editor. Sender owns From name/address and Reply-to; derive the sending domain from the resolved From address rather than storing a second domain field. Provider credentials, domain/DNS guidance, sender tests, authorization, and activation remain under Email delivery. Changing the From address clears every provider sender authorization and pauses delivery until retested and explicitly activated.
