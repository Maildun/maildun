# SMTP email delivery

SMTP is the compatibility option for users who already have a mail server or third-party relay. In the first release, Maildun treats SMTP as a **relay handoff** transport: it can confirm that the configured relay accepted the message, but it cannot confirm final delivery or automatically receive bounce and complaint events.

Use [Amazon SES](./amazon-ses.md) when confirmed Delivery, Bounce, and Complaint statuses are required.

## What you need

- A public SMTP hostname.
- Port `25`, `465`, `587`, or `2525`.
- TLS, SSL, or unencrypted transport support. TLS is recommended.
- A username and password if the relay requires authentication.
- Permission to send from the workspace sender address.

Maildun rejects SMTP hosts that resolve to private, loopback, link-local, or otherwise unsafe network addresses. TLS connections verify the server certificate against the configured hostname.

## Set up SMTP

1. In Maildun, add an SMTP provider.
2. Enter a connection name, SMTP host, port, username, password, and encryption mode.
3. Save the provider.
4. Test delivery with explicit From and To addresses. A successful test verifies only the SMTP connection.
5. Open **Settings → Sender**, add each exact From address, and complete its verification through this connection.

## Status and tracking behavior

When the SMTP relay accepts a message, Maildun records it as `Sent`. Maildun does **not mark the message as delivered**, because an accepted relay can still fail later while forwarding the message to the recipient's mail server.

If the SMTP connection or handoff fails immediately, Maildun retries the queued job according to the application queue policy and eventually records the attempt as `Failed` when retries are exhausted.

Campaign opens and clicks remain available because Maildun inserts its own tracking pixel and tracked link URLs before sending. That tracking does not depend on the SMTP provider.

The first release does not automatically process:

- delayed-delivery notifications;
- final delivery confirmations;
- bounce messages returned after handoff;
- spam complaints or abuse reports.

Do not calculate SMTP delivery rate from the `Sent` count. Label it as **Sent** or **Accepted by relay**, not **Delivered**.

## Changing providers

Each campaign attempt records the provider used for that send. Replacing the workspace provider affects new sends only and does not rewrite earlier attempts. All registered senders must be tested again for the replacement connection.

Maildun's campaign open and click URLs continue to work after a switch because they identify the Maildun delivery rather than the provider connection.

## Why generic SMTP feedback is not included yet

SMTP has standards for feedback, but they are not a universal webhook contract:

- [RFC 3461](https://www.rfc-editor.org/rfc/rfc3461.html) lets an SMTP client request Delivery Status Notifications when the relay advertises DSN support.
- [RFC 3464](https://www.rfc-editor.org/rfc/rfc3464.html) defines the structured delivery-status report format.
- [RFC 5965](https://www.rfc-editor.org/rfc/rfc5965.html) defines the Abuse Reporting Format used for complaint reports.
- [RFC 6650](https://www.rfc-editor.org/rfc/rfc6650.html) describes reporting abuse using that format.

Supporting these reliably would require Maildun to control a return-path domain, receive inbound email, parse and authenticate DSN and abuse reports, correlate them with a send attempt, and handle providers that do not emit the requested reports. A successful DSN can still describe delivery to an intermediate system rather than a human inbox, and complaint coverage varies by mailbox provider.

For an open-source installation, a future optional **SMTP feedback adapter** can provide inbound DSN/ARF ingestion or integrate a relay's provider-specific webhook. Until that module exists, Amazon SES is the recommended choice for reliable feedback.

## Current release scope

SMTP sends campaign, transactional, and automation messages through the workspace connection. First-party open and click tracking applies to campaigns; provider Delivery, Bounce, and Complaint feedback is unavailable.
