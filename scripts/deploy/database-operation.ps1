[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('Patch', 'Replace')]
    [string]$Mode,

    [Parameter(Mandatory = $true)]
    [string]$SqlFile,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-fA-F]{64}$')]
    [string]$Sha256,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_]{1,64}$')]
    [string]$TargetDatabase,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_.@-]{1,128}$')]
    [string]$DatabaseUser,

    [Parameter(Mandatory = $true)]
    [uri]$BaseUrl,

    [ValidatePattern('^(localhost|127(?:\.[0-9]{1,3}){3}|10(?:\.[0-9]{1,3}){3}|192\.168(?:\.[0-9]{1,3}){2}|172\.(1[6-9]|2[0-9]|3[01])(?:\.[0-9]{1,3}){2}|::1)$')]
    [string]$DatabaseHost = 'localhost',

    [ValidateRange(1, 65535)]
    [int]$DatabasePort = 3306,

    [string]$SshKeyPath = (Join-Path $env:USERPROFILE '.ssh\idolranking_hostinger_ed25519'),
    [string]$KnownHostsPath = (Join-Path $env:USERPROFILE '.ssh\known_hosts'),

    [switch]$Approved,
    [switch]$UseSshTunnel,
    [string]$DatabaseConfirmation,
    [string]$ConfirmSha256,
    [int]$ExpectedTableCount = 0,
    [string]$BackupReference
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if (Get-Variable -Name PSNativeCommandUseErrorActionPreference -ErrorAction SilentlyContinue) {
    $PSNativeCommandUseErrorActionPreference = $false
}

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path.TrimEnd('\')
$SshKey = [System.IO.Path]::GetFullPath($SshKeyPath)
$PublicKey = "$SshKey.pub"
$KnownHosts = [System.IO.Path]::GetFullPath($KnownHostsPath)
$SshExe = (Get-Command ssh.exe -ErrorAction Stop).Source
$ScpExe = (Get-Command scp.exe -ErrorAction Stop).Source
$Target = 'u624668418@145.79.26.175'
$RemoteHome = '/home/u624668418'
$RemoteScript = Join-Path $PSScriptRoot 'remote-database-operation.sh'

function Get-SshPublicKeyParts {
    param([Parameter(Mandatory = $true)][string]$Text)

    $lines = @($Text -split '\r?\n' | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    if ($lines.Count -ne 1) { return $null }

    $fields = $lines[0].Trim() -split '\s+'
    if ($fields.Count -lt 2 -or $fields[0] -notmatch '^[A-Za-z0-9][A-Za-z0-9@._+-]*$') { return $null }
    try {
        $keyData = [Convert]::FromBase64String($fields[1])
        if ($keyData.Length -eq 0) { return $null }
    }
    catch { return $null }

    # Public-key comments are optional and intentionally excluded from pair matching.
    return [pscustomobject]@{ Type = $fields[0]; Data = $fields[1] }
}

if ($BaseUrl.Scheme -ne 'https' -or $BaseUrl.UserInfo -or $BaseUrl.Query -or $BaseUrl.Fragment -or $BaseUrl.AbsolutePath -ne '/') {
    throw 'BaseUrl must be the HTTPS site origin only.'
}
$BaseUrlText = $BaseUrl.GetLeftPart([System.UriPartial]::Authority)
if ($BaseUrlText -notmatch '^https://[A-Za-z0-9.-]+(:[0-9]{1,5})?$') { throw 'BaseUrl contains unsupported characters.' }

if (-not $Approved) { throw 'Database operations require the explicit -Approved switch.' }
if (-not (Test-Path -LiteralPath $SshKey -PathType Leaf) -or -not (Test-Path -LiteralPath $PublicKey -PathType Leaf) -or -not (Test-Path -LiteralPath $KnownHosts -PathType Leaf)) {
    throw 'The dedicated key or pinned known_hosts file is missing.'
}
if (-not (Test-Path -LiteralPath $RemoteScript -PathType Leaf)) { throw 'Remote database helper is missing.' }

$hostEntry = & (Get-Command ssh-keygen.exe -ErrorAction Stop).Source -F '[145.79.26.175]:65002' -f $KnownHosts 2>$null
if ($LASTEXITCODE -ne 0 -or -not $hostEntry) {
    throw 'Production host key is not pinned in known_hosts; refusing the database operation.'
}

$identity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
$account = $identity.User.Translate([System.Security.Principal.NTAccount]).Value
$keyAcl = Get-Acl -LiteralPath $SshKey
$keyAces = @($keyAcl.Access)
if (-not $keyAcl.AreAccessRulesProtected -or $keyAces.Count -ne 1 -or
    $keyAces[0].IdentityReference.Translate([System.Security.Principal.NTAccount]).Value -ne $account -or
    $keyAces[0].AccessControlType -ne [System.Security.AccessControl.AccessControlType]::Allow -or
    $keyAces[0].FileSystemRights.ToString() -notmatch 'FullControl') {
    throw 'The dedicated SSH private key ACL is not restricted to the current Windows account.'
}
$keygenInfo = New-Object System.Diagnostics.ProcessStartInfo
$keygenInfo.FileName = (Get-Command ssh-keygen.exe -ErrorAction Stop).Source
$keygenInfo.Arguments = '-y -P "" -f "' + $SshKey + '"'
$keygenInfo.UseShellExecute = $false
$keygenInfo.CreateNoWindow = $true
$keygenInfo.RedirectStandardOutput = $true
$keygenInfo.RedirectStandardError = $true
$keygenProcess = [System.Diagnostics.Process]::Start($keygenInfo)
$derivedPublicKey = $keygenProcess.StandardOutput.ReadToEnd()
$null = $keygenProcess.StandardError.ReadToEnd()
$keygenProcess.WaitForExit()
$derivedParts = Get-SshPublicKeyParts -Text $derivedPublicKey
$savedParts = Get-SshPublicKeyParts -Text (Get-Content -LiteralPath $PublicKey -Raw)
if ($keygenProcess.ExitCode -ne 0 -or $null -eq $derivedParts -or $null -eq $savedParts -or
    $derivedParts.Type -cne $savedParts.Type -or $derivedParts.Data -cne $savedParts.Data) {
    throw 'Dedicated SSH key pair verification failed.'
}

$resolvedFile = (Resolve-Path -LiteralPath $SqlFile -ErrorAction Stop).Path
$fileName = [System.IO.Path]::GetFileName($resolvedFile)
if (-not (Test-Path -LiteralPath $resolvedFile -PathType Leaf)) { throw 'SQL input must be a regular file.' }
if ((Get-Item -LiteralPath $resolvedFile).Length -le 0) { throw 'SQL input is empty.' }
if ((Get-Item -LiteralPath $resolvedFile).Attributes -band [System.IO.FileAttributes]::ReparsePoint) {
    throw 'SQL input must not be a symlink or reparse point.'
}

if ($Mode -eq 'Patch') {
    $patchRoot = (Join-Path $ProjectRoot 'sql\patches').TrimEnd('\') + '\'
    if (-not $resolvedFile.StartsWith($patchRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'Patch mode accepts files only from the repository sql/patches directory.'
    }
    if ($fileName -notmatch '^\d{8}_\d{3}_[A-Za-z0-9_-]+\.sql$') {
        throw 'Patch filename must use the project YYYYMMDD_NNN_name.sql format.'
    }
    $expectedTables = 0
    $backupReferenceValue = "patch:$fileName"
}
else {
    if ($resolvedFile.StartsWith(($ProjectRoot + '\'), [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'Database dumps must be stored outside the Git repository.'
    }
    if ($ExpectedTableCount -lt 1) { throw 'Replace mode requires a positive -ExpectedTableCount.' }
    if ($ConfirmSha256 -cne $Sha256) { throw 'ConfirmSha256 must exactly match Sha256.' }
    if ([string]::IsNullOrWhiteSpace($BackupReference)) {
        throw 'Provide a backup reference, or the explicit value APPROVED-NO-BACKUP.'
    }
    if ($BackupReference -notmatch '^[A-Za-z0-9._:/-]{1,160}$') { throw 'BackupReference contains unsupported characters.' }
    $expectedTables = $ExpectedTableCount
    $backupReferenceValue = $BackupReference

    # Only accept single-database dumps. Database-level DDL/USE statements are disallowed.
    $reader = [System.IO.StreamReader]::new($resolvedFile, [System.Text.Encoding]::UTF8, $true)
    try {
        while (($line = $reader.ReadLine()) -ne $null) {
            if ($line -match '^\s*(?i:CREATE|DROP)\s+DATABASE\b' -or $line -match '^\s*(?i:USE)\s+') {
                throw 'Dump contains database selection or database-level DDL; export a single-database SQL dump.'
            }
        }
    }
    finally { $reader.Dispose() }
}

$actualSha = (Get-FileHash -LiteralPath $resolvedFile -Algorithm SHA256).Hash.ToUpperInvariant()
$expectedSha = $Sha256.ToUpperInvariant()
if ($actualSha -ne $expectedSha) { throw 'SQL file SHA-256 does not match the approved checksum.' }

$approvalPhrase = if ($Mode -eq 'Patch') {
    "APPLY PATCH $fileName TO $TargetDatabase WITH $expectedSha"
}
else {
    "REPLACE DATABASE $TargetDatabase FROM DUMP $expectedSha TABLES $expectedTables BACKUP $backupReferenceValue"
}
if ($DatabaseConfirmation -cne $approvalPhrase) {
    throw 'DatabaseConfirmation must exactly match the requested target, file, checksum, and mode.'
}
if ($UseSshTunnel -and $Mode -ne 'Patch') { throw '-UseSshTunnel is supported only for forward SQL patches.' }
if ($UseSshTunnel -and $DatabaseHost -ne '127.0.0.1') { throw 'Tunnel mode requires the deployment helper DB_HOST to remain exactly 127.0.0.1.' }
$runId = [guid]::NewGuid().ToString('N')
$stageRoot = "$RemoteHome/.idolranking-sql-staging-$runId"
$stageFile = "$stageRoot/input.sql"
$mkdirCommand = "umask 077 && mkdir -m 700 -- $stageRoot"
$commonSshOptions = @(
    '-p', '65002', '-i', $SshKey,
    '-o', 'IdentitiesOnly=yes',
    '-o', 'StrictHostKeyChecking=yes',
    '-o', "UserKnownHostsFile=$KnownHosts",
    '-o', 'BatchMode=yes',
    '-o', 'PubkeyAuthentication=yes',
    '-o', 'PreferredAuthentications=publickey',
    '-o', 'PasswordAuthentication=no',
    '-o', 'KbdInteractiveAuthentication=no',
    '-o', 'ConnectTimeout=12'
)
$sshOptions = @('-tt') + $commonSshOptions

Write-Host "Mode: $Mode"
Write-Host "Target database: $TargetDatabase"
Write-Host "Input SHA-256 verified: $expectedSha"
Write-Host 'Database passwords are requested by the MariaDB client and are not passed as command arguments.'
$typedApproval = Read-Host "Type exactly to continue: $approvalPhrase"
if ($typedApproval -cne $approvalPhrase) { throw 'Typed approval did not match; no production connection was made.' }
& $SshExe @commonSshOptions $Target $mkdirCommand
if ($LASTEXITCODE -ne 0) { throw 'Could not create the private SQL staging directory.' }

$scpOptions = @(
    '-P', '65002', '-i', $SshKey,
    '-o', 'IdentitiesOnly=yes',
    '-o', 'StrictHostKeyChecking=yes',
    '-o', "UserKnownHostsFile=$KnownHosts",
    '-o', 'BatchMode=yes',
    '-o', 'PubkeyAuthentication=yes',
    '-o', 'PasswordAuthentication=no',
    '-o', 'KbdInteractiveAuthentication=no',
    '-o', 'ConnectTimeout=12'
)
& $ScpExe @scpOptions $resolvedFile "${Target}:$stageFile"
if ($LASTEXITCODE -ne 0) { throw "SQL staging failed; partial data, if any, remains privately at $stageRoot." }

$encodedScript = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes((Get-Content -LiteralPath $RemoteScript -Raw)))
$dbArgs = @($Mode.ToLowerInvariant(), $TargetDatabase, $DatabaseUser, $DatabaseHost, "$DatabasePort", $stageFile, $expectedSha, "$expectedTables", $backupReferenceValue, $BaseUrlText) -join ' '
$remoteCommand = "printf '%s' '$encodedScript' | base64 -d | bash -s -- $dbArgs"

if ($UseSshTunnel) {
    $mysqlExe = (Get-Command mysql.exe -ErrorAction Stop).Source
    $phpCode = '<?php require "/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking/vendor/autoload.php"; $app=require "/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking/bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $c=config("database.connections.".config("database.default")); echo json_encode(["host"=>$c["host"],"port"=>(int)$c["port"],"database"=>$c["database"],"username"=>$c["username"]]);'
    $encodedPhp = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($phpCode))
    $endpointPhpPath = "$RemoteHome/.idolranking-db-endpoint-$runId.php"
    $endpointCommand = "umask 077; printf '%s' '$encodedPhp' | base64 -d > '$endpointPhpPath' && /opt/alt/php84/usr/bin/php '$endpointPhpPath'; endpoint_status=`$?; rm -f -- '$endpointPhpPath'; exit `$endpoint_status"
    $endpointLines = @(& $SshExe @commonSshOptions $Target $endpointCommand 2>$null)
    if ($LASTEXITCODE -ne 0 -or $endpointLines.Count -ne 1) { throw 'Could not read the production Laravel database endpoint through the pinned SSH connection.' }
    try { $endpoint = ($endpointLines[0] | ConvertFrom-Json -ErrorAction Stop) }
    catch { throw 'Production Laravel returned an invalid database endpoint response.' }
    if ($endpoint.host -notmatch '^(?=.{1,253}$)([A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(\.([A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*$' -or
        [int]$endpoint.port -lt 1 -or [int]$endpoint.port -gt 65535 -or
        $endpoint.database -cne $TargetDatabase -or $endpoint.username -cne $DatabaseUser) {
        throw 'The Laravel database endpoint did not match the approved database/user or contains an unsupported host.'
    }
    if ([int]$endpoint.port -ne $DatabasePort) { throw 'DatabasePort does not match the port configured by production Laravel.' }

    $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
    $listener.Start()
    $localPort = ([System.Net.IPEndPoint]$listener.LocalEndpoint).Port
    $listener.Stop()
    $tunnelProcess = $null
    $credentialFile = Join-Path ([System.IO.Path]::GetTempPath()) ("idolranking-db-" + [guid]::NewGuid().ToString('N') + '.cnf')
    $credentialCreated = $false
    $helperLaunched = $false
    $applyStarted = $false
    $passwordSecure = $null
    $passwordPlain = $null
    $escapedPassword = $null

    function Send-TunnelStatus {
        param([Parameter(Mandatory = $true)][ValidateSet('PREFLIGHT_OK','PREFLIGHT_FAILED','APPLY_OK','APPLY_FAILED')][string]$Value)
        $statusCommand = "printf '%s' '$Value' > '$stageRoot/client.status'"
        & $SshExe -n @commonSshOptions $Target $statusCommand
        if ($LASTEXITCODE -ne 0) { throw 'Could not signal the locked remote patch runner.' }
    }

    function Get-TunnelServerStatus {
        $statusCommand = "if [ -f '$stageRoot/server.status' ]; then cat '$stageRoot/server.status'; fi"
        $lines = @(& $SshExe -n @commonSshOptions $Target $statusCommand 2>$null)
        if ($LASTEXITCODE -ne 0) { throw 'Could not read the remote patch runner state.' }
        return (($lines -join '').Trim())
    }

    function Invoke-TunnelMySql {
        param([Parameter(Mandatory = $true)][AllowEmptyString()][string]$Sql, [switch]$SourceFile)
        $mysqlArgs = @("--defaults-extra-file=$credentialFile", '--protocol=TCP', '--host=127.0.0.1', "--port=$localPort", "--user=$DatabaseUser", '--default-character-set=utf8mb4', '--batch', '--skip-column-names', "--database=$TargetDatabase")
        if (-not $SourceFile) { $mysqlArgs += "--execute=$Sql" }
        $quotedArgs = ($mysqlArgs | ForEach-Object {
            if ($_ -match '[\s"]') { '"' + $_.Replace('\', '\\').Replace('"', '\"') + '"' } else { $_ }
        }) -join ' '
        $processInfo = [System.Diagnostics.ProcessStartInfo]::new()
        $processInfo.FileName = $mysqlExe
        $processInfo.Arguments = $quotedArgs
        $processInfo.UseShellExecute = $false
        $processInfo.CreateNoWindow = $true
        $processInfo.RedirectStandardInput = [bool]$SourceFile
        $processInfo.RedirectStandardOutput = $true
        $processInfo.RedirectStandardError = $true
        $process = [System.Diagnostics.Process]::Start($processInfo)
        try {
            if ($SourceFile) {
                $sqlBody = [System.IO.File]::ReadAllText($resolvedFile, [System.Text.Encoding]::UTF8)
                $sqlBytes = [System.Text.UTF8Encoding]::new($false).GetBytes($sqlBody)
                $process.StandardInput.BaseStream.Write($sqlBytes, 0, $sqlBytes.Length)
                $process.StandardInput.BaseStream.Flush()
                $process.StandardInput.Close()
            }
            $stdout = $process.StandardOutput.ReadToEnd()
            $stderr = $process.StandardError.ReadToEnd()
            $process.WaitForExit()
            $exitCode = $process.ExitCode
            if ($exitCode -ne 0) { throw "Local MySQL operation failed through the SSH tunnel: $($stderr.Trim())" }
            return $stdout.Trim()
        }
        finally {
            if (-not $process.HasExited) {
                try { $process.Kill(); $process.WaitForExit() } catch { }
            }
            $process.Dispose()
        }
    }

    try {
        $passwordSecure = Read-Host 'Production database password (entered locally; not passed as a process argument)' -AsSecureString
        $passwordPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordSecure)
        try { $passwordPlain = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPtr) }
        finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPtr) }
        if ($passwordPlain -match '[\r\n\x00]') { throw 'Database password contains unsupported control characters.' }
        $escapedPassword = $passwordPlain.Replace('\', '\\').Replace('"', '\"')
        $credentialHandle = [System.IO.File]::Open($credentialFile, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
        $credentialHandle.Dispose()
        $credentialCreated = $true
        $credentialAcl = Get-Acl -LiteralPath $credentialFile
        $credentialAcl.SetAccessRuleProtection($true, $false)
        foreach ($rule in @($credentialAcl.Access)) { [void]$credentialAcl.RemoveAccessRule($rule) }
        $credentialAcl.AddAccessRule([System.Security.AccessControl.FileSystemAccessRule]::new($account, 'FullControl', 'Allow'))
        Set-Acl -LiteralPath $credentialFile -AclObject $credentialAcl
        [System.IO.File]::WriteAllText($credentialFile, "[client]`r`nhost=127.0.0.1`r`nuser=$DatabaseUser`r`npassword=`"$escapedPassword`"`r`n", [System.Text.UTF8Encoding]::new($false))
        $passwordPlain = $null; $escapedPassword = $null

        $tunnelArguments = @($commonSshOptions) + @('-N', '-o', 'ExitOnForwardFailure=yes', '-L', "127.0.0.1:$localPort`:$($endpoint.host):$($endpoint.port)", $Target)
        $quotedTunnelArguments = ($tunnelArguments | ForEach-Object { '"' + $_.Replace('"', '\"') + '"' }) -join ' '
        $tunnelProcess = Start-Process -FilePath $SshExe -ArgumentList $quotedTunnelArguments -PassThru -WindowStyle Hidden
        $tunnelReady = $false
        for ($attempt = 0; $attempt -lt 30; $attempt++) {
            if ($tunnelProcess.HasExited) { throw 'The SSH local tunnel exited before becoming ready.' }
            try {
                $probe = [System.Net.Sockets.TcpClient]::new()
                $probe.Connect('127.0.0.1', $localPort)
                $probe.Dispose(); $tunnelReady = $true; break
            }
            catch { Start-Sleep -Milliseconds 500 }
        }
        if (-not $tunnelReady) { throw 'The SSH local tunnel did not open its loopback listener.' }

        $tunnelArgs = @('patch-tunnel', $TargetDatabase, $DatabaseUser, '127.0.0.1', "$DatabasePort", $stageFile, $expectedSha, '0', $backupReferenceValue, $BaseUrlText) -join ' '
        $helperPath = "$stageRoot/remote-database-operation.sh"
        $launchCommand = "printf '%s' '$encodedScript' | base64 -d > '$helperPath' && chmod 700 '$helperPath' || exit `$?; printf 'STARTING' > '$stageRoot/server.status'; nohup bash '$helperPath' $tunnelArgs > '$stageRoot/tunnel-runner.log' 2>&1 < /dev/null & echo `$!"
        & $SshExe @commonSshOptions $Target $launchCommand
        if ($LASTEXITCODE -ne 0) { throw 'Could not start the locked remote tunnel patch runner.' }
        $helperLaunched = $true
        $state = ''
        for ($attempt = 0; $attempt -lt 60; $attempt++) {
            $state = Get-TunnelServerStatus
            if ($state -eq 'READY') { break }
            if ($state -eq 'FAILED') { throw 'The remote patch runner rejected the operation before database preflight.' }
            Start-Sleep -Seconds 2
        }
        if ($state -ne 'READY') { throw 'The remote patch runner did not acquire its lock and become ready.' }

        $historyExists = Invoke-TunnelMySql -Sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_patch_history' AND table_type = 'BASE TABLE';"
        if ($fileName -eq '20261013_001_create_schema_patch_history.sql') {
            if ($historyExists -ne '0') { throw 'Bootstrap patch applies only when schema_patch_history is absent.' }
        }
        else {
            if ($historyExists -ne '1') { throw 'schema_patch_history is absent; apply the approved bootstrap patch first.' }
            $alreadyApplied = Invoke-TunnelMySql -Sql "SELECT COUNT(*) FROM schema_patch_history WHERE patch_name = '$fileName';"
            if ($alreadyApplied -ne '0') { throw 'Patch is already recorded; inspect schema before retrying.' }
        }
        if ($fileName -eq '20261018_001_drop_unused_fan_post_monthly_views.sql') {
            $tableCount = Invoke-TunnelMySql -Sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'fan_post_monthly_views' AND table_type = 'BASE TABLE';"
            if ($tableCount -ne '1') { throw 'Expected fan_post_monthly_views is missing; destructive patch was stopped.' }
            $rowCount = Invoke-TunnelMySql -Sql 'SELECT COUNT(*) FROM fan_post_monthly_views;'
            if ($rowCount -ne '0') { throw 'fan_post_monthly_views is not empty; destructive patch was stopped.' }
        }
        Send-TunnelStatus 'PREFLIGHT_OK'

        for ($attempt = 0; $attempt -lt 120; $attempt++) {
            $state = Get-TunnelServerStatus
            if ($state -eq 'APPLY') { $applyStarted = $true; break }
            if ($state -eq 'FAILED') { throw 'Remote patch runner failed before SQL import.' }
            Start-Sleep -Seconds 2
        }
        if (-not $applyStarted) { throw 'Remote patch runner did not enter the guarded apply phase.' }
        [void](Invoke-TunnelMySql -SourceFile -Sql '')
        [void](Invoke-TunnelMySql -Sql "INSERT INTO schema_patch_history (patch_name, applied_by, notes) VALUES ('$fileName', 'codex-deploy', 'Reviewed single-patch deployment through SSH tunnel');")
        Send-TunnelStatus 'APPLY_OK'

        for ($attempt = 0; $attempt -lt 180; $attempt++) {
            $state = Get-TunnelServerStatus
            if ($state -eq 'DONE') { break }
            if ($state -eq 'FAILED') { throw 'SQL and ledger were applied, but cache refresh or post-patch smoke checks failed; inspect production before retrying.' }
            Start-Sleep -Seconds 2
        }
        if ($state -ne 'DONE') { throw 'Remote cache refresh and smoke checks did not complete in time.' }
        Write-Host 'The selected patch completed under the existing remote lock, checksum, ledger, maintenance, cache, and smoke-test controls through a loopback-only SSH tunnel.'
    }
    catch {
        if ($helperLaunched) {
            $failureState = if ($applyStarted) { 'APPLY_FAILED' } else { 'PREFLIGHT_FAILED' }
            try { Send-TunnelStatus $failureState } catch { }
        }
        throw
    }
    finally {
        if ($null -ne $tunnelProcess -and -not $tunnelProcess.HasExited) {
            Stop-Process -Id $tunnelProcess.Id -Force -ErrorAction SilentlyContinue
        }
        if ($credentialCreated -and (Test-Path -LiteralPath $credentialFile)) { Remove-Item -LiteralPath $credentialFile -Force -ErrorAction SilentlyContinue }
        if ($null -ne $passwordSecure) { $passwordSecure.Dispose() }
        $passwordPlain = $null; $escapedPassword = $null
    }
    return
}

& $SshExe @sshOptions $Target $remoteCommand
if ($LASTEXITCODE -ne 0) {
    throw 'Remote database operation failed or was cancelled. Inspect the database before retrying; SQL DDL may be non-transactional.'
}

Write-Host 'The selected database operation and guarded Laravel cache/smoke checks completed. No Git code or public assets were deployed.'
