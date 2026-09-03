---
paths:
  - 'app/Models/Membership.php app/Concerns/HasTeams.php app/Http/Middleware/EnsureTeamMembership.php'
---

# Middleware

## Mirror existing team memberships into Spatie team roles
Keep team_members as the existing membership and UI source of truth. Membership model events synchronize each membership role to Spatie's team-scoped roles, and EnsureTeamMembership sets the active Spatie team before authorization checks. Clear cached user roles/permissions when switching team context.
