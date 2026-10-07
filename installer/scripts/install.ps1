$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$LogFile = Join-Path $Root 'logs\install.log'

function Write-Log([string]$Message) {
    $dir = Split-Path $LogFile
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Force -Path $dir | Out-Null
    }
    Add-Content -Path $LogFile -Value ("{0} {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message)
}

function Write-Utf8([string]$Path, [string]$Content) {
    $directory = Split-Path $Path
    if (-not (Test-Path $directory)) {
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
    }
    $normalized = ($Content -replace "`r`n", "`n" -replace "`r", "`n") -replace "`n", "`r`n"
    $encoding = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($Path, $normalized, $encoding)
}

function New-Secret {
    $buffer = New-Object byte[] 24
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return ([BitConverter]::ToString($buffer) -replace '-', '').ToLower()
}

function Test-PortOpen([int]$Port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $result = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        $ok = $result.AsyncWaitHandle.WaitOne(300, $false) -and $client.Connected
        return $ok
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Find-FreePort([int[]]$Candidates) {
    foreach ($port in $Candidates) {
        if (-not (Test-PortOpen $port)) { return $port }
    }
    throw "No free local port among $($Candidates -join ', ')."
}

function Wait-Tcp([int]$Port, [int]$Seconds) {
    $deadline = (Get-Date).AddSeconds($Seconds)
    do {
        if (Test-PortOpen $Port) { return }
        Start-Sleep -Milliseconds 500
    } while ((Get-Date) -lt $deadline)
    throw "Timed out waiting for 127.0.0.1:$Port"
}

function Get-EnvValue([string]$Text, [string]$Key) {
    if ($Text -match "(?m)^$([regex]::Escape($Key))=(.*)$") {
        return $Matches[1].Trim().Trim('"')
    }
    return ''
}

function Set-EnvValue([string]$Text, [string]$Key, [string]$Value) {
    $pattern = "^$([regex]::Escape($Key))="
    $found = $false
    $updated = foreach ($line in ($Text -split "`r`n|`n|`r")) {
        if ($line -match $pattern) {
            $found = $true
            "$Key=$Value"
        } else {
            $line
        }
    }
    $result = (@($updated) -join "`r`n").TrimEnd()
    if (-not $found) {
        $result += "`r`n$Key=$Value"
    }
    return $result + "`r`n"
}

function Protect-File([string]$Path) {
    & icacls $Path /inheritance:r /grant:r '*S-1-5-18:(F)' '*S-1-5-32-544:(F)' | Out-Null
}

function Protect-Installation {
    $acl = New-Object System.Security.AccessControl.DirectorySecurity
    $acl.SetAccessRuleProtection($true, $false)
    foreach ($sid in @('S-1-5-18', 'S-1-5-32-544')) {
        $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
            (New-Object System.Security.Principal.SecurityIdentifier $sid),
            'FullControl',
            'ContainerInherit,ObjectInherit',
            'None',
            'Allow')
        $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $Root -AclObject $acl
    & icacls "$Root\*" /reset /T /C /Q | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not apply the MediCore file lock.' }
    foreach ($relative in @(
        'scripts\launch.vbs',
        'scripts\launch.ps1',
        'medicore.ico',
        'config\ready.flag',
        'config\site-url.txt'
    )) {
        $path = Join-Path $Root $relative
        if (-not (Test-Path $path)) { continue }
        & icacls $path /grant '*S-1-5-32-545:(RX)' | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'Could not allow the MediCore shortcut.' }
    }
}

function Invoke-Artisan([string[]]$ArtisanArgs) {
    Push-Location (Join-Path $Root 'app\backend')
    try {
        & $script:Php (@('artisan') + $ArtisanArgs)
        if ($LASTEXITCODE -ne 0) {
            throw "php artisan $($ArtisanArgs -join ' ') failed with exit code $LASTEXITCODE"
        }
    } finally {
        Pop-Location
    }
}

