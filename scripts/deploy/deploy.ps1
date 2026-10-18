[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-fA-F]{40}$')]
    [string]$Commit,

    [Parameter(Mandatory = $true)]
    [uri]$BaseUrl,

    [switch]$DryRun,

    [switch]$Build
)

if ($DryRun -and $Build) {
    throw 'Choose one mode: -DryRun validates only; -Build creates isolated production assets.'
}

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if (Get-Variable -Name PSNativeCommandUseErrorActionPreference -ErrorAction SilentlyContinue) {
    $PSNativeCommandUseErrorActionPreference = $false
}

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$DeployRequested = -not $DryRun -and -not $Build
$SshKey = 'C:\Users\LEO\.ssh\idolranking_hostinger_ed25519'
$KnownHosts = 'C:\Users\LEO\.ssh\known_hosts'
$SshExe = (Get-Command ssh.exe -ErrorAction Stop).Source
$ScpExe = (Get-Command scp.exe -ErrorAction Stop).Source
$GitExe = (Get-Command git.exe -ErrorAction Stop).Source
$NpmExe = if (-not $DryRun) { (Get-Command npm.cmd -ErrorAction Stop).Source } else { $null }
$Target = 'u624668418@145.79.26.175'
$RemoteApp = '/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/idolranking'
$RemotePublic = '/home/u624668418/domains/orchid-oryx-674671.hostingersite.com/public_html'
$RemoteHome = '/home/u624668418'
$RemoteScript = Join-Path $PSScriptRoot 'remote-code-deploy.sh'

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

function Assert-SshIdentity {
    $identity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
    $account = $identity.User.Translate([System.Security.Principal.NTAccount]).Value
    $acl = Get-Acl -LiteralPath $SshKey
    $entries = @($acl.Access)
    if (-not $acl.AreAccessRulesProtected -or $entries.Count -ne 1 -or
        $entries[0].IdentityReference.Translate([System.Security.Principal.NTAccount]).Value -ne $account -or
        $entries[0].AccessControlType -ne [System.Security.AccessControl.AccessControlType]::Allow -or
        $entries[0].FileSystemRights.ToString() -notmatch 'FullControl' -or $account -notmatch '\\LEO$') {
        throw 'The dedicated SSH private key ACL is not restricted to the current LEO account.'
    }

    # Derive the public half only into memory and compare it; never print private-key contents.
    $keygen = (Get-Command ssh-keygen.exe -ErrorAction Stop).Source
    $processInfo = New-Object System.Diagnostics.ProcessStartInfo
    $processInfo.FileName = $keygen
    $processInfo.Arguments = '-y -P "" -f C:\Users\LEO\.ssh\idolranking_hostinger_ed25519'
    $processInfo.UseShellExecute = $false
    $processInfo.CreateNoWindow = $true
    $processInfo.RedirectStandardOutput = $true
    $processInfo.RedirectStandardError = $true
    $process = [System.Diagnostics.Process]::Start($processInfo)
    $derivedPublicKey = $process.StandardOutput.ReadToEnd().Trim()
    $null = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    $derivedParts = Get-SshPublicKeyParts -Text $derivedPublicKey
    $savedParts = Get-SshPublicKeyParts -Text (Get-Content -LiteralPath "$SshKey.pub" -Raw)
    if ($process.ExitCode -ne 0 -or $null -eq $derivedParts -or $null -eq $savedParts -or
        $derivedParts.Type -cne $savedParts.Type -or $derivedParts.Data -cne $savedParts.Data) {
        throw 'Dedicated SSH key pair verification failed.'
    }
}

if ($BaseUrl.Scheme -ne 'https' -or $BaseUrl.UserInfo -or $BaseUrl.Query -or $BaseUrl.Fragment -or $BaseUrl.AbsolutePath -ne '/') {
    throw 'BaseUrl must be the HTTPS site origin only, for example https://idol.example.com.'
}
$BaseUrlText = $BaseUrl.GetLeftPart([System.UriPartial]::Authority)
if ($BaseUrlText -notmatch '^https://[A-Za-z0-9.-]+(:[0-9]{1,5})?$') {
    throw 'BaseUrl contains unsupported characters.'
}

