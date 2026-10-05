$ErrorActionPreference = 'Continue'
Start-Sleep -Seconds 5
$Root = Split-Path -Parent $PSScriptRoot
$env:PHPRC = Join-Path $Root 'runtime\php'
Set-Location (Join-Path $Root 'app\backend')
& (Join-Path $Root 'runtime\php\php.exe') artisan optimize:clear
& (Join-Path $Root 'runtime\php\php.exe') artisan config:cache
foreach ($name in @('HMSFrontend', 'HMSWeb', 'HMSPhpCgi', 'HMSMariaDB')) {
    $svc = Get-Service $name -ErrorAction SilentlyContinue
    if ($svc -and $svc.Status -eq 'Running') {
        Restart-Service $name -Force -ErrorAction SilentlyContinue
    }
}
Start-Sleep -Seconds 3
foreach ($name in @('HMSMariaDB', 'HMSPhpCgi', 'HMSWeb', 'HMSFrontend')) {
    $svc = Get-Service $name -ErrorAction SilentlyContinue
    if ($svc -and $svc.Status -ne 'Running') {
        Start-Service $name -ErrorAction SilentlyContinue
    }
}
