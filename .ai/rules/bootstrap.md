---
paths:
  - bootstrap/app.php
---

# Bootstrap

## Never flash email provider secrets
Register every TeamEmailIntegration credential input in Exceptions::dontFlash. Validation redirects must not put SMTP passwords, SES/Mailgun passwords, SendGrid/Resend API keys, or Postmark tokens into old input or session storage.

## Preserve exact email-provider credentials
Email-provider password and token input names must remain in both TrimStrings exceptions and exception dontFlash. SMTP passwords and API tokens are opaque bytes: never trim, normalize, flash, log, or expose them.
