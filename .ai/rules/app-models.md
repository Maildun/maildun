---
paths:
  - 'app/Models/{User,Team,Audience,Subscriber}.php'
---

# App Models

## Generate DiceBear fallbacks locally
User, Team, Audience, and Subscriber fallbacks must return the root-relative `avatars.show` URL. `AvatarController` renders the allowlisted Critters, Shape Grid, and Micah styles through `DiceBearAvatarGenerator`; do not use api.dicebear.com or SVG data URIs. Uploaded User and Team assets still take precedence.

## Sign local avatar URLs
Generate local avatar URLs with `URL::signedRoute(..., absolute: false)`. The `avatars.show` route uses `signed:relative` and throttling so only application-issued UUID/fallback seeds can trigger cached SVG rendering.
