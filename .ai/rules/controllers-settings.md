---
paths:
  - 'app/Actions/Teams/**,app/Http/Controllers/Settings/ProfileController.php'
---

# Controllers Settings

## Account deletion hands over the teams it owns
ProfileController::destroy runs ReleaseUserTeams inside a transaction before deleting the user. Memberships must be removed through the model, never left to the team_members cascade, or Membership::deleted never fires and the user's Spatie role rows survive. An owner's shared team is handed to its longest standing admin, else its longest standing member; with nobody left (always true of a personal team) the team is soft deleted. Admins hold neither DeleteTeam nor the member permissions, so an ownerless team is unmanageable forever.
