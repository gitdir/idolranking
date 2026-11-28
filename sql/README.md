# SQL deployment

SQL files are the source of truth for database deployment. Laravel migrations and Eloquent are not used for schema management.

## Fresh installation

`deploy/mariadb11_schema.sql` is the authoritative fresh-install schema snapshot and production baseline. It currently contains 44 application tables plus `schema_patch_history` (45 tables total), 71 named foreign-key constraints, and no application records. Run it first, then run `deploy/mariadb11_seed.sql` for essential Heart reward configuration:

```sh
mariadb --default-character-set=utf8mb4 -u DB_USER -p DB_NAME < sql/deploy/mariadb11_schema.sql
mariadb --default-character-set=utf8mb4 -u DB_USER -p DB_NAME < sql/deploy/mariadb11_seed.sql
```

Use an empty database. Create the database and a least-privilege database account through the hosting administrator's secure process. Enter passwords interactively; keep credentials outside the repository and environment files protected.

The seed preserves the `watch_ads` reward configuration (50 Hearts, daily limit 10) but sets `is_enabled = 0`. Keep it unavailable until rewarded-ad delivery is implemented and verified.

**Reconciled initial release state:** the local database and the latest external release dump match the canonical schema: 37 tables, 58 foreign keys, `schema_patch_history` present, and `fan_post_monthly_views` absent. The unused legacy table was confirmed empty and removed locally after explicit approval; patch `20261018_001_drop_unused_fan_post_monthly_views.sql` documents the guarded operation for databases that still have it. The external dump is sensitive and is never stored in Git.

## Existing databases and future patches

Patch `20261128_003_create_community_scraping.sql` adds soft-deleted community sources, persisted Seoul-day collection schedules, and permanent import history. Apply it before enabling the administrator's scraping controls. The global and source switches default to OFF; no source is seeded.

Patch `20261128_004_add_scrape_collection_method.sql` adds the per-source `http` or `browser` collection method. Existing sources remain `http`. Apply the patch before selecting Browser in the administrator UI.

Patch `20261128_002_create_weekly_dummy_member_creations.sql` adds a weekly creation ledger with a unique Seoul Monday date. Apply it before enabling weekly dummy creation in administrator settings. The member and ledger row are created in one transaction; existing member records are unchanged.

Treat `deploy/mariadb11_schema.sql` as the baseline for future production patches. For an existing database, first verify its schema against that baseline and back it up. Apply only new, unapplied forward patches needed after the baseline. Do not replay the legacy schema or historical patches after a fresh installation; those files describe earlier development changes and can duplicate objects already in the deployment snapshot. If an older database differs from the baseline, prepare a reviewed forward patch for its specific differences instead of replaying the historical patch set.

Name new patches `YYYYMMDD_NNN_short_description.sql`, for example `20261014_001_add_member_status_index.sql`. Use the authoring date, increment `NNN` for multiple patches on that date, and keep each patch focused and immutable after release. Write MariaDB 11.8 SQL and keep it compatible with local MySQL 8.4 where practical.

`schema_patch_history` is an empty patch ledger in a fresh installation. It tracks only patches applied after the deployment baseline; do not populate it with or replay the historical patch list. Before applying a new patch, check whether its exact filename is already recorded. Apply one patch at a time, inspect the result, then insert its filename and optional operator/notes. Never record a patch before it succeeds. MariaDB DDL may commit implicitly, so inspect and reconcile a partially applied patch before retrying. The history table does not execute, validate, or roll back patches.

For an existing database that matches the baseline but lacks `schema_patch_history`, back it up and apply `patches/20261013_001_create_schema_patch_history.sql` through the dedicated bootstrap path in `database-operation.ps1`. That path permits this exact patch only when the ledger table is absent, then records the bootstrap after the CREATE succeeds. Do not run this bootstrap patch on a fresh installation; the table is already in the deployment schema. This records only creation of the ledger and does not mean earlier historical patches should be replayed or entered. Other patches fail closed while the ledger is absent.

Patch `20261018_001_drop_unused_fan_post_monthly_views.sql` removes the legacy `fan_post_monthly_views` table, which is not referenced by application code. It is destructive: immediately before applying it, verify the table exists and `SELECT COUNT(*) FROM fan_post_monthly_views` returns zero. The supported database-operation runner enforces both checks before entering maintenance mode; stop if the table contains any rows. The table's FK is removed with the table. The canonical fresh-install snapshot already omits this legacy table and includes `schema_patch_history`, so after this patch the database inventory should match 37 tables and 58 FKs.

