---
paths:
  - 'resources/js/pages/companies/show.tsx,app/Http/Controllers/CompanyController.php'
---

# Companies Http Controllers

## Company contact table shows audience names
The Audiences column on a company contact table shows comma-separated audience names, not the numeric membership count. Constrain the cell and use truncate with the full list in title; show an em dash when there are no memberships. Eager-load subscribers.audience in CompanyController::show to avoid per-row queries; the hover card may still use audiences_count.

## Company contacts use a bare filterable table
On the company detail page, render Contacts as a plain section with the shared FilterMenu, ListSearch, and ActiveFilters toolbar; do not wrap the contacts table in a Card. Filter by audience only because company is already fixed by the page, preserve search/audience in pagination, and use the shared Empty states for no data or no matches.
