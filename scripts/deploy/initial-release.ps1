[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-fA-F]{40}$')]
    [string]$Commit,

    [Parameter(Mandatory = $true)]
    [uri]$BaseUrl,

    [Parameter(Mandatory = $true)]
    [string]$DatabaseDump,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-fA-F]{64}$')]
    [string]$Sha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_]{1,64}$')]
    [string]$TargetDatabase,

    [Parameter(Mandatory = $true)]
    [ValidateRange(1, 1000)]
    [int]$ExpectedTableCount,

    [Parameter(Mandatory = $true)]
    [ValidateRange(0, 1000)]
    [int]$ExpectedProductionTableCount,

    [Parameter(Mandatory = $true)]
    [ValidateSet('APPROVED-NO-BACKUP')]
    [string]$BackupDecision,

    [string]$SshKeyPath = (Join-Path $env:USERPROFILE '.ssh\idolranking_hostinger_ed25519'),
    [string]$KnownHostsPath = (Join-Path $env:USERPROFILE '.ssh\known_hosts'),

    [switch]$Approved,
    [switch]$DryRun
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if (Get-Variable -Name PSNativeCommandUseErrorActionPreference -ErrorAction SilentlyContinue) {
    $PSNativeCommandUseErrorActionPreference = $false
}

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path.TrimEnd('\')
$Commit = $Commit.ToLowerInvariant()
$ExpectedDatabase = 'u624668418_idol'
$ExpectedSha256 = 'BA0C271DF57E377268A7FE4D9CA73FA4E2BFAD3AC4262BAC9EC819955080CACB'
$ExpectedTables = 37
$ExpectedForeignKeys = 58
$ExpectedOldTables = 25
$BaseUrlExpected = 'https://orchid-oryx-674671.hostingersite.com'
$SshKey = [IO.Path]::GetFullPath($SshKeyPath)
$KnownHosts = [IO.Path]::GetFullPath($KnownHostsPath)
$SshCommandTimeoutSeconds = 30
$SshCoordinatorTimeoutSeconds = 300
$ScpTimeoutSeconds = 900
$Target = 'u624668418@145.79.26.175'
$RemoteHome = '/home/u624668418'
$RemoteApp = '/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking'
$RemotePublic = '/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/public_html'
$RemoteCoordinator = Join-Path $PSScriptRoot 'remote-initial-release.sh'
$GitExe = (Get-Command git.exe -ErrorAction Stop).Source

function Get-SshPublicKeyParts {
    param([Parameter(Mandatory = $true)][string]$Text)

    $lines = @($Text -split '\r?\n' | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    if ($lines.Count -ne 1) { return $null }
    $fields = $lines[0].Trim() -split '\s+'
    if ($fields.Count -lt 2 -or $fields[0] -notmatch '^[A-Za-z0-9][A-Za-z0-9@._+-]*$') { return $null }
    try {
        if ([Convert]::FromBase64String($fields[1]).Length -eq 0) { return $null }
    }
    catch { return $null }

    return [pscustomobject]@{ Type = $fields[0]; Data = $fields[1] }
}

function Assert-SshIdentity {
    foreach ($path in @($SshKey, "$SshKey.pub", $KnownHosts)) {
        if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw 'A required SSH identity file is missing or unreadable.' }
    }

    $identity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
    $account = $identity.User.Translate([System.Security.Principal.NTAccount]).Value
    $acl = Get-Acl -LiteralPath $SshKey
    $entries = @($acl.Access)
    if (-not $acl.AreAccessRulesProtected -or $entries.Count -ne 1 -or
        $entries[0].IdentityReference.Translate([System.Security.Principal.NTAccount]).Value -ne $account -or
        $entries[0].AccessControlType -ne [System.Security.AccessControl.AccessControlType]::Allow -or
        $entries[0].FileSystemRights.ToString() -notmatch 'FullControl') {
        throw 'The dedicated SSH private-key ACL is not restricted to the current Windows account.'
    }

    # Only the derived public half is held in memory. Private-key contents are never printed.
    $keygen = (Get-Command ssh-keygen.exe -ErrorAction Stop).Source
    $keyResult = Invoke-BoundedProcess -FileName $keygen -Arguments @('-y', '-P', '', '-f', $SshKey) `
        -TimeoutSeconds 15 -Operation 'SSH key-pair verification'
    $derived = $keyResult.StdOut.Trim()
    $derivedParts = Get-SshPublicKeyParts -Text $derived
    $savedParts = Get-SshPublicKeyParts -Text (Get-Content -LiteralPath "$SshKey.pub" -Raw)
    if ($keyResult.ExitCode -ne 0 -or $null -eq $derivedParts -or $null -eq $savedParts -or
        $derivedParts.Type -cne $savedParts.Type -or $derivedParts.Data -cne $savedParts.Data) {
        throw 'Dedicated SSH key-pair verification failed.'
    }

    $hostResult = Invoke-BoundedProcess -FileName $keygen -Arguments @('-F', '[145.79.26.175]:65002', '-f', $KnownHosts) `
        -TimeoutSeconds 15 -Operation 'SSH host-key lookup'
    if ($hostResult.ExitCode -ne 0 -or -not $hostResult.StdOut) { throw 'The production host key is not pinned in known_hosts.' }
}

function Assert-LocalInputs {
    if ($TargetDatabase -cne $ExpectedDatabase -or $ExpectedTableCount -ne $ExpectedTables -or
        $ExpectedProductionTableCount -ne $ExpectedOldTables -or $Sha256.ToUpperInvariant() -cne $ExpectedSha256) {
        throw 'Database target, baseline, table count, or checksum differs from the reviewed initial release.'
    }
    if ($BaseUrl.GetLeftPart([System.UriPartial]::Authority).TrimEnd('/') -cne $BaseUrlExpected -or
        $BaseUrl.Scheme -ne 'https' -or $BaseUrl.UserInfo -or $BaseUrl.Query -or $BaseUrl.Fragment -or $BaseUrl.AbsolutePath -ne '/') {
        throw 'BaseUrl must be the verified production HTTPS origin.'
    }
    if (-not (Test-Path -LiteralPath $RemoteCoordinator -PathType Leaf)) { throw 'The remote initial-release coordinator is missing.' }

    $localBranch = & $GitExe -C $ProjectRoot branch --show-current
    if ($LASTEXITCODE -ne 0 -or $localBranch -cne 'main') { throw 'Local deployment checkout must be on main.' }
    # Only tracked files are release inputs. Reject staged/unstaged tracked changes,
    # while leaving unrelated developer files untracked and outside the Git archive.
    $localChanges = & $GitExe -C $ProjectRoot status --porcelain=v1 --untracked-files=no
    if ($LASTEXITCODE -ne 0 -or $localChanges) { throw 'Local deployment worktree must have no staged or unstaged tracked changes.' }

    # Fetch the current main tip into FETCH_HEAD without changing the local branch or worktree.
    & $GitExe -C $ProjectRoot fetch --no-tags origin refs/heads/main
    if ($LASTEXITCODE -ne 0) { throw 'Could not read the current origin/main commit.' }
    $originMain = (& $GitExe -C $ProjectRoot rev-parse FETCH_HEAD).Trim().ToLowerInvariant()
    if ($LASTEXITCODE -ne 0 -or $originMain -notmatch '^[0-9a-f]{40}$') { throw 'Could not resolve the current origin/main commit.' }
    & $GitExe -C $ProjectRoot cat-file -e "$Commit^{commit}" 2>$null
    if ($LASTEXITCODE -ne 0) { throw 'The supplied target commit is unavailable locally.' }
    & $GitExe -C $ProjectRoot merge-base --is-ancestor $Commit FETCH_HEAD 2>$null
    if ($LASTEXITCODE -ne 0) { throw 'The supplied target commit is not reachable from the current origin/main tip.' }
    $dumpPath = (Resolve-Path -LiteralPath $DatabaseDump -ErrorAction Stop).Path
    if (-not (Test-Path -LiteralPath $dumpPath -PathType Leaf) -or
        (Get-Item -LiteralPath $dumpPath).Attributes -band [System.IO.FileAttributes]::ReparsePoint) {
        throw 'Database dump must be a regular, non-symlink file.'
    }
    if ($dumpPath.StartsWith(($ProjectRoot + '\'), [System.StringComparison]::OrdinalIgnoreCase) -or
        $dumpPath.StartsWith((Join-Path $ProjectRoot 'public').TrimEnd('\') + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'The database dump must remain outside the Git repository and public web directory.'
    }
    $actualHash = (Get-FileHash -LiteralPath $dumpPath -Algorithm SHA256).Hash.ToUpperInvariant()
    if ($actualHash -cne $ExpectedSha256) { throw 'Database dump SHA-256 does not match the reviewed value.' }

    # Inspect only SQL structure. No SQL is executed and no row contents are printed.
    $tableNames = [System.Collections.Generic.List[string]]::new()
    $foreignKeyCount = 0
    $reader = [System.IO.StreamReader]::new($dumpPath, [System.Text.Encoding]::UTF8, $true)
    try {
        while (($line = $reader.ReadLine()) -ne $null) {
            if ($line -match '^\s*(?i:CREATE|DROP)\s+DATABASE\b' -or $line -match '^\s*(?i:USE)\s+') {
                throw 'Dump contains database-level statements.'
            }
            if ($line -match '^\s*(?i:CREATE)\s+(?:(?:DEFINER\s*=\s*\S+)\s+)?(?i:VIEW|TRIGGER|PROCEDURE|FUNCTION|EVENT)\b') {
                throw 'Dump contains a non-table schema object outside the reviewed 37-table inventory.'
            }
            if ($line -match '(?i:DEFINER\s*=|GTID_PURGED|utf8mb4_0900_|\bINVISIBLE\b)') {
                throw 'Dump contains a MySQL-specific feature that was not approved for MariaDB import.'
            }
            if ($line -match '^CREATE TABLE(?: IF NOT EXISTS)?\s+`?([^`\s(]+)`?') { $tableNames.Add($Matches[1]) }
            if ($line -match '^\s*CONSTRAINT\s+[^\s]+\s+FOREIGN KEY\b') { $foreignKeyCount++ }
            if ($line -match 'utf8mb4_0900_') { throw 'Dump still contains a MySQL 8.0-only collation.' }
        }
    }
    finally { $reader.Dispose() }
    if ($tableNames.Count -ne $ExpectedTables -or @($tableNames | Sort-Object -Unique).Count -ne $ExpectedTables) {
        throw "Dump must contain exactly $ExpectedTables unique CREATE TABLE definitions."
    }
    if ($foreignKeyCount -ne $ExpectedForeignKeys) { throw 'Dump foreign-key definition count differs from the reviewed schema.' }
    $canonicalSchema = Join-Path $ProjectRoot 'sql\deploy\mariadb11_schema.sql'
    if (-not (Test-Path -LiteralPath $canonicalSchema -PathType Leaf)) { throw 'Canonical MariaDB schema snapshot is missing.' }
    $canonicalNames = [System.Collections.Generic.List[string]]::new()
    foreach ($line in [System.IO.File]::ReadLines($canonicalSchema)) {
        if ($line -match '^CREATE TABLE(?: IF NOT EXISTS)?\s+`?([^`\s(]+)`?') { $canonicalNames.Add($Matches[1]) }
    }
    if ($canonicalNames.Count -ne $ExpectedTables -or
        (@(Compare-Object ($tableNames | Sort-Object -Unique) ($canonicalNames | Sort-Object -Unique) -CaseSensitive).Count -ne 0) -or
        $tableNames.Contains('fan_post_monthly_views') -or -not $tableNames.Contains('schema_patch_history')) {
        throw 'Dump table names differ from the canonical schema snapshot.'
    }

    $mediaRoot = Join-Path $ProjectRoot 'storage\app'
    $mediaInventory = @{}
    foreach ($kind in @('public', 'private')) {
        $path = Join-Path $mediaRoot $kind
        if (-not (Test-Path -LiteralPath $path -PathType Container)) { throw "Local storage/app/$kind directory is missing." }
        foreach ($entry in Get-ChildItem -LiteralPath $path -Recurse -Force) {
            if ($entry.Attributes -band [System.IO.FileAttributes]::ReparsePoint) { throw "Local storage/app/$kind contains a link or reparse point." }
        }
        $files = @(Get-ChildItem -LiteralPath $path -File -Recurse -Force | Where-Object { $_.Name -cne '.gitignore' })
        [long]$byteSum = 0
        foreach ($file in $files) { $byteSum += [long]$file.Length }
        $mediaInventory[$kind] = [pscustomobject]@{ Files = $files; Count = $files.Count; Bytes = $byteSum }
    }

    return [pscustomobject]@{ DumpPath = $dumpPath; Media = $mediaInventory }
}

function Invoke-BoundedProcess {
    param(
        [Parameter(Mandatory = $true)][string]$FileName,
        [Parameter(Mandatory = $true)][string[]]$Arguments,
        [Parameter(Mandatory = $true)][ValidateRange(1, 3600)][int]$TimeoutSeconds,
        [string]$Operation = 'Native command',
        [byte[]]$StandardInputBytes
    )

    $nativeArguments = $Arguments | ForEach-Object {
        $argument = [string]$_
        if ($argument.Contains('"')) { throw 'Unexpected double quote in a native process argument.' }
        if ($argument.Length -eq 0) { '""' }
        elseif ($argument -match '\s') { '"' + $argument + '"' }
        else { $argument }
    }
    $startInfo = [System.Diagnostics.ProcessStartInfo]::new()
    $startInfo.FileName = $FileName
    $startInfo.Arguments = $nativeArguments -join ' '
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true

    $process = [System.Diagnostics.Process]::new()
    $process.StartInfo = $startInfo
    $timer = [System.Diagnostics.Stopwatch]::StartNew()
    $started = $false
    try {
        if (-not $process.Start()) { throw "Could not start $Operation." }
        $started = $true
        $stdoutTask = $process.StandardOutput.ReadToEndAsync()
        $stderrTask = $process.StandardError.ReadToEndAsync()

        if ($null -ne $StandardInputBytes -and $StandardInputBytes.Length -gt 0) {
            $writeTask = $process.StandardInput.BaseStream.WriteAsync($StandardInputBytes, 0, $StandardInputBytes.Length)
            $remaining = [Math]::Max(1, ($TimeoutSeconds * 1000) - [int]$timer.ElapsedMilliseconds)
            if (-not $writeTask.Wait($remaining)) {
                try { $process.Kill() } catch { }
                try { $null = $process.WaitForExit(5000) } catch { }
                throw "$Operation timed out after $TimeoutSeconds seconds while sending input; the process was terminated."
            }
            $writeTask.GetAwaiter().GetResult()
        }
        $process.StandardInput.Close()

        $remaining = [Math]::Max(1, ($TimeoutSeconds * 1000) - [int]$timer.ElapsedMilliseconds)
        if (-not $process.WaitForExit($remaining)) {
            try { $process.Kill() } catch { }
            try { $null = $process.WaitForExit(5000) } catch { }
            throw "$Operation timed out after $TimeoutSeconds seconds; the process was terminated."
        }

        return [pscustomobject]@{
            ExitCode = $process.ExitCode
            StdOut = $stdoutTask.GetAwaiter().GetResult()
            StdErr = $stderrTask.GetAwaiter().GetResult()
        }
    }
    finally {
        if ($started -and -not $process.HasExited) {
            try { $process.Kill() } catch { }
            try { $null = $process.WaitForExit(5000) } catch { }
        }
        $process.Dispose()
    }
}

function Invoke-SshHelper {
    param(
        [Parameter(Mandatory = $true)][string[]]$Arguments,
        [switch]$AllocateTty,
        [string]$RemoteScriptPath
    )

    # Quote each argument as a POSIX single-quoted word; never interpolate it as shell syntax.
    $remoteArgs = ($Arguments | ForEach-Object { "'" + $_.Replace("'", "'\''") + "'" }) -join ' '
    if ($RemoteScriptPath) {
        # Interactive deployment reads its second confirmation from /dev/tty. Run the
        # checksum-verified helper from the private stage so SSH stdin remains the TTY.
        if ($RemoteScriptPath -notmatch '^/home/u624668418/\.idolranking-initial-release-[a-f0-9]{32}/remote-initial-release\.sh$') {
            throw 'Remote coordinator path is outside the unique protected staging directory.'
        }
        $scriptHash = (Get-FileHash -LiteralPath $RemoteCoordinator -Algorithm SHA256).Hash.ToLowerInvariant()
        $quotedPath = "'" + $RemoteScriptPath.Replace("'", "'\''") + "'"
        $remoteCommand = "printf '%s  %s\n' '$scriptHash' $quotedPath | sha256sum -c - >/dev/null && exec bash -- $quotedPath $remoteArgs"
    }
    else {
        # Non-interactive calls receive the coordinator over SSH stdin, not the
        # Windows command line. Normalize line endings for the remote Bash parser.
        $scriptText = (Get-Content -LiteralPath $RemoteCoordinator -Raw -Encoding UTF8).Replace("`r`n", "`n").Replace("`r", "`n")
        $remoteCommand = "bash -s -- $remoteArgs"
    }
    $tty = if ($AllocateTty) { '-tt' } else { '-T' }
    $options = @(
        $tty, '-p', '65002', '-i', $SshKey,
        '-o', 'IdentitiesOnly=yes', '-o', 'PubkeyAuthentication=yes',
        '-o', 'StrictHostKeyChecking=yes', '-o', "UserKnownHostsFile=$KnownHosts",
        '-o', 'BatchMode=yes', '-o', 'PasswordAuthentication=no',
        '-o', 'KbdInteractiveAuthentication=no', '-o', 'PreferredAuthentications=publickey',
        '-o', 'ConnectTimeout=12'
    )
    $ssh = (Get-Command ssh.exe -ErrorAction Stop).Source
    if ($AllocateTty) {
        if (-not $RemoteScriptPath) { throw 'Interactive remote execution requires a staged, checksum-verified coordinator.' }
        # Do not capture stdout or pipe stdin here; preserve the local and remote TTY prompts.
        & $ssh @options $Target $remoteCommand
        $exitCode = $LASTEXITCODE
        if ($exitCode -ne 0) { throw "Remote coordinator failed (exit $exitCode). Review its last safe stage report; do not blindly retry." }
        return
    }

    if ($RemoteScriptPath) { $options += '-n' }
    # Coordinator preflight streams the script bytes through stdin; a staged
    # coordinator receives EOF immediately and is run with stdin disabled.
    $stdinBytes = $null
    if (-not $RemoteScriptPath) { $stdinBytes = [System.Text.Encoding]::UTF8.GetBytes($scriptText) }
    $result = Invoke-BoundedProcess -FileName $ssh -Arguments (@($options) + @($Target, $remoteCommand)) `
        -TimeoutSeconds $SshCoordinatorTimeoutSeconds -Operation 'Remote coordinator' -StandardInputBytes $stdinBytes
    if ($result.StdErr) { [Console]::Error.WriteLine($result.StdErr.TrimEnd()) }
    if ($result.ExitCode -ne 0) { throw "Remote coordinator failed (exit $($result.ExitCode)). Review its last safe stage report; do not blindly retry." }
    return @($result.StdOut -split "`r?`n" | Where-Object { $_.Length -gt 0 })
}

function Invoke-SshCommand {
    param(
        [Parameter(Mandatory = $true)][string]$Command,
        [string]$FailureMessage = 'Remote SSH command failed.'
    )
    $options = @(
        '-T', '-n', '-p', '65002', '-i', $SshKey,
        '-o', 'IdentitiesOnly=yes', '-o', 'PubkeyAuthentication=yes',
        '-o', 'StrictHostKeyChecking=yes', '-o', "UserKnownHostsFile=$KnownHosts",
        '-o', 'BatchMode=yes', '-o', 'PasswordAuthentication=no',
        '-o', 'KbdInteractiveAuthentication=no', '-o', 'PreferredAuthentications=publickey',
        '-o', 'ConnectTimeout=12'
    )
    $ssh = (Get-Command ssh.exe -ErrorAction Stop).Source
    try {
        $result = Invoke-BoundedProcess -FileName $ssh -Arguments (@($options) + @($Target, $Command)) `
            -TimeoutSeconds $SshCommandTimeoutSeconds -Operation 'Standalone SSH command'
    }
    catch { throw "$FailureMessage $($_.Exception.Message)" }
    if ($result.StdOut) { [Console]::Out.Write($result.StdOut) }
    if ($result.StdErr) { [Console]::Error.WriteLine($result.StdErr.TrimEnd()) }
    if ($result.ExitCode -ne 0) { throw "$FailureMessage (SSH exit code $($result.ExitCode))." }
}

function Remove-AbandonedRemoteStage {
    param([Parameter(Mandatory = $true)][string]$StagePath)

    $match = [regex]::Match($StagePath, '^/home/u624668418/\.idolranking-initial-release-([a-f0-9]{32})$')
    if (-not $match.Success) { throw 'Refusing cleanup: remote stage path is not a generated per-run path.' }
    $runId = $match.Groups[1].Value
    $expectedPath = "$RemoteHome/.idolranking-initial-release-$runId"
    if ($StagePath -cne $expectedPath) { throw 'Refusing cleanup: remote stage path differs from the expected home directory.' }

    # Only remove the exact mode-700 directory created for this run, owned by the
    # authenticated account, with known workflow payload names at its top level.
    $cleanupCommand = @'
stage="$HOME/.idolranking-initial-release-__RUN_ID__"
expected="$HOME/.idolranking-initial-release-__RUN_ID__"
if [ ! -e "$stage" ] && [ ! -L "$stage" ]; then echo STAGE_ALREADY_ABSENT; exit 0; fi
[ "$stage" = "$expected" ] || exit 41
[ -d "$stage" ] && [ ! -L "$stage" ] || exit 42
[ "$(readlink -f -- "$stage")" = "$expected" ] || exit 43
[ "$(stat -c %u -- "$stage")" = "$(id -u)" ] || exit 44
[ "$(stat -c %a -- "$stage")" = 700 ] || exit 45
for child in "$stage"/* "$stage"/.[!.]* "$stage"/..?*; do
    [ -e "$child" ] || [ -L "$child" ] || continue
    case "${child##*/}" in
        build|images|media) [ -d "$child" ] && [ ! -L "$child" ] || exit 46 ;;
        input.sql|remote-initial-release.sh) [ -f "$child" ] && [ ! -L "$child" ] || exit 47 ;;
        *) exit 48 ;;
    esac
done
rm -rf -- "$stage" || exit 49
[ ! -e "$stage" ] && [ ! -L "$stage" ] || exit 50
echo STAGE_CLEANED
'@
    $cleanupCommand = $cleanupCommand.Replace('__RUN_ID__', $runId)
    Invoke-SshCommand -Command $cleanupCommand -FailureMessage 'Safe remote staging cleanup refused or failed; inspect the exact per-run path manually.'
}

