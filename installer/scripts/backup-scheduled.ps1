$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$env:PHPRC = Join-Path $Root 'runtime\php'
Set-Location (Join-Path $Root 'app\backend')
& (Join-Path $Root 'runtime\php\php.exe') artisan hms:backup local
exit $LASTEXITCODE
