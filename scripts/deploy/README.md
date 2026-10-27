# IdolRanking deployment scripts

These scripts deploy a specific Git commit from Windows to the verified Hostinger layout. They do not change the production `.env` or `APP_KEY`, run SQL during code deployment, or delete production files.

## Verified deployment settings

- SSH: `u624668418@145.79.26.175`, port `65002`
- Identity default: `$env:USERPROFILE\.ssh\idolranking_hostinger_ed25519`; override with `-SshKeyPath` when needed.
- Host key default: `$env:USERPROFILE\.ssh\known_hosts`; override with `-KnownHostsPath`. The verified host key must already be pinned as `[145.79.26.175]:65002`; SSH always uses `StrictHostKeyChecking=yes`.
- `deploy.ps1`, `initial-release.ps1`, and `database-operation.ps1` use the current Windows profile rather than a machine-specific account path. Each accepts `-SshKeyPath` and `-KnownHostsPath` overrides.
- Laravel checkout: `/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking`
- Public web root and `APP_PUBLIC_PATH`: `/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/public_html`
- Production branch: `main`
- PHP 8.4: `/opt/alt/php84/usr/bin/php`
- Composer: `/usr/local/bin/composer`, invoked through PHP 8.4

No private key, database password, or production `.env` belongs in this directory.

The PowerShell drivers verify that the private-key ACL is protected and grants full control only to the current Windows account. Standalone SSH commands close/disable stdin and have a 30-second timeout; coordinator operations and staging uploads also have bounded timeouts. Coordinator streaming still sends its script on stdin, and the two destructive confirmations still use the existing interactive local prompt and remote TTY.

## One-time coordinated first release

Use `initial-release.ps1` only for the reviewed initial release that combines code, the complete local database, and local uploaded media. This workflow is separate from ordinary code deployment and from the standalone SQL tools. It never applies historical SQL patches.

`-Commit` is required and must be the full 40-character SHA to deploy. The local driver requires a clean `main` worktree, fetches the current `origin/main` tip into `FETCH_HEAD`, and verifies the target commit is an ancestor of or equal to that tip. The remote coordinator repeats the SHA, branch, clean-worktree, origin reachability, and production-to-target fast-forward checks. The command also validates the verified SQL checksum, exact canonical table-name set (37 tables including `schema_patch_history`, excluding `fan_post_monthly_views`) and 58 foreign-key definitions, configured production database name and current 25-table baseline, local media inventory, remote destination collisions, Vite manifest, and SSH host key before maintenance mode. It builds from a clean Git archive in an isolated temporary directory, then uploads the dump, build, images, and media into a private mode-700 directory under the production home. No public destination is touched during staging.

The coordinator is streamed over SSH standard input for the non-interactive remote preflight, avoiding Windows command-line length limits. After the protected stage is created, the same coordinator is copied there and SHA-256 verified before stage validation and deployment. The interactive deploy runs from that verified staged file with a TTY and no piped stdin, so its second typed confirmation remains interactive and cannot be consumed as script input.

The remote coordinator uses ordinary pipes for media count/byte inventories and NUL-delimited `find` pipelines for collision checks; it does not depend on `/dev/fd` process substitution. If build, upload, staging validation, or the local confirmation fails before an interactive deployment attempt, the PowerShell driver removes only the current run's exact private stage after checking its generated path, canonical path, owner, mode `700`, and allowed top-level payload names. It leaves the stage untouched when any check fails. Once the remote deployment attempt begins, staging is retained for recovery inspection.

Local validation only (reads GitHub `origin/main`; no npm, SSH, upload, or production change). It requires the local `main` worktree to have no staged or unstaged tracked changes. Untracked developer files are ignored by validation and are never included in the committed-source Git archive or deployment payload; only the explicitly configured dump and media directories are considered as external inputs:

```powershell
.\scripts\deploy\initial-release.ps1 `
  -Commit 8e1e1d7cc7a496945cd3988de36f4f17b9c8fffa `
  -BaseUrl https://orchid-oryx-674671.hostingersite.com `
  -DatabaseDump (Join-Path $env:TEMP 'idolranking-release\idolranking-mariadb118-compatible.sql') `
  -Sha256 BA0C271DF57E377268A7FE4D9CA73FA4E2BFAD3AC4262BAC9EC819955080CACB `
  -TargetDatabase u624668418_idol `
  -ExpectedTableCount 37 `
  -ExpectedProductionTableCount 25 `
  -BackupDecision APPROVED-NO-BACKUP `
  -DryRun
