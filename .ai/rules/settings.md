---
paths:
  - 'app/Http/Controllers/Teams/TeamController.php,routes/settings.php,resources/js/pages/teams/**,resources/js/layouts/settings/layout.tsx'
  - 'app/Http/Controllers/Teams/TeamMemberController.php,resources/js/pages/teams/**,resources/js/layouts/settings/layout.tsx,routes/settings.php'
---

# Settings

## Teams settings is the current team editor
GET settings/teams redirects to the current team's edit page. The settings sidebar Teams item links to teams.edit for currentTeam. There is no teams/index list; leave/delete redirect back to the remaining current team's editor.

## Team members live on a dedicated settings page
Members and pending invitations are at settings/teams/{team}/members (page teams/members), not on teams/edit. The settings sidebar Members item links there. Invite/role/remove/cancel redirect back to teams.members.index.
