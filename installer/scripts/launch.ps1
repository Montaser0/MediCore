$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$ready = Join-Path $Root 'config\ready.flag'
$urlFile = Join-Path $Root 'config\site-url.txt'
$url = 'http://localhost'
if (Test-Path $urlFile) {
    $url = (Get-Content $urlFile -Raw).Trim()
}

if (-not (Test-Path $ready)) {
    Add-Type -AssemblyName PresentationFramework
    [System.Windows.MessageBox]::Show("MediCore is not ready. See $Root\logs\install.log") | Out-Null
    exit 1
}

$services = @('HMSMariaDB', 'HMSPhpCgi', 'HMSWeb', 'HMSFrontend')
$down = @($services | Where-Object {
    $svc = Get-Service $_ -ErrorAction SilentlyContinue
    -not $svc -or $svc.Status -ne 'Running'
})

if ($down.Count -gt 0) {
    $admin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
        [Security.Principal.WindowsBuiltInRole]::Administrator
    )
    if (-not $admin) {
        Start-Process powershell.exe -Verb RunAs -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`""
        exit 0
    }
    foreach ($name in $services) {
        $svc = Get-Service $name -ErrorAction SilentlyContinue
        if ($svc -and $svc.Status -ne 'Running') {
            Start-Service $name
        }
    }
}

$health = "$url/up"
$deadline = (Get-Date).AddSeconds(60)
$ok = $false
do {
    try {
        $response = Invoke-WebRequest -UseBasicParsing $health -TimeoutSec 3
        if ($response.StatusCode -eq 200) {
            $ok = $true
            break
        }
    } catch {
    }
    Start-Sleep -Seconds 1
} while ((Get-Date) -lt $deadline)

if (-not $ok) {
    Add-Type -AssemblyName PresentationFramework
    [System.Windows.MessageBox]::Show("MediCore did not respond yet. See $Root\logs") | Out-Null
}

Start-Process $url
