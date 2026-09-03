---
paths:
  - 'resources/js/components/paginator.tsx,resources/js/components/ui/pagination.tsx'
---

# Js Components Ui

## Shared paginator uses Base UI pagination links
All paginated table pages render the shared Paginator. Keep the shadcn PaginationPrevious, PaginationLink, PaginationEllipsis, and PaginationNext composition, and pass Inertia Link through the Base UI render prop with preserveScroll so pagination remains an SPA visit.