Patch `20261014_001_add_fan_post_language_code.sql` applies only to an existing database at the deployment baseline. It adds nullable `fan_posts.language_code`; existing rows remain `NULL` because their original language is unknown. Record this patch in `schema_patch_history` after it succeeds. Fresh installations already include the column in `deploy/mariadb11_schema.sql` and must not run the patch separately.

Patch `20261014_002_create_fan_post_translations.sql` creates `fan_post_translations` for existing databases. If applying both October 14 patches to a database at the earlier baseline, apply `_001` before `_002`; fresh installations already include both schema changes and must not replay them. The table stores one cached translation per post and target language. A cached row is valid only while its `source_hash` matches the SHA-256 hash of the current original title and content; invalidate or replace cached translations when either source text changes. Do not create a translation for the post's original language.

Use the same source hash algorithm when generating or validating a cache: SHA-256 of the exact original title, one line-feed byte (`\n`), then the original content (treat SQL `NULL` content as an empty string). Do not trim or normalize the source text before hashing.

Patch `20261015_001_add_fan_post_comment_translations.sql` applies to an existing database at the deployment baseline. It adds nullable `fan_post_comments.language_code` and creates `fan_post_comment_translations`; existing comments remain `NULL` because their source languages are unknown. New comments record the selected source language, defaulting to the current interface locale when omitted. Do not infer a language from a member's country or label an unknown source language.

Comment translations are stored separately from the original comment, one cache row per comment and target language. Original comment text is never changed. A cache entry is valid only while its `source_hash` matches SHA-256 of the exact original comment text; do not trim or normalize it. Editing the comment makes prior cache entries stale, so they must be invalidated or replaced before being served. Do not create a translation for a known original language. Comment deletion is currently a soft delete, so the parent row remains and its cache rows are not orphaned. The restrictive foreign key prevents hard deletion while cache rows remain; any future permanent deletion must remove translations in the same transaction before deleting the comment.

Patch `20261016_001_create_daily_check_ins.sql` adds the text-only `daily_check_ins` table and nullable `min_heart_amount` / `max_heart_amount` settings. Existing fixed reward actions continue to use `heart_amount`; the patch initializes the Daily Check-in range to its current `heart_amount` without changing existing Heart history. Fresh installs include the range columns and seed the current Daily Check-in reward as an equal min/max range. Configure a wider range explicitly when enabling random rewards. The table's unique `(member_idx, check_in_date)` key enforces one row per member per UTC calendar day, and `check_in_date` is not backfilled from Heart history. Check-in messages and `awarded_hearts` are independent public records; `language_code` may remain `NULL` when the source language is unknown. Both member foreign keys are restrictive, so permanent member deletion requires handling check-in records under the application's retention policy.

Patch `20261016_002_create_daily_check_in_translations.sql` creates the separate translation cache for existing databases and must be applied after `_001`. Fresh installations already include the table in the deployment schema. Original check-in messages remain unchanged. Each cached translation is keyed by check-in, source language, and target language; it is served only when `source_hash` matches SHA-256 of the exact original message. Unknown source languages are not guessed or translated. The restrictive foreign key means permanent check-in deletion must remove its translation rows in the same transaction first.

Patch `20261016_003_add_member_heart_history_order_index.sql` adds an index for a member's newest-first Heart History query. Apply it once to existing databases at the deployment baseline and record it in `schema_patch_history`. Fresh installations already include the index in the deployment schema.

Patch `20261016_004_create_fan_post_comment_reports.sql` creates `fan_post_comment_reports` for existing databases at the deployment baseline. It allows one report per comment and reporting member, records the effective activity member separately from the authenticated actor, and uses the existing `pending`, `reviewing`, `reviewed`, and `rejected` statuses. Apply it once and record it in `schema_patch_history`. Fresh installations already include the table in the deployment schema. Comments are soft-deleted, so their report records remain attached; restrictive foreign keys prevent orphaning if hard deletion is introduced.

Patch `20261017_001_add_idol_social_links.sql` adds nullable `sns_links` text columns to `idols` and `idol_registration_requests`. New values are JSON arrays containing zero to three HTTP/HTTPS URLs; the existing `sns` column remains intact for legacy single URLs or handles. Fresh installations include both columns in the deployment schema. Apply this patch once to an existing baseline database and record it in `schema_patch_history`.

Patch `20261017_002_add_idol_group_genre_leader.sql` adds nullable `genre` and `leader` text fields to `idols` and `idol_registration_requests`. They preserve optional group registration details; leader is free text and is not synchronized to an individual idol record or relationship. Individual idol rows and requests leave both fields `NULL`. Fresh installations already include the columns in the deployment schema.

