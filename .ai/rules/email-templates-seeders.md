---
paths:
  - database/seeders/InstallSeeder.php
---

# Email Templates Seeders

## Installation does not seed an email builder template gallery
The four built-in templates in `EmailTemplate::starters()` remain seeded by their migration because `blankBodyFor()` depends on the blank builder and HTML entries. Do not add a gallery or example Email Builder designs to `InstallSeeder`; installations must not create a starter-kit template library.
