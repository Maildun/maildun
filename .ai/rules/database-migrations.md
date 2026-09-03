---
paths:
  - 'database/migrations/**'
---

# Database Migrations

## Partial index predicates take no bindings
Postgres (dev) and SQLite (phpunit) both reject bound parameters inside a partial index WHERE clause: "parameters prohibited in partial index WHERE clauses". Inline the values as quoted literals in the DB::statement string instead of passing a bindings array. See 2026_08_22_172424_add_open_subscriber_unique_to_automation_runs_table.

Drop such an index with DB::statement('drop index if exists <name>'), not $table->dropUnique(): on Postgres dropUnique compiles to "alter table drop constraint", which does not match an index created by CREATE UNIQUE INDEX.
