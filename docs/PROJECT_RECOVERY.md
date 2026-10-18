# IdolRanking recovery guide

This is the operational entry point for restoring the project on a new Windows PC or in a fresh Codex session. It contains configuration names and paths, never secret values. Verify every machine/server fact before acting; do not infer successful deployment from Git history.

## 1. Fresh-session procedure

For a short request such as “Analyze this project and set up the environment,” Codex should:

1. Read `AGENTS.md`, this guide, `docs/PROJECT_OVERVIEW.md`, `sql/README.md`, and `scripts/deploy/README.md` when deployment is involved.
2. Inspect `git status`, branch and HEAD; list tracked/untracked files; inspect `.gitignore`, lockfiles, `.env.example`, SQL snapshots, and deployment scripts. Never print `.env`, key, OAuth, mail, or database secret values.
3. Detect the actual PHP, Apache, MySQL, Composer, Node/npm tools and versions, `vendor/autoload.php`, `node_modules/vite/bin/vite.js`, and `public/build/manifest.json`. A PATH entry does not prove Apache is using that PHP.
4. Compare what exists with the steps below. Keep existing local data, uploads, `node_modules`, and backups. Install only missing dependencies. If an existing dependency folder is damaged, stage and verify a replacement separately before proposing a swap.
5. Do not execute SQL, replace a database, change production, or deploy unless the user explicitly authorized that operation in the current context. Confirm whether a database is empty before a local import.
6. Report verified state, missing items, and safe next actions. Mark production status “unverified” unless a current read-only production check proves otherwise.

## 2. Repository and source control

- Repository: `https://github.com/gitdir/idolranking.git`
- Primary branch: `main`
- Local project inspected: `D:\www\idolranking`
- Repository HEAD during this documentation pass: `86b685ec52c309e8a46dad2b4aa65a7e4993d553` (first integrated release commit). `origin/main` resolved to the same commit at inspection.
- Use focused changes and SQL patches, inspect diffs, run relevant tests, then commit/push only when explicitly asked. Do not run `git reset --hard`, `git clean`, or silently overwrite a worktree.
- Commit `composer.json`/`composer.lock`, `package.json`/`package-lock.json`, approved source SQL, tests, docs, and source public assets. Do not commit `.env`, credentials, database dumps, uploads, `vendor`, `node_modules`, `public/build`, caches, or temporary files.
- At inspection, `scripts/` and `docs/ui/fan-garden.png` were untracked. The non-secret deployment scripts and project docs should be committed if GitHub is to be the recovery source. Preserve `docs/ui/fan-garden.png`; include it if it remains a design reference. Recheck status before staging.

## 3. Verified Windows development environment

Expected setup:

- Windows 11, XAMPP Apache 2.4, PHP 8.4, MySQL 8.4, Composer 2.x, Node.js LTS/npm, Laravel 13, Vite 7/Tailwind 4.
- Local URL: `http://idolranking.test`; Apache document root must be `D:/www/idolranking/public`, with `AllowOverride All`/rewrite enabled for Laravel.
- Common XAMPP files: `C:\xampp\apache\bin\httpd.exe`, `C:\xampp\php\php.exe`, `C:\xampp\apache\conf\extra\httpd-vhosts.conf`; the Windows hosts file is under `%SystemRoot%\System32\drivers\etc\hosts`.

Observed on the current PC (re-detect on a new one):

- Standalone CLI PHP `C:\php84\php.exe`: PHP 8.4.26. PHP modules included `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `curl`, and `fileinfo`.
- XAMPP Apache executable exists and reports Apache 2.4.58. XAMPP's bundled CLI PHP reports 8.2.12, below the project target. This does **not** prove which PHP module Apache currently loads; verify Apache's configured PHP runtime and align it to PHP 8.4 before local web testing.
- MySQL server/client: MySQL 8.4.11; the MySQL84 service was running during inspection.
- Composer 2.10.3.
- Node 24.19.0 and npm 11.17.0 were supplied by a PhpStorm ACP runtime at a machine-specific path. On a replacement PC, install a supported Node LTS and use its normal PATH; do not depend on the old IDE runtime path.
- `vendor/autoload.php`, Vite executable, and `public/build/manifest.json` existed during inspection. Their presence is not a substitute for checking versions or a successful test/build.

Read-only inventory commands (PowerShell):

```powershell
Get-Command php.exe, composer.bat, node.exe, npm.cmd, mysql.exe, ssh.exe, scp.exe -ErrorAction SilentlyContinue
php -v
composer.bat --version
node --version
npm.cmd --version
Test-Path vendor/autoload.php
Test-Path node_modules/vite/bin/vite.js
Test-Path public/build/manifest.json
```

Also inspect XAMPP Control Panel and Apache configuration rather than assuming Apache is running because `httpd.exe` exists. Configure the local virtual host and `hosts` entry only after checking for conflicts; never overwrite existing XAMPP configuration without preserving it.

## 4. Fresh local setup

1. Install Git, XAMPP/Apache, PHP 8.4 for CLI and Apache, MySQL 8.4, Composer, and Node LTS. Clone the repository into the chosen workspace and inspect status before setup.
2. Install locked PHP dependencies:

   ```powershell
   composer.bat install
   ```

3. If `.env` is absent, copy `.env.example` to `.env`; never replace an existing one. Set a local-only `APP_KEY` with `php artisan key:generate`. Do not use the local key on production or regenerate production `APP_KEY`.
4. Create a new, empty local MySQL database and a local DB account in MySQL Workbench or another approved local administration tool. Configure `.env` locally. The example uses `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, port `3306`, database `idolranking`, `utf8mb4`, and a local collation. Enter credentials interactively or in the ignored `.env`; never place them in commands, docs, or Git.
5. Fresh schema is `sql/deploy/mariadb11_schema.sql`; it is written with MySQL/MariaDB-compatible InnoDB and `utf8mb4_unicode_ci`. For a **new empty local database only**, import schema first, then `sql/deploy/mariadb11_seed.sql`. In Command Prompt (so `<` is input redirection), the form is:

   ```cmd
   "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" --default-character-set=utf8mb4 -u LOCAL_DB_USER -p idolranking < "D:\www\idolranking\sql\deploy\mariadb11_schema.sql"
   "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" --default-character-set=utf8mb4 -u LOCAL_DB_USER -p idolranking < "D:\www\idolranking\sql\deploy\mariadb11_seed.sql"
   ```

   `-p` prompts; never append a password. Do not run this against production or a database with data. For an existing local database, compare it to the snapshot and use only reviewed, unapplied forward patches; do not replay every historical patch.
