$ErrorActionPreference = 'Stop'
$Installer = $PSScriptRoot
$Repo = Split-Path -Parent $Installer
$Cache = Join-Path $Installer 'cache'
$Staging = Join-Path $Installer 'staging'
$Version = '1.0.0'

function Copy-Tree([string]$From, [string]$To, [string[]]$ExcludeDirs, [string[]]$ExcludeFiles) {
    New-Item -ItemType Directory -Force -Path $To | Out-Null
    $robocopyArgs = @($From, $To, '/E', '/NFL', '/NDL', '/NJH', '/NJS', '/NC', '/NS')
    $files = @('*.map')
    if ($ExcludeFiles) { $files += $ExcludeFiles }
    $robocopyArgs += @('/XF') + $files
    if ($ExcludeDirs -and $ExcludeDirs.Count -gt 0) { $robocopyArgs += @('/XD') + $ExcludeDirs }
    & robocopy @robocopyArgs | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy failed with exit code $LASTEXITCODE" }
}

function Find-RuntimeRoot([string]$Base, [string]$Marker) {
    if (Test-Path (Join-Path $Base $Marker)) { return Get-Item $Base }
    $dir = Get-ChildItem $Base -Directory -ErrorAction SilentlyContinue | Where-Object { Test-Path (Join-Path $_.FullName $Marker) } | Select-Object -First 1
    if (-not $dir) { throw "Could not find $Marker under $Base" }
    return $dir
}

function Save-Download([string]$Url, [string]$Dest) {
    if (Test-Path $Dest) {
        Write-Host "Cached $Dest"
        return
    }
    Write-Host "Downloading $Url"
    & curl.exe -L --fail --retry 3 -o $Dest $Url
    if ($LASTEXITCODE -ne 0) { throw "Download failed: $Url" }
}

function Resolve-LatestPhp {
    $fallback = [pscustomobject]@{
        Version = '8.5.12'
        ZipName = 'php-8.5.12-nts-Win32-vs17-x64.zip'
    }
    try {
        $releases = Invoke-RestMethod 'https://windows.php.net/downloads/releases/releases.json'
    } catch {
        return $fallback
    }

    $lines = @($releases.PSObject.Properties.Name) |
        Where-Object { $_ -match '^8\.\d+$' } |
        Sort-Object { [version]"$_.0" } -Descending
    foreach ($line in $lines) {
        $branch = $releases.$line
        $zip = [string]$branch.'nts-vs17-x64'.zip.path
        $version = [string]$branch.version
        if ($zip -and $version -match '^\d+\.\d+\.\d+$') {
            return [pscustomobject]@{ Version = $version; ZipName = $zip }
        }
    }

    return $fallback
}

function Expand-Zip([string]$Zip, [string]$Dest) {
    if (Test-Path $Dest) { Remove-Item $Dest -Recurse -Force }
    New-Item -ItemType Directory -Force -Path $Dest | Out-Null
    Expand-Archive -Path $Zip -DestinationPath $Dest -Force
}

New-Item -ItemType Directory -Force -Path $Cache | Out-Null
if (Test-Path $Staging) { Remove-Item $Staging -Recurse -Force }
New-Item -ItemType Directory -Force -Path $Staging | Out-Null

Write-Host 'Staging Laravel'
$backendStage = Join-Path $Staging 'app\backend'
Copy-Tree (Join-Path $Repo 'back-end') $backendStage @(
    (Join-Path $Repo 'back-end\tests'),
    (Join-Path $Repo 'back-end\vendor'),
    (Join-Path $Repo 'back-end\node_modules'),
    (Join-Path $Repo 'back-end\storage'),
    (Join-Path $Repo 'back-end\.git'),
    (Join-Path $Repo 'back-end\database\factories'),
    (Join-Path $Repo 'back-end\public\storage')
) @('.env', '.env.example', 'phpunit.xml', 'phpunit.xml.dist', '.phpunit.result.cache', 'DemoDataSeeder.php', 'DemoOperationalDataSeeder.php')
Get-ChildItem (Join-Path $backendStage 'bootstrap\cache') -Filter *.php -ErrorAction SilentlyContinue | Remove-Item -Force
Copy-Item (Join-Path $Repo 'back-end\.env.example') (Join-Path $backendStage '.env')
Push-Location $backendStage
& composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }
Pop-Location
Remove-Item (Join-Path $backendStage '.env') -Force
Remove-Item (Join-Path $backendStage 'database\database.sqlite') -Force -ErrorAction SilentlyContinue
Get-ChildItem (Join-Path $backendStage 'bootstrap\cache') -Filter *.php | Where-Object { $_.Name -ne 'packages.php' -and $_.Name -ne 'services.php' } | Remove-Item -Force

