---
paths:
  - 'app/Http/Controllers/Teams/TeamController.php,app/Actions/Teams/ResolveWorkspaceSwitchDestination.php,resources/js/components/team-switcher.tsx'
---

# Actions Teams Js Components

## Workspace switch falls back to the dashboard
Switching workspaces keeps the current page only when the equivalent URL exists in the destination workspace (list pages, account settings, rewritten workspace settings). If the page is a record that does not exist there, TeamController::switch redirects to that workspace dashboard. Do not rewrite the URL in TeamSwitcher after switch — that visit 404s and shows Inertia's error dialog.
