---
paths:
  - app/Services/TeamMailer.php
---

# Services

## Sanitize transport failures at the mail boundary
Catch Symfony TransportExceptionInterface inside TeamMailer and replace it with App\Exceptions\EmailTransportException without retaining the original as `previous`. Raw SMTP messages may contain usernames or Postmark tokens and must never reach logs or persisted failure reasons.

## Custom SMTP must resolve only to public addresses
Before opening a custom SMTP transport, resolve all A/AAAA answers through PublicSmtpHostGuard. Reject unresolved hosts or any private, loopback, link-local, reserved, multicast, or transition-range answer with the credential-safe EmailTransportException; never bypass this in queued sends.

## Pin custom SMTP sockets after DNS validation
The guard's validated address is the SocketStream host; keep the configured hostname only as ssl.peer_name and SNI_server_name. Do not reconnect by hostname after validation, because that reintroduces a DNS-rebinding TOCTOU. SystemDnsResolver follows CNAMEs with loop detection and a bounded depth.
