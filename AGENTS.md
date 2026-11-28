# IdolRanking - Codex Development Instructions

## 1. Project Overview

Project name: IdolRanking

### Optional browser scraping runtime

Community sources using Browser require Node.js 20+, the `playwright` npm dependency, and Chromium installed for the PHP/Scheduler account (`npx playwright install chromium` on Linux). Windows uses installed Chrome by default. Set `SCRAPER_NODE_PATH` or `SCRAPER_CHROMIUM_PATH` if executables are outside the PHP process PATH. Apply SQL patch `20261128_004_add_scrape_collection_method.sql` before selecting Browser. Production currently has no Node.js, so Browser collection requires separate server setup during an authorized deployment; HTTP collection does not.

IdolRanking is a Laravel-based idol ranking and global fan community web application.

### Fresh Codex session entry point

At the start of every new session, read `docs/PROJECT_RECOVERY.md`, `docs/PROJECT_OVERVIEW.md`, `sql/README.md`, and `scripts/deploy/README.md` when deployment is in scope. Then inspect the actual Git branch/status, `.env.example` (never print `.env`), lockfiles, installed PHP/Composer/Node tools, SQL snapshots, and relevant scripts. Do not assume dependencies, databases, SSH access, or a successful production release merely because documentation describes them. Report missing tools and configuration, then guide or perform only the reversible setup steps authorized by the request. Never expose or commit secrets, overwrite existing local data, execute production SQL, or deploy without explicit authorization.

For a short request such as “Analyze this project and set up the environment,” automatically run a read-only environment inventory first, compare it with the recovery checklist, and give the user the exact safe next steps. Install dependencies only when missing and permitted; do not run `npm ci` against a potentially damaged existing `node_modules`. Before importing a database, confirm the target is local and empty or obtain explicit approval for replacement. Always distinguish verified current state from historical notes.

### Local Development Environment

- OS: Windows 11
- Web Server: Apache from XAMPP
- PHP: 8.4.x
- Laravel: 13.x
- Database: MySQL 8.4 LTS
- Composer: latest stable
- Node.js: LTS
- Editor: Visual Studio Code
- AI Coding: OpenAI Codex

Local project path:

```text
D:\www\idolranking
```

Local URL:

```text
http://idolranking.test
```

### Production Environment

Verified production layout (recheck before acting; these values are configuration, not credentials):

- Hostinger Linux with Apache/PHP-FPM and Laravel 13.
- MariaDB 11.8.9 (local development uses MySQL 8.4; review SQL compatibility).
- Laravel checkout: `/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking`.
- Apache document root: `/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/public_html`.
- `APP_PUBLIC_PATH` points to `public_html`; `public_html/index.php` bootstraps the separate checkout. Preserve that file and server-specific `.htaccess`.
- PHP 8.4 executable: `/opt/alt/php84/usr/bin/php`; Composer: `/usr/local/bin/composer`. Node/npm are not installed on production; build Vite assets locally.
- SSH uses Windows OpenSSH on port `65002`. Existing scripts currently use a dedicated key and pinned `known_hosts`. For server administration, prefer SSH username/password **only if the server supports it**; do not break the existing working key-based deployment path. Always verify the server host key and never store credentials in Git.

The current production commit and whether the integrated initial database/media release completed are **not verified by repository state**. Do not claim success until read-only server checks confirm it.

The local Apache environment should remain compatible with the production Apache environment as much as practical.

Apache-specific behavior should be testable locally before deployment.

---

## 2. Core Development Principles

Before modifying code:

1. Read and understand the existing related files.
2. Inspect the current implementation before changing it.
3. Do not modify unrelated files.
4. Do not perform unnecessary refactoring.
5. Do not replace working code without a clear reason.
6. Keep changes focused on the requested feature.
7. Preserve existing functionality.
8. Prefer simple and maintainable implementations.
9. Do not overengineer.
10. Do not add features that were not requested.

For large changes that affect architecture, database structure, authentication, Apache configuration, or multiple major features, provide a short implementation plan first.

For small and obvious changes, proceed without unnecessary clarification.

---

## 3. Laravel Rules

Use Laravel 13 conventions unless they conflict with this file.

### Eloquent

Do NOT use Eloquent ORM.

Do NOT create Eloquent Models unless explicitly requested by the user.

### Migration

Do NOT use Laravel migrations.

Do NOT use Laravel Schema Builder for database schema management.

Do NOT create migration files unless explicitly requested.

### Database Access

Use primarily:

