#!/usr/bin/env bash

# One-time coordinated release. This helper is sent over SSH and is not stored on the host.
set -Eeuo pipefail
umask 077

APP_DIR='/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking'
PUBLIC_DIR='/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/public_html'
PHP='/opt/alt/php84/usr/bin/php'
COMPOSER='/usr/local/bin/composer'
EXPECTED_DB='u624668418_idol'
EXPECTED_SHA='BA0C271DF57E377268A7FE4D9CA73FA4E2BFAD3AC4262BAC9EC819955080CACB'
EXPECTED_URL='https://orchid-oryx-674671.hostingersite.com'
EXPECTED_TABLE_NAMES='admin_activity_logs,admin_dummy_activity_requests,blocked_ips,daily_check_in_translations,daily_check_ins,fan_post_comment_reports,fan_post_comment_translations,fan_post_comments,fan_post_likes,fan_post_reports,fan_post_tags,fan_post_translations,fan_posts,heart_histories,heart_reward_settings,idol_debuts,idol_group_members,idol_localizations,idol_name_aliases,idol_profile_image_reports,idol_registration_request_debuts,idol_registration_request_localizations,idol_registration_requests,idol_votes,idols,member_access_logs,member_login_histories,member_social_accounts,members,partner_banner_clicks,partner_banners,password_reset_tokens,restricted_nicknames,schema_patch_history,site_settings,visitor_daily_stats,visitor_daily_uniques'