Patch `20261017_003_add_idol_multilingual_localizations.sql` creates `idol_localizations`, `idol_name_aliases`, and `idol_registration_request_localizations`. It leaves all existing idol and request columns and data unchanged. Future request handling must keep `submitted` localization rows immutable and save administrator edits as `reviewed` rows. Do not backfill legacy names or descriptions whose language is unknown; assign a language only when verified. The database does not enforce `text_role` values, so application validation must restrict them to `submitted` and `reviewed`. Fresh installations already include these tables in the deployment schema.

Patch `20261017_004_add_idol_profile_localized_fields.sql` adds nullable `agency`, `position`, `genre`, and `leader` fields to both approved idol localizations and request localizations. Request-side fields allow immutable `submitted` rows to retain the language originally used and `reviewed` rows to hold administrator edits. It also adds nullable two-character ASCII `nationality_code` columns to `idols` and `idol_registration_requests`. Fresh installations already include these fields in the deployment schema.

Patch `20261017_005_drop_legacy_nationality_columns.sql` removes the obsolete free-text `nationality` columns from `idols` and `idol_registration_requests`. Existing values in those columns are discarded; the patch leaves all `nationality_code` values unchanged. Apply it once, after patch `20261017_004_add_idol_profile_localized_fields.sql`, to an existing database with both nationality columns, then record it in `schema_patch_history`. Fresh installations use the updated schema and must not run this patch separately.

Patch `20261017_006_add_country_specific_idol_debuts.sql` replaces the single `debut_date` columns with `idol_debuts` and `idol_registration_request_debuts`, each uniquely keyed by entity and ISO alpha-2 country. It also drops `nationality_code` from `idols` and `idol_registration_requests`. Existing single debut dates are not backfilled because they have no reliable country attribution; those values and nationality codes are removed. The free-text nationality columns were removed earlier by patch `20261017_005`. Apply this patch after `20261017_005`, then record it in `schema_patch_history`. Fresh installations already use the new tables and must not run the patch separately.

Patch `20261017_007_create_idol_profile_image_reports.sql` creates the rights-report queue for individual idol and group profile images. It allows one report per authenticated actor, entity, and image path, and records the latest administrator and moderation time. When a managed public image is removed, its file is moved to private evidence storage and served only through the admin-authorized evidence route. Apply this patch after `20261017_006_add_country_specific_idol_debuts.sql`, then record it in `schema_patch_history`. Fresh installations already include the table in the MySQL and MariaDB schema snapshots.

Patch `20261017_008_create_admin_dummy_activity_requests.sql` stores the administrator, randomly selected dummy, action token, payload fingerprint, and completed result for random dummy activity. It prevents retries from selecting a different dummy or repeating an activity. Apply it after `20261017_007_create_idol_profile_image_reports.sql`, then record it in `schema_patch_history`. Fresh installations already include the table in both schema snapshots.

After each production schema change, update the canonical fresh-install snapshot so new installations match the resulting baseline. Preserve the existing legacy files; use them for historical reference only. In particular, `schema/partner_banners.sql` is a legacy/reference definition, not an additional installation step. Its table definitions are already part of the authoritative deployment snapshot.

Example record after a future patch succeeds:

```sql
INSERT INTO schema_patch_history (patch_name, applied_by, notes)
VALUES ('20261014_001_add_member_status_index.sql', 'deployment-operator', 'Applied during release 2026-10-14');
```

Patch `20261025_001_create_member_quizzes.sql` creates the quiz, ordered question/choice, daily attempt, and immutable submitted-answer tables, then adds the `quiz_perfect` range reward setting if missing. Apply it once to an existing database that matches the deployment baseline, after a consistent backup. Inspect all five tables and the reward row, then record the exact patch filename in `schema_patch_history` only after success. Do not run this patch on a fresh database because `deploy/mariadb11_schema.sql` and `deploy/mariadb11_seed.sql` already include the quiz schema and initial 10–30 Heart reward configuration. The unique quiz/member/service-date key enforces one attempt per quiz per member per `app.timezone` date. The existing unique `(member_idx, operation_key)` Heart History key enforces one perfect-score reward per attempt (`quiz_perfect:{attempt_idx}`). No production database changes were executed as part of implementation.

Patch `20261025_002_add_quiz_creation_reward.sql` adds the `quiz_create` row to the existing `heart_reward_settings` table when it is missing (100 Hearts, 20 rewarded creations per UTC day, enabled). Apply it after `20261025_001_create_member_quizzes.sql` on existing installations, then record the exact patch filename in `schema_patch_history`. The patch does not alter existing reward rows; fresh-install schema and seed snapshots include the default row.

