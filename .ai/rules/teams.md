---
paths:
  - 'app/Models/Team.php app/Http/Controllers/Teams/**'
---

# Teams

## Team logo mirrors User avatar pattern
Teams have a `logo_path` column + `logo` accessor (appended), stored on the `public` disk under `team-logos/`, mirroring `User::avatar_path`/`avatar`. Upload/replace is handled in `TeamController::update` the same way `ProfileController::update` handles avatars: capture the old path inside the DB transaction, save, then delete the old file only after the transaction commits. `UserTeam` DTO and `HasTeams::toUserTeam()` both carry `logo` through to the frontend `Team` type.

## Default team logos use DiceBear loops
When logo_path is empty, Team::logo returns a signed local URL for the DiceBear loops style using the team UUID as its seed (fallback seed maildun-team). Uploaded files still win. Teams use the loops style while User::avatar keeps its existing style.
