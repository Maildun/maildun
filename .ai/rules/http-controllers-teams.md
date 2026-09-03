---
paths:
  - app/Http/Controllers/Teams/TeamMemberController.php
---

# Http Controllers Teams

## Member credential actions protect team ownership
Password generation and reset-link actions reuse updateMember authorization, resolve the target through the selected team's membership, and reject owner targets. Keep both routes behind recent password confirmation and throttling; generated plaintext passwords may only be delivered once through Inertia flash after rotating the remember token and deleting reset tokens.