```

The coordinated production command uses the same arguments, removes `-DryRun`, and adds `-Approved`. It requires the user to type the complete confirmation phrase locally, then again on the remote TTY. The phrase includes the exact commit, database name, dump SHA-256, 37 table count, and `APPROVED-NO-BACKUP`. Do not use it until reviewing the dry-run and staged-payload validation:

```powershell
.\scripts\deploy\initial-release.ps1 `
  -Commit 8e1e1d7cc7a496945cd3988de36f4f17b9c8fffa `
  -BaseUrl https://orchid-oryx-674671.hostingersite.com `
  -DatabaseDump (Join-Path $env:TEMP 'idolranking-release\idolranking-mariadb118-compatible.sql') `
  -Sha256 BA0C271DF57E377268A7FE4D9CA73FA4E2BFAD3AC4262BAC9EC819955080CACB `
  -TargetDatabase u624668418_idol `
  -ExpectedTableCount 37 `
  -ExpectedProductionTableCount 25 `
  -BackupDecision APPROVED-NO-BACKUP `
  -Approved
```

The remote coordinator takes the same non-blocking lock used by other deployment helpers and maintains one maintenance window. It rechecks Git and the DB identity before the window, fast-forwards only, runs Composer and Laravel commands with PHP 8.4, and imports the complete dump without running patches. To remain within the Hostinger database user's existing schema grants, it replaces all base tables inside the verified target database instead of dropping and recreating the database itself; the target database identity and `.env` remain unchanged. Preflight stops if views, triggers, routines, or events exist, because they are outside this reviewed dump. It validates the dump SHA-256, exact 37 table names, 58 foreign-key definitions, and MariaDB compatibility during staging and immediately before dropping existing tables. After import it checks both exact counts and table names. Table DDL is not transactional. It synchronizes media additively (no `--delete`) and refuses any existing unexpected storage path. Build assets are published additively with the Vite manifest moved into place last. It verifies unchanged `.env`, `public_html/index.php` and `.htaccess`, exact Git HEAD, HTTP routes, all four Vite entries, and `/fan-garden` returning 404.
The remote coordinator's initial preflight fetches the current `origin/main` tip into `FETCH_HEAD` without switching the checked-out branch, then verifies the requested commit is contained in that tip and the production HEAD can fast-forward to it. A later fetch immediately before maintenance repeats the reachability check. It otherwise takes the same non-blocking lock used by other deployment helpers and maintains one maintenance window. It rechecks Git and the DB identity before the window, fast-forwards only, runs Composer and Laravel commands with PHP 8.4, and imports the complete dump without running patches. To remain within the Hostinger database user's existing schema grants, it replaces all base tables inside the verified target database instead of dropping and recreating the database itself; the target database identity and `.env` remain unchanged. Preflight stops if views, triggers, routines, or events exist, because they are outside this reviewed dump. It validates the dump SHA-256, exact 37 table names, 58 foreign-key definitions, and MariaDB compatibility once during staging and again immediately before dropping any existing table. After import it checks both exact counts and table names. Table DDL is not transactional. It synchronizes media additively (no `--delete`) and refuses any existing unexpected storage path. Build assets are published additively with the Vite manifest moved into place last. It verifies unchanged `.env`, `public_html/index.php` and `.htaccess`, exact Git HEAD, HTTP routes, all four Vite entries, and `/fan-garden` returning 404.

### Uploaded image delivery in the initial release

Laravel stores public uploads on the `public` disk at `storage/app/public`, and image records are rendered as `/storage/...` URLs. In this split layout, the web server serves static paths from `public_html`; there is no Laravel route that streams ordinary uploaded images. The initial release therefore needs this mapping:

- Link source: `/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking/storage/app/public`
- Link destination: `/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/public_html/storage`
- Reason: make the existing `/storage/...` image URLs resolve to Laravel's public upload files.

The read-only inspection found the destination link missing. The coordinator will create it only during the approved maintenance window and only if the destination is still absent; it refuses to replace an existing file, directory, or different symlink. No symlink has been created on production during review. The preflight also found no file-content collisions among the local uploads and production destinations.

The database host configured on the production server resolves as a public-network endpoint. The workflow neither opens a port nor changes firewall rules and keeps the database password inside PHP memory, but deployment approval should be contingent on Hostinger's existing database ingress restrictions being confirmed.

The current Hostinger account was previously approved to skip a database backup. The script encodes that choice as `APPROVED-NO-BACKUP` and asks for it explicitly again. Database replacement is not transactional. If failure occurs after replacement begins, the coordinator does not retry or claim rollback; it leaves the site in maintenance mode and reports the stage and current Git commit for manual recovery. After a deployment attempt starts, private staged files are retained for inspection; remove them only after reviewing the reported state. Never invoke `database-operation.ps1 -Mode Replace` or historical patches as part of this first release.

### Resume after database import

If the initial release stops after importing the dump and leaves Laravel in maintenance mode, do not rerun the normal initial-release workflow: it would replace the database again. First inspect the reported production state and retain the exact protected staging directory. Recovery mode requires the production checkout to already equal the requested commit, a clean production working tree, maintenance mode still enabled, and a staged dump whose checksum and canonical schema validate. It compares every production table's row count with the staged dump before continuing. It does not fetch or change Git, run Composer, drop tables, or import SQL. After those checks pass it resumes additive media and Vite publication, the storage symlink check, Laravel cache refresh, protected-file checks, HTTP smoke tests, and turns maintenance mode off only after all checks pass. Any failure keeps maintenance mode enabled and retains the stage for inspection.

Use the run ID printed in the failed deployment's exact protected staging path (the 32 lowercase hexadecimal characters after `.idolranking-initial-release-`). Do not use recovery if the stage path, database verification, or maintenance state differs from the failed run.

```powershell
.\scripts\deploy\initial-release.ps1 `
  -Commit f66aa7e50ba590f66719d4b4be7c346374b75f3c `
  -BaseUrl https://orchid-oryx-674671.hostingersite.com `
  -Sha256 BA0C271DF57E377268A7FE4D9CA73FA4E2BFAD3AC4262BAC9EC819955080CACB `
  -TargetDatabase u624668418_idol `
  -ExpectedTableCount 37 `
  -ExpectedProductionTableCount 25 `
  -BackupDecision APPROVED-NO-BACKUP `
  -RecoverStageRunId 43ba9efbd7a747a9b77204879fd66eeb `
  -Approved
