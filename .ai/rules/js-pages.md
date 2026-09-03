---
paths:
  - 'resources/js/pages/**/*.tsx'
---

# Js Pages

## Status filter uses StatusIcon
FilterMenu Status fields use Hugeicons StatusIcon, not PulseIcon. Applies to campaigns and transactional; subscriber status stays as sliding tabs.

## List table names are clickable
Campaign, transactional, and automation index tables wrap the row name in an Inertia Link (font-medium underline-offset-4 hover:underline, matching audience-name-link). Campaigns go to edit while draft and to the report (show) after send; transactional and automation names always go to edit.