function Set-HmsService {
    param(
        [string]$Name,
        [string]$Exe,
        [string]$Params,
        [string]$Work,
        [string]$Stdout,
        [string]$Stderr,
        [string]$EnvLines,
        [string]$Depends
    )
    if (-not (Get-Service $Name -ErrorAction SilentlyContinue)) {
        & $script:Nssm install $Name $Exe | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "Could not install the $Name service." }
    }
    & $script:Nssm set $Name Application $Exe | Out-Null
    & $script:Nssm set $Name AppDirectory $Work | Out-Null
    & $script:Nssm set $Name AppParameters $Params | Out-Null
    & $script:Nssm set $Name Start SERVICE_AUTO_START | Out-Null
    & $script:Nssm set $Name AppStdout $Stdout | Out-Null
    & $script:Nssm set $Name AppStderr $Stderr | Out-Null
    & $script:Nssm set $Name AppRotateFiles 1 | Out-Null
    & $script:Nssm set $Name AppRotateBytes 1048576 | Out-Null
    & $script:Nssm set $Name AppExit Default Restart | Out-Null
    & $script:Nssm set $Name AppRestartDelay 3000 | Out-Null
    if ($Depends) {
        & $script:Nssm set $Name DependOnService $Depends | Out-Null
    }
    if ($EnvLines) {
        & $script:Nssm set $Name AppEnvironmentExtra $EnvLines | Out-Null
    }
}