```

## Standard code deployment

The PowerShell entry point is intentionally code-only. SQL files are never discovered or applied by it.

```powershell
.\scripts\deploy\deploy.ps1 -Commit 8e1e1d7cc7a496945cd3988de36f4f17b9c8fffa -BaseUrl https://orchid-oryx-674671.hostingersite.com -DryRun
```

`-DryRun` is validation only: it checks that the exact commit object and required local deployment files exist. It does not create temporary files or logs, invoke npm, inspect SSH credentials, connect to production, upload files, or change production state.

Run an isolated local build separately before deployment:

```powershell
.\scripts\deploy\deploy.ps1 -Commit 8e1e1d7cc7a496945cd3988de36f4f17b9c8fffa -BaseUrl https://orchid-oryx-674671.hostingersite.com -Build
```

`-Build` creates a Git archive in a unique OS temporary directory and runs `npm ci` with a cache under that temporary directory, followed by `npm run build`. It never uses or changes the project's existing `node_modules`, `package.json`, or `package-lock.json`; archive, cache, logs, and installed dependencies are removed when the run finishes. It does not inspect SSH credentials or connect to production.

After reviewing the dry-run output, the production command is:

```powershell
.\scripts\deploy\deploy.ps1 -Commit 8e1e1d7cc7a496945cd3988de36f4f17b9c8fffa -BaseUrl https://orchid-oryx-674671.hostingersite.com
```

Use the full SHA of the approved release. The production command builds from a Git archive of that exact commit, rather than the current working tree. It transfers only `public/build`, `public/images`, and `public/favicon.ico` to a unique mode-700 staging directory under the production home. Run `-DryRun` first, then `-Build`, review the generated manifest/assets, and only then run the production command. The remote script then:

1. Requires a clean `main` checkout and takes a non-blocking deployment lock.
2. Fetches only `origin/main`, verifies the requested commit is reachable from it, and proves the update is a fast-forward from the current production commit.
3. Enters Laravel maintenance mode, applies `git merge --ff-only`, installs Composer dependencies using PHP 8.4, and refreshes Laravel caches using PHP 8.4.
4. Copies release-owned assets without `--delete`. After syncing, it explicitly applies mode 755 to directories and 644 to regular files inside only `public_html/build` and `public_html/images/flags` using `find -P` (symlink targets are not followed), then verifies those final modes. This ensures newly copied flag SVGs remain readable despite restrictive staging permissions. `rsync --chmod` is retained only as a transfer hint; it is not trusted as the final permission check. The Vite manifest is published atomically after its files, and the production `.env` checksum is verified.
5. Opens the application only after filesystem checks pass, then verifies the homepage and both ranking pages, `/fan-garden` (404), every CSS path in the deployed manifest (HTTP 200, `Content-Type: text/css`), and all seven language flag SVGs (HTTP 200, `Content-Type: image/svg+xml`). A failed page-health check re-enters maintenance mode. An asset-only HTTP failure is reported as a failed deployment but does not take an otherwise healthy application back offline.

If a failure happens before application files change, the script attempts to clear maintenance mode. If asset publication or permission validation fails after the code/cache setup, it clears maintenance mode only while it still owns the deployment lock and only after verifying the requested clean Git commit, unchanged `.env`, and healthy homepage/ranking routes. If any guard or health check fails, the site remains in maintenance mode for manual inspection. Other failures after code changes also remain fail-closed; the script never guesses at a rollback. Private staging assets are retained outside the public root for review and are not automatically removed.

The standalone initial-release and resumed-recovery workflows are historical one-time procedures, not the routine deployment path. Use them only to recover their exact matching interrupted state; they can replace the selected database in initial-release mode. Routine updates use the code-only procedure below and never run SQL.

### Public files and uploads

The production `public_html/index.php` and `.htaccess` are never copied. `APP_PUBLIC_PATH` points Laravel at `public_html`, so Vite assets go to `public_html/build`. App-owned images and the favicon are synchronized additively. No `--delete` option is used.

`robots.txt` is not synchronized because production may have site-specific crawling rules. Review it separately when SEO settings change. `public_html/storage`, storage links, uploads, private moderator evidence, server-specific configuration, and all other public-root files are untouched. User-upload synchronization is deliberately not part of an ordinary code deployment.

## Explicit SQL patch mode

Run `database-operation.ps1` separately. It accepts one conventionally named file under `sql/patches`, requires the exact SHA-256, the target database and user, `-Approved`, and an exact typed confirmation. The remote helper shares the code-deployment lock, enters maintenance mode, verifies the checksum, checks `schema_patch_history`, runs only that file, records the patch only after successful import, refreshes Laravel caches, then smoke-tests the site. By default, the server-side MariaDB client prompts for the password interactively; it is never an argument or stored in a file. The direct mode accepts only local/private database hosts and does not open a public database port.

When the production database endpoint is reachable only from the local workstation, add `-UseSshTunnel -DatabaseHost 127.0.0.1`. This mode reads only the host, port, database name, and username from the bootstrapped production Laravel configuration over the existing pinned SSH connection, requires those database identifiers to match the approved arguments, then opens an SSH local forward bound exclusively to `127.0.0.1`. It requires the local MySQL client on `PATH` and prompts for the database password locally. The password is briefly stored in a per-user ACL-protected temporary MySQL option file (never in command arguments or repository files), which is deleted in `finally`. The existing remote helper remains responsible for the deployment lock, remote checksum validation, maintenance mode, failure handling, cache refresh, and smoke tests; it holds that lock while the local MySQL client performs the ledger preflight, imports the one staged patch, and records its filename. The tunnel process is stopped on success or failure. Public database hosts are not accepted as script parameters and no firewall or database listener is changed.

### Production database backup

The approved local backup location is `D:\backup\idolranking`; its ACL must allow only the current Windows user and local Administrators. Use the existing pinned SSH identity and a local tunnel bound to `127.0.0.1`, then run the installed MySQL 8.4 `mysqldump` with `--single-transaction --quick --routines --triggers --events --hex-blob --column-statistics=0 --no-tablespaces`. A timestamped SQL dump and sidecar manifest belong only in that restricted directory. The manifest records the database identifier, timestamp, size, and SHA-256, never credentials. For the approved deployment flow, the SQL file itself is not separately encrypted; do not move it outside the restricted directory or repository-external backup storage. Verify the checksum and test restore in a uniquely named isolated temporary MySQL instance, compare table and representative row counts, then stop and remove only that temporary instance. Production restore is a separate, explicitly approved operation using the existing `database-operation.ps1 -Mode Replace` safeguards; do not use it as a patch rollback shortcut.

Example (replace the placeholders with the reviewed patch, checksum, and actual database name/user):

```powershell
.\scripts\deploy\database-operation.ps1 `
  -Mode Patch `
  -SqlFile .\sql\patches\YYYYMMDD_001_reviewed_patch.sql `
  -Sha256 <64-CHAR-SHA256> `
  -TargetDatabase <DATABASE_NAME> `
  -DatabaseUser <DATABASE_USER> `
  -BaseUrl https://orchid-oryx-674671.hostingersite.com `
  -Approved `
  -DatabaseConfirmation 'APPLY PATCH YYYYMMDD_001_reviewed_patch.sql TO <DATABASE_NAME> WITH <UPPERCASE_SHA256>'
```

