---
paths:
  - 'resources/js/pages/teams/members.tsx,resources/js/components/edit-member-modal.tsx'
---

# Teams Js Components

## Member table uses badge roles and row actions
Show a passive role Badge in the member table; do not make the role cell an inline picker. The overflow menu owns Edit and Delete: Edit opens EditMemberModal for the role, while Delete preserves confirmation and removes workspace access without deleting the user account.