function Copy-LocalMedia {
    param([Parameter(Mandatory = $true)][object]$Inventory, [Parameter(Mandatory = $true)][string]$DestinationRoot)

    foreach ($kind in @('public', 'private')) {
        $sourceRoot = Join-Path (Join-Path $ProjectRoot 'storage\app') $kind
        foreach ($file in $Inventory.Media[$kind].Files) {
            $relative = $file.FullName.Substring($sourceRoot.Length).TrimStart('\')
            $destination = Join-Path (Join-Path $DestinationRoot $kind) $relative
            $parent = Split-Path -Parent $destination
            if (-not (Test-Path -LiteralPath $parent)) { New-Item -ItemType Directory -Path $parent -Force | Out-Null }
            Copy-Item -LiteralPath $file.FullName -Destination $destination
        }
    }
}

$tempRoot = $null
$remoteStageRoot = $null
$remoteStageCreated = $false
$deploymentAttempted = $false
try {
    $local = Assert-LocalInputs
    Write-Host "Local release commit and dump validated; $ExpectedTables tables, $ExpectedForeignKeys foreign keys."
    Write-Host "Local media inventory: public=$($local.Media.public.Count) files/$($local.Media.public.Bytes) bytes; private=$($local.Media.private.Count) files/$($local.Media.private.Bytes) bytes."
    if ($DryRun) {
        Write-Host 'Dry run completed locally. No build, SSH connection, upload, SQL, or production operation was performed.'
        return
    }
    if (-not $Approved) { throw 'Production initial release requires the explicit -Approved switch.' }

    Assert-SshIdentity
    & $GitExe -C $ProjectRoot fetch --no-tags origin refs/heads/main
    if ($LASTEXITCODE -ne 0) { throw 'Could not refresh origin/main before production preflight.' }
    & $GitExe -C $ProjectRoot merge-base --is-ancestor $Commit FETCH_HEAD 2>$null
    if ($LASTEXITCODE -ne 0) { throw 'The target commit is no longer reachable from current origin/main.' }

    $runId = [guid]::NewGuid().ToString('N')
    $remoteStageRoot = "$RemoteHome/.idolranking-initial-release-$runId"
    $preflightArgs = @('preflight', $Commit, $BaseUrlExpected, $ExpectedDatabase,
        "$ExpectedOldTables", "$ExpectedTables", "$ExpectedForeignKeys", $ExpectedSha256,
        "$($local.Media.public.Count)", "$($local.Media.public.Bytes)",
        "$($local.Media.private.Count)", "$($local.Media.private.Bytes)", '-', '-', $runId)
    $preflight = Invoke-SshHelper -Arguments $preflightArgs
    $preflight | ForEach-Object { Write-Host $_ }
    $baseLine = $preflight | Where-Object { $_ -match '^PRODUCTION_BASE_COMMIT=[0-9a-f]{40}$' } | Select-Object -First 1
    if (-not $baseLine) { throw 'Remote preflight did not return a verified production base commit.' }
    $baseCommit = ($baseLine -replace '^PRODUCTION_BASE_COMMIT=', '')
    $tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) "idolranking-initial-release-$runId"
    New-Item -ItemType Directory -Path $tempRoot | Out-Null
    $payload = Join-Path $tempRoot 'payload'
    foreach ($folder in @('build', 'images', 'media\public', 'media\private')) {
        New-Item -ItemType Directory -Path (Join-Path $payload $folder) -Force | Out-Null
    }
    $archivePath = Join-Path $tempRoot 'release.zip'
    $sourceRoot = Join-Path $tempRoot 'source'
    & $GitExe -C $ProjectRoot archive --format=zip "--output=$archivePath" $Commit
    if ($LASTEXITCODE -ne 0) { throw 'Could not create the exact release source archive.' }
    Expand-Archive -LiteralPath $archivePath -DestinationPath $sourceRoot
    foreach ($required in @('package-lock.json', 'public\images', 'resources\css\app.css', 'resources\css\admin.css', 'resources\js\app.js', 'resources\js\admin.js')) {
        if (-not (Test-Path -LiteralPath (Join-Path $sourceRoot $required))) { throw "Release archive is missing required input: $required" }
    }

    $npm = (Get-Command npm.cmd -ErrorAction Stop).Source
    Push-Location $sourceRoot
    try {
        $npmCache = Join-Path $tempRoot 'npm-cache'
        New-Item -ItemType Directory -Path $npmCache | Out-Null
        Write-Host 'Building Vite assets in the isolated Git archive.'
        & $npm ci --cache $npmCache --no-audit --no-fund --fetch-retries=1 --fetch-timeout=20000
        if ($LASTEXITCODE -ne 0) { throw 'Isolated npm ci failed.' }
        & $npm run build
        if ($LASTEXITCODE -ne 0) { throw 'Isolated npm run build failed.' }
    }
    finally { Pop-Location }

    $built = Join-Path $sourceRoot 'public\build'
    $manifestPath = Join-Path $built 'manifest.json'
    if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) { throw 'Vite build did not create a manifest.' }
    $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
    foreach ($entry in @('resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.js', 'resources/js/admin.js')) {
        if (-not $manifest.PSObject.Properties[$entry]) { throw "Vite manifest is missing entry $entry." }
    }
    $referenced = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::OrdinalIgnoreCase)
    foreach ($property in $manifest.PSObject.Properties) {
        foreach ($field in @('file', 'css', 'assets')) {
            $manifestProperty = $property.Value.PSObject.Properties[$field]
            if ($null -eq $manifestProperty -or $null -eq $manifestProperty.Value) { continue }
            foreach ($relative in @($manifestProperty.Value)) {
                if ($relative -isnot [string] -or [string]::IsNullOrWhiteSpace($relative)) { continue }
                if ([IO.Path]::IsPathRooted($relative) -or $relative -match '(^|[/\\])\.\.([/\\]|$)') { throw 'Vite manifest contains an unsafe path.' }
                $full = [IO.Path]::GetFullPath((Join-Path $built ($relative -replace '/', '\')))
                if (-not $full.StartsWith(($built.TrimEnd('\') + '\'), [StringComparison]::OrdinalIgnoreCase) -or
                    -not (Test-Path -LiteralPath $full -PathType Leaf)) { throw 'A Vite manifest asset is missing or escapes public/build.' }
                [void]$referenced.Add($relative)
            }
        }
    }
    if ($referenced.Count -eq 0) { throw 'Vite manifest has no verifiable asset references.' }

    $buildDest = Join-Path $payload 'build'
    Get-ChildItem -LiteralPath $built -Force | Copy-Item -Destination $buildDest -Recurse -Force
    Get-ChildItem -LiteralPath (Join-Path $sourceRoot 'public\images') -Force | Copy-Item -Destination (Join-Path $payload 'images') -Recurse -Force
    Copy-LocalMedia -Inventory $local -DestinationRoot (Join-Path $payload 'media')
    Copy-Item -LiteralPath $local.DumpPath -Destination (Join-Path $payload 'input.sql')

    Invoke-SshCommand -Command "umask 077 && mkdir -m 700 -- $remoteStageRoot" -FailureMessage 'Could not create the unique protected staging directory.'
    $remoteStageCreated = $true
    $scp = (Get-Command scp.exe -ErrorAction Stop).Source
    $scpOptions = @(
        '-r', '-P', '65002', '-i', $SshKey,
        '-o', 'IdentitiesOnly=yes', '-o', 'PubkeyAuthentication=yes',
        '-o', 'StrictHostKeyChecking=yes', '-o', "UserKnownHostsFile=$KnownHosts",
        '-o', 'BatchMode=yes', '-o', 'PasswordAuthentication=no',
        '-o', 'KbdInteractiveAuthentication=no', '-o', 'StdinNull=yes', '-o', 'ConnectTimeout=12'
    )
    $scpResult = Invoke-BoundedProcess -FileName $scp -Arguments (@($scpOptions) + @(
        (Join-Path $payload 'build'), (Join-Path $payload 'images'), (Join-Path $payload 'media'),
        (Join-Path $payload 'input.sql'), $RemoteCoordinator, "${Target}:$remoteStageRoot/")) `
        -TimeoutSeconds $ScpTimeoutSeconds -Operation 'Private staging upload'
    if ($scpResult.ExitCode -ne 0) { throw "Private staging upload failed (exit $($scpResult.ExitCode)); review $remoteStageRoot before cleanup." }
    if ($scpResult.StdErr) { [Console]::Error.WriteLine($scpResult.StdErr.TrimEnd()) }

    $stageArgs = @('validate-stage', $Commit, $BaseUrlExpected, $ExpectedDatabase,
        "$ExpectedOldTables", "$ExpectedTables", "$ExpectedForeignKeys", $ExpectedSha256,
        "$($local.Media.public.Count)", "$($local.Media.public.Bytes)",
        "$($local.Media.private.Count)", "$($local.Media.private.Bytes)",
        $remoteStageRoot, $baseCommit, $runId)
    $stagedCoordinator = "$remoteStageRoot/remote-initial-release.sh"
    $stageResult = Invoke-SshHelper -Arguments $stageArgs -RemoteScriptPath $stagedCoordinator
    $stageResult | ForEach-Object { Write-Host $_ }

    $approvalPhrase = "INITIAL RELEASE $Commit REPLACE DATABASE $ExpectedDatabase FROM DUMP $ExpectedSha256 TABLES $ExpectedTables BACKUP $BackupDecision"
    Write-Host "Staging is ready. Database confirmation must match exactly:`n$approvalPhrase"
    $typed = Read-Host 'Type the complete confirmation phrase to continue'
    if ($typed -cne $approvalPhrase) { throw 'Confirmation did not match; production changes were not started.' }

    $deployArgs = @('deploy', $Commit, $BaseUrlExpected, $ExpectedDatabase,
        "$ExpectedOldTables", "$ExpectedTables", "$ExpectedForeignKeys", $ExpectedSha256,
        "$($local.Media.public.Count)", "$($local.Media.public.Bytes)",
        "$($local.Media.private.Count)", "$($local.Media.private.Bytes)",
        $remoteStageRoot, $baseCommit, $runId)
    $deploymentAttempted = $true
    Invoke-SshHelper -Arguments $deployArgs -AllocateTty -RemoteScriptPath $stagedCoordinator
    Write-Host "Initial release completed for $Commit."
}
catch {
    if ($remoteStageCreated -and -not $deploymentAttempted) {
        try {
            Remove-AbandonedRemoteStage -StagePath $remoteStageRoot
            Write-Warning 'Removed only this run’s verified private staging directory because deployment had not started.'
        }
        catch { Write-Warning "Protected remote staging may remain at $remoteStageRoot; cleanup checks refused or could not complete." }
    }
    elseif ($remoteStageCreated) { Write-Warning "Protected remote staging remains at $remoteStageRoot for manual inspection after a deployment attempt." }
    Write-Error $_ -ErrorAction Continue
    throw
}
finally {
    if ($tempRoot -and (Test-Path -LiteralPath $tempRoot) -and
        $tempRoot.StartsWith([IO.Path]::GetTempPath(), [StringComparison]::OrdinalIgnoreCase) -and
        (Split-Path -Leaf $tempRoot) -match '^idolranking-initial-release-[0-9a-f]{32}$') {
        try { Remove-Item -LiteralPath $tempRoot -Recurse -Force } catch { Write-Warning 'Local isolated build directory could not be removed.' }
    }
}
