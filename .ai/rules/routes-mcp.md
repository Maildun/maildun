---
paths:
  - '{routes/ai.php,app/Mcp/**}'
---

# Routes Mcp

## Keep remote MCP OAuth and tenant scoped
The local `maildun` stdio server remains trusted and unauthenticated. The `/mcp/maildun` web server must keep Passport `auth:api`, the `mcp:use` scope, and rate limiting. For authenticated MCP requests, resolve workspaces only through the user's `team_members` memberships and enforce existing model policies on every mutation; never restore global workspace discovery.
