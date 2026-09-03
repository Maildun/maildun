---
paths:
  - '{app/Mcp/**,routes/ai.php,.mcp.json}'
---

# Mcp

## Keep the Maildun MCP server local by default
Register application MCP primitives on App\Mcp\Servers\MaildunServer and expose it with the local `maildun` handle. Do not add an HTTP MCP route until it has an explicit authentication and tenant-authorization design.
