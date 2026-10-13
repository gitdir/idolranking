# SQL deployment

SQL files are the source of truth for database deployment. Laravel migrations and Eloquent are not used for schema management.

## Fresh installation

`deploy/mariadb11_schema.sql` is the authoritative fresh-install schema and production baseline. It contains the current 24 application tables and `schema_patch_history` (25 total), with no application records. Run it first, then run `deploy/mariadb11_seed.sql` for essential Heart reward configuration:

```sh
mariadb --default-character-set=utf8mb4 -u DB_USER -p DB_NAME < sql/deploy/mariadb11_schema.sql
mariadb --default-character-set=utf8mb4 -u DB_USER -p DB_NAME < sql/deploy/mariadb11_seed.sql
```

Use an empty database. Create the database and a least-privilege database account through the hosting administrator's secure process. Enter passwords interactively; keep credentials outside the repository and environment files protected.

The seed preserves the `watch_ads` reward configuration (50 Hearts, daily limit 10) but sets `is_enabled = 0`. Keep it unavailable until rewarded-ad delivery is implemented and verified.

## Existing databases and future patches

Treat `deploy/mariadb11_schema.sql` as the baseline for future production patches. For an existing database, first verify its schema against that baseline and back it up. Apply only new, unapplied forward patches needed after the baseline. Do not replay the legacy schema or historical patches after a fresh installation; those files describe earlier development changes and can duplicate objects already in the deployment snapshot. If an older database differs from the baseline, prepare a reviewed forward patch for its specific differences instead of replaying the historical patch set.

Name new patches `YYYYMMDD_NNN_short_description.sql`, for example `20261014_001_add_member_status_index.sql`. Use the authoring date, increment `NNN` for multiple patches on that date, and keep each patch focused and immutable after release. Write MariaDB 11.8 SQL and keep it compatible with local MySQL 8.4 where practical.

`schema_patch_history` is an empty patch ledger in a fresh installation. It tracks only patches applied after the deployment baseline; do not populate it with or replay the historical patch list. Before applying a new patch, check whether its exact filename is already recorded. Apply one patch at a time, inspect the result, then insert its filename and optional operator/notes. Never record a patch before it succeeds. MariaDB DDL may commit implicitly, so inspect and reconcile a partially applied patch before retrying. The history table does not execute, validate, or roll back patches.

For an existing database that matches the baseline but lacks `schema_patch_history`, back it up, apply `patches/20261013_001_create_schema_patch_history.sql` once, then record that bootstrap patch. Do not run this bootstrap patch on a fresh installation; the table is already in the deployment schema. This records only creation of the ledger and does not mean earlier historical patches should be replayed or entered.

After each production schema change, update the canonical fresh-install snapshot so new installations match the resulting baseline. Preserve the existing legacy files; use them for historical reference only. In particular, `schema/partner_banners.sql` is a legacy/reference definition, not an additional installation step. Its table definitions are already part of the authoritative deployment snapshot.

Example record after a future patch succeeds:

```sql
INSERT INTO schema_patch_history (patch_name, applied_by, notes)
VALUES ('20261014_001_add_member_status_index.sql', 'deployment-operator', 'Applied during release 2026-10-14');
```

## Backups

Take a consistent database backup before every production patch or risky database maintenance. Store it in approved encrypted, access-controlled storage outside this repository, and periodically verify restore procedures. Example:

```sh
mariadb-dump --single-transaction --routines --triggers -u DB_USER -p DB_NAME > idolranking-YYYYMMDD-HHMMSS.sql
```

Never put a database dump, production data, credentials, or `.env` file in this repository or commit them. Protect backups as production data and follow the applicable retention policy.

## Compatibility notes

- The deployment snapshot uses InnoDB and `utf8mb4_unicode_ci`, supported by both MySQL 8.4 and MariaDB 11.8. MySQL 8.4's `utf8mb4_0900_ai_ci` is not available in MariaDB 11.8; the selected collation has different Unicode sorting weights.
- The inspected current schema has no JSON columns. MariaDB `JSON` is an alias based on `LONGTEXT`, unlike MySQL's native binary JSON. If JSON is introduced, define and test validation and indexing behavior for both engines.
- The snapshot uses common InnoDB features including unsigned integer types, microsecond timestamps, foreign keys, and ordinary indexes. Validate future patches against both supported database engines.

## SQL files and Git

Only the approved SQL sources and this documentation are allowlisted in the root `.gitignore`. Add an explicit allowlist entry for every new schema, seed, or patch file. Keep dumps and credentials out of all allowlisted paths.