MariaDB DDL can commit implicitly. A failed patch is not retried or marked applied; inspect and reconcile the database before another attempt.

## Explicit full-database replacement mode

This is a separate destructive tool and is never called by `deploy.ps1`. Supply a single-database SQL dump outside the repository, its expected SHA-256, exact target database, expected base-table count, and a backup reference. Use `APPROVED-NO-BACKUP` only when skipping a backup has been explicitly approved for that replacement. The script rejects dumps with `CREATE DATABASE`, `DROP DATABASE`, or `USE` statements, uploads to a private staging directory, verifies the checksum remotely, then requires an exact local confirmation and a second remote confirmation before dropping and recreating the named database. MariaDB asks for the password interactively. It checks the imported table count and removes only its verified staging dump after success; a failed import leaves the protected dump for recovery. The replacement has a local preflight that parses the provided plain SQL dump; validate unusual binary payloads or client-specific dump formats separately before use.

```powershell
.\scripts\deploy\database-operation.ps1 `
  -Mode Replace `
  -SqlFile C:\secure-backups\idolranking-mariadb.sql `
  -Sha256 <64-CHAR-SHA256> `
  -TargetDatabase <DATABASE_NAME> `
  -DatabaseUser <DATABASE_ADMIN_USER> `
  -BaseUrl https://orchid-oryx-674671.hostingersite.com `
  -ExpectedTableCount 37 `
  -BackupReference <VERIFIED_BACKUP_REFERENCE_OR_APPROVED-NO-BACKUP> `
  -ConfirmSha256 <SAME-64-CHAR-SHA256> `
  -Approved `
  -DatabaseConfirmation 'REPLACE DATABASE <DATABASE_NAME> FROM DUMP <UPPERCASE_SHA256> TABLES 37 BACKUP <BACKUP_REFERENCE>'