Patch `20261025_003_add_quiz_idol_and_cover.sql` adds nullable `quizzes.idol_idx` and `quizzes.cover_image_path` fields, an idol lookup index, and a restrictive foreign key for existing installations. Nullability preserves quizzes already present before the relationship was introduced. New quiz submissions require an active idol/group and validate that its type and gender match the submitted category. Apply after the quiz creation patches on an existing installation, then record the exact patch filename in `schema_patch_history`. Fresh installations include both columns and the foreign key in `deploy/mariadb11_schema.sql`. No existing quiz or uploaded image data is altered.

Patch `20261025_004_add_quiz_question_versions.sql` adds a current version to each quiz, a version to its question rows, and the version captured by each participation attempt. It replaces the question-order unique key with `(quiz_idx, quiz_version, question_order)`, retaining the existing question and choice rows so historical and in-progress attempts keep their original answer keys and result text. Existing records are assigned version `1` by the non-null defaults. Apply after the quiz creation patches on an existing installation and record its filename in `schema_patch_history`; fresh installations include these columns and the versioned unique key in `deploy/mariadb11_schema.sql`. The patch does not delete or rewrite quiz, attempt, answer, or Heart history records.

Patch `20261025_005_allow_quiz_creator_retries.sql` removes the once-per-day unique constraint from quiz attempts and adds a non-unique lookup index, allowing creators to keep test attempts while ordinary-member daily eligibility remains enforced by server-side checks. It preserves all existing attempts. Apply after `20261025_004_add_quiz_question_versions.sql`; fresh installations already use the non-unique index.

Patch `20261025_006_add_quiz_title.sql` adds the required `quizzes.title` field and backfills existing quizzes from the first question prompt in their current version (truncated only if needed to fit 255 characters; question rows are unchanged). Apply once after checking `schema_patch_history`, then record the exact patch filename after success. Fresh installations include the title in `deploy/mariadb11_schema.sql` and `schema/quizzes.sql`.

Patch `20261025_007_update_quiz_creator_reward_policy.sql` disables the obsolete quiz-creation reward setting and adds configurable creator rewards for first completion by another member: 3, 5, or 10 Hearts for quizzes with one, two, or three questions. It does not delete or change existing `quiz_create_reward` history. Apply once after checking `schema_patch_history`, then record the patch filename after success. Fresh installs seed the three creator-reward settings and omit the obsolete creation-reward setting.

Patch `20261025_008_add_quiz_creation_daily_limit.sql` creates persistent quiz-creation history used to enforce a limit of five quizzes per member per Asia/Seoul day. It backfills existing quizzes and recoverable legacy creation-reward records. The history deliberately has no quiz foreign key, so deleting a quiz does not restore its creation quota. Quiz creation writes the quiz and its history row in one transaction; fresh installs include this table in the schema snapshot.

Patch `20261025_009_weekly_quiz_participation_ranking.sql` marks quiz creator-owned attempts as test-only (including existing creator attempts), adds an index for completed-attempt week scans, creates idempotent weekly settlement and occupied-rank audit tables, and seeds the 1,000 / 500 / 100 Heart ranking rewards. Its initial settlement-reward table permits one row per member and rank; patch `20261025_011` then changes this to rank-based audit rows associated with the ranked quiz, allowing one creator to own multiple TOP 3 quizzes. Attempts remain intact. Apply once after checking the patch ledger; fresh installations include the final column, tables, and reward defaults in the schema and seed snapshots. `quizzes:settle-weekly-ranking` settles the previous Asia/Seoul Monday-to-Monday week and is scheduled every Monday at 00:05 Asia/Seoul. The unique week, rank, and operation keys plus a locked settlement header prevent repeated rewards.

Patch `20261025_010_centralize_quiz_heart_reward_settings.sql` removes only the obsolete `quiz_create` configuration row after the replacement creator-completion reward settings have been introduced by patch `20261025_007`. It does not delete Heart history, change member balances, or revoke any prior reward. Apply it in numeric order after patch `20261025_009` and before patch `20261025_011`; fresh installations omit the obsolete setting and must not run this patch separately.

Patch `20261025_011_rank_weekly_quiz_creator_rewards.sql` adds the nullable ranked `quiz_idx`, its index and `ON DELETE SET NULL` foreign key, and removes the per-settlement member uniqueness constraint. Existing settlement rows keep their member, rank, counts, amounts, and Heart history; their new `quiz_idx` remains `NULL`. Apply after `20261025_010_centralize_quiz_heart_reward_settings.sql`; fresh installations already include the final definition.

