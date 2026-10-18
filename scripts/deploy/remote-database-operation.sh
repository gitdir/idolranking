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

[[ $# -eq 10 ]] || fail 'Expected MODE DATABASE USER HOST PORT STAGED_FILE SHA256 TABLE_COUNT BACKUP_REFERENCE BASE_URL.'
MODE=$1
DATABASE=$2
DB_USER=$3
DB_HOST=$4
DB_PORT=$5
STAGED_FILE=$6
EXPECTED_SHA=${7^^}
EXPECTED_TABLES=$8
BACKUP_REFERENCE=$9
BASE_URL=${10}
APP_DIR='/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking'
PUBLIC_DIR='/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/public_html'
PHP='/opt/alt/php84/usr/bin/php'

[[ "$MODE" == 'patch' || "$MODE" == 'replace' ]] || fail 'Unknown database operation mode.'
[[ "$DATABASE" =~ ^[A-Za-z0-9_]{1,64}$ ]] || fail 'Unsafe database name.'
[[ "$DB_USER" =~ ^[A-Za-z0-9_.@-]{1,128}$ ]] || fail 'Unsafe database username.'
[[ "$DB_HOST" =~ ^(localhost|127(\.[0-9]{1,3}){3}|10(\.[0-9]{1,3}){3}|192\.168(\.[0-9]{1,3}){2}|172\.(1[6-9]|2[0-9]|3[01])(\.[0-9]{1,3}){2}|::1)$ ]] \
    || fail 'Database host must be local or private; public database endpoints are not allowed.'
[[ "$DB_PORT" =~ ^[0-9]{1,5}$ ]] && (( DB_PORT >= 1 && DB_PORT <= 65535 )) || fail 'Invalid database port.'
[[ "$EXPECTED_SHA" =~ ^[A-F0-9]{64}$ ]] || fail 'Invalid expected SHA-256.'
[[ "$EXPECTED_TABLES" =~ ^[0-9]+$ ]] || fail 'Invalid expected table count.'
[[ "$BASE_URL" =~ ^https://[A-Za-z0-9.-]+(:[0-9]{1,5})?/?$ ]] || fail 'Invalid HTTPS base URL.'
[[ "$STAGED_FILE" == "$HOME"/.idolranking-sql-staging-*/input.sql ]] \
    || fail 'SQL input is not under the protected per-run staging path.'
stage_root=${STAGED_FILE%/input.sql}
[[ -d "$stage_root" && ! -L "$stage_root" && -f "$STAGED_FILE" ]] || fail 'Protected SQL staging input is missing.'
[[ "$(stat -c '%a' "$stage_root" 2>/dev/null || true)" == '700' ]] || fail 'SQL staging directory permissions are not 700.'
command -v mariadb >/dev/null 2>&1 || fail 'MariaDB command-line client is unavailable on the server.'
command -v flock >/dev/null 2>&1 || fail 'Deployment lock utility is unavailable.'
[[ -x "$PHP" && -f "$APP_DIR/artisan" && -f "$APP_DIR/.env" ]] || fail 'Laravel application or PHP 8.4 is unavailable.'
[[ "$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" == '8.4' ]] \
    || fail 'Configured PHP executable is not PHP 8.4.'
[[ -d "$PUBLIC_DIR" && -f "$PUBLIC_DIR/index.php" && -f "$PUBLIC_DIR/build/manifest.json" ]] \
    || fail 'Laravel public root, index entry, or current Vite manifest is unavailable.'
grep -Eq '^APP_KEY=.+$' "$APP_DIR/.env" || fail 'Production APP_KEY is missing.'
grep -Fqx "APP_PUBLIC_PATH=$PUBLIC_DIR" "$APP_DIR/.env" \
    || grep -Fqx "APP_PUBLIC_PATH=\"$PUBLIC_DIR\"" "$APP_DIR/.env" \
    || fail 'APP_PUBLIC_PATH no longer points to the verified public root.'

mkdir -m 700 -p "$HOME/.idolranking-deploy"
exec 8>"$HOME/.idolranking-deploy/deploy.lock"
flock -n 8 || fail 'Another code or database deployment is already running.'

actual_sha=$(sha256sum "$STAGED_FILE" | awk '{print toupper($1)}')
[[ "$actual_sha" == "$EXPECTED_SHA" ]] || fail 'Remote SQL file checksum mismatch.'

mariadb_base=(mariadb --default-character-set=utf8mb4 --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --password)

env_checksum=$(sha256sum "$APP_DIR/.env" | awk '{print $1}')
maintenance_active=0
database_mutated=0
database_cleanup() {
    result=$?
    trap - EXIT INT TERM
    if (( result != 0 && maintenance_active == 1 )); then
        if (( database_mutated == 0 )); then
            if "$PHP" "$APP_DIR/artisan" up >/dev/null 2>&1; then
                log 'Maintenance mode cleared because the database had not been changed.'
            else
                log 'WARNING: could not clear maintenance mode; inspect the site manually.'
            fi
        else
            log 'Database operation failed after changes began; maintenance mode remains enabled for manual recovery.'
        fi
    fi
    exit "$result"
}
trap database_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

enter_maintenance() {
    maintenance_active=1
    "$PHP" "$APP_DIR/artisan" down --retry=60
}

refresh_laravel_caches() {
    "$PHP" "$APP_DIR/artisan" optimize:clear
    "$PHP" "$APP_DIR/artisan" config:cache
    "$PHP" "$APP_DIR/artisan" route:cache
    "$PHP" "$APP_DIR/artisan" view:cache
}

smoke_tests() {
    base_url=${BASE_URL%/}
    smoke_fail() {
        log "$1"
        maintenance_active=1
        if ! "$PHP" "$APP_DIR/artisan" down --retry=60 >/dev/null 2>&1; then
            maintenance_active=0
            log 'WARNING: smoke test failed and maintenance mode could not be re-enabled.'
        fi
        fail 'Post-database smoke test failed; inspect before reopening the site.'
    }
    check_2xx() {
        label=$1
        url=$2
        code=$(curl --silent --show-error --location --max-time 30 --output /dev/null --write-out '%{http_code}' "$url") \
            || smoke_fail "Smoke test request failed: $label."
        [[ "$code" =~ ^2[0-9]{2}$ ]] || smoke_fail "Smoke test returned HTTP $code: $label."
        log "Smoke test passed: $label (HTTP $code)."
    }
    check_2xx 'homepage' "$base_url/"
    check_2xx 'idol rankings' "$base_url/ranking/idol"
    check_2xx 'group rankings' "$base_url/ranking/group"
    garden_code=$(curl --silent --show-error --max-time 30 --output /dev/null --write-out '%{http_code}' "$base_url/fan-garden") \
        || smoke_fail 'Fan Garden smoke test request failed.'
    [[ "$garden_code" == '404' ]] || smoke_fail "Fan Garden should return 404, got HTTP $garden_code."
    log 'Smoke test passed: /fan-garden remains HTTP 404.'
    asset_path=$("$PHP" -r '$manifest=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); foreach ($manifest as $entry) { if (isset($entry["file"])) { echo $entry["file"]; exit(0); } } exit(1);' "$PUBLIC_DIR/build/manifest.json") \
        || smoke_fail 'Could not read the published Vite manifest.'
    [[ "$asset_path" == assets/* && "$asset_path" != *'..'* ]] || smoke_fail 'Unsafe asset path in the Vite manifest.'
    check_2xx 'Vite asset' "$base_url/build/$asset_path"
}

if [[ "$MODE" == 'patch' ]]; then
    # The wrapper has already verified the repository filename and checksum. Keep it as an explicit argument.
    [[ "$BACKUP_REFERENCE" == patch:* ]] || fail 'Patch filename token is missing.'
    patch_name=${BACKUP_REFERENCE#patch:}
    [[ "$patch_name" =~ ^[0-9]{8}_[0-9]{3}_[A-Za-z0-9_-]+\.sql$ ]] || fail 'Patch name validation failed.'
    phrase="APPLY PATCH $patch_name TO $DATABASE WITH $EXPECTED_SHA"
    printf 'Selected patch: %s\nTarget database: %s\nChecksum: %s\n' "$patch_name" "$DATABASE" "$EXPECTED_SHA"
    read -r -p "Type exactly to authorize: $phrase " typed_phrase
    [[ "$typed_phrase" == "$phrase" ]] || fail 'Patch confirmation did not match.'

    count=$("${mariadb_base[@]}" --database="$DATABASE" --batch --skip-column-names \
        --execute="SELECT COUNT(*) FROM schema_patch_history WHERE patch_name = '$patch_name';") \
        || fail 'Could not check schema_patch_history; patch was not run.'
    [[ "$count" == '0' ]] || fail 'Patch is already recorded; inspect the schema before any retry.'

    enter_maintenance
    database_mutated=1
    log "Applying the single selected patch $patch_name."
    "${mariadb_base[@]}" --database="$DATABASE" < "$STAGED_FILE" \
        || fail 'Patch import failed. DDL may have partially committed; inspect before retrying.'
    "${mariadb_base[@]}" --database="$DATABASE" \
        --execute="INSERT INTO schema_patch_history (patch_name, applied_by, notes) VALUES ('$patch_name', 'codex-deploy', 'Reviewed single-patch deployment');" \
        || fail 'Patch ran but ledger insertion failed; inspect before retrying.'
    refresh_laravel_caches
    after_env_checksum=$(sha256sum "$APP_DIR/.env" | awk '{print $1}')
    [[ "$env_checksum" == "$after_env_checksum" ]] || fail '.env changed unexpectedly during patch deployment.'
    "$PHP" "$APP_DIR/artisan" up
    maintenance_active=0
    smoke_tests
    log 'Selected patch and schema_patch_history entry completed.'
    exit 0
fi

(( EXPECTED_TABLES > 0 )) || fail 'Full replacement requires an expected table count.'
[[ "$BACKUP_REFERENCE" =~ ^[A-Za-z0-9._:/-]{1,160}$ ]] || fail 'Backup reference is missing or invalid.'
if grep -Eiq '^[[:space:]]*(CREATE|DROP)[[:space:]]+DATABASE\b|^[[:space:]]*USE[[:space:]]+' "$STAGED_FILE"; then
    fail 'Dump contains database-level statements; only a single-database dump is accepted.'
fi

phrase="REPLACE DATABASE $DATABASE FROM DUMP $EXPECTED_SHA TABLES $EXPECTED_TABLES BACKUP $BACKUP_REFERENCE"
printf 'WARNING: this will drop and recreate the selected production database.\nTarget database: %s\nDump SHA-256: %s\nExpected tables: %s\n' \
    "$DATABASE" "$EXPECTED_SHA" "$EXPECTED_TABLES"
read -r -p "Type exactly to authorize: $phrase " typed_phrase
[[ "$typed_phrase" == "$phrase" ]] || fail 'Full replacement confirmation did not match.'

# Database DDL is not transactional. The caller must verify the backup and target before this point.
enter_maintenance
database_mutated=1
ddl="DROP DATABASE IF EXISTS \`$DATABASE\`; CREATE DATABASE \`$DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"${mariadb_base[@]}" --execute="$ddl" || fail 'Database replacement DDL failed. Restore from the verified backup and inspect manually.'
"${mariadb_base[@]}" --database="$DATABASE" < "$STAGED_FILE" \
    || fail 'Dump import failed after database replacement. The database may be partial; restore or reconcile manually.'

table_count=$("${mariadb_base[@]}" --database="$DATABASE" --batch --skip-column-names \
    --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DATABASE' AND table_type = 'BASE TABLE';") \
    || fail 'Could not verify imported table count.'
[[ "$table_count" == "$EXPECTED_TABLES" ]] || fail "Imported table count $table_count does not match expected $EXPECTED_TABLES."

refresh_laravel_caches
after_env_checksum=$(sha256sum "$APP_DIR/.env" | awk '{print $1}')
[[ "$env_checksum" == "$after_env_checksum" ]] || fail '.env changed unexpectedly during replacement.'
"$PHP" "$APP_DIR/artisan" up
maintenance_active=0
smoke_tests

# Remove only the verified private staging dump after a successful import.
rm -f -- "$STAGED_FILE"
log "Full replacement completed and table count $table_count was verified."
