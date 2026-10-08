[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $Serial,

    [Parameter(Mandatory = $true)]
    [string] $ApkPath,

    [string] $Package = 'com.bsolutions.micatalogo',

    [switch] $Install
)

$ErrorActionPreference = 'Stop'

function Invoke-Adb {
    param([Parameter(Mandatory = $true)][string[]] $Arguments)

    $adb = (Get-Command adb -ErrorAction Stop).Source
    $result = & $adb @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "adb fallo ($LASTEXITCODE): $($result -join ' ')"
    }

    return @($result)
}

function Get-CertificateSha256 {
    param([Parameter(Mandatory = $true)][string] $Path)

    $sdkRoot = Join-Path $env:LOCALAPPDATA 'Android\Sdk\build-tools'
    $apksigner = Get-ChildItem -LiteralPath $sdkRoot -Filter 'apksigner.bat' -Recurse |
        Sort-Object FullName -Descending |
        Select-Object -First 1
    if ($null -eq $apksigner) {
        throw "No se encontró apksigner en $sdkRoot"
    }

    $output = & $apksigner.FullName verify --print-certs $Path 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudo verificar la firma de $Path"
    }

    $line = $output | Where-Object { $_ -match 'Signer #1 certificate SHA-256 digest:' } | Select-Object -First 1
    if ($null -eq $line -or $line -notmatch '([0-9a-fA-F]{64})\s*$') {
        throw "No se encontró el SHA-256 del certificado de $Path"
    }

    return $Matches[1].ToLowerInvariant()
}

if (-not (Test-Path -LiteralPath $ApkPath -PathType Leaf)) {
    throw "No existe la APK: $ApkPath"
}

$apkPathResolved = (Resolve-Path -LiteralPath $ApkPath).Path
$adbState = (Invoke-Adb @('-s', $Serial, 'get-state') | Select-Object -First 1).Trim()
if ($adbState -ne 'device') {
    throw "El destino '$Serial' no está listo; estado: '$adbState'"
}

$model = (Invoke-Adb @('-s', $Serial, 'shell', 'getprop', 'ro.product.model') | Select-Object -First 1).Trim()
$isEmulator = (Invoke-Adb @('-s', $Serial, 'shell', 'getprop', 'ro.kernel.qemu') | Select-Object -First 1).Trim() -eq '1'
if ($isEmulator) {
    throw "Este verificador es únicamente para release en teléfono físico; '$Serial' es un emulador."
}

$apkInfo = & 'C:\Users\waila\.config\opencode\skills\micatalogo-android-release\scripts\release.ps1' -Mode VerifyApk -ApkPath $apkPathResolved 2>&1 | Out-String
if ($LASTEXITCODE -ne 0) {
    throw "La APK no pasó la verificación de release: $($apkInfo.Trim())"
}
$apkMetadata = $apkInfo | ConvertFrom-Json

$dump = Invoke-Adb @('-s', $Serial, 'shell', 'dumpsys', 'package', $Package)
$versionLine = $dump | Where-Object { $_ -match '^\s*versionCode=' } | Select-Object -First 1
$versionNameLine = $dump | Where-Object { $_ -match '^\s*versionName=' } | Select-Object -First 1
$versionCode = $null
$versionName = $null
if ($versionLine -match 'versionCode=(\d+)') { $versionCode = [int] $Matches[1] }
if ($versionNameLine -match 'versionName=([^\s]+)') { $versionName = $Matches[1] }

$installedPathLine = Invoke-Adb @('-s', $Serial, 'shell', 'pm', 'path', $Package) |
    Where-Object { $_ -match '^package:' } |
    Select-Object -First 1
$installedPath = if ($installedPathLine) { ($installedPathLine -replace '^package:', '').Trim() } else { $null }
$installedCertificate = $null
$tempPath = $null

try {
    if ($installedPath) {
        $tempPath = Join-Path ([System.IO.Path]::GetTempPath()) ("micatalogo-installed-$([guid]::NewGuid().ToString('N')).apk")
        $adb = (Get-Command adb -ErrorAction Stop).Source
        & $adb -s $Serial pull $installedPath $tempPath | Out-Null
        if ($LASTEXITCODE -ne 0) {
            throw "No se pudo leer la APK instalada para comparar el certificado."
        }
        $installedCertificate = Get-CertificateSha256 -Path $tempPath
    }

    $sameCertificate = $installedCertificate -and ($installedCertificate -eq $apkMetadata.CertificateSha256.ToLowerInvariant())
    $status = if (-not $installedPath) {
        'ready_new_install'
    } elseif (-not $sameCertificate) {
        'blocked_signature_mismatch'
    } elseif ($versionCode -ge [int] $apkMetadata.VersionCode) {
        'blocked_not_newer'
    } elseif (-not $Install) {
        'ready_update_dry_run'
    } else {
        $installOutput = Invoke-Adb @('-s', $Serial, 'install', '-r', $apkPathResolved)
        'installed'
    }

    [pscustomobject]@{
        status = $status
        serial = $Serial
        model = $model
        package = $Package
        installed_version_code = $versionCode
        installed_version_name = $versionName
        installed_certificate_sha256 = $installedCertificate
        release_version_code = [int] $apkMetadata.VersionCode
        release_version_name = [string] $apkMetadata.VersionName
        release_certificate_sha256 = [string] $apkMetadata.CertificateSha256
        release_apk_sha256 = [string] $apkMetadata.ApkSha256
        install_requested = [bool] $Install
    } | ConvertTo-Json -Depth 4
}
finally {
    if ($tempPath -and (Test-Path -LiteralPath $tempPath -PathType Leaf)) {
        Remove-Item -LiteralPath $tempPath -Force
    }
}