log() { printf '[%s] %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$1"; }
fail() {
    log "ERROR: $1"
    if declare -F on_error >/dev/null 2>&1; then on_error 1; else exit 1; fi
}

[[ $# -eq 15 ]] || fail 'Expected MODE COMMIT URL DATABASE OLD_TABLES TABLES FKS SHA PUBLIC_COUNT PUBLIC_BYTES PRIVATE_COUNT PRIVATE_BYTES STAGE BASE RUN_ID.'
MODE=$1; COMMIT=$2; BASE_URL=$3; DATABASE=$4; OLD_TABLES=$5; TABLES=$6; FKS=$7; SHA=$8
PUBLIC_COUNT=$9; PUBLIC_BYTES=${10}; PRIVATE_COUNT=${11}; PRIVATE_BYTES=${12}; STAGE=${13}; BASE_COMMIT=${14}; RUN_ID=${15}
[[ "$COMMIT" =~ ^[a-f0-9]{40}$ ]] || fail 'Target commit must be a full lowercase 40-character SHA.'
[[ "$BASE_URL" == "$EXPECTED_URL" && "$DATABASE" == "$EXPECTED_DB" ]] || fail 'Release identity differs from the reviewed values.'
[[ "$OLD_TABLES" == 25 && "$TABLES" == 37 && "$FKS" == 58 && "$SHA" == "$EXPECTED_SHA" ]] || fail 'Database inventory differs from the reviewed values.'
for value in "$PUBLIC_COUNT" "$PUBLIC_BYTES" "$PRIVATE_COUNT" "$PRIVATE_BYTES"; do [[ "$value" =~ ^[0-9]+$ ]] || fail 'Invalid staged media inventory.'; done
[[ "$RUN_ID" =~ ^[a-f0-9]{32}$ ]] || fail 'Invalid run identifier.'
[[ "$BASE_COMMIT" == '-' || "$BASE_COMMIT" =~ ^[a-f0-9]{40}$ ]] || fail 'Invalid base commit.'
[[ "$MODE" == preflight || "$MODE" == validate-stage || "$MODE" == deploy ]] || fail 'Unknown coordinator mode.'

for command in git flock curl sha256sum stat find grep awk rsync base64 sed readlink wc tr paste install mv mkdir ln date; do
    command -v "$command" >/dev/null 2>&1 || fail "Required command unavailable: $command"
done
[[ -x "$PHP" && -x "$COMPOSER" ]] || fail 'PHP 8.4 or Composer executable unavailable.'
[[ "$($PHP -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" == 8.4 ]] || fail 'Configured PHP is not version 8.4.'
[[ -f "$APP_DIR/artisan" && -d "$APP_DIR/.git" && -f "$APP_DIR/.env" ]] || fail 'Laravel application structure is incomplete.'
[[ -d "$PUBLIC_DIR" && -f "$PUBLIC_DIR/index.php" && -f "$PUBLIC_DIR/.htaccess" ]] || fail 'Existing public entry point or Apache config is missing.'
grep -Eq '^APP_KEY=.+$' "$APP_DIR/.env" || fail 'Production APP_KEY is missing.'
grep -Fqx "APP_PUBLIC_PATH=$PUBLIC_DIR" "$APP_DIR/.env" || grep -Fqx "APP_PUBLIC_PATH=\"$PUBLIC_DIR\"" "$APP_DIR/.env" || fail 'APP_PUBLIC_PATH differs from the verified public root.'

cd "$APP_DIR"
[[ "$(git branch --show-current)" == main ]] || fail 'Production checkout is not on main.'
[[ -z "$(git status --porcelain=v1 --untracked-files=all)" ]] || fail 'Production Git working tree is not clean.'
CURRENT=$(git rev-parse HEAD)

# Read environment values without requiring Composer/vendor before the first deployment.
# Secrets stay in PHP memory; supported operations emit only safe metadata or status.
DB_HELPER=$(cat <<'PHP'
set_error_handler(static function ($severity, $message) { throw new ErrorException('environment parse failed'); });
try {
    $root = $argv[1]; $target = $argv[2]; $mode = $argv[3];
    $envLines = file($root.'/.env', FILE_IGNORE_NEW_LINES);
    restore_error_handler();
    if (!is_array($envLines)) { exit(70); }
    $fileValues = [];
    $dbKeys = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
    foreach ($envLines as $line) {
        if (!preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/', $line, $match)
            || !in_array($match[1], $dbKeys, true)) { continue; }
        $raw = $match[2];
        if ($raw !== '' && ($raw[0] === '"' || $raw[0] === "'")) {
            $quote = $raw[0];
            $pattern = $quote === '"' ? '/^"((?:\\\\.|[^"\\\\])*)"(?:[ \t]*(?:#.*)?)$/' : "/^'((?:\\\\.|[^'\\\\])*)'(?:[ \\t]*(?:#.*)?)$/";
            if (!preg_match($pattern, $raw, $quoted)) { exit(70); }
            $value = $quoted[1];
            if ($quote === '"') {
                $value = strtr($value, ['\\\\' => "\\", '\\"' => '"', '\\n' => "\n", '\\r' => "\r"]);
            } else {
                $value = strtr($value, ["\\\\" => "\\", "\\'" => "'"]);
            }
        } else {
            $value = trim((string)preg_replace('/[ \t]+#.*$/', '', $raw));
        }
        // Fail closed rather than guess at dotenv variable interpolation.
        if (str_contains($value, '${')) { exit(70); }
        $fileValues[$match[1]] = $value;
    }
    $value = static function (string $key, ?string $default = null) use ($fileValues): ?string {
        if (array_key_exists($key, $_ENV)) return (string)$_ENV[$key];
        if (array_key_exists($key, $_SERVER)) return (string)$_SERVER[$key];
        $fromProcess = getenv($key);
        if ($fromProcess !== false) return (string)$fromProcess;
        return array_key_exists($key, $fileValues) ? (string)$fileValues[$key] : $default;
    };
    $host = $value('DB_HOST', 'localhost'); if ($host === '') $host = 'localhost';
    $port = $value('DB_PORT', '3306'); $database = $value('DB_DATABASE', '');
    $user = $value('DB_USERNAME', ''); $password = $value('DB_PASSWORD', '');
    if ($database !== $target || $user === '' || !preg_match('/^(?:[A-Za-z0-9.-]{1,253}|[0-9a-fA-F:]{2,45})$/', $host) || !ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) exit(71);
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $user, $password ?? '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    if ($mode === 'metadata') {
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        $tables = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type=0x42415345205441424c45')->fetchColumn();
        $fks = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE()')->fetchColumn();
        $views = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type=0x56494557')->fetchColumn();
        $triggers = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE()')->fetchColumn();
        $routines = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema=DATABASE()')->fetchColumn();
        $events = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.events WHERE event_schema=DATABASE()')->fetchColumn();
        $names = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type=0x42415345205441424c45 ORDER BY table_name')->fetchAll(PDO::FETCH_COLUMN);
        printf("DB_VERSION=%s\nDB_TABLES=%d\nDB_FKS=%d\nDB_TABLE_NAMES=%s\nDB_VIEWS=%d\nDB_TRIGGERS=%d\nDB_ROUTINES=%d\nDB_EVENTS=%d\n",$version,$tables,$fks,implode(',', $names),$views,$triggers,$routines,$events);
        exit(0);
    }
    if ($mode === 'grants') {
        $required=['CREATE','DROP','INSERT','INDEX','ALTER','REFERENCES','LOCK TABLES']; $found=[];
        $scope='(?:`?'.preg_quote($database,'/').'`?|\\*)\\.\\*';
        foreach ($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN) as $grant) {
            if (preg_match('/GRANT\\s+(.+?)\\s+ON\\s+'.$scope.'\\s+TO/i',$grant,$m)) {
                if (preg_match('/\\bALL PRIVILEGES\\b/i',$m[1])) { $found=array_fill_keys($required,true); break; }
                foreach ($required as $priv) if (preg_match('/(?:^|,)\\s*'.preg_quote($priv,'/').'\\s*(?:,|$)/i',$m[1])) $found[$priv]=true;
            }
        }
        echo count($found)===count($required) ? "DB_REQUIRED_GRANTS=yes\n" : "DB_REQUIRED_GRANTS=no\n";
        exit(0);
    }
    if ($mode === 'import-support') { echo extension_loaded('mysqli') ? "DB_IMPORT_SUPPORT=yes\n" : "DB_IMPORT_SUPPORT=no\n"; exit(0); }
    if ($mode === 'drop') {
        $expected = (int)$argv[4];
        $names=$pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type=0x42415345205441424c45')->fetchAll(PDO::FETCH_COLUMN);
        if (count($names) !== $expected) exit(72);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($names as $name) { $quoted='`'.str_replace('`','``',$name).'`'; $pdo->exec("DROP TABLE IF EXISTS $quoted"); }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); exit(0);
    }
    if ($mode === 'import') {
        if (!extension_loaded('mysqli')) exit(75);
        $dump = realpath($argv[4] ?? ''); $sha = strtoupper((string)($argv[5] ?? ''));
        if ($dump === false || !is_file($dump) || is_link($argv[4]) || !preg_match('/^[A-F0-9]{64}$/', $sha) || !hash_equals($sha, strtoupper(hash_file('sha256', $dump)))) exit(76);
        $sql = file_get_contents($dump); if (!is_string($sql)) exit(77);
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $mysqli = new mysqli($host, $user, $password ?? '', $database, (int)$port);
        $mysqli->set_charset('utf8mb4');
        $mysqli->multi_query($sql);
        do {
            $result = $mysqli->store_result(); if ($result instanceof mysqli_result) $result->free();
            if (!$mysqli->more_results()) break;
            $mysqli->next_result();
        } while (true);
        $mysqli->close(); exit(0);
    }
    exit(73);
} catch (Throwable $error) { fwrite(STDERR, "database preflight/operation failed\n"); exit(74); }
PHP
)
db_php() { "$PHP" -r "$DB_HELPER" "$APP_DIR" "$DATABASE" "$1" "${2:-0}" "${3:-}" "${4:-}"; }