function Invoke-Mysql([string]$DefaultsFile, [string]$Sql) {
    $sqlFile = Join-Path $Root 'logs\mysql-init.sql'
    $output = Join-Path $Root 'logs\mysql-client.out'
    $errorLog = Join-Path $Root 'logs\mysql-client.err'
    Write-Utf8 $sqlFile $Sql
    try {
        $defaults = $DefaultsFile.Replace('\', '/')
        $proc = Start-Process -FilePath $script:Mysql -ArgumentList "--defaults-extra-file=$defaults", '--protocol=tcp' -RedirectStandardInput $sqlFile -RedirectStandardOutput $output -RedirectStandardError $errorLog -Wait -PassThru -NoNewWindow
        if ($proc.ExitCode -ne 0) {
            $detail = ''
            if (Test-Path $errorLog) { $detail = (Get-Content $errorLog -Raw).Trim() }
            throw "MariaDB setup failed with exit code $($proc.ExitCode). $detail"
        }
    } finally {
        Remove-Item $sqlFile, $output, $errorLog -Force -ErrorAction SilentlyContinue
    }
}

try {
    Write-Log "Install started in $Root"
    . (Join-Path $PSScriptRoot 'file-encryption.ps1')
    if (Test-MediCoreEncrypted) {
        Write-Log 'Decrypting MediCore files before setup.'
        Invoke-MediCoreFileCrypto Decrypt
    }
    $script:Php = Join-Path $Root 'runtime\php\php.exe'
    $script:Mysql = Join-Path $Root 'runtime\mariadb\bin\mysql.exe'
    $script:Mysqld = Join-Path $Root 'runtime\mariadb\bin\mysqld.exe'
    $script:Nssm = Join-Path $Root 'runtime\nssm\nssm.exe'
    $nginx = Join-Path $Root 'runtime\nginx\nginx.exe'
    $node = Join-Path $Root 'runtime\node\node.exe'
    foreach ($tool in @($script:Php, $script:Mysql, $script:Mysqld, $script:Nssm, $nginx, $node)) {
        if (-not (Test-Path $tool)) { throw "Missing runtime file $tool" }
    }

    $vc = Join-Path $Root 'runtime\vcredist\vc_redist.x64.exe'
    if (Test-Path $vc) {
        $vcProcess = Start-Process -FilePath $vc -ArgumentList '/install', '/quiet', '/norestart' -Wait -PassThru
        Write-Log "Visual C++ redistributable exit $($vcProcess.ExitCode)"
    }

    $dirs = @(
        'storage\app\public',
        'storage\app\private',
        'storage\framework\cache\data',
        'storage\framework\sessions',
        'storage\framework\views',
        'storage\logs',
        'backups',
        'logs\nginx',
        'runtime\nginx\logs',
        'runtime\nginx\temp\client_body_temp',
        'runtime\nginx\temp\proxy_temp',
        'runtime\nginx\temp\fastcgi_temp',
        'logs\php',
        'logs\mysql',
        'logs\frontend',
        'data\mysql',
        'config'
    )
    foreach ($relative in $dirs) {
        New-Item -ItemType Directory -Force -Path (Join-Path $Root $relative) | Out-Null
    }

    $env:PHPRC = Join-Path $Root 'runtime\php'
    $version = '1.0.0'
    $versionFile = Join-Path $Root 'config\version.txt'
    if (Test-Path $versionFile) { $version = (Get-Content $versionFile -Raw).Trim() }

    $envPath = Join-Path $Root 'config\app.env'
    $upgrade = Test-Path $envPath
    $existing = ''
    if ($upgrade) { $existing = [System.IO.File]::ReadAllText($envPath) }

    $httpPort = 80
    $mysqlPort = 3306
    if ($upgrade) {
        $savedHttp = Get-EnvValue $existing 'APP_URL'
        if ($savedHttp -match ':(\d+)$') { $httpPort = [int]$Matches[1] }
        $savedMysql = Get-EnvValue $existing 'DB_PORT'
        if ($savedMysql) { $mysqlPort = [int]$savedMysql }
    } else {
        $httpPort = Find-FreePort @(80, 8080, 8081)
        $mysqlPort = Find-FreePort @(3306, 3307, 3308)
        $nodePort = Find-FreePort @(3000, 3010, 3011)
    }
    if ($upgrade) {
        $savedNode = Get-EnvValue $existing 'HMS_NODE_PORT'
        if (-not $savedNode) { $savedNode = '3000' }
        $nodePort = [int]$savedNode
    }
    $siteUrl = if ($httpPort -eq 80) { 'http://localhost' } else { "http://localhost:$httpPort" }
    Write-Log "HTTP $siteUrl MariaDB 127.0.0.1:$mysqlPort upgrade=$upgrade"

    $slash = $Root.Replace('\', '/')
    $phpIni = Get-Content (Join-Path $Root 'config\templates\php.ini') -Raw
    $phpIni = $phpIni.Replace('__PHPDIR__', $slash + '/runtime/php').Replace('__LOG__', $slash + '/logs')
    $phpIni += "`r`nopcache.enable=0`r`nauto_prepend_file=`"$slash/runtime/php/medicore-loader.php`"`r`n"
    Write-Utf8 (Join-Path $Root 'runtime\php\php.ini') $phpIni
    Copy-Item (Join-Path $PSScriptRoot 'medicore-loader.php') (Join-Path $Root 'runtime\php\medicore-loader.php') -Force
    Copy-Item (Join-Path $PSScriptRoot 'medicore-loader.js') (Join-Path $Root 'runtime\node\medicore-loader.js') -Force

    $myIni = Get-Content (Join-Path $Root 'config\templates\my.ini') -Raw
    $myIni = $myIni.Replace('__BASEDIR__', $slash + '/runtime/mariadb').Replace('__DATADIR__', $slash + '/data/mysql').Replace('__PORT__', "$mysqlPort").Replace('__ERRORLOG__', $slash + '/logs/mysql/error.log').Replace('__PIDFILE__', $slash + '/logs/mysql/mariadb.pid')
    Write-Utf8 (Join-Path $Root 'config\my.ini') $myIni

    $nginxPrefix = Join-Path $Root 'runtime\nginx'
    $nginxConf = Get-Content (Join-Path $Root 'config\templates\nginx.conf') -Raw
    $nginxConf = $nginxConf.Replace('__PORT__', "$httpPort").Replace('__NODE__', "$nodePort").Replace('__PUBLIC__', $slash + '/app/backend/public').Replace('__STORAGE__', $slash + '/storage')
    $nginxFile = Join-Path $nginxPrefix 'conf\hms.conf'
    Write-Utf8 $nginxFile $nginxConf
    & $nginx -t -p $nginxPrefix -c 'conf/hms.conf'
    if ($LASTEXITCODE -ne 0) {
        $nginxConf = $nginxConf -replace "(?m)^\s*listen \[::1\]:.*\r?\n", ''
        Write-Utf8 $nginxFile $nginxConf
        & $nginx -t -p $nginxPrefix -c 'conf/hms.conf'
        if ($LASTEXITCODE -ne 0) { throw 'Nginx configuration is invalid.' }
    }

    $storageLink = Join-Path $Root 'app\backend\storage'
    $storageTarget = Join-Path $Root 'storage'
    if (Test-Path $storageLink) {
        $item = Get-Item $storageLink -Force
        $isLink = [bool]($item.Attributes -band [IO.FileAttributes]::ReparsePoint)
        if (-not $isLink) {
            & robocopy $storageLink $storageTarget /E /NFL /NDL /NJH /NJS /NC /NS | Out-Null
            Remove-Item $storageLink -Recurse -Force
            & cmd /c mklink /J "$storageLink" "$storageTarget" | Out-Null
        }
    } else {
        & cmd /c mklink /J "$storageLink" "$storageTarget" | Out-Null
    }
    if (-not (Test-Path $storageLink)) { throw 'Could not link Laravel storage.' }
    foreach ($relative in @('app\public', 'app\private', 'framework\cache\data', 'framework\sessions', 'framework\views', 'logs')) {
        New-Item -ItemType Directory -Force -Path (Join-Path $storageTarget $relative) | Out-Null
    }

    $dataReady = Test-Path (Join-Path $Root 'data\mysql\mysql')
    $rootSecretPath = Join-Path $Root 'config\mysql-root.secret'
    if (-not $dataReady) {
        Write-Log 'Initializing MariaDB'
        $rootPassword = New-Secret
        if (Test-Path $rootSecretPath) {
            $rootPassword = (Get-Content $rootSecretPath -Raw).Trim()
        } else {
            Write-Utf8 $rootSecretPath $rootPassword
            Protect-File $rootSecretPath
        }
        $installDb = Join-Path $Root 'runtime\mariadb\bin\mariadb-install-db.exe'
        $dataDir = $Root.Replace('\', '/') + '/data/mysql'
        & $installDb "--datadir=$dataDir" "--port=$mysqlPort" "--password=$rootPassword"
        if ($LASTEXITCODE -ne 0) { throw 'MariaDB initialization failed. See logs\mysql\error.log' }
    }

    $mariaDir = Join-Path $Root 'runtime\mariadb'
    $myIniFile = $Root.Replace('\', '/') + '/config/my.ini'
    Set-HmsService 'HMSMariaDB' $script:Mysqld "--defaults-file=$myIniFile --console" $mariaDir (Join-Path $Root 'logs\mysql\service.log') (Join-Path $Root 'logs\mysql\service.err.log') '' ''
    Set-HmsService 'HMSPhpCgi' $script:Php.Replace('php.exe', 'php-cgi.exe') '-b 127.0.0.1:9000' (Join-Path $Root 'runtime\php') (Join-Path $Root 'logs\php\cgi.log') (Join-Path $Root 'logs\php\cgi.err.log') "PHPRC=$Root\runtime\php" 'HMSMariaDB'
    Set-HmsService 'HMSWeb' $nginx "-p $Root\runtime\nginx -c conf/hms.conf" (Join-Path $Root 'runtime\nginx') (Join-Path $Root 'logs\nginx\service.log') (Join-Path $Root 'logs\nginx\service.err.log') '' 'HMSPhpCgi'
    $nodeLoader = Join-Path $Root 'runtime\node\medicore-loader.js'
    Set-HmsService 'HMSFrontend' $node "-r `"$nodeLoader`" server.js" (Join-Path $Root 'app\frontend') (Join-Path $Root 'logs\frontend\service.log') (Join-Path $Root 'logs\frontend\service.err.log') "PORT=$nodePort`nHOSTNAME=127.0.0.1`nNODE_ENV=production" 'HMSWeb'

    if (-not $upgrade) {
        if ((Get-Service HMSMariaDB).Status -ne 'Running') { Start-Service HMSMariaDB }
        Wait-Tcp $mysqlPort 60
        $dbPassword = New-Secret
        $rootPassword = New-Secret
        $knownRoot = ''
        if (Test-Path $rootSecretPath) { $knownRoot = (Get-Content $rootSecretPath -Raw).Trim() }
        $rootCnf = Join-Path $Root 'logs\root-client.cnf'
        $rootLine = if ($knownRoot) { "password=$knownRoot" } else { 'password=' }
        Write-Utf8 $rootCnf "[client]`nhost=127.0.0.1`nport=$mysqlPort`nuser=root`n$rootLine`n"
        $rootAlter = if ($knownRoot) { '' } else { "ALTER USER 'root'@'localhost' IDENTIFIED BY '$rootPassword';" }
        $grant = @"
CREATE DATABASE IF NOT EXISTS hms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'hms'@'127.0.0.1' IDENTIFIED BY '$dbPassword';
CREATE USER IF NOT EXISTS 'hms'@'localhost' IDENTIFIED BY '$dbPassword';
ALTER USER 'hms'@'127.0.0.1' IDENTIFIED BY '$dbPassword';
ALTER USER 'hms'@'localhost' IDENTIFIED BY '$dbPassword';
GRANT ALL PRIVILEGES ON hms.* TO 'hms'@'127.0.0.1';
GRANT ALL PRIVILEGES ON hms.* TO 'hms'@'localhost';
$rootAlter
DELETE FROM mysql.user WHERE User='';
FLUSH PRIVILEGES;
"@
        Invoke-Mysql $rootCnf $grant
        Remove-Item $rootCnf -Force -ErrorAction SilentlyContinue
        if (-not $knownRoot) {
            Write-Utf8 $rootSecretPath $rootPassword
            Protect-File $rootSecretPath
        }

        $envText = @"
APP_NAME="MediCore"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=$siteUrl
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_LEVEL=warning
HMS_EDITION=pharmacy
HMS_APP_VERSION=$version
HMS_DEFAULT_HOSPITAL_CODE=MAIN
HMS_BACKUP_DIR=$slash/backups
HMS_MYSQLDUMP=$slash/runtime/mariadb/bin/mysqldump.exe
HMS_MYSQL=$slash/runtime/mariadb/bin/mysql.exe
HMS_CONTROL_SCRIPT=$slash/scripts/restart-services.ps1
HMS_BACKUP_TIMEZONE=Asia/Damascus
HMS_NODE_PORT=$nodePort
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=$mysqlPort
DB_DATABASE=hms
DB_USERNAME=hms
DB_PASSWORD=$dbPassword
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=database
MAIL_MAILER=log
"@
        Write-Utf8 $envPath $envText
        Protect-File $envPath
    } else {
        $envText = $existing
        $envText = Set-EnvValue $envText 'HMS_APP_VERSION' $version
        $envText = Set-EnvValue $envText 'HMS_EDITION' 'pharmacy'
        $envText = Set-EnvValue $envText 'APP_ENV' 'production'
        $envText = Set-EnvValue $envText 'APP_DEBUG' 'false'
        $envText = Set-EnvValue $envText 'HMS_BACKUP_DIR' "$slash/backups"
        $envText = Set-EnvValue $envText 'HMS_MYSQLDUMP' "$slash/runtime/mariadb/bin/mysqldump.exe"
        $envText = Set-EnvValue $envText 'HMS_MYSQL' "$slash/runtime/mariadb/bin/mysql.exe"
        $envText = Set-EnvValue $envText 'HMS_CONTROL_SCRIPT' "$slash/scripts/restart-services.ps1"
        if (-not (Get-EnvValue $envText 'HMS_BACKUP_TIMEZONE')) {
            $envText = Set-EnvValue $envText 'HMS_BACKUP_TIMEZONE' 'Asia/Damascus'
        }
        $envText = Set-EnvValue $envText 'HMS_NODE_PORT' "$nodePort"
        Write-Utf8 $envPath $envText
        Protect-File $envPath
        if ((Get-Service HMSMariaDB -ErrorAction SilentlyContinue).Status -ne 'Running') {
            Start-Service HMSMariaDB
            Wait-Tcp $mysqlPort 60
        }
    }

    $envLink = Join-Path $Root 'app\backend\.env'
    if (Test-Path $envLink) { Remove-Item $envLink -Force }
    & cmd /c mklink "$envLink" "$envPath" | Out-Null
    if (-not (Test-Path $envLink)) {
        Copy-Item $envPath $envLink -Force
    }

    $cachedConfig = Join-Path $Root 'app\backend\bootstrap\cache\config.php'
    if (Test-Path $cachedConfig) { Remove-Item $cachedConfig -Force }

    if (-not $upgrade) {
        Invoke-Artisan @('key:generate', '--force')
    }
    Invoke-Artisan @('migrate', '--force')
    $seedMarker = Join-Path $Root 'app\backend\seed-check.php'
    $seedCount = Join-Path $Root 'app\backend\seed-count.txt'
    Write-Utf8 $seedMarker @'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents(__DIR__ . '/seed-count.txt', (string) Illuminate\Support\Facades\DB::table('hospitals')->count());
'@
    Push-Location (Join-Path $Root 'app\backend')
    & $script:Php 'seed-check.php'
    Pop-Location
    $hospitalCount = if (Test-Path $seedCount) { (Get-Content $seedCount -Raw).Trim() } else { '0' }
    Remove-Item $seedMarker, $seedCount -Force -ErrorAction SilentlyContinue
    if (-not $upgrade -or $hospitalCount -eq '0') {
        Invoke-Artisan @('db:seed', '--class=Database\Seeders\PermissionSeeder', '--force')
        Invoke-Artisan @('db:seed', '--class=Database\Seeders\HospitalSeeder', '--force')
        Invoke-Artisan @('db:seed', '--class=Database\Seeders\ReferenceDataSeeder', '--force')
    }
    Push-Location (Join-Path $Root 'app\backend')
    & $script:Php artisan storage:link --force
    Pop-Location
    $publicStorage = Join-Path $Root 'app\backend\public\storage'
    if (-not (Test-Path $publicStorage)) {
        & cmd /c mklink /J "$publicStorage" "$Root\storage\app\public" | Out-Null
    }
    Invoke-Artisan @('config:cache')
    Invoke-Artisan @('view:cache')

    Write-Utf8 (Join-Path $Root 'app\frontend\public\hms-config.js') "window.__HMS__ = window.__HMS__ || {};`nwindow.__HMS__.apiUrl = `"/api`";`n"
    Write-Utf8 (Join-Path $Root 'config\site-url.txt') $siteUrl
    Write-Utf8 $versionFile $version

    foreach ($name in @('HMSMariaDB', 'HMSPhpCgi', 'HMSWeb', 'HMSFrontend')) {
        $svc = Get-Service $name
        if ($svc.Status -ne 'Running') { Start-Service $name }
    }
    Wait-Tcp $mysqlPort 60
    Wait-Tcp 9000 30
    Wait-Tcp $nodePort 60
    Wait-Tcp $httpPort 30

    $deadline = (Get-Date).AddSeconds(90)
    $healthy = $false
    do {
        try {
            $response = Invoke-WebRequest -UseBasicParsing "http://127.0.0.1:$httpPort/up" -TimeoutSec 3
            if ($response.StatusCode -eq 200) { $healthy = $true; break }
        } catch {
        }
        Start-Sleep -Seconds 1
    } while ((Get-Date) -lt $deadline)
    if (-not $healthy) { throw "HMS did not answer on $siteUrl/up" }

    & schtasks.exe /Create /F /TN "MediCore Daily Backup" /SC DAILY /ST 02:00 /RU SYSTEM /RL HIGHEST /TR "$Root\scripts\backup-scheduled.cmd" | Out-Null
    if (Test-Path (Join-Path $Root 'config\upgrade-backup.ok')) {
        Remove-Item (Join-Path $Root 'config\upgrade-backup.ok') -Force
    }
    Write-Utf8 (Join-Path $Root 'config\ready.flag') (Get-Date -Format o)
    Write-Log 'Encrypting MediCore files.'
    foreach ($name in @('HMSFrontend', 'HMSWeb', 'HMSPhpCgi')) {
        $svc = Get-Service $name -ErrorAction SilentlyContinue
        if ($svc -and $svc.Status -ne 'Stopped') { Stop-Service $name -Force }
    }
    try {
        Invoke-MediCoreFileCrypto Encrypt
    } catch {
        Write-Log $_.Exception.Message
        Invoke-MediCoreFileCrypto Decrypt
        throw
    } finally {
        foreach ($name in @('HMSPhpCgi', 'HMSWeb', 'HMSFrontend')) {
            $svc = Get-Service $name -ErrorAction SilentlyContinue
            if ($svc -and $svc.Status -ne 'Running') { Start-Service $name }
        }
    }
    $deadline = (Get-Date).AddSeconds(90)
    $healthy = $false
    do {
        try {
            $response = Invoke-WebRequest -UseBasicParsing "http://127.0.0.1:$httpPort/up" -TimeoutSec 3
            if ($response.StatusCode -eq 200) { $healthy = $true; break }
        } catch {
        }
        Start-Sleep -Seconds 1
    } while ((Get-Date) -lt $deadline)
    if (-not $healthy) {
        Invoke-MediCoreFileCrypto Decrypt
        throw "HMS did not answer on $siteUrl/up after file encryption"
    }
    Protect-Installation
    Write-Log 'Install completed.'
    exit 0
} catch {
    Write-Log $_.Exception.Message
    Write-Log $_.ScriptStackTrace
    exit 1
}
