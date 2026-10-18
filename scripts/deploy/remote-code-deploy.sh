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

maintenance_active=0
release_mutated=0
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

# Fetch only the named main branch. No force refspec or reset is used.
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

if [[ "$current_commit" != "$RELEASE_COMMIT" ]]; then
    git merge --ff-only "$RELEASE_COMMIT"
    release_mutated=1
fi

log 'Installing production Composer dependencies with PHP 8.4.'
release_mutated=1
"$PHP" "$COMPOSER" install --working-dir="$APP_DIR" --no-dev --optimize-autoloader --no-interaction --prefer-dist

log 'Refreshing Laravel caches with PHP 8.4.'
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

# Publish only release-owned public assets. No --delete is used, and uploads are untouched.
mkdir -p "$PUBLIC_DIR/build" "$PUBLIC_DIR/images"
rsync -rlt --checksum --omit-dir-times --no-owner --no-group --no-perms \
    --exclude='manifest.json' "$ASSET_STAGE/" "$PUBLIC_DIR/build/"
rsync -rlt --checksum --omit-dir-times --no-owner --no-group --no-perms \
    "$stage_root/images/" "$PUBLIC_DIR/images/"
install -m 644 "$stage_root/favicon.ico" "$PUBLIC_DIR/.idolranking-favicon.$$.tmp"
mv -f -- "$PUBLIC_DIR/.idolranking-favicon.$$.tmp" "$PUBLIC_DIR/favicon.ico"
install -m 644 "$ASSET_STAGE/manifest.json" "$PUBLIC_DIR/build/.manifest.$$.tmp"
mv -f -- "$PUBLIC_DIR/build/.manifest.$$.tmp" "$PUBLIC_DIR/build/manifest.json"

after_env_checksum=$(sha256sum "$APP_DIR/.env" | awk '{print $1}')
[[ "$env_checksum" == "$after_env_checksum" ]] \
    || fail 'Production .env changed unexpectedly; APP_KEY preservation could not be verified.'

"$PHP" artisan up
maintenance_active=0

base_url=${BASE_URL%/}
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
        maintenance_active=0
        log 'WARNING: smoke test failed and maintenance mode could not be re-enabled.'
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

if ! asset_path=$("$PHP" -r '$manifest=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); foreach ($manifest as $entry) { if (isset($entry["file"])) { echo $entry["file"]; exit(0); } } exit(1);' "$PUBLIC_DIR/build/manifest.json"); then
    smoke_fail 'Could not read a Vite asset path from the published manifest.'
fi
[[ "$asset_path" == assets/* && "$asset_path" != *'..'* ]] || smoke_fail 'Could not identify a safe Vite asset path from the manifest.'
check_2xx 'Vite asset' "$base_url/build/$asset_path"

log "Deployment completed successfully at $RELEASE_COMMIT."
