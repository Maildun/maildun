# Maildun API

Maildun exposes a versioned JSON API for sending published transactional emails and managing audience subscriptions.

## Authentication

Create and revoke API keys from **Settings → API** for the relevant team. A key belongs to exactly one team, and every request is scoped to that team.

The plaintext secret is displayed once. Store it in a server-side secret manager and never expose it in browser or mobile application code.

```http
Authorization: Bearer maildun_live_...
Accept: application/json
Content-Type: application/json
```

The base path is `/api/v1`. Invalid or revoked keys return `401`. Requests are limited to 120 requests per minute per key, with an additional pre-authentication limit to slow key guessing.

## Send a transactional email

```http
POST /api/v1/transactional-emails/{slug}/send
```

The slug must identify a published transactional email in the API key's team. Values in `data` replace the template's `{{ key }}` variables. Values inserted into HTML are escaped.

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

## Subscribe an address

```http
POST /api/v1/audiences/{audience_uuid}/subscribers
```

```json
{
  "email": "ada@example.com",
  "first_name": "Ada",
  "last_name": "Lovelace",
  "consent_text": "Signed up during checkout",
  "attributes": {
    "company": "Analytical Engines"
  }
}
```

The operation is idempotent. It creates a subscriber, updates an already subscribed address, or resubscribes an unsubscribed address. Configured required audience attributes are required by the API as well.

```json
{
  "data": {
    "id": "ff55fd14-4b87-4923-b933-4912e14566f4",
    "email": "ada@example.com",
    "first_name": "Ada",
    "last_name": "Lovelace",
    "status": "subscribed",
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