- `DB` Facade
- Laravel Query Builder
- Database transactions
- Raw SQL only when Query Builder is not appropriate

Example:

```php
use Illuminate\Support\Facades\DB;

$rows = DB::table('example')
    ->where('status', 1)
    ->get();
```

Do not introduce Repository patterns, Service layers, DTOs, or other abstractions unless they provide a clear practical benefit.

Controllers may contain practical application logic, but avoid large duplicated blocks of code.

---

## 4. Database Rules

Database engine:

```text
MySQL 8.4 LTS
```

Default character set:

```text
utf8mb4
```

Default collation:

```text
utf8mb4_0900_ai_ci
```

Database schema must be managed using SQL files rather than Laravel migrations.

### Database Naming Rules

- Every single-column AUTO_INCREMENT primary key must be named `idx`.
- Every foreign key column must use the `_idx` suffix and reference the target table's `idx` column.
- Role-specific foreign keys follow the same rule, such as `actor_member_idx`, `created_by_member_idx`, `approved_idol_idx`, `approved_fan_post_idx`, `reviewed_by_member_idx`, and `reversal_of_history_idx`.
- Relationship tables without an AUTO_INCREMENT primary key must use their foreign key columns as the composite primary key. Do not add an unnecessary standalone `idx` column.
- Examples: `idol_group_members` uses `PRIMARY KEY (individual_idx, group_idx)`; `fan_post_likes` uses `PRIMARY KEY (fan_post_idx, member_idx)`.
- Keep foreign key, UNIQUE, and INDEX names and column references consistent with these column names. Update SQL comments when column names change.
- External identifiers that are not foreign keys, such as `provider_user_id`, are not subject to the `_idx` suffix rule.

When database work begins, use the following project structure:

```text
/sql
├─ schema
├─ patches
└─ seed
```

### `/sql/schema`

Contains the current complete schema definition.

Example:

```text
/sql/schema/members.sql
/sql/schema/idols.sql
/sql/schema/idol_groups.sql
/sql/schema/votes.sql
```

### `/sql/patches`

Contains SQL required to update an existing database.

Use filenames such as:

```text
20260924_001_create_members.sql
20260924_002_create_idols.sql
20260924_003_add_status_to_members.sql
```

Patch SQL must be ordered and understandable.

### `/sql/seed`

Contains optional initial or reference data.

Example:

```text
/sql/seed/countries.sql
/sql/seed/default_settings.sql
```

Whenever a database structure changes:

1. Update the related schema SQL.
2. Create a patch SQL when needed.
3. Update PHP code that depends on the schema.
4. Consider indexes and constraints.
5. Do not assume Laravel migrations will ever be executed.

---

## 5. Database Query Rules

Avoid unnecessary queries.

Do not execute queries repeatedly inside loops when the same data can be retrieved efficiently in one query.

Avoid N+1 query patterns.

Use appropriate indexes for columns commonly used in:

- `WHERE`
- `JOIN`
- `ORDER BY`
- `GROUP BY`
- duplicate checks
- unique lookups

Use explicit ordering when result order matters.

Never rely on undefined database row ordering.

Use parameter binding or Query Builder.

Never concatenate untrusted user input directly into SQL.

---

## 6. Database Transaction Rules

Use transactions whenever multiple related database modifications must succeed or fail together.

Typical examples:

- deducting hearts and inserting heart history
- inserting a vote and updating related totals
- creating a member and related records
- approving idol registration requests
- operations modifying multiple related tables

Example:

```php
DB::transaction(function () {
    // related database operations
});
```

Do not leave partially completed database states.

Use row locking when race conditions could cause incorrect balances, votes, points, or duplicate processing.

---

## 7. Frontend Rules

Primary frontend stack:

- Blade
- HTML
- CSS
- JavaScript
- `fetch`
- AJAX
- JSON API

Use Blade for normal page rendering.

Use JavaScript and `fetch` for interactive features where a full page reload is unnecessary.

Prefer native JavaScript when it is sufficient.

Do NOT introduce the following unless explicitly requested:

- React
- Vue
- Inertia
- Livewire
- Alpine
- jQuery
- large frontend frameworks

Do not install unnecessary npm packages.

---

## 8. UI Development Rules

The site must be responsive.

Mobile usability must always be considered.

Avoid excessive UI complexity.

Prefer:

- clear layouts
- readable spacing
- reusable CSS
- predictable interactions
- fast page rendering

Do not create unnecessary animations or heavy visual effects.

Do not redesign unrelated pages while implementing a specific feature.

