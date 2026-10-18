# IdolRanking project overview

Last repository inspection: 2026-10-18. Treat live machine/server state as unverified until checked in that session.

## Product and current scope

IdolRanking is a multilingual idol/group ranking and fan-community application. The first integrated code release is repository commit `86b685ec52c309e8a46dad2b4aa65a7e4993d553` on `main`.

Implemented scope visible in routes, controllers, SQL, and tests:

- Public idol and group profiles, search, active-only rankings, monthly Heart voting, and top-voter displays.
- Email verification/signup/login, Google OAuth, password reset, member profiles, and Heart history.
- Fan posts, comments, likes/recommendations, translations, reports, and profile-image rights reports.
- Attendance/daily check-in and configured Heart rewards; partner-banner rewards and referral rewards.
- Administrator moderation, member/IP management, Heart settings and grants, audit/access logs, and controlled dummy-account activity.
- Responsive Blade UI with native JavaScript/fetch and Vite/Tailwind CSS. The four Vite inputs are `resources/css/app.css`, `resources/css/admin.css`, `resources/js/app.js`, and `resources/js/admin.js`.

Deferred or intentionally unavailable:

- Fan Garden remains in source, views, translations, assets, and tests for future work, but `/fan-garden` deliberately returns 404 and has a regression test. Do not remove those preserved sources as part of recovery.
- Stars are displayed on My Page; the Star purchase button is disabled. No payment integration is in scope.
- Quiz/rewarded-ad delivery and other unrequested features are not production-ready. The seeded `watch_ads` reward is disabled.

## Application structure and implementation constraints

- Laravel 13 app. Routes are centralized in `routes/web.php`; middleware is registered in `bootstrap/app.php`.
- Application data access uses `DB` facade / Query Builder, not Eloquent models. Authentication is custom and uses `members.idx` with `member_idx` in the session. Authorization checks `members` on requests; administrator level is 10.
- SQL files are the schema source of truth. Do not add Laravel migrations or switch to Eloquent without explicit architectural approval. Follow the `idx`, `_idx`, and composite-key rules in `AGENTS.md`.
- Blade is the page renderer. Keep the current UI, use native JavaScript where practical, and do not add React/Vue/Livewire/jQuery or other frameworks.
- `SESSION_DRIVER=file`, `CACHE_STORE=file`, and `QUEUE_CONNECTION=sync` are the documented defaults. Tests use PHPUnit and an in-memory SQLite database via `phpunit.xml`.
- Public uploaded files use Laravel's `public` disk at `storage/app/public`, stored paths are rendered as `/storage/...`, and `storage/app/private` is reserved for private moderation evidence. Only public storage may be linked below the web root.
- In production, Laravel's app directory and Apache document root are separate. `APP_PUBLIC_PATH` directs Laravel to `public_html`; preserve its server-specific `index.php` and `.htaccess`.

## Database sources and known mismatch

`sql/deploy/mariadb11_schema.sql` is the current fresh-install snapshot. It contains 37 table definitions and 58 named foreign-key constraints. `sql/deploy/mariadb11_seed.sql` inserts essential Heart reward settings only; it does not provide the local idol/member/activity dataset. There are 36 historical/forward patch SQL files; see `sql/README.md` for baseline and ordering rules.

The one-time integrated deployment script is pinned to an external dump with 37 tables and 59 foreign keys. A read-only DDL comparison found a table-set difference: the dump contains `fan_post_monthly_views` and its FK `fan_post_monthly_views.fan_post_idx → fan_posts.idx`, while the checked-in snapshot contains `schema_patch_history` instead. The dump therefore lacks the patch ledger required by the incremental patch workflow. This is not just a count discrepancy; do not import the dump or claim schema parity until its data and schema are reconciled. A fresh PC can recreate the checked-in schema, but cannot reproduce the external dump's full data. Never put a database dump in Git.

## Development history landmarks

The current `main` history includes Laravel 13 setup, custom DB/session authentication, SQL `idx` key conventions, removal of default Laravel database scaffolding, ranking/UI work, Heart/referral rewards and tests, and split public-path support. `86b685e` is the integrated first-release repository commit. The actual current Hostinger state is not inferred from this history.

Fan Garden's public route is a deliberate `abort(404)` in `routes/web.php`; preserve its test `tests/Feature/FanGardenPreviewTest.php` and all source assets.

## Current state and pending work

- Local branch and `origin/main` were observed at `86b685ec52c309e8a46dad2b4aa65a7e4993d553` during this documentation pass.
- The deployment scripts under `scripts/deploy/` and `docs/ui/fan-garden.png` were untracked in the worktree at inspection. Keep the image; commit the non-secret deployment scripts and these recovery docs if they are intended to be available from GitHub.
- Current production deployment status is unknown. A historical read-only observation recorded production at `4d16c54f2272afd21a7dbac6c1c846397a7e1164` before the integrated code/database/media release. Recheck current production commit and HTTP behavior read-only before saying the release succeeded.
- Reconcile the `fan_post_monthly_views` versus `schema_patch_history` table-set discrepancy, including the 58-versus-59 FK count, and provide a secure, durable, non-Git transfer plan for any full database dump and private/public uploads needed on a new machine.
- Make deployment scripts portable to a new Windows user or document the required local changes: the current scripts hardcode `C:\Users\LEO\.ssh` paths, SSH identity/host, and Hostinger filesystem paths.
- Verify XAMPP's actual Apache PHP runtime. The inspected XAMPP CLI PHP was 8.2.12, while the standalone CLI PHP at `C:\php84\php.exe` was 8.4.26; Laravel 13 requires PHP 8.3+, and the project target is 8.4.