db_meta() { db_php metadata; }

validate_dump() {
    [[ -f "$STAGE/input.sql" && ! -L "$STAGE/input.sql" ]] || fail 'Staged SQL dump is missing or is not a regular file.'
    [[ "$(sha256sum "$STAGE/input.sql" | awk '{print toupper($1)}')" == "$EXPECTED_SHA" ]] || fail 'Staged SQL checksum mismatch.'
    local create_count fk_count create_names
    create_count=$(grep -Ec '^CREATE TABLE( IF NOT EXISTS)? ' "$STAGE/input.sql" || true)
    fk_count=$(grep -Ec '^[[:space:]]*CONSTRAINT .* FOREIGN KEY' "$STAGE/input.sql" || true)
    create_names=$(sed -n 's/^CREATE TABLE `\([^`]*\)`.*/\1/p' "$STAGE/input.sql" | LC_ALL=C sort | paste -sd, -)
    [[ "$create_count" == "$TABLES" && "$fk_count" == "$FKS" && "$create_names" == "$EXPECTED_TABLE_NAMES" ]] || fail 'Staged SQL table names or foreign-key definition inventory mismatch.'
    if grep -Eiq '^[[:space:]]*(CREATE|DROP) DATABASE|^[[:space:]]*USE[[:space:]]|utf8mb4_0900_|^[[:space:]]*CREATE[[:space:]]+(DEFINER=.*[[:space:]]+)?(VIEW|TRIGGER|PROCEDURE|FUNCTION|EVENT)\b' "$STAGE/input.sql"; then
        fail 'Staged SQL contains disallowed database-level DDL, unsupported collation, or an unreviewed schema object.'
    fi
}

