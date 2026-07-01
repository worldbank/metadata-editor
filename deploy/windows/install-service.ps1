#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Install the metadata-editor-worker as a Windows service using NSSM.

.DESCRIPTION
    Wraps `php index.php cli/worker/run --max-jobs=N` as a native Windows service
    via NSSM (Non-Sucking Service Manager). The worker exits after processing
    MaxJobs tasks; NSSM restarts it automatically, preventing PHP memory leaks.

.PARAMETER AppRoot
    Path to the application root (directory containing index.php and worker.sh).
    Default: C:\inetpub\metadata-editor

.PARAMETER PhpExe
    Full path to php.exe, or just "php" if it is on the system PATH.
    Default: php

.PARAMETER NssmExe
    Full path to nssm.exe, or just "nssm" if it is on the system PATH.
    Default: nssm

.PARAMETER MaxJobs
    Worker exits after processing this many jobs; NSSM restarts it.
    Default: 50

.PARAMETER ServiceUser
    Account to run the service under (e.g. ".\IIS_AppPool", "DOMAIN\svcworker").
    Leave blank to run as LocalSystem.

.PARAMETER ServicePassword
    Password for ServiceUser. Leave blank for LocalSystem or gMSA accounts.

.EXAMPLE
    .\install-service.ps1

.EXAMPLE
    .\install-service.ps1 -AppRoot "D:\www\metadata-editor" -MaxJobs 100

.EXAMPLE
    .\install-service.ps1 -AppRoot "D:\www\metadata-editor" `
        -PhpExe "C:\php\php.exe" -NssmExe "C:\tools\nssm.exe" `
        -ServiceUser ".\svc_worker" -ServicePassword "s3cr3t"
#>

[CmdletBinding(SupportsShouldProcess)]
param(
    [string] $AppRoot          = "C:\inetpub\metadata-editor",
    [string] $PhpExe           = "php",
    [string] $NssmExe          = "nssm",
    [int]    $MaxJobs          = 50,
    [string] $ServiceUser      = "",
    [string] $ServicePassword  = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$ServiceName = "metadata-editor-worker"
$LogFile     = Join-Path $AppRoot "logs\worker.log"
$LogDir      = Split-Path $LogFile

# ── Helpers ───────────────────────────────────────────────────────────────────

function Write-Step  { param([string]$Msg) Write-Host "-> $Msg" -ForegroundColor Cyan }
function Write-Ok    { param([string]$Msg) Write-Host "   $Msg" -ForegroundColor Green }
function Write-Fail  { param([string]$Msg) Write-Error "ERROR: $Msg" }

function Resolve-Exe {
    param([string]$Exe, [string]$Label)
    $resolved = Get-Command $Exe -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source
    if (-not $resolved) {
        Write-Fail "$Label not found. Install it or pass -$($Label)Exe with the full path."
    }
    return $resolved
}

function Nssm {
    param([string[]]$Args)
    & $NssmExe @Args | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Fail "nssm $($Args -join ' ') failed (exit $LASTEXITCODE)."
    }
}

# ── Validate prerequisites ────────────────────────────────────────────────────

Write-Step "Resolving executables..."
$PhpExe  = Resolve-Exe $PhpExe  "PhpExe"
$NssmExe = Resolve-Exe $NssmExe "NssmExe"
Write-Ok "php:  $PhpExe"
Write-Ok "nssm: $NssmExe"

Write-Step "Validating AppRoot..."
if (-not (Test-Path (Join-Path $AppRoot "index.php"))) {
    Write-Fail "index.php not found in '$AppRoot'. Pass the correct -AppRoot."
}
Write-Ok $AppRoot

if ($MaxJobs -lt 1) { Write-Fail "-MaxJobs must be a positive integer." }

# ── Remove existing service if present ───────────────────────────────────────

$existing = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
if ($existing) {
    Write-Step "Removing existing '$ServiceName' service..."
    if ($existing.Status -eq "Running") {
        Nssm @("stop", $ServiceName)
    }
    Nssm @("remove", $ServiceName, "confirm")
    Write-Ok "Removed."
}

# ── Ensure log directory exists ───────────────────────────────────────────────

Write-Step "Ensuring log directory: $LogDir"
New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
Write-Ok "OK"

# ── Install service ───────────────────────────────────────────────────────────

Write-Step "Installing service '$ServiceName'..."
Nssm @("install", $ServiceName, $PhpExe)

# Working directory and arguments
Nssm @("set", $ServiceName, "AppDirectory",  $AppRoot)
Nssm @("set", $ServiceName, "AppParameters", "index.php cli/worker/run --max-jobs=$MaxJobs")

# Stdout/stderr → log file (append mode, disposition 4 = append)
Nssm @("set", $ServiceName, "AppStdout",                    $LogFile)
Nssm @("set", $ServiceName, "AppStderr",                    $LogFile)
Nssm @("set", $ServiceName, "AppStdoutCreationDisposition", "4")
Nssm @("set", $ServiceName, "AppStderrCreationDisposition", "4")

# Restart 5 s after exit — mirrors RestartSec=5 in the systemd unit
Nssm @("set", $ServiceName, "AppRestartDelay", "5000")

# Auto-start with Windows
Nssm @("set", $ServiceName, "Start", "SERVICE_AUTO_START")

# Description shown in services.msc
Nssm @("set", $ServiceName, "Description",
    "Metadata Editor job-queue worker. Restarts automatically after $MaxJobs jobs to prevent memory leaks.")

# ── Service account ───────────────────────────────────────────────────────────

if ($ServiceUser -ne "") {
    Write-Step "Setting service account: $ServiceUser"
    Nssm @("set", $ServiceName, "ObjectName", $ServiceUser, $ServicePassword)
    Write-Ok "Done."
}

# ── Start the service ─────────────────────────────────────────────────────────

Write-Step "Starting service..."
Nssm @("start", $ServiceName)

Start-Sleep -Seconds 2

$svc = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
if ($svc -and $svc.Status -eq "Running") {
    Write-Host ""
    Write-Host "Service installed and running." -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "Service installed but may not be running yet — check the log:" -ForegroundColor Yellow
    Write-Host "  $LogFile"
}

# ── Summary ───────────────────────────────────────────────────────────────────

Write-Host ""
Write-Host "Useful commands:" -ForegroundColor Cyan
Write-Host "  nssm status  $ServiceName       # current state"
Write-Host "  nssm restart $ServiceName       # restart"
Write-Host "  nssm stop    $ServiceName       # stop"
Write-Host "  nssm start   $ServiceName       # start"
Write-Host "  Get-Content '$LogFile' -Tail 50 -Wait    # follow log"
Write-Host ""
Write-Host "Or open services.msc and look for '$ServiceName'."