foreach ($requiredPath in @($RemoteScript, (Join-Path $ProjectRoot 'package-lock.json'))) {
    if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
        throw "Required deployment input is missing: $requiredPath"
    }
}

# Only deployment needs credentials or a production connection. Dry-run and build stay local.
if ($DeployRequested) {
    foreach ($requiredPath in @($SshKey, "$SshKey.pub", $KnownHosts)) {
        if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
            throw "Required SSH input is missing or unreadable: $requiredPath"
        }
    }
    $hostEntry = & (Get-Command ssh-keygen.exe -ErrorAction Stop).Source -F '[145.79.26.175]:65002' -f $KnownHosts 2>$null
    if ($LASTEXITCODE -ne 0 -or -not $hostEntry) {
        throw 'The production host key is not pinned in known_hosts. Verify it with Hostinger before continuing.'
    }
    Assert-SshIdentity
}

$objectName = "${Commit}^{commit}"
& $GitExe -C $ProjectRoot cat-file -e $objectName 2>$null
if ($LASTEXITCODE -ne 0) {
    throw "Commit $Commit is not available in the local Git repository."
}
$resolvedCommit = (& $GitExe -C $ProjectRoot rev-parse --verify $objectName 2>$null | Select-Object -First 1).Trim()
if ($resolvedCommit -ne $Commit.ToLowerInvariant()) {
    throw 'The requested commit did not resolve exactly.'
}

$runId = [guid]::NewGuid().ToString('N')
$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) "idolranking-deploy-$($Commit.ToLowerInvariant())-$runId"
$archivePath = Join-Path $tempRoot 'source.zip'
$sourceRoot = Join-Path $tempRoot 'source'
$logRoot = Join-Path $tempRoot 'logs'
$logPath = Join-Path $logRoot "deploy-$($Commit.ToLowerInvariant())-$runId.log"
$transcriptStarted = $false

if ($DryRun) {
    Write-Host "Validated release commit: $($Commit.ToLowerInvariant())"
    Write-Host 'Dry run completed locally. It validates the exact Git object and required local deployment files only.'
    Write-Host 'No temporary files, npm commands, credentials, network connections, or production resources were accessed or changed.'
    exit 0
}

function Invoke-RemoteScript {
    param(
        [Parameter(Mandatory = $true)][string]$ScriptPath,
        [Parameter(Mandatory = $true)][string[]]$Arguments,
        [switch]$AllocateTty
    )

    $scriptText = Get-Content -LiteralPath $ScriptPath -Raw
    $encoded = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($scriptText))
    # Quote each fixed-path/validated argument so spaces or shell metacharacters
    # cannot change the remote helper's argument boundaries.
    $remoteArguments = ($Arguments | ForEach-Object { "'$_'" }) -join ' '
    $remoteCommand = "printf '%s' '$encoded' | base64 -d | bash -s -- $remoteArguments"
    $ttyOption = if ($AllocateTty) { '-tt' } else { '-T' }
    $options = @(
        $ttyOption, '-p', '65002', '-i', $SshKey,
        '-o', 'IdentitiesOnly=yes',
        '-o', 'PubkeyAuthentication=yes',
        '-o', 'StrictHostKeyChecking=yes',
        '-o', "UserKnownHostsFile=$KnownHosts",
        '-o', 'BatchMode=yes',
        '-o', 'PasswordAuthentication=no',
        '-o', 'KbdInteractiveAuthentication=no',
        '-o', 'PreferredAuthentications=publickey',
        '-o', 'ConnectTimeout=12'
    )
    & $SshExe @options $Target $remoteCommand
    if ($LASTEXITCODE -ne 0) { throw "Remote deployment script failed with exit code $LASTEXITCODE." }
}

