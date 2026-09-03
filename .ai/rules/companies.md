---
paths:
  - resources/js/pages/companies/show.tsx
---

# Companies

## Company contact table mirrors contact list columns
Inside a company, the contacts table uses Contact, Tags, Audiences, and Created columns. Do not add a redundant Company column or separate Email column; ContactHoverCard already renders name and email together. Show up to three tags plus a remainder badge, matching the main contacts list.
