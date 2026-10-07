function Test-MediCoreEncrypted {
    $sample = Join-Path $Root 'app\backend\bootstrap\app.php'
    if (-not (Test-Path -LiteralPath $sample)) { return $false }
    $stream = [System.IO.File]::Open($sample, 'Open', 'Read', 'ReadWrite')
    try {
        $magic = New-Object byte[] 6
        $count = $stream.Read($magic, 0, 6)
        if ($count -lt 6) { return $false }
        return [System.Text.Encoding]::ASCII.GetString($magic) -eq 'MCENC1'
    } finally {
        $stream.Close()
    }
}

function Get-MediCoreContentKey {
    $keyPath = Join-Path $Root 'config\content.key'
    if (Test-Path -LiteralPath $keyPath) {
        $key = [System.IO.File]::ReadAllBytes($keyPath)
        if ($key.Length -eq 32) { return $key }
    }
    $created = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($created)
    [System.IO.File]::WriteAllBytes($keyPath, $created)
    return $created
}

function Convert-MediCoreCipher([byte[]]$InputBytes, [byte[]]$Key, [bool]$Encrypt) {
    if (-not $Encrypt) {
        if ($InputBytes.Length -lt 22) { return $null }
        $magic = [System.Text.Encoding]::ASCII.GetString($InputBytes, 0, 6)
        if ($magic -ne 'MCENC1') { return $null }
        $iv = New-Object byte[] 16
        [System.Buffer]::BlockCopy($InputBytes, 6, $iv, 0, 16)
        $cipher = New-Object byte[] ($InputBytes.Length - 22)
        [System.Buffer]::BlockCopy($InputBytes, 22, $cipher, 0, $cipher.Length)
        $aes = [System.Security.Cryptography.Aes]::Create()
        $aes.Mode = [System.Security.Cryptography.CipherMode]::CBC
        $aes.Padding = [System.Security.Cryptography.PaddingMode]::PKCS7
        $aes.KeySize = 256
        $aes.Key = $Key
        $aes.IV = $iv
        $decryptor = $aes.CreateDecryptor()
        $plain = $decryptor.TransformFinalBlock($cipher, 0, $cipher.Length)
        $decryptor.Dispose()
        $aes.Dispose()
        return $plain
    }
    $aes = [System.Security.Cryptography.Aes]::Create()
    $aes.Mode = [System.Security.Cryptography.CipherMode]::CBC
    $aes.Padding = [System.Security.Cryptography.PaddingMode]::PKCS7
    $aes.KeySize = 256
    $aes.Key = $Key
    $aes.GenerateIV()
    $encryptor = $aes.CreateEncryptor()
    $cipher = $encryptor.TransformFinalBlock($InputBytes, 0, $InputBytes.Length)
    $out = New-Object byte[] (22 + $cipher.Length)
    [System.Buffer]::BlockCopy([System.Text.Encoding]::ASCII.GetBytes('MCENC1'), 0, $out, 0, 6)
    [System.Buffer]::BlockCopy($aes.IV, 0, $out, 6, 16)
    [System.Buffer]::BlockCopy($cipher, 0, $out, 22, $cipher.Length)
    $encryptor.Dispose()
    $aes.Dispose()
    return $out
}

function Get-MediCoreSourceFiles {
    $files = New-Object System.Collections.Generic.List[string]
    $roots = @(
        (Join-Path $Root 'app\backend'),
        (Join-Path $Root 'app\frontend')
    )
    $extensions = @('.php', '.js', '.mjs', '.cjs', '.json', '.css', '.html', '.htm')
    foreach ($dir in $roots) {
        if (-not (Test-Path -LiteralPath $dir)) { continue }
        $pending = New-Object System.Collections.Generic.Queue[string]
        $pending.Enqueue($dir)
        while ($pending.Count -gt 0) {
            $current = $pending.Dequeue()
            foreach ($entry in [System.IO.Directory]::EnumerateFileSystemEntries($current)) {
                $attrs = [System.IO.File]::GetAttributes($entry)
                if ($attrs -band [System.IO.FileAttributes]::ReparsePoint) { continue }
                $name = [System.IO.Path]::GetFileName($entry)
                if ($attrs -band [System.IO.FileAttributes]::Directory) {
                    if ($name -eq 'node_modules' -or $name -eq 'storage') { continue }
                    $pending.Enqueue($entry)
                    continue
                }
                $lower = $name.ToLower()
                if ($lower -eq 'index.php' -and [System.IO.Path]::GetFileName($current) -eq 'public') { continue }
                if ($lower -eq 'artisan' -or $lower -eq 'package.json' -or $lower -eq 'package-lock.json' -or $lower -eq 'composer.json') { continue }
                $ext = [System.IO.Path]::GetExtension($lower)
                if ($extensions -contains $ext) { $files.Add($entry) }
            }
        }
    }
    foreach ($relative in @('config\app.env', 'config\mysql-root.secret')) {
        $path = Join-Path $Root $relative
        if (Test-Path -LiteralPath $path) { $files.Add($path) }
    }
    return $files
}

function Invoke-MediCoreFileCrypto([ValidateSet('Encrypt', 'Decrypt')][string]$Mode) {
    $key = Get-MediCoreContentKey
    $count = 0
    foreach ($path in (Get-MediCoreSourceFiles)) {
        $bytes = [System.IO.File]::ReadAllBytes($path)
        $magic = if ($bytes.Length -ge 6) { [System.Text.Encoding]::ASCII.GetString($bytes, 0, 6) } else { '' }
        if ($Mode -eq 'Encrypt') {
            if ($magic -eq 'MCENC1' -or $bytes.Length -eq 0) { continue }
            $next = Convert-MediCoreCipher $bytes $key $true
        } else {
            if ($magic -ne 'MCENC1') { continue }
            $next = Convert-MediCoreCipher $bytes $key $false
        }
        [System.IO.File]::WriteAllBytes($path, $next)
        $count++
    }
    if (Test-Path function:Write-Log) { Write-Log "$Mode completed for $count MediCore files." }
}

if ($MyInvocation.InvocationName -ne '.') {
    $ErrorActionPreference = 'Stop'
    $Root = [string]$args[1]
    if ($args[0] -eq 'Decrypt') { Invoke-MediCoreFileCrypto Decrypt } else { Invoke-MediCoreFileCrypto Encrypt }
}
