---
paths:
  - 'app/Actions/Teams/**,app/Http/Controllers/Settings/ProfileController.php'
---

# Controllers Settings

## Account deletion hands over the teams it owns
ProfileController::destroy runs ReleaseUserTeams inside a transaction before deleting the user. Memberships must be removed through the model, never left to the team_members cascade, or Membership::deleted never fires and the user's Spatie role rows survive. An owner's shared team is handed to its longest standing admin, else its longest standing member; with nobody left (always true of a personal team) the team is soft deleted. The successor becomes Owner and so gains DeleteTeam through the owner role; without a handover an ownerless team could never be deleted.

## Only the owner deletes a workspace by default
Admins hold every permission except DeleteTeam: they manage members (UpdateMember, RemoveMember, CreateInvitation, CancelInvitation) and the workspace settings, but TeamRole::Admin and the stored admin roles (revoked by 2026_11_08_101457_revoke_team_delete_permission_from_admin_roles) do not include DeleteTeam. TeamPolicy::delete still checks the permission, so an owner can deliberately grant it to a role from the Roles page.