check_identity() {
    local expected_tables=${1:-$OLD_TABLES} meta version tables fks names views triggers routines events
    meta=$(db_meta 2>/dev/null) || fail 'Could not verify production database identity using application configuration.'
    version=$(printf '%s\n' "$meta" | sed -n 's/^DB_VERSION=//p')
    tables=$(printf '%s\n' "$meta" | sed -n 's/^DB_TABLES=//p')
    fks=$(printf '%s\n' "$meta" | sed -n 's/^DB_FKS=//p')
    names=$(printf '%s\n' "$meta" | sed -n 's/^DB_TABLE_NAMES=//p')
    views=$(printf '%s\n' "$meta" | sed -n 's/^DB_VIEWS=//p')
    triggers=$(printf '%s\n' "$meta" | sed -n 's/^DB_TRIGGERS=//p')
    routines=$(printf '%s\n' "$meta" | sed -n 's/^DB_ROUTINES=//p')
    events=$(printf '%s\n' "$meta" | sed -n 's/^DB_EVENTS=//p')
    [[ "$version" == 11.8.9* && "$tables" == "$expected_tables" && "$fks" =~ ^[0-9]+$ && "$views" == 0 && "$triggers" == 0 && "$routines" == 0 && "$events" == 0 ]] || fail 'Database version or schema-object inventory differs from the expected state.'
    if [[ "$expected_tables" == "$TABLES" ]]; then
        [[ "$fks" == "$FKS" && "$names" == "$EXPECTED_TABLE_NAMES" && ",$names," == *',schema_patch_history,'* && ",$names," != *',fan_post_monthly_views,'* ]] || fail 'Imported table names or foreign-key count differ from the canonical release schema.'
    fi
    printf '%s\n' "$meta"
}

check_grants() {
    # Grants are inspected through the authenticated application account; never print grant text.
    local grant_status
    grant_status=$(db_php grants 2>/dev/null) || fail 'Could not inspect production database grants.'
    [[ "$grant_status" == 'DB_REQUIRED_GRANTS=yes' ]] || fail 'Database account lacks privileges required for the reviewed table replacement.'
}

check_import_support() {
    local support
    support=$(db_php import-support 2>/dev/null) || fail 'Could not verify the PHP database import client.'
    [[ "$support" == 'DB_IMPORT_SUPPORT=yes' ]] || fail 'The configured PHP 8.4 runtime lacks mysqli for secret-safe SQL import.'
}

