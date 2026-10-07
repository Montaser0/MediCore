$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $Root 'config\app.env'
$data = Join-Path $Root 'data\mysql\mysql'
if (-not (Test-Path $envFile) -or -not (Test-Path $data)) {
    exit 0
}

New-Item -ItemType Directory -Force -Path (Join-Path $Root 'logs') | Out-Null
$log = Join-Path $Root 'logs\upgrade.log'
function Write-Log([string]$Message) {
    Add-Content -Path $log -Value ("{0} {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message)
}

try {
    . (Join-Path $PSScriptRoot 'file-encryption.ps1')
    if (Test-MediCoreEncrypted) {
        Write-Log 'Decrypting MediCore files before update.'
        Invoke-MediCoreFileCrypto Decrypt
    }
    $mysql = Get-Service HMSMariaDB -ErrorAction SilentlyContinue
    if ($mysql -and $mysql.Status -ne 'Running') {
        Start-Service HMSMariaDB
        Start-Sleep -Seconds 5
    }

    $env:PHPRC = Join-Path $Root 'runtime\php'
    Set-Location (Join-Path $Root 'app\backend')
    & (Join-Path $Root 'runtime\php\php.exe') artisan hms:backup local --label=pre-upgrade
    if ($LASTEXITCODE -ne 0) {
        Write-Log 'Backup failed. Update stopped and the current services were left as they were.'
        exit 1
    }

    Set-Content -Path (Join-Path $Root 'config\upgrade-backup.ok') -Value (Get-Date -Format o) -Encoding ASCII
    foreach ($name in @('HMSFrontend', 'HMSWeb', 'HMSPhpCgi', 'HMSMariaDB')) {
        $svc = Get-Service $name -ErrorAction SilentlyContinue
        if ($svc -and $svc.Status -ne 'Stopped') {
            Stop-Service $name -Force
        }
    }
    Write-Log 'Upgrade backup completed and services stopped.'
    exit 0
} catch {
    Write-Log $_.Exception.Message
    exit 1
}
