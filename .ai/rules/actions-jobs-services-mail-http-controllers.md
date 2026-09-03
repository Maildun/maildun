---
paths:
  - 'app/{Actions,Jobs,Services,Mail,Http/Controllers}/**'
---

# Actions Jobs Services Mail Http Controllers

## Workspace mail delivery overrides the environment mailer
Campaign, automation, transactional, and test sends must go through TeamMailer so the current team's encrypted TeamEmailIntegration is resolved at send time. The configured environment mailer remains only as the legacy fallback when no team integration exists. SendGrid, SES, Mailgun, Resend, and Postmark currently use supported SMTP credential presets, and TeamMailer must purge its runtime mailer after every send to prevent credentials leaking between Horizon jobs.

## Workspace mail delivery overrides the environment mailer
Campaign, automation, transactional, and test sends must go through TeamMailer so the current team's encrypted TeamEmailIntegration is resolved at send time. The environment mailer is only a legacy fallback for teams that have never configured UI delivery; once requires_email_integration is set, disconnecting must block all fallback sends. Provider integrations use SMTP presets, and TeamMailer must purge its runtime mailer and sanitize transport errors after every send.

## Disconnected UI providers never fall back
Treat requires_email_integration as the authoritative opt-in marker. If it is true and no TeamEmailIntegration row exists, block campaign, automation, transactional, retry, and test sends with the safe configuration error; never send through mail.default. This specific rule supersedes any broader legacy-fallback wording.
