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

    [switch]$Approved,
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
$SshKey = 'C:\Users\LEO\.ssh\idolranking_hostinger_ed25519'
$PublicKey = "$SshKey.pub"
$KnownHosts = 'C:\Users\LEO\.ssh\known_hosts'
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
    $keyAces[0].FileSystemRights.ToString() -notmatch 'FullControl' -or $account -notmatch '\\LEO$') {
    throw 'The dedicated SSH private key ACL is not restricted to the current LEO account.'
}
$keygenInfo = New-Object System.Diagnostics.ProcessStartInfo
$keygenInfo.FileName = (Get-Command ssh-keygen.exe -ErrorAction Stop).Source
$keygenInfo.Arguments = '-y -P "" -f C:\Users\LEO\.ssh\idolranking_hostinger_ed25519'
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
& $SshExe @sshOptions $Target $remoteCommand
if ($LASTEXITCODE -ne 0) {
    throw 'Remote database operation failed or was cancelled. Inspect the database before retrying; SQL DDL may be non-transactional.'
}

Write-Host 'The selected database operation and guarded Laravel cache/smoke checks completed. No Git code or public assets were deployed.'
