---
paths:
  - 'config/trustedproxy.php,app/Providers/AppServiceProvider.php'
---

# Providers

## Client IP comes from a trusted proxy
config/trustedproxy.php reads TRUSTED_PROXIES; Laravel's TrustProxies middleware falls back to that key on its own. Do not set it from bootstrap/app.php: the withMiddleware callback runs when the HTTP kernel resolves, before config and .env load, so env() there is null under any real deploy. Untrusted, Request::ip() is the balancer, which collapses the public-subscribe and automation-trigger rate limiters into one bucket and writes the wrong subscribers.consent_ip, the GDPR consent record. public-unsubscribe is keyed by delivery rather than IP because one-click requests share the mail provider's addresses.
