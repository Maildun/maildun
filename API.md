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

## Record a conversion

```http
POST /api/v1/conversions
```

Send a completed sales outcome, such as a booked meeting, a started trial, or a purchase, from your booking, billing, or CRM system. Maildun credits it to a [campaign series](#how-conversions-are-attributed) and shows it in the series report.

```json
{
  "event_id": "booking_01J9Y8PGT2",
  "event": "meeting_booked",
  "email": "lead@example.com",
  "occurred_at": "2026-09-14T08:30:00Z",
  "value_minor": 250000,
  "currency": "USD",
  "campaign_series": "5b0e8f3c-3a5e-4f43-9d0e-6f1e2a7c9b10",
  "metadata": {
    "booking_id": "demo_1842"
  }
}
```

| Field | Required | Rules |
| --- | --- | --- |
| `event_id` | Yes | Your own ID for this event, up to 255 characters. Unique within the workspace. |
| `event` | Yes | The event key: lowercase letters, numbers, and underscores, starting with a letter, up to 64 characters. It must match the conversion event configured on a series to be credited to it. |
| `email` | Yes | The person who converted. Matched case-insensitively. |
| `occurred_at` | No | ISO 8601 time of the conversion. Defaults to when Maildun receives it. May not be more than 5 minutes in the future. |
| `value_minor` | No | A non-negative integer in the currency's minor unit, such as cents. Requires `currency`. |
| `currency` | With `value_minor` | A three-letter ISO 4217 code. Values in different currencies are reported separately, never converted. |
| `campaign_series` | No | A series UUID in your workspace. Restricts attribution to that series. |
| `metadata` | No | Up to 20 string keys with string, number, boolean, or null values, at most 4096 bytes as JSON. |

A new event returns `201 Created`:

```json
{
  "data": {
    "id": "0f9b6c2e-5d1a-4c7e-8f3b-2a9d4e6c1b70",
    "event_id": "booking_01J9Y8PGT2",
    "event": "meeting_booked",
    "email": "lead@example.com",
    "occurred_at": "2026-09-14T08:30:00.000000Z",
    "received_at": "2026-09-14T08:30:02.000000Z",
    "value_minor": 250000,
    "currency": "USD",
    "metadata": {
      "booking_id": "demo_1842"
    },
    "attribution": {
      "type": "click",
      "model": "last_touch",
      "campaign_series": "5b0e8f3c-3a5e-4f43-9d0e-6f1e2a7c9b10",
      "campaign": "9c3a7d52-1f4b-4e8a-b6d0-3e5f7a9c2b41",
      "delivery": "2d6e8a14-7b3c-4f5e-9a1d-0c8b6e4f2a37",
      "touched_at": "2026-09-12T15:04:11.000000Z"
    }
  },
  "meta": {
    "idempotent_replay": false
  }
}
```

### Retries

`event_id` makes the request idempotent within the workspace. Repeating it with identical data returns the stored event with `200 OK` and `"idempotent_replay": true`, and nothing is counted twice. Reusing it with different data returns `409 Conflict` with the code `idempotency_conflict`. If you omit `occurred_at`, retries still match, because the omitted field is compared rather than the time Maildun filled in.

### How conversions are attributed

A series takes part when its conversion event is set to the event's `event` key in the series settings. When the request names a `campaign_series`, only that series takes part, whatever its conversion event. Maildun decides the attribution once, when the event arrives, and stores it. Later changes to campaigns or series settings do not move it.

1. Only campaigns sent to the person no later than the conversion, and within the series' attribution window before it (30 days unless the series sets another), are eligible. The window boundary is inclusive.
2. **`click`**: the latest tracked link click before the conversion, across every eligible series, credits that campaign and its series. Opens never earn credit.
3. **`assisted`**: without a click, the latest eligible delivery credits its series. The response names that delivery and its campaign for audit, but the campaign gets no credit.
4. **`explicit`**: when `campaign_series` was sent and that series has no eligible touch, the event is credited to that series without a campaign.
5. **`unattributed`**: otherwise the event is stored for audit and appears in no report.

Validation failures return `422` with the code `validation_failed`. An unknown `campaign_series` and one that belongs to another workspace get the same error. Conversions are limited to 60 requests per minute per key, in addition to the general API limit.

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