if [[ "$MODE" == preflight ]]; then
    # Fetch only the current main tip into FETCH_HEAD; do not change the checked-out branch.
    git fetch --no-tags origin refs/heads/main
    remote_main=$(git rev-parse FETCH_HEAD)
    git cat-file -e "$COMMIT^{commit}" || fail 'Target commit object is unavailable in the production repository.'
    git merge-base --is-ancestor "$COMMIT" FETCH_HEAD || fail 'Target commit is not reachable from current origin/main.'
    git merge-base --is-ancestor "$CURRENT" "$COMMIT" || fail 'Production HEAD cannot fast-forward to the target commit.'
    check_identity
    check_grants
    check_import_support
    [[ ! -e "$PUBLIC_DIR/storage" || ( -L "$PUBLIC_DIR/storage" && "$(readlink -f "$PUBLIC_DIR/storage")" == "$APP_DIR/storage/app/public" ) ]] || fail 'public_html/storage already exists but is not the verified storage symlink.'
    [[ -w "$APP_DIR" && -w "$PUBLIC_DIR" && -w "$APP_DIR/storage/app/public" && -w "$APP_DIR/storage/app/private" ]] || fail 'Required deployment destination is not writable.'
    printf 'PRODUCTION_BASE_COMMIT=%s\n' "$CURRENT"
    log 'Read-only release preflight passed; no production Git refs or files were changed.'
    exit 0
fi

[[ "$STAGE" == "$HOME/.idolranking-initial-release-$RUN_ID" && -d "$STAGE" && ! -L "$STAGE" ]] || fail 'Private stage path is invalid.'
[[ "$(stat -c '%a' "$STAGE")" == 700 ]] || fail 'Private stage root must have mode 700.'
[[ -f "$STAGE/build/manifest.json" && -d "$STAGE/images" && -d "$STAGE/media/public" && -d "$STAGE/media/private" ]] || fail 'One or more staged payload directories are missing.'
validate_dump
if find "$STAGE" -type l -print -quit | grep -q .; then fail 'Staged payload contains a symlink.'; fi

# Validate the four Vite entry points and every referenced file without running application code.
"$PHP" -r '
  $m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
  $entries=["resources/css/app.css","resources/css/admin.css","resources/js/app.js","resources/js/admin.js"];
  foreach($entries as $e) if(!isset($m[$e])){fwrite(STDERR,"missing manifest entry\n");exit(2);}
  foreach($m as $item){foreach(array_merge(isset($item["file"])?[$item["file"]]:[], $item["css"]??[], $item["assets"]??[]) as $f){if(!is_string($f)||str_contains($f,"..")||str_starts_with($f,"/")){exit(3);} if(!is_file(dirname($argv[1])."/".$f)){exit(4);}}}
' "$STAGE/build/manifest.json" || fail 'Staged Vite manifest has missing or unsafe assets.'

media_inventory() {
    local dir=$1 count bytes
    count=$(find "$dir" -type f ! -name .gitignore -printf x | wc -c | tr -d ' ') || return 1
    bytes=$(find "$dir" -type f ! -name .gitignore -printf '%s\n' | awk '{total += $1} END {printf "%.0f", total}') || return 1
    printf '%s %s\n' "$count" "$bytes"
}
public_inventory=$(media_inventory "$STAGE/media/public") || fail 'Could not inspect staged public media.'
private_inventory=$(media_inventory "$STAGE/media/private") || fail 'Could not inspect staged private media.'
read -r staged_public_count staged_public_bytes <<< "$public_inventory"
read -r staged_private_count staged_private_bytes <<< "$private_inventory"
[[ "$staged_public_count" == "$PUBLIC_COUNT" && "$staged_public_bytes" == "$PUBLIC_BYTES" &&
   "$staged_private_count" == "$PRIVATE_COUNT" && "$staged_private_bytes" == "$PRIVATE_BYTES" ]] || fail 'Staged upload inventory differs from the local inventory.'

