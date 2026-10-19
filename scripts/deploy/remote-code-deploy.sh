#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

log() {
    printf '[%s] %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$1"
}

fail() {
    log "ERROR: $1"
    exit 1
}

if [[ $# -ne 6 ]]; then
    fail 'Expected APP_DIR PUBLIC_DIR COMMIT BASE_URL ASSET_STAGE MODE.'
fi

APP_DIR=$1
PUBLIC_DIR=$2
RELEASE_COMMIT=$3
BASE_URL=$4
ASSET_STAGE=$5
MODE=$6
base_url=${BASE_URL%/}

EXPECTED_APP='/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking'
EXPECTED_PUBLIC='/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/public_html'
PHP='/opt/alt/php84/usr/bin/php'
COMPOSER='/usr/local/bin/composer'

[[ "$APP_DIR" == "$EXPECTED_APP" ]] || fail 'Application path does not match the verified production path.'
[[ "$PUBLIC_DIR" == "$EXPECTED_PUBLIC" ]] || fail 'Public path does not match the verified production path.'
[[ "$RELEASE_COMMIT" =~ ^[0-9a-f]{40}$ ]] || fail 'Release commit must be a full lowercase SHA-1.'
[[ "$BASE_URL" =~ ^https://[A-Za-z0-9.-]+(:[0-9]{1,5})?/?$ ]] || fail 'Base URL must be a plain HTTPS origin.'
[[ "$MODE" == 'dry-run' || "$MODE" == 'deploy' ]] || fail 'Unknown deployment mode.'

for command in git rsync flock curl sha256sum stat install find grep awk; do
    command -v "$command" >/dev/null 2>&1 || fail "Required command is unavailable: $command"
done
[[ -x "$PHP" ]] || fail 'PHP 8.4 executable is unavailable.'
[[ -x "$COMPOSER" ]] || fail 'Composer executable is unavailable.'
php_version=$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
[[ "$php_version" == '8.4' ]] || fail 'Configured PHP executable is not PHP 8.4.'
"$PHP" "$COMPOSER" --version --no-ansi >/dev/null \
    || fail 'Composer cannot run through PHP 8.4.'
[[ -d "$APP_DIR/.git" && -f "$APP_DIR/artisan" ]] || fail 'Laravel Git checkout is unavailable.'
[[ -f "$APP_DIR/.env" ]] || fail 'Production .env is missing; refusing to continue.'
grep -Eq '^APP_KEY=.+$' "$APP_DIR/.env" || fail 'Production APP_KEY is missing; refusing to continue.'
[[ -d "$PUBLIC_DIR" && -f "$PUBLIC_DIR/index.php" ]] || fail 'The existing public entry point is missing.'
grep -Fqx "APP_PUBLIC_PATH=$PUBLIC_DIR" "$APP_DIR/.env" \
    || grep -Fqx "APP_PUBLIC_PATH=\"$PUBLIC_DIR\"" "$APP_DIR/.env" \
    || fail 'APP_PUBLIC_PATH no longer points to the verified public web root.'

cd "$APP_DIR"
branch=$(git branch --show-current)
[[ "$branch" == 'main' ]] || fail "Expected branch main; found $branch."

status=$(git status --porcelain=v1 --untracked-files=all)
[[ -z "$status" ]] || fail 'Production Git working tree is not clean; no files were changed.'

current_commit=$(git rev-parse HEAD)
env_checksum=$(sha256sum "$APP_DIR/.env" | awk '{print $1}')
log "Production branch: $branch"
log "Current commit: $current_commit"
log "Requested release: $RELEASE_COMMIT"

if [[ "$MODE" == 'dry-run' ]]; then
    log 'Dry run: no fetch, Git change, file transfer, maintenance mode, cache command, or SQL operation will run.'
    if git cat-file -e "$RELEASE_COMMIT^{commit}" 2>/dev/null; then
        if git show-ref --verify --quiet refs/remotes/origin/main; then
            git merge-base --is-ancestor "$RELEASE_COMMIT" refs/remotes/origin/main \
                || fail 'The available release object is not reachable from the locally known origin/main.'
            log 'Release object is reachable from the currently cached origin/main ref.'
        else
            log 'No cached origin/main ref is available; real deployment will fetch and validate it.'
        fi
        if [[ "$current_commit" == "$RELEASE_COMMIT" ]]; then
            log 'Requested commit is already deployed.'
        else
            git merge-base --is-ancestor "$current_commit" "$RELEASE_COMMIT" \
                || fail 'The requested release is not a fast-forward from the current production commit.'
            log 'Current production commit can fast-forward to the requested release.'
        fi
    else
        log 'Release object is not present locally; real deployment must fetch it and verify origin/main reachability.'
    fi
    [[ -d "$PUBLIC_DIR/build" ]] || log 'The Vite target directory will be created during a real deployment.'
    [[ -d "$PUBLIC_DIR/images" ]] || log 'The application images directory will be created during a real deployment.'
    log 'Dry-run preflight passed.'
    exit 0
fi

if [[ $# -ne 6 || "$ASSET_STAGE" != "$HOME"/.idolranking-staging-*/build ]]; then
    fail 'Asset stage must be a private per-run directory under the production home.'
fi
stage_root=${ASSET_STAGE%/build}
[[ -d "$ASSET_STAGE" && ! -L "$ASSET_STAGE" && ! -L "$stage_root" ]] \
    || fail 'Private asset staging directory is missing or is a symlink.'
stage_mode=$(stat -c '%a' "$stage_root" 2>/dev/null || true)
[[ "$stage_mode" == '700' ]] || fail 'Private staging directory permissions are not 700.'
[[ -f "$ASSET_STAGE/manifest.json" ]] || fail 'Staged Vite manifest is missing.'
[[ -d "$stage_root/images" && -f "$stage_root/favicon.ico" ]] \
    || fail 'Staged application images or favicon are missing.'
[[ -f "$APP_DIR/composer.json" && -f "$APP_DIR/composer.lock" ]] \
    || fail 'Composer definition or lock file is missing.'
if find "$stage_root" -type l -print -quit | grep -q .; then
    fail 'Staged assets unexpectedly contain symlinks.'
fi

mkdir -m 700 -p "$HOME/.idolranking-deploy"
exec 9>"$HOME/.idolranking-deploy/deploy.lock"
flock -n 9 || fail 'Another deployment is already running.'
deployment_lock_held=1

maintenance_active=0
release_mutated=0
application_ready=0
cleanup() {
    result=$?
    trap - EXIT INT TERM
    if (( result != 0 )); then
        log 'Deployment stopped after an error.'
        if (( maintenance_active == 1 )); then
            if (( release_mutated == 0 )); then
                if "$PHP" artisan up >/dev/null 2>&1; then
                    log 'Maintenance mode was cleared because release files had not changed.'
                else
                    log 'WARNING: automatic maintenance recovery failed; inspect the site manually.'
                fi
            else
                log 'Maintenance mode was left enabled after release changes; inspect and recover manually.'
            fi
        fi
    fi
    exit "$result"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

maintenance_status() {
    "$PHP" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo $app->isDownForMaintenance() ? "down" : "up";'
}

initial_maintenance_status=$(maintenance_status) \
    || fail 'Could not determine the current Laravel maintenance state.'
[[ "$initial_maintenance_status" == 'up' ]] \
    || fail 'The application is already in maintenance mode; refusing to take over or later clear it.'

recovery_http_check() {
    local path code
    for path in / /ranking/idol /ranking/group; do
        code=$(curl --silent --show-error --location --max-time 30 --output /dev/null \
            --write-out '%{http_code}' "$base_url$path") || return 1
        [[ "$code" =~ ^2[0-9]{2}$ ]] || return 1
    done
}

# Asset failures should not strand a healthy application in maintenance mode. Reopen only
# after the exclusive lock, completed Laravel setup, clean release, and preserved .env pass.
asset_failure() {
    local message=$1 after_env_checksum
    log "ERROR: $message"

    if (( maintenance_active == 0 )); then
        fail "$message Application routes already passed; leaving the site online."
    fi
    (( application_ready == 1 && deployment_lock_held == 1 )) \
        || fail "$message Application is not in a verified safe-to-reopen state; maintenance remains enabled."
    [[ -f "$APP_DIR/storage/framework/down" ]] \
        || fail "$message Maintenance state changed unexpectedly; refusing automatic recovery."
    [[ "$(git rev-parse HEAD)" == "$RELEASE_COMMIT" ]] \
        || fail "$message Production HEAD changed unexpectedly; refusing automatic recovery."
    [[ -z "$(git status --porcelain=v1 --untracked-files=all)" ]] \
        || fail "$message Production working tree is not clean; refusing automatic recovery."
    after_env_checksum=$(sha256sum "$APP_DIR/.env" | awk '{print $1}')
    [[ "$env_checksum" == "$after_env_checksum" ]] \
        || fail "$message Production .env changed; refusing automatic recovery."
    maintenance_state=$(maintenance_status) \
        || fail "$message Could not confirm the maintenance state; refusing automatic recovery."
    [[ "$maintenance_state" == 'down' ]] \
        || fail "$message Maintenance state changed unexpectedly; refusing automatic recovery."

    if ! "$PHP" artisan up; then
        maintenance_state=$(maintenance_status 2>/dev/null || true)
        [[ "$maintenance_state" == 'up' ]] && maintenance_active=0
        fail "$message Could not safely clear maintenance mode."
    fi
    maintenance_active=0
    maintenance_state=$(maintenance_status) \
        || fail "$message Could not verify the state after artisan up."
    [[ "$maintenance_state" == 'up' ]] \
        || { maintenance_active=1; fail "$message Laravel remains in maintenance mode after artisan up."; }

    if recovery_http_check; then
        log 'WARNING: asset publication failed, but application routes are healthy; site remains online for manual asset repair.'
        exit 1
    fi

    log 'Application health check failed after asset recovery; restoring maintenance mode.'
    if "$PHP" artisan down --retry=60; then
        maintenance_active=1
    else
        log 'WARNING: could not restore maintenance mode after failed application health checks.'
    fi
    fail "$message Application health recovery failed."
}

normalize_build_permissions() {
    local unsafe_path
    [[ -d "$PUBLIC_DIR/build" && ! -L "$PUBLIC_DIR/build" ]] || return 1

    # -P prevents traversal into symlink targets; only this managed public build tree is touched.
    find -P "$PUBLIC_DIR/build" -type d -exec chmod 755 -- {} + || return 1
    find -P "$PUBLIC_DIR/build" -type f -exec chmod 644 -- {} + || return 1

    unsafe_path=$(find -P "$PUBLIC_DIR/build" -type d ! -perm 755 -print -quit) || return 1
    [[ -z "$unsafe_path" ]] || { log "Unexpected build directory mode: $unsafe_path"; return 1; }
    unsafe_path=$(find -P "$PUBLIC_DIR/build" -type f ! -perm 644 -print -quit) || return 1
    [[ -z "$unsafe_path" ]] || { log "Unexpected build file mode: $unsafe_path"; return 1; }
    log 'Verified public/build directories are mode 755 and regular files are mode 644.'
}

# Fetch only the named main branch. No force refspec or reset is used.
GIT_SSH_COMMAND='ssh -i /home/u624668418/.ssh/idolranking_deploy -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=/home/u624668418/.ssh/known_hosts' \
    git fetch --no-tags origin refs/heads/main:refs/remotes/origin/main
git cat-file -e "$RELEASE_COMMIT^{commit}" 2>/dev/null \
    || fail 'Requested release commit was not fetched from origin/main.'
git merge-base --is-ancestor "$RELEASE_COMMIT" refs/remotes/origin/main \
    || fail 'Requested release commit is not reachable from origin/main.'

if [[ "$current_commit" != "$RELEASE_COMMIT" ]]; then
    git merge-base --is-ancestor "$current_commit" "$RELEASE_COMMIT" \
        || fail 'Requested release is behind or diverged from production; refusing non-fast-forward deployment.'
fi

maintenance_active=1
"$PHP" artisan down --retry=60
maintenance_state=$(maintenance_status) \
    || fail 'Could not verify Laravel entered maintenance mode.'
[[ "$maintenance_state" == 'down' ]] \
    || fail 'Laravel did not enter maintenance mode; stopping before release changes.'

if [[ "$current_commit" != "$RELEASE_COMMIT" ]]; then
    git merge --ff-only "$RELEASE_COMMIT"
    release_mutated=1
fi

composer_changes=$(git diff --name-only "$current_commit" "$RELEASE_COMMIT" -- composer.json composer.lock)
if [[ -n "$composer_changes" ]]; then
    log 'Composer definitions changed; installing production dependencies with PHP 8.4.'
    release_mutated=1
    "$PHP" "$COMPOSER" install --working-dir="$APP_DIR" --no-dev --optimize-autoloader --no-interaction --prefer-dist
else
    log 'Composer definitions are unchanged; skipping dependency installation.'
fi

log 'Refreshing Laravel caches with PHP 8.4.'
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

after_env_checksum=$(sha256sum "$APP_DIR/.env" | awk '{print $1}')
[[ "$env_checksum" == "$after_env_checksum" ]] \
    || fail 'Production .env changed unexpectedly; APP_KEY preservation could not be verified.'
[[ -z "$(git status --porcelain=v1 --untracked-files=all)" ]] \
    || fail 'Production Git working tree changed unexpectedly after code setup.'
application_ready=1

# Publish only release-owned public assets. No --delete is used, and uploads are untouched.
[[ ! -L "$PUBLIC_DIR/build" && ! -L "$PUBLIC_DIR/images" ]] \
    || asset_failure 'A managed public asset destination is a symlink; refusing to follow it.'
if ! mkdir -p "$PUBLIC_DIR/build" "$PUBLIC_DIR/images"; then
    asset_failure 'Could not create the managed public asset directories.'
fi
existing_build_symlink=$(find -P "$PUBLIC_DIR/build" -type l -print -quit) \
    || asset_failure 'Could not inspect the managed public/build tree for symlinks.'
if [[ -n "$existing_build_symlink" ]]; then
    asset_failure 'The managed public/build tree contains a symlink; refusing to risk writing through it.'
fi
if ! rsync -rlt --checksum --omit-dir-times --no-owner --no-group --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
    --exclude='manifest.json' "$ASSET_STAGE/" "$PUBLIC_DIR/build/"; then
    asset_failure 'Could not publish staged Vite assets.'
fi
if ! rsync -rlt --checksum --omit-dir-times --no-owner --no-group --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
    "$stage_root/images/" "$PUBLIC_DIR/images/"; then
    asset_failure 'Could not publish staged application images.'
fi
if ! chmod 755 -- "$PUBLIC_DIR/images"; then
    asset_failure 'Could not set the public images directory mode.'
fi
if ! install -m 644 "$stage_root/favicon.ico" "$PUBLIC_DIR/.idolranking-favicon.$$.tmp" \
    || ! mv -f -- "$PUBLIC_DIR/.idolranking-favicon.$$.tmp" "$PUBLIC_DIR/favicon.ico"; then
    asset_failure 'Could not publish the staged favicon.'
fi
if ! install -m 644 "$ASSET_STAGE/manifest.json" "$PUBLIC_DIR/build/.manifest.$$.tmp" \
    || ! mv -f -- "$PUBLIC_DIR/build/.manifest.$$.tmp" "$PUBLIC_DIR/build/manifest.json"; then
    asset_failure 'Could not publish the Vite manifest.'
fi
if ! normalize_build_permissions; then
    asset_failure 'Could not set or verify public/build filesystem permissions.'
fi

if ! css_assets=$("$PHP" -r '
$manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$css = [];
foreach ($manifest as $entry) {
    if (isset($entry["file"]) && str_ends_with($entry["file"], ".css")) $css[] = $entry["file"];
    foreach ($entry["css"] ?? [] as $file) $css[] = $file;
}
$css = array_values(array_unique($css));
if (!$css) exit(1);
foreach ($css as $file) {
    if (!is_string($file) || !preg_match("#^assets/[A-Za-z0-9._/-]+\\.css$#", $file) || str_contains($file, "..")) exit(2);
    echo $file, "\n";
}
' "$PUBLIC_DIR/build/manifest.json"); then
    asset_failure 'Could not read safe CSS paths from the published Vite manifest.'
fi
while IFS= read -r css_asset; do
    css_path="$PUBLIC_DIR/build"
    IFS='/' read -r -a css_components <<< "$css_asset"
    for css_component in "${css_components[@]}"; do
        css_path="$css_path/$css_component"
        [[ ! -L "$css_path" ]] \
            || asset_failure 'A manifest CSS path contains a symlink; refusing to follow it.'
    done
    [[ -f "$css_path" ]] || asset_failure 'A manifest CSS file is missing.'
    css_mode=$(stat -c '%a' -- "$css_path") \
        || asset_failure 'Could not inspect a manifest CSS file mode.'
    [[ "$css_mode" == '644' ]] \
        || asset_failure 'A manifest CSS file is not mode 644 after permission normalization.'
done <<< "$css_assets"

"$PHP" artisan up
maintenance_state=$(maintenance_status) \
    || fail 'Could not verify Laravel left maintenance mode.'
[[ "$maintenance_state" == 'up' ]] \
    || fail 'Laravel remains in maintenance mode after artisan up.'
maintenance_active=0

check_2xx() {
    label=$1
    url=$2
    code=$(curl --silent --show-error --location --max-time 30 --output /dev/null --write-out '%{http_code}' "$url") \
        || smoke_fail "Smoke test request failed: $label."
    [[ "$code" =~ ^2[0-9]{2}$ ]] || smoke_fail "Smoke test returned HTTP $code: $label."
    log "Smoke test passed: $label (HTTP $code)."
}

smoke_fail() {
    log "$1"
    maintenance_active=1
    if ! "$PHP" artisan down --retry=60 >/dev/null 2>&1; then
        maintenance_state=$(maintenance_status 2>/dev/null || true)
        if [[ "$maintenance_state" == 'down' ]]; then
            maintenance_active=1
            log 'Maintenance mode is active despite the artisan down command error.'
        else
            maintenance_active=0
            log 'WARNING: smoke test failed and maintenance mode could not be re-enabled.'
        fi
    else
        maintenance_active=1
    fi
    fail 'Post-deployment smoke test failed; inspect before reopening the site.'
}

check_2xx 'homepage' "$base_url/"
check_2xx 'idol rankings' "$base_url/ranking/idol"
check_2xx 'group rankings' "$base_url/ranking/group"

garden_code=$(curl --silent --show-error --max-time 30 --output /dev/null --write-out '%{http_code}' "$base_url/fan-garden") \
    || smoke_fail 'Fan Garden smoke test request failed.'
[[ "$garden_code" == '404' ]] || smoke_fail "Fan Garden should return 404, but returned HTTP $garden_code."
log 'Smoke test passed: /fan-garden remains HTTP 404.'

while IFS= read -r css_asset; do
    response=$(curl --silent --show-error --location --max-time 30 --output /dev/null \
        --write-out '%{http_code} %{content_type}' "$base_url/build/$css_asset") \
        || asset_failure 'Vite CSS request failed.'
    code=${response%% *}
    content_type=${response#* }
    [[ "$code" == '200' ]] || asset_failure "Vite CSS returned HTTP $code."
    case "$content_type" in text/css*) ;; *) asset_failure 'Vite CSS response has an unexpected Content-Type.' ;; esac
    log "Smoke test passed: Vite CSS $css_asset (HTTP 200, text/css)."
done <<< "$css_assets"

log "Deployment completed successfully at $RELEASE_COMMIT."