Patch `20261025_012_add_quiz_soft_deletion.sql` adds `quizzes.deleted_at` and an index for public quiz visibility. Deletion only sets this timestamp; questions, attempts, Likes, Heart histories, and settlement audit rows remain intact. Apply once after checking `schema_patch_history`; fresh installations include the column and index in both Quiz schema snapshots.

Patch `20261025_013_create_quiz_likes.sql` creates the quiz-specific member Like table with one row per `(quiz_idx, member_idx)` and an optional actor reference for Admin Dummy Mode audit. It does not add Heart rewards or ranking inputs. Apply once after checking `schema_patch_history`; fresh installations include the table in both Quiz schema snapshots.

## Backups

Before a production patch or risky database maintenance, save a consistent dump in the approved local directory `D:\backup\idolranking`, outside the repository. Use the existing pinned SSH connection and loopback-only tunnel when the production database is reachable only from the production host. The MySQL 8.4 client options used for MariaDB are `--single-transaction --quick --routines --triggers --events --hex-blob --column-statistics=0 --no-tablespaces`. Do not connect the local client directly to a public database host.

Keep the directory ACL restricted to the current Windows account and local Administrators. For this approved deployment, the SQL dump is stored without separate file-level encryption. Keep it in that restricted directory; never copy it into the repository, a public directory, or a log. Store a sidecar manifest with the timestamp, database identifier, file size, and SHA-256 checksum, but no credentials.

Verify the checksum and restore the dump into a fresh isolated temporary MySQL instance before relying on it. Compare the restored schema/table count and representative row counts, then stop the temporary server and remove only its uniquely named temporary data directory. For production recovery, first preserve the current state and use the existing `scripts/deploy/database-operation.ps1 -Mode Replace` procedure only with a separately approved maintenance/recovery action, exact dump checksum, expected table count, and verified backup reference. Replacement can lose writes made after the backup; it never changes `.env` or uploads. Never put dumps, production data, credentials, or `.env` files in Git.

## Visitor statistics privacy

Patch `20261019_001_stop_storing_raw_visitor_ips.sql` makes `member_access_logs.ip_address` nullable. Both the legacy canonical schema and `deploy/mariadb11_schema.sql` define it as nullable, matching the patch. New public-page access rows write `NULL` rather than raw IP addresses; existing values are retained until the existing 90-day access-log cleanup expires them. The visitor aggregates continue to use random first-party visitor IDs and do not depend on IP addresses.

Patch `20261019_002_add_fan_post_distribution.sql` adds `queued` and `cancelled` Fan Post states, queue entry time, actual publication time, and an index for oldest-first queue processing. Apply it once to an existing database and record it in `schema_patch_history`; fresh installs already contain these definitions in both canonical schema snapshots. The application stores distribution settings in the existing `site_settings` table and uses defaults of 10 minutes, 2 posts, and 09:00–23:00 Asia/Seoul when no settings have been saved. The queue is published by the `fan-posts:publish-queued` Artisan command; it must be run by Laravel Scheduler every minute. No per-post publication times are precomputed, so settings changes immediately govern future queue processing without reordering queued posts.

Add the Laravel scheduler to the production user's cron once:

```cron
* * * * * cd /home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking && /opt/alt/php84/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs `fan-posts:publish-queued` each minute. Confirm the hosting account already has the standard Laravel scheduler cron before adding another copy.

## Compatibility notes

- The deployment snapshot uses InnoDB and `utf8mb4_unicode_ci`, supported by both MySQL 8.4 and MariaDB 11.8. MySQL 8.4's `utf8mb4_0900_ai_ci` is not available in MariaDB 11.8; the selected collation has different Unicode sorting weights.
- `sns_links` stores a small JSON-encoded URL array in a `TEXT` column, not a database `JSON` column. This avoids MySQL/MariaDB JSON type differences; application validation controls the array contents and the field is not indexed.
- MariaDB `JSON` is an alias based on `LONGTEXT`, unlike MySQL's native binary JSON. Avoid database-specific JSON queries for this field.
- The snapshot uses common InnoDB features including unsigned integer types, microsecond timestamps, foreign keys, and ordinary indexes. Validate future patches against both supported database engines.

## SQL files and Git

Only the approved SQL sources and this documentation are allowlisted in the root `.gitignore`. Add an explicit allowlist entry for every new schema, seed, or patch file. Keep dumps and credentials out of all allowlisted paths.