6. `sql/deploy/mariadb11_seed.sql` seeds reward settings only, not idols, members, or activity. No sanitized initial ~100-record dataset or full database dump is committed. A new machine will not reproduce the prior local content without a separately transferred, access-controlled data source. Never add that dump to Git.
7. If this is a fresh checkout with no `node_modules`, run `npm.cmd ci` and then `npm.cmd run build`. `npm ci` can replace files in `node_modules`; do not run it against an existing damaged/locked folder. For deployment, use the isolated archive build in `scripts/deploy/deploy.ps1` instead of the development dependency folder. A prior Windows `EPERM` damaged the old Vite installation; the safe recovery was staging separately before replacement.
8. Clear only local Laravel caches if configuration is stale, then verify:

   ```powershell
   php artisan --version
   php artisan route:list
   php artisan test
   npm.cmd run build
   ```

   PHPUnit uses in-memory SQLite (`phpunit.xml`); production MySQL compatibility still needs SQL review. Local HTTP smoke checks: homepage, `/ranking/idol`, `/ranking/group`, and `/fan-garden` returning 404.

## 5. Environment variables and secrets

Use `.env.example` to identify variable names. The real values are deliberately absent from Git and this guide.

- App: `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, optional `APP_PUBLIC_PATH`, locale, maintenance driver.
- Database: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, charset/collation. Never assume local `.env` values match production.
- Session/cache/queue: default to file/file/sync unless schema and deployment are deliberately changed.
- Mail: `MAIL_MAILER`, host/port, username/password, sender values. Local example uses log mail; production password belongs in hosting-managed environment configuration.
- Google OAuth: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`. Register the exact local and production callback URLs with Google. Google Translate is separate: `GOOGLE_TRANSLATE_API_KEY` is optional and secret.
- Vite exposes only intentionally public `VITE_*` values. Do not put private credentials in a Vite variable.
- Production `.env` and `APP_KEY` must be preserved. Never print `.env`, paste credentials into chat, put them in command-line arguments, or write them into deployment logs.

## 6. SQL and data recovery

- SQL, not Laravel migrations, owns the schema. See `sql/README.md`; all SQL files currently under `sql/` are allowlisted source files. Add an explicit `.gitignore` allowlist entry for each new schema/patch/seed file.
- Fresh install uses `sql/deploy/mariadb11_schema.sql` then `sql/deploy/mariadb11_seed.sql`; legacy `sql/schema/001_initial_schema.sql`, `schema/partner_banners.sql`, and historical patches are not extra fresh-install steps.
- For a continuing database, verify its exact baseline, take a consistent encrypted backup, apply one checksum-reviewed forward patch, verify it, then record it in `schema_patch_history`. MariaDB DDL can implicitly commit; a partial patch is not safe to retry blindly.
- Reconciled schema: the local database, `sql/deploy/mariadb11_schema.sql`, and the newly prepared compatible dump have the same 37 table names and 58 FK definitions. `schema_patch_history` is present and `fan_post_monthly_views` is absent. The legacy table was verified empty and dropped locally with explicit approval; only the successful history-table bootstrap is recorded in the ledger.
- Current one-time MariaDB-compatible dump: `C:\Users\LEO\AppData\Local\Temp\idolranking-mariadb-reconciled-deadca0acc0a49269a96d09eb9d4e56d\idolranking-mariadb118-compatible.sql`, SHA-256 `BA0C271DF57E377268A7FE4D9CA73FA4E2BFAD3AC4262BAC9EC819955080CACB`. The unchanged MySQL 8.4 original is beside it. Both are sensitive, temporary, outside Git, and may disappear; verify the path, checksum, schema inventory, and approved data provenance before use.
- The earlier verified local inventory had 11 public upload files (about 3.55 MB) and no private files, but this is a historical inventory, not an available backup. Preserve and transfer `storage/app/public`, `storage/app/private`, and release-owned `public/images` separately from SQL. Never commit private evidence or member uploads.

