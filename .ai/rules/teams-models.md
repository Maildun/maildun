---
paths:
  - 'app/{Services,Http/Requests/Teams,Models}/**/*.php'
---

# Teams Models

## Local SMTP loopback opt-in
Workspace SMTP targets must resolve only to public addresses by default. In the local or testing environment, MAIL_ALLOW_LOCAL_SMTP_HOSTS=true permits only localhost, 127.0.0.1, and ::1 for a local SMTP catcher; private, link-local, and other internal targets remain rejected. Keep the resolved socket pinned after validation.
