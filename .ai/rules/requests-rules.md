---
paths:
  - 'app/{Services,Actions/Emails,Actions/Transactional,Actions/Automations,Jobs,Http/Requests,Rules}/**'
---

# Requests Rules

## Sender overrides are domain-aligned to the authorized connection
Campaign, audience, and transactional emails may override the workspace From address, but the override must stay on the domain of the active connection's authorized_from_address. A provider test authorizes one exact address; sending is allowed for any sibling on that domain, because SES domain identities and SPF/DKIM alignment both work per domain.

Enforce it in two places. TeamMailer::sendResolved takes the From address it is about to put on the wire and refuses an unauthorized one via ResolvedEmailTransport::allowsSender — never make that parameter optional, or a new send path silently skips the check. StartEmailSend, RetryEmailDeliveries, and QueueTransactionalEmail also check up front so an author sees one clear error instead of every recipient failing against the provider. Form requests use App\Rules\AuthorizedSenderAddress for save-time feedback.

TeamMailer::sendWithIntegration passes enforceSenderAuthorization: false — the provider test is what creates the authorization. A transport with no authorized address (the platform mailer) is unconstrained.