Write-Host 'Building Next.js'
Push-Location (Join-Path $Repo 'front-end')
$previousApi = $env:NEXT_PUBLIC_API_URL
$previousName = $env:NEXT_PUBLIC_APP_NAME
$env:NEXT_PUBLIC_API_URL = '/api'
$env:NEXT_PUBLIC_APP_NAME = 'MediCore'
& npm.cmd run build
if ($LASTEXITCODE -ne 0) { throw 'npm run build failed' }
if ($null -eq $previousApi) { Remove-Item Env:NEXT_PUBLIC_API_URL -ErrorAction SilentlyContinue } else { $env:NEXT_PUBLIC_API_URL = $previousApi }
if ($null -eq $previousName) { Remove-Item Env:NEXT_PUBLIC_APP_NAME -ErrorAction SilentlyContinue } else { $env:NEXT_PUBLIC_APP_NAME = $previousName }
Pop-Location

$standalone = Join-Path $Repo 'front-end\.next\standalone'
if (-not (Test-Path (Join-Path $standalone 'server.js'))) { throw 'Next.js standalone output was not produced.' }
$frontendStage = Join-Path $Staging 'app\frontend'
Copy-Tree $standalone $frontendStage @() @()
Copy-Tree (Join-Path $Repo 'front-end\.next\static') (Join-Path $frontendStage '.next\static') @() @()
Copy-Tree (Join-Path $Repo 'front-end\public') (Join-Path $frontendStage 'public') @() @()

Write-Host 'Resolving runtimes'
$phpPackage = Resolve-LatestPhp
$phpVersion = $phpPackage.Version
$phpZipName = $phpPackage.ZipName
Write-Host "PHP $phpVersion"
$phpUrl = "https://windows.php.net/downloads/releases/$phpZipName"
$phpZip = Join-Path $Cache $phpZipName
try { Save-Download $phpUrl $phpZip } catch {
    $phpUrl = "https://windows.php.net/downloads/releases/archives/$phpZipName"
    Save-Download $phpUrl $phpZip
}

$nodeIndex = Invoke-RestMethod 'https://nodejs.org/dist/index.json'
$nodeVersion = ($nodeIndex | Where-Object { $_.version -like 'v22.*' } | Select-Object -First 1).version
if (-not $nodeVersion) { throw 'Could not resolve Node.js 22.' }
$nodeZip = Join-Path $Cache "node-$nodeVersion-win-x64.zip"
Save-Download "https://nodejs.org/dist/$nodeVersion/node-$nodeVersion-win-x64.zip" $nodeZip

$nginxVersion = '1.28.0'
$nginxZip = Join-Path $Cache "nginx-$nginxVersion.zip"
try { Save-Download "https://nginx.org/download/nginx-$nginxVersion.zip" $nginxZip } catch {
    $nginxVersion = '1.26.3'
    $nginxZip = Join-Path $Cache "nginx-$nginxVersion.zip"
    Save-Download "https://nginx.org/download/nginx-$nginxVersion.zip" $nginxZip
}

$mariaZip = $null
foreach ($mariaVersion in @('11.4.8', '11.4.7', '11.4.5', '11.8.3', '11.8.2', '10.11.13', '10.11.11')) {
    $candidate = Join-Path $Cache "mariadb-$mariaVersion-winx64.zip"
    if (Test-Path $candidate) { $mariaZip = $candidate; break }
    $url = "https://archive.mariadb.org/mariadb-$mariaVersion/winx64-packages/mariadb-$mariaVersion-winx64.zip"
    try {
        Save-Download $url $candidate
        $mariaZip = $candidate
        break
    } catch {
        Write-Host $_.Exception.Message
        if (Test-Path $candidate) { Remove-Item $candidate -Force }
    }
}
if (-not $mariaZip) { throw 'Could not download MariaDB.' }

