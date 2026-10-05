$ErrorActionPreference = 'Continue'
$Root = Split-Path -Parent $PSScriptRoot
$nssm = Join-Path $Root 'runtime\nssm\nssm.exe'

foreach ($name in @('HMSFrontend', 'HMSWeb', 'HMSPhpCgi', 'HMSMariaDB')) {
    $svc = Get-Service $name -ErrorAction SilentlyContinue
    if (-not $svc) { continue }
    Stop-Service $name -Force -ErrorAction SilentlyContinue
    if (Test-Path $nssm) {
        & $nssm remove $name confirm | Out-Null
    } else {
        & sc.exe delete $name | Out-Null
    }
}

& schtasks.exe /Delete /TN "HMS Pharmacy Daily Backup" /F | Out-Null
& schtasks.exe /Delete /TN "MediCore Daily Backup" /F | Out-Null

$flag = Join-Path $Root 'config\uninstall-delete-data.flag'
$delete = $false
if (Test-Path $flag) {
    $delete = (Get-Content $flag -Raw).Trim() -eq '1'
}

if ($delete) {
    foreach ($relative in @('data', 'storage', 'backups')) {
        $path = Join-Path $Root $relative
        if (Test-Path $path) {
            Remove-Item $path -Recurse -Force -ErrorAction SilentlyContinue
        }
    }
    foreach ($relative in @('config\app.env', 'config\mysql-root.secret', 'config\ready.flag', 'app\backend\bootstrap\cache\config.php')) {
        $path = Join-Path $Root $relative
        if (Test-Path $path) {
            Remove-Item $path -Force -ErrorAction SilentlyContinue
        }
    }
}
if (Test-Path $flag) {
    Set-Content -Path $flag -Value '0' -Encoding ASCII
}

exit 0