function Invoke-RemoteCommand {
    param([Parameter(Mandatory = $true)][string]$Command)

    $options = @(
        '-T', '-p', '65002', '-i', $SshKey,
        '-o', 'IdentitiesOnly=yes',
        '-o', 'PubkeyAuthentication=yes',
        '-o', 'StrictHostKeyChecking=yes',
        '-o', "UserKnownHostsFile=$KnownHosts",
        '-o', 'BatchMode=yes',
        '-o', 'PreferredAuthentications=publickey',
        '-o', 'PasswordAuthentication=no',
        '-o', 'KbdInteractiveAuthentication=no',
        '-o', 'ConnectTimeout=12'
    )
    & $SshExe @options $Target $Command
    if ($LASTEXITCODE -ne 0) { throw "Remote staging-directory creation failed with exit code $LASTEXITCODE." }
}

try {
    New-Item -ItemType Directory -Path $tempRoot -Force | Out-Null
    New-Item -ItemType Directory -Path $logRoot -Force | Out-Null
    Start-Transcript -Path $logPath -Force | Out-Null
    $transcriptStarted = $true

    Write-Host "Release commit: $($Commit.ToLowerInvariant())"
    Write-Host "Local log: $logPath"
    Write-Host 'Creating an isolated source archive for the requested commit.'

    & $GitExe -C $ProjectRoot archive --format=zip "--output=$archivePath" $Commit
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $archivePath -PathType Leaf)) {
        throw 'Could not create the requested commit archive.'
    }
    Expand-Archive -LiteralPath $archivePath -DestinationPath $sourceRoot

    if (-not (Test-Path -LiteralPath (Join-Path $sourceRoot 'package-lock.json') -PathType Leaf)) {
        throw 'The requested commit does not contain package-lock.json.'
    }
    foreach ($assetPath in @('public\images', 'public\favicon.ico')) {
        if (-not (Test-Path -LiteralPath (Join-Path $sourceRoot $assetPath))) {
            throw "The requested commit is missing required public assets: $assetPath"
        }
    }

    Push-Location $sourceRoot
    try {
        $isolatedNpmCache = Join-Path $tempRoot 'npm-cache'
        New-Item -ItemType Directory -Path $isolatedNpmCache -Force | Out-Null
        Write-Host 'Running npm ci in the isolated release archive (never in the development project).'
        & $NpmExe ci --cache $isolatedNpmCache --no-audit --no-fund --fetch-retries=1 --fetch-timeout=20000
        if ($LASTEXITCODE -ne 0) { throw 'npm ci failed.' }

        Write-Host 'Building Vite production assets.'
        & $NpmExe run build
        if ($LASTEXITCODE -ne 0) { throw 'npm run build failed.' }
    }
    finally {
        Pop-Location
    }

    $buildRoot = Join-Path $sourceRoot 'public\build'
    $manifestPath = Join-Path $buildRoot 'manifest.json'
    if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) {
        throw 'Vite build did not create public/build/manifest.json.'
    }

    # Check every Vite manifest reference stays under public/build and exists before transfer.
    $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
    $manifestFiles = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::OrdinalIgnoreCase)
    foreach ($entryProperty in $manifest.PSObject.Properties) {
        $entry = $entryProperty.Value
        foreach ($field in @('file', 'css', 'assets')) {
            $manifestProperty = $entry.PSObject.Properties[$field]
            if ($null -eq $manifestProperty) { continue }
            $value = $manifestProperty.Value
            if ($null -eq $value) { continue }
            foreach ($relativeFile in @($value)) {
                if ($relativeFile -isnot [string] -or [string]::IsNullOrWhiteSpace($relativeFile)) { continue }
                if ([System.IO.Path]::IsPathRooted($relativeFile) -or $relativeFile -match '(^|[/\\])\.\.([/\\]|$)') {
                    throw "Vite manifest contains an unsafe asset path: $relativeFile"
                }
                $fullPath = [System.IO.Path]::GetFullPath((Join-Path $buildRoot ($relativeFile -replace '/', '\')))
                if (-not $fullPath.StartsWith(($buildRoot.TrimEnd('\') + '\'), [System.StringComparison]::OrdinalIgnoreCase)) {
                    throw "Vite manifest asset escapes public/build: $relativeFile"
                }
                if (-not (Test-Path -LiteralPath $fullPath -PathType Leaf)) {
                    throw "Vite manifest asset is missing: $relativeFile"
                }
                [void]$manifestFiles.Add($relativeFile)
            }
        }
    }
    if ($manifestFiles.Count -eq 0) { throw 'Vite manifest has no verifiable asset references.' }

    $assetCount = @(Get-ChildItem -LiteralPath $buildRoot -File -Recurse).Count
    $assetBytes = (Get-ChildItem -LiteralPath $buildRoot -File -Recurse | Measure-Object -Property Length -Sum).Sum
    Write-Host "Vite output verified: $assetCount files, $assetBytes bytes, $($manifestFiles.Count) referenced files."
    Write-Host 'Only build/, images/, and favicon.ico are release-owned public assets.'
    Write-Host 'robots.txt, index.php, .htaccess, public_html/storage, and uploads are excluded.'

    if ($Build) {
        Write-Host "Isolated build completed for $($Commit.ToLowerInvariant()); production was not contacted or changed."
        return
    }

    $remoteArgs = @($RemoteApp, $RemotePublic, $Commit.ToLowerInvariant(), $BaseUrlText)

    $stageId = "$($Commit.ToLowerInvariant())-$runId"
    $stageRoot = "$RemoteHome/.idolranking-staging-$stageId"
    $stageBuild = "$stageRoot/build"
    $remoteMkdir = "umask 077 && mkdir -m 700 -- $stageRoot"
    Invoke-RemoteCommand -Command $remoteMkdir

    $scpOptions = @(
        '-r', '-P', '65002', '-i', $SshKey,
        '-o', 'IdentitiesOnly=yes',
        '-o', 'PubkeyAuthentication=yes',
        '-o', 'StrictHostKeyChecking=yes',
        '-o', "UserKnownHostsFile=$KnownHosts",
        '-o', 'BatchMode=yes',
        '-o', 'PasswordAuthentication=no',
        '-o', 'KbdInteractiveAuthentication=no',
        '-o', 'ConnectTimeout=12'
    )
    Write-Host 'Transferring release-owned assets to a private staging directory.'
    & $ScpExe @scpOptions (Join-Path $sourceRoot 'public\build') (Join-Path $sourceRoot 'public\images') (Join-Path $sourceRoot 'public\favicon.ico') "${Target}:$stageRoot/"
    if ($LASTEXITCODE -ne 0) {
        throw "Asset staging failed. Partial files, if any, remain outside the public root at $stageRoot."
    }

    $remoteArgs = @($RemoteApp, $RemotePublic, $Commit.ToLowerInvariant(), $BaseUrlText, $stageBuild, 'deploy')
    Invoke-RemoteScript -ScriptPath $RemoteScript -Arguments $remoteArgs -AllocateTty:$false
    Write-Host "Deployment completed for $($Commit.ToLowerInvariant()). Private staged source remains at $stageRoot."
}
catch {
    Write-Error $_
    exit 1
}
finally {
    if ($transcriptStarted) {
        try { Stop-Transcript | Out-Null } catch { }
    }
    # Remove only the unique temporary source archive created by this invocation.
    if ($tempRoot -and (Test-Path -LiteralPath $tempRoot) -and $tempRoot.StartsWith([System.IO.Path]::GetTempPath(), [System.StringComparison]::OrdinalIgnoreCase) -and (Split-Path -Leaf $tempRoot) -match '^idolranking-deploy-[0-9a-f]{40}-[0-9a-f]{32}$') {
        try { Remove-Item -LiteralPath $tempRoot -Recurse -Force } catch { Write-Warning 'Could not remove the unique temporary build directory.' }
    }
}
