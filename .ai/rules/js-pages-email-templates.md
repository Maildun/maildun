---
paths:
  - 'app/Http/Controllers/EmailTemplateController.php,app/Http/Requests/SaveEmailTemplateRequest.php,resources/js/pages/email-templates/**'
---

# Js Pages Email Templates

## New templates take the team editor with no switcher
Create uses the team's email_editor; updates keep the template's stored editor. Do not accept editor from the client or put a per-template editor switch on the compose page. HTML templates stay editable after the team switches to the block editor, but Use is hidden when the editors do not match.
