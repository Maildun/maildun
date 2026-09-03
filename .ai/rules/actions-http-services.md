---
paths:
  - 'app/{Actions,Http,Services}/**/*Contact*.php'
---

# Actions Http Services

## Preserve shared contact profile during audience joins
A team owns one Contact per normalized email and audiences own Subscriber memberships. Creating or reusing an email for another audience must preserve the existing Contact name, company, and tags; only an explicit Contact or subscriber profile edit may update them. Subscriber email edits must validate against the team-level contacts unique key and reject collisions rather than merging implicitly.