check_additive_collisions() {
    local source=$1 destination=$2 omit_manifest=${3:-0} file relative target
    if [[ -e "$destination" ]]; then
        [[ -d "$destination" && ! -L "$destination" ]] || fail 'An upload destination is not a regular directory.'
        if find "$destination" -type l -print -quit | grep -q .; then fail 'An upload destination contains a symlink.'; fi
    fi
    find "$source" -mindepth 1 -type d -print0 | while IFS= read -r -d '' file; do
        relative=${file#"$source"/}
        target="$destination/$relative"
        if [[ -e "$target" || -L "$target" ]]; then
            [[ -d "$target" && ! -L "$target" ]] || fail 'An upload directory collides with a non-directory destination.'
        fi
    done || return 1
    find "$source" -type f -print0 | while IFS= read -r -d '' file; do
        relative=${file#"$source"/}
        if [[ "$omit_manifest" == 1 && "$relative" == manifest.json ]]; then continue; fi
        target="$destination/$relative"
        if [[ -e "$target" ]]; then
            [[ -f "$target" && ! -L "$target" ]] || fail 'An upload file collides with a non-regular destination.'
            [[ "$(sha256sum "$file" | awk '{print $1}')" == "$(sha256sum "$target" | awk '{print $1}')" ]] || fail 'An upload file collides with different existing content.'
        fi
    done || return 1
}

validate_destinations() {
    check_additive_collisions "$STAGE/media/public" "$APP_DIR/storage/app/public"
    check_additive_collisions "$STAGE/media/private" "$APP_DIR/storage/app/private"
    check_additive_collisions "$STAGE/images" "$PUBLIC_DIR/images"
    check_additive_collisions "$STAGE/build" "$PUBLIC_DIR/build" 1
    if [[ -e "$PUBLIC_DIR/build/manifest.json" || -L "$PUBLIC_DIR/build/manifest.json" ]]; then
        [[ -f "$PUBLIC_DIR/build/manifest.json" && ! -L "$PUBLIC_DIR/build/manifest.json" ]] || fail 'Existing Vite manifest is not a regular file.'
    fi
}
validate_destinations

check_identity >/dev/null
check_import_support
if [[ "$MODE" == validate-stage ]]; then
    log 'Staged dump, Vite manifest, production database identity, and current inventory validated.'
    exit 0
fi

[[ "$BASE_COMMIT" == "$CURRENT" ]] || fail 'Production base commit changed after initial preflight.'
git merge-base --is-ancestor "$CURRENT" "$COMMIT" || fail 'Production update is no longer a fast-forward.'
check_grants
LOCK_DIR="$HOME/.idolranking-deploy"
mkdir -m 700 -p "$LOCK_DIR"
exec 9>"$LOCK_DIR/deploy.lock"
flock -n 9 || fail 'Another deployment holds the shared deployment lock.'

approval="INITIAL RELEASE $COMMIT REPLACE DATABASE $DATABASE FROM DUMP $SHA TABLES $TABLES BACKUP APPROVED-NO-BACKUP"
printf 'Remote confirmation required: %s\n' "$approval"
read -r -p 'Type the complete phrase to start the coordinated release: ' typed </dev/tty
[[ "$typed" == "$approval" ]] || fail 'Remote confirmation did not match.'

# Recheck mutable server state after the confirmation prompt and immediately before downtime.
[[ "$(git -C "$APP_DIR" rev-parse HEAD)" == "$BASE_COMMIT" && -z "$(git -C "$APP_DIR" status --porcelain=v1 --untracked-files=all)" ]] || fail 'Production Git state changed during confirmation.'
check_identity >/dev/null
check_grants
check_import_support
validate_destinations

stage_name=pre-maintenance-validation
maintenance=0
destructive_started=0
changed=0
release_incomplete=0
original_env=$(sha256sum "$APP_DIR/.env" | awk '{print $1}')
original_index=$(sha256sum "$PUBLIC_DIR/index.php" | awk '{print $1}')
original_htaccess=$(sha256sum "$PUBLIC_DIR/.htaccess" | awk '{print $1}')
on_error() {
    rc=${1:-$?}
    trap - ERR
    log "FAILED_STAGE=$stage_name EXIT=$rc CURRENT_COMMIT=$(git -C "$APP_DIR" rev-parse HEAD)"
    if (( maintenance == 1 || release_incomplete == 1 )); then
        if (( destructive_started == 1 )); then
            if (( maintenance == 0 )); then
                if "$PHP" "$APP_DIR/artisan" down --retry=60 >/dev/null 2>&1; then
                    maintenance=1
                else
                    log 'WARNING: could not restore maintenance mode after a post-import failure; manual recovery is urgent.'
                fi
            fi
            log 'Database replacement began. No rollback or retry was attempted; site remains in maintenance mode for manual recovery.'
        elif (( changed == 0 )); then
            "$PHP" "$APP_DIR/artisan" up >/dev/null 2>&1 || log 'WARNING: could not clear maintenance mode; manual recovery required.'
        else
            log 'Code or assets changed; site remains in maintenance mode for manual inspection.'
        fi
    fi
    exit "$rc"
}
trap 'on_error $?' ERR
trap 'on_error 130' INT
trap 'on_error 143' TERM

stage_name=locked-final-preflight
[[ "$(git -C "$APP_DIR" rev-parse HEAD)" == "$BASE_COMMIT" ]] || fail 'Production HEAD changed after preflight.'
[[ -z "$(git -C "$APP_DIR" status --porcelain=v1 --untracked-files=all)" ]] || fail 'Production tree changed after preflight.'
git -C "$APP_DIR" fetch --no-tags origin refs/heads/main:refs/remotes/origin/main
git -C "$APP_DIR" merge-base --is-ancestor "$COMMIT" refs/remotes/origin/main || fail 'Target is no longer reachable from origin/main.'
check_identity >/dev/null
check_import_support
validate_destinations
stage_name=maintenance-mode
maintenance=1
release_incomplete=1
"$PHP" "$APP_DIR/artisan" down --retry=60

stage_name=fast-forward-code
if [[ "$BASE_COMMIT" != "$COMMIT" ]]; then git -C "$APP_DIR" merge --ff-only "$COMMIT"; fi
changed=1
stage_name=composer-install
"$PHP" "$COMPOSER" install --working-dir="$APP_DIR" --no-dev --optimize-autoloader --no-interaction --prefer-dist >/dev/null

stage_name=database-replacement
check_identity >/dev/null
validate_dump
destructive_started=1
# Remove only objects in the verified target schema. Database credentials remain inside PHP memory.
# DDL is not transactional: any interruption is reported as a partial replacement, never retried.
db_php drop "$OLD_TABLES" >/dev/null 2>&1
if ! db_php import "$STAGE/input.sql" "$EXPECTED_SHA" >/dev/null 2>&1; then
    fail 'Database import failed; schema or data may be partial and no retry or rollback was attempted.'
fi
stage_name=database-verification
post_meta=$(check_identity "$TABLES")
post_tables=$(printf '%s\n' "$post_meta" | sed -n 's/^DB_TABLES=//p')
post_fks=$(printf '%s\n' "$post_meta" | sed -n 's/^DB_FKS=//p')
[[ "$post_tables" == "$TABLES" && "$post_fks" == "$FKS" ]] || fail 'Imported database inventory differs from the verified dump.'

stage_name=media-synchronization
mkdir -p "$APP_DIR/storage/app/public" "$APP_DIR/storage/app/private" "$PUBLIC_DIR/images" "$PUBLIC_DIR/build"
rsync -rlt --checksum --ignore-existing --omit-dir-times --no-owner --no-group --no-perms "$STAGE/media/public/" "$APP_DIR/storage/app/public/"
rsync -rlt --checksum --ignore-existing --omit-dir-times --no-owner --no-group --no-perms "$STAGE/media/private/" "$APP_DIR/storage/app/private/"
rsync -rlt --checksum --ignore-existing --omit-dir-times --no-owner --no-group --no-perms "$STAGE/images/" "$PUBLIC_DIR/images/"

stage_name=storage-link
if [[ ! -e "$PUBLIC_DIR/storage" && ! -L "$PUBLIC_DIR/storage" ]]; then
    ln -s "$APP_DIR/storage/app/public" "$PUBLIC_DIR/storage"
elif [[ -L "$PUBLIC_DIR/storage" && "$(readlink -f "$PUBLIC_DIR/storage")" == "$APP_DIR/storage/app/public" ]]; then
    :
else
    fail 'public_html/storage changed or is not the expected symlink; refusing to overwrite it.'
fi

stage_name=publish-vite-assets
rsync -rlt --checksum --omit-dir-times --no-owner --no-group --no-perms --exclude='manifest.json' "$STAGE/build/" "$PUBLIC_DIR/build/"
install -m 644 "$STAGE/build/manifest.json" "$PUBLIC_DIR/build/.manifest.$RUN_ID.tmp"
mv -f -- "$PUBLIC_DIR/build/.manifest.$RUN_ID.tmp" "$PUBLIC_DIR/build/manifest.json"

stage_name=laravel-cache-refresh
"$PHP" "$APP_DIR/artisan" optimize:clear >/dev/null
"$PHP" "$APP_DIR/artisan" config:cache >/dev/null
"$PHP" "$APP_DIR/artisan" route:cache >/dev/null
"$PHP" "$APP_DIR/artisan" view:cache >/dev/null
[[ "$(sha256sum "$APP_DIR/.env" | awk '{print $1}')" == "$original_env" ]] || fail 'Production .env changed unexpectedly.'
[[ "$(sha256sum "$PUBLIC_DIR/index.php" | awk '{print $1}')" == "$original_index" ]] || fail 'Existing public index.php changed unexpectedly.'
[[ "$(sha256sum "$PUBLIC_DIR/.htaccess" | awk '{print $1}')" == "$original_htaccess" ]] || fail 'Existing public .htaccess changed unexpectedly.'
[[ "$(git -C "$APP_DIR" rev-parse HEAD)" == "$COMMIT" && -z "$(git -C "$APP_DIR" status --porcelain=v1 --untracked-files=all)" ]] || fail 'Final Git state does not match the exact release commit.'

stage_name=http-smoke-tests
base=${BASE_URL%/}
check_http() { local label=$1 url=$2 code; code=$(curl --silent --show-error --location --max-time 30 --output /dev/null --write-out '%{http_code}' "$url") || fail "HTTP request failed: $label"; [[ "$code" =~ ^2[0-9]{2}$ ]] || fail "$label returned HTTP $code."; }
check_http homepage "$base/"
check_http idol-ranking "$base/ranking/idol"
check_http group-ranking "$base/ranking/group"
garden=$(curl --silent --show-error --max-time 30 --output /dev/null --write-out '%{http_code}' "$base/fan-garden")
[[ "$garden" == 404 ]] || fail "/fan-garden returned HTTP $garden instead of 404."
for entry in resources/css/app.css resources/css/admin.css resources/js/app.js resources/js/admin.js; do
    asset=$("$PHP" -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m[$argv[2]]["file"];' "$PUBLIC_DIR/build/manifest.json" "$entry")
    check_http "asset $entry" "$base/build/$asset"
done
stage_name=exit-maintenance
"$PHP" "$APP_DIR/artisan" up >/dev/null
maintenance=0
release_incomplete=0
stage_name=complete
log "Initial release completed at $COMMIT with $post_tables tables and $post_fks foreign keys."
log "Protected staging retained for manual review: $STAGE"
