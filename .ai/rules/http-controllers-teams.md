---
paths:
  - app/Http/Controllers/Teams/TeamMemberController.php
---

# Http Controllers Teams

## Member credential actions protect team ownership
Password generation and reset-link actions reuse updateMember authorization, resolve the target through the selected team's membership, and reject owner targets. Keep both routes behind recent password confirmation and throttling; generated plaintext passwords may only be delivered once through Inertia flash after rotating the remember token and deleting reset tokens.

A password covers the whole account, not one workspace. `TeamPolicy::manageMemberPassword` therefore also refuses a target who owns any other workspace, or who belongs to another workspace where the actor lacks updateMember (including workspaces the actor is not in). A personal workspace the target uses alone is exempt, because every account has one; once anyone else joins it, it counts like any other workspace. Admins may still manage their own password. The members page shows Reset password only when the per-member `can_manage_password` flag, computed from the same policy, is true.
