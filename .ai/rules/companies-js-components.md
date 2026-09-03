---
paths:
  - 'resources/js/pages/companies/show.tsx,resources/js/components/contact-hover-card.tsx'
---

# Companies Js Components

## Company contacts use the shared hover card
Contacts listed on a company page use ContactHoverCard with the same Profile and manager-only label Edit actions as the main contacts index. CompanyController::show must provide the full ContactSummary shape plus company and tag options so ContactDialog preserves company assignment and tags.