Save-Download 'https://nssm.cc/release/nssm-2.24.zip' (Join-Path $Cache 'nssm-2.24.zip')
Save-Download 'https://aka.ms/vs/17/release/vc_redist.x64.exe' (Join-Path $Cache 'vc_redist.x64.exe')

Write-Host 'Unpacking runtimes'
$unpack = Join-Path $Cache 'unpack'
Expand-Zip $phpZip (Join-Path $unpack 'php')
Expand-Zip $nodeZip (Join-Path $unpack 'node')
Expand-Zip $nginxZip (Join-Path $unpack 'nginx')
Expand-Zip $mariaZip (Join-Path $unpack 'maria')
Expand-Zip (Join-Path $Cache 'nssm-2.24.zip') (Join-Path $unpack 'nssm')

$phpRoot = Find-RuntimeRoot (Join-Path $unpack 'php') 'php.exe'
Copy-Tree $phpRoot.FullName (Join-Path $Staging 'runtime\php') @() @()

$nodeRoot = Find-RuntimeRoot (Join-Path $unpack 'node') 'node.exe'
Copy-Tree $nodeRoot.FullName (Join-Path $Staging 'runtime\node') @() @()

$nginxRoot = Find-RuntimeRoot (Join-Path $unpack 'nginx') 'nginx.exe'
Copy-Tree $nginxRoot.FullName (Join-Path $Staging 'runtime\nginx') @() @()

$mariaRoot = Find-RuntimeRoot (Join-Path $unpack 'maria') 'bin\mysqld.exe'
Copy-Tree $mariaRoot.FullName (Join-Path $Staging 'runtime\mariadb') @() @()

New-Item -ItemType Directory -Force -Path (Join-Path $Staging 'runtime\nssm') | Out-Null
Copy-Item (Join-Path $unpack 'nssm\nssm-2.24\win64\nssm.exe') (Join-Path $Staging 'runtime\nssm\nssm.exe')
New-Item -ItemType Directory -Force -Path (Join-Path $Staging 'runtime\vcredist') | Out-Null
Copy-Item (Join-Path $Cache 'vc_redist.x64.exe') (Join-Path $Staging 'runtime\vcredist\vc_redist.x64.exe')

Copy-Tree (Join-Path $Installer 'scripts') (Join-Path $Staging 'scripts') @() @()
Copy-Tree (Join-Path $Installer 'templates') (Join-Path $Staging 'config\templates') @() @()
Set-Content -Path (Join-Path $Staging 'config\version.txt') -Value $Version -Encoding ASCII

$iscc = Get-ChildItem -Path "${env:ProgramFiles(x86)}", $env:ProgramFiles -Filter ISCC.exe -Recurse -ErrorAction SilentlyContinue |
    Where-Object { $_.FullName -match 'Inno Setup' } |
    Select-Object -First 1 -ExpandProperty FullName
if (-not $iscc) {
    $setup = Join-Path $Cache 'innosetup-7.1.0-x64.exe'
    Save-Download 'https://github.com/jrsoftware/issrc/releases/download/is-7_1_0/innosetup-7.1.0-x64.exe' $setup
    $localDir = Join-Path $Cache 'innosetup'
    $install = Start-Process -FilePath $setup -ArgumentList '/VERYSILENT', '/SUPPRESSMSGBOXES', '/NORESTART', '/SP-', "/DIR=$localDir" -Wait -PassThru
    if ($install.ExitCode -ne 0 -and -not (Test-Path (Join-Path $localDir 'ISCC.exe'))) {
        throw "Inno Setup installer exit $($install.ExitCode)"
    }
    $iscc = Join-Path $localDir 'ISCC.exe'
    if (-not (Test-Path $iscc)) {
        $iscc = Get-ChildItem -Path "${env:ProgramFiles(x86)}", $env:ProgramFiles -Filter ISCC.exe -Recurse -ErrorAction SilentlyContinue |
            Where-Object { $_.FullName -match 'Inno Setup' } |
            Select-Object -First 1 -ExpandProperty FullName
    }
}
& $iscc (Join-Path $Installer 'HMS-Pharmacy.iss')
if ($LASTEXITCODE -ne 0) { throw 'Inno Setup compilation failed' }

$built = Join-Path $Installer "dist\MediCore-Setup-$Version.exe"
Copy-Item $built (Join-Path $Installer 'dist\MediCore-Setup.exe') -Force
Write-Host "Built $built"
