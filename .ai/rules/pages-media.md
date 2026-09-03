---
paths:
  - 'resources/js/components/media-detail-dialog.tsx,resources/js/pages/media/**'
---

# Pages Media

## Media details open in a two-column dialog
Clicking a media tile opens a Dialog (not a Sheet). Layout is two columns on md+: preview + status on the left, File URL / name / alt / category / tags on the right. TagsCombobox sits in a normal modal Dialog — Base UI combobox and dialog compose natively, do not add Radix-era modal patches.
