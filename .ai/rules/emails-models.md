---
paths:
  - 'app/{Actions/Emails,Models}/**'
---

# Emails Models

## Enrich tracking from local MMDB without retaining raw IP
Derive country/city centroid and ASN from local DB-IP Lite MMDB files during tracking capture. Never persist the raw IP: retain only its keyed hash and derived facts. Missing or unreadable databases must fail open. Geo fields are immutable event payload; add geo aggregates and UI only after classification rules are settled, and include DB-IP attribution in any UI.
