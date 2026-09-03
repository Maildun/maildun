---
paths:
  - '{app/Models/TeamSender*.php,app/Models/TeamEmailIntegration.php,app/Http/Controllers/Teams/TeamSender*.php,app/Services/SenderDomainVerifier.php,resources/js/pages/teams/sender.tsx,tests/Feature/*Sender*Test.php}'
---

# Teams Feature

## DNS sender domains remain connection-scoped
A sender domain is valid only when the current delivery connection has successfully tested a From address on that exact domain and the Maildun TXT token resolves. DNS proof authorizes only explicitly registered sender rows, stamped with the current integration id and verification_version. Normal delivery must keep rejecting unregistered addresses and stale provider or domain proofs.
