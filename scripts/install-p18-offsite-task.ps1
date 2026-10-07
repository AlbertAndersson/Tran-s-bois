param(
    [Parameter(Mandatory = $true)]
    [string]$PrivateDir,

    [Parameter(Mandatory = $true)]
    [string]$PythonExe,

    [string]$RepoRoot = (Split-Path -Parent $PSScriptRoot),

    [string]$TaskName = "Tranas BoIS P18 offsite relay",

    [switch]$Install,

    [switch]$Remove
)

$ErrorActionPreference = "Stop"

if ($Install -and $Remove) { throw "Choose either -Install or -Remove" }

$private = [System.IO.Path]::GetFullPath($PrivateDir)
if (-not [System.IO.Path]::IsPathRooted($private)) { throw "Absolute private path required" }
if ($private.ToLowerInvariant().Contains("onedrive")) { throw "Private path cannot be in OneDrive" }
if (-not (Test-Path -LiteralPath $private -PathType Container)) { throw "Private directory missing" }
if (-not (Test-Path -LiteralPath $PythonExe -PathType Leaf)) { throw "Python executable missing" }

$cycle = Join-Path $RepoRoot "scripts\p18-offsite-cycle.ps1"
if (-not (Test-Path -LiteralPath $cycle -PathType Leaf)) { throw "Cycle script missing" }

if ($Remove) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction Stop
    Write-Output "Removed scheduled task: $TaskName"
    exit 0
}

$principalName = "$env:USERDOMAIN\$env:USERNAME"
$q = [char]34
$arguments = "-NoProfile -NonInteractive -ExecutionPolicy RemoteSigned -File $q$cycle$q -PrivateDir $q$private$q -PythonExe $q$PythonExe$q -RepoRoot $q$RepoRoot$q -MaxAgeHours 30"

$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $arguments
$triggers = @(
    (New-ScheduledTaskTrigger -Daily -At "00:15"),
    (New-ScheduledTaskTrigger -Daily -At "06:15"),
    (New-ScheduledTaskTrigger -Daily -At "12:15"),
    (New-ScheduledTaskTrigger -Daily -At "18:15"),
    (New-ScheduledTaskTrigger -AtLogOn -User $principalName)
)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 20) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$principal = New-ScheduledTaskPrincipal -UserId $principalName -LogonType Interactive -RunLevel Limited
$task = New-ScheduledTask -Action $action -Trigger $triggers -Settings $settings -Principal $principal -Description "Copies and authenticates the latest closed BoIS backup to Besovida. Runs only in Albert's interactive Windows session; no credentials are provisioned."

if (-not $Install) {
    Write-Output "PREVIEW ONLY - no task installed."
    Write-Output "Task: $TaskName"
    Write-Output "User: $principalName"
    Write-Output "Triggers: at logon and daily 00:15, 06:15, 12:15 and 18:15 while an interactive user session exists."
    Write-Output "Run again with -Install after reviewing the preview."
    exit 0
}

Register-ScheduledTask -TaskName $TaskName -InputObject $task -Force | Out-Null
Write-Output "Installed scheduled task: $TaskName"
Write-Output "Important: LogonType=Interactive avoids storing a Windows password, so the task cannot run before Albert has logged on after a reboot."
