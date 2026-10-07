param(
    [Parameter(Mandatory = $true)]
    [string]$PrivateDir,

    [Parameter(Mandatory = $true)]
    [string]$PythonExe,

    [string]$RepoRoot = (Split-Path -Parent $PSScriptRoot),

    [double]$MaxAgeHours = 30
)

$ErrorActionPreference = "Stop"

function Write-PrivateAlert([string]$Reason) {
    $alertPath = Join-Path $PrivateDir "p18-offsite-alert.json"
    $payload = @{
        ok = $false
        at = [DateTimeOffset]::UtcNow.ToString("o")
        reason = $Reason
        external_notification_sent = $false
    } | ConvertTo-Json
    [System.IO.File]::WriteAllText($alertPath, $payload + [Environment]::NewLine)
}

try {
    $private = [System.IO.Path]::GetFullPath($PrivateDir)
    if (-not [System.IO.Path]::IsPathRooted($private)) { throw "Absolute private path required" }
    if ($private.ToLowerInvariant().Contains("onedrive")) { throw "Private path cannot be in OneDrive" }
    if (-not (Test-Path -LiteralPath $private -PathType Container)) { throw "Private directory missing" }
    if (-not (Test-Path -LiteralPath $PythonExe -PathType Leaf)) { throw "Python executable missing" }

    $relay = Join-Path $RepoRoot "scripts\p18-offsite-relay.py"
    $health = Join-Path $RepoRoot "scripts\p18-offsite-health.py"
    if (-not (Test-Path -LiteralPath $relay -PathType Leaf)) { throw "Relay script missing" }
    if (-not (Test-Path -LiteralPath $health -PathType Leaf)) { throw "Health script missing" }

    & $PythonExe $relay --private-dir $private
    if ($LASTEXITCODE -ne 0) { throw "Relay failed; inspect private status" }

    & $PythonExe $health --private-dir $private --max-age-hours $MaxAgeHours
    if ($LASTEXITCODE -ne 0) { throw "Health check failed; inspect private status" }

    $alertPath = Join-Path $private "p18-offsite-alert.json"
    if (Test-Path -LiteralPath $alertPath) {
        Remove-Item -LiteralPath $alertPath -Force
    }
    exit 0
}
catch {
    Write-PrivateAlert "P18 offsite cycle failed; inspect private relay and health status"
    try {
        if (Get-Command "msg.exe" -ErrorAction SilentlyContinue) {
            & msg.exe $env:USERNAME "Tranås BoIS: offsite-backup behöver kontrolleras. Öppna den privata P18-statusen på AlberIQ-datorn." 2>$null
        }
    } catch {
        # Notification failure must not hide the backup failure.
    }
    Write-Error "P18 offsite cycle failed; no credentials emitted"
    exit 1
}
