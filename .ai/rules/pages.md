---
paths:
  - 'resources/js/components/active-filters.tsx,resources/js/pages/**/*.tsx'
---

# Pages

## Active filters use the shared ActiveFilters component
List pages that support search/filter chips use resources/js/components/active-filters.tsx (ActiveFilter + ActiveFilters). Do not hand-roll field-is-value chips. Pass field, value, and onClear per item — chips have no field icon (only Cancel01Icon on dismiss). ActiveFilters hides itself when the list is empty. Chip surface is bg-muted.
