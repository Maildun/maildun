---
paths:
  - '{app/Models/TeamSender*.php,app/Models/TeamEmailIntegration.php,app/Http/Controllers/Teams/TeamSender*.php,resources/js/pages/teams/{sender,email-provider-show}.tsx,tests/Feature/*Sender*Test.php}'
---

# Pages Teams Feature

## Provider trust remains exact-sender scoped
A workspace manager may opt to trust a tested delivery provider instead of publishing Maildun's DNS TXT record. This only auto-authorizes explicitly registered sender rows whose domain exactly matches the connection's most recently tested From address; it never allows arbitrary domains or unregistered addresses. Provider-trusted proof is tied to the connection id and verification version, and disabling provider trust revokes those proofs.