```

Database replacement is not transactional. The database can be left empty or partially imported if an operation fails. Confirm Hostinger grants permit dropping and creating the exact database and have a verified recovery plan before use. The helper holds the shared deployment lock, enters maintenance mode before the database changes, refreshes Laravel caches, and performs the same smoke tests. It does not change Git code or public assets. If a schema change or smoke test fails after the database changes, it leaves the application in maintenance mode for manual recovery.

## Current release note

Historical incident note: an initial-release recovery run once resumed after a successful database import at commit `f66aa7e50ba590f66719d4b4be7c346374b75f3c`. This is not a current production-state claim or an instruction to run recovery. Recheck production state and only follow the recovery section if the exact stage and interrupted state still exist.

The reconciled local database and release dump now match the canonical `sql/deploy/mariadb11_schema.sql`: 37 tables, 58 foreign keys, `schema_patch_history` present, and `fan_post_monthly_views` absent. The MariaDB 11.8-compatible copy was generated separately from the preserved MySQL 8.4 original; only the verified `utf8mb4_0900_ai_ci` collation was changed to `utf8mb4_unicode_ci`. Current compatible-copy SHA-256: `BA0C271DF57E377268A7FE4D9CA73FA4E2BFAD3AC4262BAC9EC819955080CACB`. The dump files are sensitive, machine-local temporary artifacts outside Git; confirm availability and checksum before any future use.
