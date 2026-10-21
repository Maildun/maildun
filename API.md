# Maildun API

Maildun exposes a versioned JSON API for sending published transactional emails, checking their delivery status, and managing audience subscriptions. A separate endpoint starts automations that use the API trigger.

## Authentication

Create and revoke API keys from **Settings → API** for the relevant team. A key belongs to exactly one team, and every request is scoped to that team.

The plaintext secret is displayed once. Store it in a server-side secret manager and never expose it in browser or mobile application code.

```http
Authorization: Bearer maildun_live_...
Accept: application/json
Content-Type: application/json
```

The base path is `/api/v1`; the [automation trigger](#trigger-an-automation) uses its own path and token. Invalid or revoked keys return `401`. Requests are limited to 120 requests per minute per key, with an additional pre-authentication limit to slow key guessing.

## Send a transactional email

```http
POST /api/v1/transactional-emails/{slug}/send
```

The slug must identify a published transactional email in the API key's team. Values in `data` replace the template's `{{ key }}` variables. Values inserted into HTML are escaped.

`web_view_url` and `unsubscribe_url` are filled by Maildun and override any value sent in `data`. `unsubscribe_url` links the recipient out of the email's Unsubscribe audience, and is empty when no audience is set or the recipient is not subscribed to it.

```json
{
  "to": "ada@example.com",
  "data": {
    "first_name": "Ada",
    "order_number": "1001"
  }
}
```

The request returns `202 Accepted` after the delivery is queued:

```json
{
  "data": {
    "id": "e7a9a935-2651-4d97-a815-05de77825dbf",
    "status": "queued",
    "transactional_email": "order-receipt",
    "to": "ada@example.com",
    "created_at": "2026-08-23T03:00:00.000000Z"
  },
  "meta": {
    "idempotent_replay": false
  }
}
```

### Idempotency

Send an `Idempotency-Key` header when retrying a logical send:

```http
Idempotency-Key: order-1001-receipt
```

Repeating the same request with the same key returns the original delivery and does not queue another email. Reusing the key with a different recipient, template, or data returns `409 Conflict`.

## Check a delivery

```http
GET /api/v1/transactional-deliveries/{id}
```

Use the `id` returned by a send to see where that message stands. Only deliveries from the API key's team are visible, and the rendered body is never returned. An unknown id returns `404` with the code `delivery_not_found`.

```json
{
  "data": {
    "id": "e7a9a935-2651-4d97-a815-05de77825dbf",
    "status": "delivered",
    "transactional_email": "order-receipt",
    "to": "ada@example.com",
    "failure_reason": null,
    "created_at": "2026-08-23T03:00:00.000000Z",
    "sent_at": "2026-08-23T03:00:02.000000Z"
  }
}
```

`status` moves from `queued` through `sending` to `sent`. With Amazon SES feedback connected it then becomes `delivered`, `delayed`, `bounced`, `complained`, or `rejected`. A message to an address the workspace has suppressed goes straight from `queued` to `rejected` without being sent. `failed` means Maildun could not hand the message to the provider. `failure_reason` says why a message was `failed` or `rejected`.

## Subscribe an address

```http
POST /api/v1/audiences/{audience_uuid}/subscribers
```

```json
{
  "email": "ada@example.com",
  "first_name": "Ada",
  "last_name": "Lovelace",
  "language": "en",
  "consent_text": "Signed up during checkout",
  "consent_ip": "203.0.113.24",
  "attributes": {
    "company": "Analytical Engines"
  }
}
```

The operation is idempotent. It creates a subscriber or updates an already subscribed address. Configured required audience attributes are required by the API as well. `language` is optional and must be one of the languages the audience allows.

An address that unsubscribed stays unsubscribed. The request records a resubscription request instead: the response has `"status": "unsubscribed"` and `"pending_confirmation": true`. On a double opt-in audience Maildun emails the audience's confirmation link, and the address is resubscribed only when the recipient clicks it. A single opt-in audience has no confirmation email, so a team member must resubscribe the address in Maildun.

`consent_ip` is the IP address of the person who gave consent, kept on their consent record. Send it when your server subscribes someone on their behalf; when it is omitted, Maildun records the IP address of the API request instead.

```json
{
  "data": {
    "id": "ff55fd14-4b87-4923-b933-4912e14566f4",
    "email": "ada@example.com",
    "first_name": "Ada",
    "last_name": "Lovelace",
    "language": "en",
    "status": "subscribed",
    "pending_confirmation": false,
    "subscribed_at": "2026-08-23T03:00:00.000000Z"
  }
}
```

## Unsubscribe an address

```http
DELETE /api/v1/audiences/{audience_uuid}/subscribers
```

```json
{
  "email": "ada@example.com"
}
```

Unsubscribe is idempotent. Repeating the request, or requesting an address that does not exist in that audience, returns a successful response without disclosing prior membership.

```json
{
  "data": {
    "id": "ff55fd14-4b87-4923-b933-4912e14566f4",
    "email": "ada@example.com",
    "status": "unsubscribed",
    "unsubscribed_at": "2026-08-23T03:05:00.000000Z"
  }
}
```

## Trigger an automation

```http
POST /api/automations/{automation_uuid}/trigger
```

This endpoint is outside `/api/v1` and does not accept a team API key. It only accepts active automations that use the API trigger, and authenticates with that automation's secret token. Copy the token from the **API trigger** panel in the automation editor and send it in the `X-Automation-Token` header (preferred) or as a bearer token:

```http
X-Automation-Token: <automation token>
Accept: application/json
Content-Type: application/json
```

The subscriber must already exist in the automation's team. Include `audience` when the email belongs to more than one audience in that team. The optional `data` object is available to later automation steps.

```json
{
  "email": "ada@example.com",
  "audience": "a1946f2e-74a0-4c2e-9186-819b10ddd90f",
  "data": {
    "plan": "pro",
    "source": "checkout"
  }
}
```

The request returns `202 Accepted` after the run is started:

```json
{
  "run": "db39d403-0375-495b-bf62-c2f79ad0c081",
  "status": "pending",
  "automation": "f9aef07f-0e7b-462d-8afd-b5caed677019",
  "subscriber": "ff55fd14-4b87-4923-b933-4912e14566f4"
}
```

An automation that does not exist, is inactive, or does not use the API trigger returns `404`. A missing or invalid token returns `401`. An unknown subscriber, or an email that matches several audiences without `audience`, returns `422`. A subscriber who already has an open run for the automation returns `409 Conflict`. Requests are limited to 60 per minute per token and 120 per minute per IP address for each automation.

## Errors

Validation failures return `422` using Laravel's standard JSON error shape. Resources outside the API key's team, unpublished transactional templates, and unknown identifiers return `404`.

```json
{
  "message": "The to field must be a valid email address.",
  "errors": {
    "to": [
      "The to field must be a valid email address."
    ]
  }
}
```