User-facing text may be Korean or multilingual.

Internal code names, PHP variables, database columns, routes, and API fields should generally use clear English names.

---

## 9. AJAX / API Rules

Interactive actions should generally communicate through JSON APIs.

Successful response example:

```json
{
    "success": true,
    "message": "처리가 완료되었습니다.",
    "data": {}
}
```

Failure response example:

```json
{
    "success": false,
    "message": "처리 중 오류가 발생했습니다."
}
```

Use appropriate HTTP status codes.

Typical examples:

```text
200 OK
201 Created
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Unprocessable Entity
500 Internal Server Error
```

Always validate server-side input.

Never trust validation performed only in JavaScript.

---

## 10. Security Rules

Never expose secrets.

Never commit:

- `.env`
- passwords
- database credentials
- API keys
- private tokens
- access tokens
- secret keys

Do not hardcode secrets inside:

- PHP
- Blade
- JavaScript
- JSON
- Git-tracked configuration files

Use environment variables where appropriate.

Always validate user input.

Use CSRF protection for state-changing web requests.

Use Laravel Query Builder or proper parameter binding.

Escape user-generated output appropriately.

Prevent:

- SQL injection
- XSS
- CSRF
- privilege escalation
- insecure direct object references
- duplicate reward processing
- duplicate voting caused by race conditions

Authorization must always be checked server-side.

---

## 11. Authentication Rules

### Authentication Approach

- Implement authentication directly with the DB Facade / Query Builder and file sessions (`SESSION_DRIVER=file`).
- Use the `members` table and its `idx` primary key, not a separate `users` table.
- Do not use an Eloquent User model, Laravel Auth facade authentication, or Laravel's default UserProvider implementations.
- Do not generate Laravel starter-kit authentication unless explicitly requested.
- These rules define the implementation requirements; do not implement signup or login until requested.

### Login Session and Logout

- The login session identifier is `member_idx`, containing `members.idx`.
- On successful login, regenerate the session ID and store the member identifier, for example `session(['member_idx' => $member->idx]);`.
- Logout must use POST with CSRF protection.
- On logout, invalidate the session and regenerate the CSRF token using `session()->invalidate()` and `session()->regenerateToken()`.
- Do not store permission levels or `is_dummy` as fixed session values. Determine them from the current member record in `members`.

### Current Member Validation and Middleware

At login and whenever resolving the current member, require all of:

```text
member_level > 0
status = active
deleted_at IS NULL
```

- An administrator is a valid current member with `member_level = 10`.
- A dummy account has `is_dummy = 1`; this does not grant administrator privileges.
- `ResolveMember` middleware reads `member_idx` from the session and queries `members.idx` using Query Builder.
- If the member is valid, make the current member record reusable within the same request to avoid repeated queries.
- If the session member no longer exists or fails validation, invalidate the login session and regenerate the CSRF token.
- `RequireMember` permits only valid logged-in members.
- `RequireAdmin` permits only valid logged-in members with `member_level = 10`.
- Resolve the current member before running member or administrator authorization checks.

### Login Histories

Record both successful and failed login attempts in `member_login_histories`.

- Success: `member_idx` is required, `result = success`, and `failure_reason` is NULL. Update `members.last_login_at` and insert the success history in the same transaction.
- Failure: `result = failed` and `failure_reason` contains an internal failure code. `member_idx` may be NULL when the member cannot be identified.
- Never record plaintext passwords, authentication tokens, or session IDs in login histories.

### Blade and Password Handling

- Actual IdolRanking Blade views must use the resolved current member and must not depend on Laravel's default `@auth`, `@guest`, or `Auth::user()`.
- Verify passwords with `Hash::check()` against `members.password_hash`.
- Hash passwords with `Hash::make()` before storing them in `members.password_hash`.
- Do not use Laravel's default password broker. Implement password reset separately using the project's SQL-defined `password_reset_tokens` structure.

---

## 12. Session, Cache, and Queue

Current local defaults:

```env
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Do not change these to database-backed drivers unless explicitly required.

If database-backed sessions, cache, or queues are later required:

- do not create Laravel migrations
- create the required SQL schema files under `/sql`
- update schema and patch files properly

---

## 13. Apache Rules

Local development uses Apache.

Production will also use Apache.

Laravel's `public` directory must always be the Apache DocumentRoot.

Local DocumentRoot:

```text
D:/www/idolranking/public
```

Local domain:

```text
idolranking.test
```

Do not create Nginx-specific dependencies.

Apache-related features may include:

- VirtualHost
- `.htaccess`
- `mod_rewrite`
- `mod_headers`
- `mod_expires`
- `mod_deflate`
- redirects
- caching rules
- security headers
- directory rules
- upload settings

When Apache configuration changes are necessary:

1. Clearly explain why the change is required.
2. Explain which local Apache file needs modification.
3. Explain whether the same setting is needed in production.
4. Avoid unrelated Apache configuration changes.

Do not modify Apache configuration merely to work around application bugs.

Prefer solving application problems inside Laravel when appropriate.

---

## 14. PHP Rules

Target PHP version:

```text
PHP 8.4
```

Use PHP 8.4-compatible syntax.

Prefer readable code.

Use early returns when they improve clarity.

Avoid deeply nested conditions.

Use strict comparisons where appropriate.

Do not suppress PHP errors with `@` unless there is an exceptional and documented reason.

Do not install Composer packages unless needed.

Before adding a new Composer dependency, check whether Laravel or PHP already provides the required functionality.

---

## 15. JavaScript / Node Rules

Node.js is primarily used for frontend development and asset building.

Do not assume Node.js must exist on the production server.

Production assets may be built locally before deployment.

Typical commands:

```bash
npm install
npm run dev
npm run build
```

Do not commit:

```text
node_modules/
```

Do commit dependency definitions such as:

```text
package.json
package-lock.json
```

Do not install frontend libraries unless there is a clear reason.

---

## 16. Composer Rules

Do not commit:

```text
vendor/
```

Do commit:

```text
composer.json
composer.lock
```

Production dependencies should normally be installed using:

```bash
composer install --no-dev --optimize-autoloader
```

Do not run `composer update` casually.

Prefer `composer install` when reproducing an existing project environment.

Do not modify `composer.lock` unnecessarily.

---

## 17. Git Rules

Primary branch:

```text
main
```

Do not commit:

```text
.env
vendor/
node_modules/
local logs
local cache files
secret credentials
temporary development files
```

Do not automatically run:

```text
git push
```

unless explicitly requested.

Do not automatically create commits unless explicitly requested.

An explicit user request to deploy authorizes the necessary reviewed commit and push for that deployment only. For ordinary coding tasks, Codex must not silently commit or push.

Codex may show the commands the user should run, but should not silently push code outside an authorized deployment.

Before completing major work, summarize:

- files created
- files modified
- database changes
- Apache/configuration changes
- dependencies added
- testing steps

---

## 18. File Modification Rules

Do not modify unrelated files.

Do not reformat entire files unnecessarily.

Avoid changing whitespace or code style in unrelated sections.

Do not rename existing files unless needed.

Do not rename database columns or routes without considering backward compatibility.

When deleting code, confirm it is no longer used.

Do not delete existing functionality solely because a different implementation appears cleaner.

---

## 19. Error Handling Rules

Do not silently ignore errors.

User-facing errors should be understandable.

AJAX/API errors should return JSON.

Do not expose the following to production users:

- stack traces
- database passwords
- SQL internals
- absolute filesystem paths
- sensitive environment variables

Detailed errors are acceptable during local development.

Application errors should be logged when appropriate.

---

## 20. Performance Rules

Avoid obvious performance problems.

Examples to avoid:

- querying the database repeatedly inside loops
- loading unnecessarily large result sets
- selecting columns that are not needed in large queries
- missing indexes on frequently searched columns
- recalculating expensive values repeatedly without reason

Use pagination for large lists.

Use database aggregation when appropriate.

Do not prematurely introduce complex caching systems.

Optimize only where there is a practical need.

---

## 21. IdolRanking Architecture Principles

IdolRanking is expected to include features such as:

- members
- idols
- idol groups
- rankings
- heart balances
- voting
- fan posts
- likes
- comments
- idol registration requests
- quizzes
- multilingual features
- admin management

Do not implement any of these features until requested.

Do not assume final table structures before they are designed.

Features should be implemented incrementally.

The application should remain easy to understand and maintain.

---

## 22. Idol Ranking Rules

When the ranking system is implemented:

- ranking must be based on actual votes given to idols
- member heart balance itself must not directly determine ranking
- vote processing must be transaction-safe
- heart deductions and vote records must remain consistent
- duplicate or race-condition processing must be prevented

Do not implement ranking logic until its detailed rules are confirmed.

---

## 23. Heart System Rules

When the heart system is implemented, prefer maintaining history records rather than only storing a mutable balance.

Possible heart history examples:

```text
+100 quiz reward
-50 idol vote
+10 event reward
```

Any heart balance modification must be auditable.

Operations that modify both heart balance and another table must use transactions.

Do not implement the final heart schema until requested.

---

## 24. Fan Post Rules

Fan post features may later include:

- image
- comment/text
- author
- likes
- comments
- created date
- idol association

Fan post likes and idol votes are separate concepts.

Do not mix fan post likes with idol ranking votes.

When implemented, representative idol images may later depend on fan post popularity.

Exact business rules must be confirmed before implementation.

---

## 25. Multilingual Rules

The site may support multiple interface languages.

Do not automatically machine-translate official idol or group names.

Official idol/group names may require explicit multilingual database fields.

User-generated content may later support optional translation.

Do not build translation infrastructure until requested.

---

## 26. Testing Rules

After modifying PHP:

- check syntax when appropriate
- verify affected routes
- test affected database operations

Useful commands:

```bash
php artisan route:list
php artisan optimize:clear
php artisan test
```

When frontend assets change:

```bash
npm run build
```

For CSS or visual UI changes, also verify the affected page in a real browser after the build. On Windows, use Chrome with a temporary profile and a screenshot or browser inspection. If the restricted shell prevents Chrome's GPU process from starting, run only that browser check with the approved elevated execution path; do not treat the sandbox GPU failure as an application failure. Check the relevant viewport and hover/focus states, and report any browser check that could not be completed.

Do not claim a feature works unless there is a reasonable verification basis.

If something could not be tested, say so clearly.

---

## 27. Destructive Command Rules

Never execute destructive commands unless explicitly requested.

Examples requiring extreme caution:

```text
DROP DATABASE
DROP TABLE
TRUNCATE TABLE
DELETE without an appropriate WHERE condition
php artisan migrate:fresh
php artisan db:wipe
git reset --hard
git clean -fd
```

Do not delete production or user data.

When a potentially destructive operation is necessary, clearly explain it first.

---

## 28. Codex Workflow

For each task:

1. Read `AGENTS.md`.
2. Inspect relevant existing files.
3. Understand the existing implementation.
4. Determine the smallest appropriate change.
5. Modify only relevant files.
6. Check for syntax or obvious runtime problems.
7. Verify routes/database logic when relevant.
8. Summarize the work.
9. Explain how the user can test it.

For large features:

1. Inspect the project first.
2. Present a short implementation plan.
3. Wait for confirmation only when architecture or requirements are materially ambiguous.
4. Otherwise proceed with the agreed task.

Do not repeatedly ask unnecessary questions.

---

## 29. Codex Response Format After Implementation

After completing a coding task, provide a concise summary containing:

### Created

Files newly created.

### Modified

Files modified.

### Database

Schema, query, or SQL changes.

### Configuration

Apache, `.env`, Composer, npm, or other configuration changes.

### Test

How to verify the feature.

Do not produce unnecessarily long reports for very small changes.

---

## 30. Useful Commands

Laravel:

```bash
php artisan --version
php artisan optimize:clear
php artisan route:list
php artisan test
```

Composer:

```bash
composer install
composer dump-autoload
```

Node:

```bash
npm install
npm run dev
npm run build
```

Git:

```bash
git status
git add .
git commit -m "message"
git push
```

MySQL connection example:

```bash
mysql -h 127.0.0.1 -u idolranking -p idolranking
```

---

## 31. Current Development Philosophy

Feature development continues on top of the first integrated release commit. Check `docs/PROJECT_OVERVIEW.md` for the current scope and deferred features.

Priority order:

1. correctness
2. maintainability
3. security
4. simplicity
5. performance
6. abstraction

Prefer:

- simple structure
- explicit code
- predictable behavior
- easy debugging
- easy maintenance

Avoid:

- premature abstractions
- unnecessary packages
- unnecessary frameworks
- large unrelated refactors
- speculative features

Build one feature at a time.

Preserve a stable working project after each feature.

---

## 32. Production Deployment and Authentication Policy (Authoritative)

This section defines the approved deployment workflow. Apply it in new Codex sessions and on replacement PCs. It takes precedence over older deployment/authentication descriptions in this file when they conflict. Preserve existing working scripts and security controls unless a specific change is necessary.

### 32.1 Authentication: three separate connections

1. **GitHub / Git:** Use the user's existing registered Git SSH key wherever the configured remote supports it. Do not ask for GitHub account credentials for routine Git operations. Verify the actual local and production `git remote -v` separately: they may use different transport methods. If a remote currently uses HTTPS and works, do not break it merely to standardize authentication; report the difference and use the working configuration. Never create or replace keys without need.
2. **Production database:** Use the existing MySQL/MariaDB username and password for authorized backups, SQL patches, and data changes. Direct remote DB access is allowed when already available and securely configured. Otherwise use the existing verified SSH tunnel or approved DB connection path. Do not disable host restrictions or open database firewall access to force direct connections. Never print credentials or put passwords into Git, logs, or command-line arguments visible to other users.
3. **Production server administration via SSH:** Prefer SSH username/password login for server condition checks, deployment, and authorized configuration/service changes **when supported by the server**. The currently documented deployment script uses a dedicated SSH key; preserve this working path until password authentication has been confirmed and any minimal script adjustment is tested. Do not weaken SSH host-key verification, alter server authentication settings without authorization, or confuse the GitHub key with server SSH credentials.

### 32.2 Standard one-pass deployment

When the user explicitly requests a full production deployment, the necessary reviewed Git commit/push, production DB operations, and server deployment are authorized for that request. Execute the established workflow without repeated planning or unnecessary approval loops. Do not assume a generic coding request authorizes deployment.

1. Read `AGENTS.md`, `scripts/deploy/README.md`, and `sql/README.md`, then inspect current Git status, tracked/untracked changes, existing scripts, and the production revision. Use already verified findings rather than repeating investigations.
2. Review the exact files to release, including SQL patches, and exclude secrets, temporary files, incomplete work, and the entire `/docs/` directory. Keep local docs intact. If removing previously tracked `/docs` files, Git may remove those tracked files from the production checkout as specifically authorized; do not delete unrelated production data or untracked files.
3. Before modifying the production DB, create a fresh consistent backup in the restricted local directory `D:\backup\idolranking` using the existing DB credentials and supported secure connection. A standard SQL dump is acceptable under the user's approved simple workflow; no DPAPI or new encryption/key-management system is required. Keep the dump out of Git and public directories, and verify successful completion and basic integrity. Do not claim a full restore test was performed unless it actually was.
4. Apply only missing, reviewed SQL patches in the correct dependency order using the existing patch runner and its lock, checksum, and patch-history protections. Preserve existing users, Heart balances, votes, histories, and uploads. Never apply the full schema over an existing production DB. Confirm MySQL 8.4-to-MariaDB production compatibility for affected SQL.
5. Run `npm.cmd run build` locally when frontend sources or required assets changed, or when the previous build is stale. Production has no Node/npm; deploy the matching Vite `public/build` assets and `manifest.json` through the established process. Do not repeat a successful unchanged build.
6. Commit all reviewed release changes with a **Korean commit message** and push to `origin/main` using the existing Git authentication. For this explicit deployment request, commit and push are permitted. Do not force-push or discard unrelated changes.
7. Use the existing deployment scripts to deploy the **exact pushed commit SHA**. Keep the production `.env`, server-specific `public_html/index.php` and `.htaccess`, uploads, and other production-only files intact. Maintain strict SSH host-key checking and use the existing working server login method unless a verified password-based method has been safely adopted.
8. Refresh the necessary Laravel caches and perform one set of essential checks: deployed SHA, application availability, affected pages/functions, and latest CSS/JS assets. Avoid repeated test suites, browser automation, or redundant checks unless a failure warrants them.
9. Report briefly in Korean: backup result/path, SQL patch results, build result, Git SHA, deployment status, and actual remaining blockers.

### 32.3 Safety and portability

- Stop for actual authentication failure, invalid/incomplete backup, unexpected destructive SQL, patch checksum mismatch, data-loss risk, or failed deployment; explain the exact blocker once. Do not bypass safeguards to make deployment appear successful.
- Do not add new SSH keys, authentication systems, DPAPI, encryption infrastructure, or backup services unless genuinely required. Keep backup access restricted; an unencrypted SQL dump contains sensitive production data and must not be shared or committed.
- Keep the deployment reproducible on a new Windows PC: derive user-profile-dependent paths from environment variables; document necessary tools, SSH/Git setup, DB access, backup path, and scripts without storing credentials.
- `/docs/` is Git-ignored. Store critical portable deployment/recovery instructions in Git-tracked `AGENTS.md` and/or `scripts/deploy/README.md`, not exclusively under `/docs`.
- Existing deployment scripts and documentation remain the source of executable commands. This policy does not imply that unsupported SSH password login or unrestricted direct DB connectivity already works.
