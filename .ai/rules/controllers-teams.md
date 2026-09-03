---
paths:
  - 'app/Http/Requests/Teams/**,app/Http/Controllers/Teams/**'
---

# Controllers Teams

## Owner is never an assignable role
Both the invite and the member-role update validate against TeamRole::assignable(), which excludes Owner. Rule::enum(TeamRole::class) on an invitation is a privilege escalation: CreateInvitation belongs to Admins, so an admin could invite an address they control as owner and inherit DeleteTeam, RemoveMember, and UpdateMember. Ownership only moves through ReleaseUserTeams when an owner deletes their account.
