---
paths:
  - '{app/Console/Commands/InstallCommand.php,app/Actions/Install/**,app/Http/Controllers/InstallationController.php,app/Http/Middleware/EnsureInstallationIsPending.php,app/Services/InstallationState.php,routes/web.php}'
---

# Middleware Services

## Protect browser installation with a deploy-signed link
Run `app:install` during deployment to prepare the schema and baseline data; when no user exists it prints a 24-hour relative signed `/install` URL. Keep both installer routes behind signed and pending-installation middleware, never accept infrastructure credentials in the browser, and persist completion in the singleton `installations` row. Existing users also close the installer to protect upgraded deployments.