## 7. GitHub and Hostinger deployment

Verified configuration (recheck before deployment):

- Git remote: `origin` → `https://github.com/gitdir/idolranking.git`; branch `main`.
- Production URL: `https://orchid-oryx-674671.hostingersite.com`.
- SSH: Windows OpenSSH to host `145.79.26.175`, port `65002`, account `u624668418`; enforce `StrictHostKeyChecking=yes` and the pinned `$env:USERPROFILE\.ssh\known_hosts` file.
- The initial-release script defaults to `$env:USERPROFILE\.ssh\idolranking_hostinger_ed25519` and `$env:USERPROFILE\.ssh\known_hosts`; override these with `-SshKeyPath` and `-KnownHostsPath` when needed. A new PC needs its own key or securely provisioned identity, registered with Hostinger. Never transfer the private key through Git/chat and never trust a first-seen host key without an independently verified Hostinger fingerprint. The script requires the private-key ACL to grant full control only to the current Windows account.
- Laravel path: `/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking`; Apache root: `/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/public_html`.
- Production PHP: `/opt/alt/php84/usr/bin/php`; Composer: `/usr/local/bin/composer`; no Node/npm on production. The web root's `index.php` bootstraps Laravel from the separate app path; preserve it, `.htaccess`, `.env`, `APP_KEY`, storage, and server-only files.
- Keep routine deployment on native Git, SSH, SCP/rsync, Composer, PHP, and MySQL/MariaDB commands; do not depend on the one-time initial-release or recovery experiments. The repository must be committed and pushed to `origin/main` first. On production, verify the app path and clean `main` checkout, then use `git pull --ff-only`. Run `/usr/local/bin/composer install --working-dir=/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking --no-dev --optimize-autoloader` with PHP 8.4 only when `composer.lock` changes.
- Build Vite locally only when frontend assets change. Transfer `public/build` additively to `public_html/build`; never delete unrelated webroot files. SQL changes require a reviewed, single forward patch, checksum verification, a consistent backup, and an explicit maintenance window. Apply no SQL during ordinary code deployment. Refresh only the necessary Laravel caches with `/opt/alt/php84/usr/bin/php` and run HTTP smoke tests before ending maintenance mode.
- A clean production installation or complete database replacement is a separate destructive operation requiring explicit approval, verified source/dump checksums, confirmed target paths/database, and preservation or secure recreation of `.env`. Do not infer a successful initial release from Git history. The one-time deployment scripts and their staged dumps are not generic restore tools.
- Vite builds locally and publishes to `public_html/build`; uploaded public media goes to app `storage/app/public`, private evidence to `storage/app/private`, and public static source assets to `public_html/images`. The required public link is only `public_html/storage` → app `storage/app/public`; never link `storage/app/private`.
- On a failure after code/assets change, the app can remain in maintenance mode. After database replacement starts, the schema/data can be partial; there is no automatic rollback or retry. Confirm DB state and restore/reconcile from a verified backup before manually bringing the site up.

Production was last inspected during the interrupted initial deployment at commit `f66aa7e50ba590f66719d4b4be7c346374b75f3c`, with the imported database at 37 tables and 58 foreign keys and Laravel maintenance mode enabled. This is a historical snapshot, not a claim that the installation is currently complete. Verify current SSH and HTTP state before making further changes.

## 8. Portability issues and pending work

1. **Initial release artifacts:** the one-time dump and uploads remain machine-local temporary artifacts, not Git recovery inputs. Keep them in approved protected storage and verify checksums before any transfer.
2. **External data:** the full dump, initial idol/group records, and uploads are not recoverable from this Git repository. Establish encrypted, durable, access-controlled storage or a sanitized seed dataset.
3. **Hostinger identity/layout:** deployment scripts target the verified Hostinger account and application layout. Local SSH key and `known_hosts` paths now default relative to the current Windows profile and can be overridden; each new PC still needs its own trusted host-key pin and registered SSH public key.
4. **Apache PHP:** local XAMPP includes PHP 8.2.12 while standalone CLI PHP is 8.4.26. Confirm web runtime and configure Apache for PHP 8.4.
5. **Runtime version:** current Node/npm came from an IDE-specific directory. Install a standard supported Node LTS on a new PC.
6. **Production status:** no current production state is embedded in Git. Verify it live and read-only; never infer success from a local commit or prior conversation.
7. **Untracked deliverables:** deployment scripts, docs added for recovery, and `docs/ui/fan-garden.png` need review and an explicit Git staging/commit operation to survive a fresh clone. No commit/push is performed by this guide.
