---
paths:
  - 'app/Http/Controllers/Teams/TeamMemberController.php,resources/js/pages/teams/members.tsx'
---

# Js Pages Teams

## Member and invitation lists paginate independently
The Members settings page keeps active members and pending invitations in compact tables. Paginate members by the default page parameter and invitations by invitations_page, and use withQueryString() so one list page change preserves the other.

## Member lists use independent icon-only paginators
Members and invitations use distinct page/per-page query parameters with allowed sizes 10, 25, 50, and 100. Their table footers intentionally use the compact rows-per-page Select plus only PaginationPrevious and PaginationNext, rather than the shared full Paginator; preserve the other list query state and reset only the resized list to page 1.
