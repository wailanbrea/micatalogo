param(
    [string]$Serial = 'emulator-5554',
    [string]$TestPackage = 'com.bsolutions.micatalogo.test',
    [string]$Runner = 'androidx.test.runner.AndroidJUnitRunner',
    [string]$PackageFilter = 'com.example.bspos',
    [string]$ClassFilter = '',
    [string]$InventoryImportShop = '',
    [string]$PosSaleShop = '',
    [string]$PosSaleProduct = '',
    [string]$PosSaleSourceProduct = '',
    [string]$PosSaleDecantProduct = '',
    [string]$PosSaleToken = '',
    [string]$OutputFile = '',
    [switch]$FailOnAssumptions
)

$ErrorActionPreference = 'Stop'

$adb = (Get-Command adb -ErrorAction Stop).Source
$devices = @(& $adb devices | Select-Object -Skip 1 | Where-Object { $_ -match '\S+\s+device$' } | ForEach-Object { ($_ -split '\s+')[0] })
if ($devices -notcontains $Serial) {
    throw "El serial '$Serial' no esta conectado como device. Dispositivos visibles: $($devices -join ', ')"
}

$isEmulator = (& $adb -s $Serial shell getprop ro.kernel.qemu).Trim()
if ($isEmulator -ne '1') {
    throw "Por seguridad este runner solo acepta un emulador (ro.kernel.qemu=1). No se ejecutara en un dispositivo fisico."
}
Write-Host "[QA] Destino validado: $Serial (emulador Android; no se toca ningun dispositivo fisico)."

$installed = & $adb -s $Serial shell pm list packages
if (-not ($installed -contains "package:$TestPackage")) {
    throw "No esta instalado el APK de tests '$TestPackage' en '$Serial'. Instala una variante debug de QA en el emulador antes de ejecutar este runner."
}

$target = "$TestPackage/$Runner"
$extra = @()
if (-not [string]::IsNullOrWhiteSpace($InventoryImportShop)) {
    $extra += @('-e', 'inventoryImportShop', $InventoryImportShop)
}
if (-not [string]::IsNullOrWhiteSpace($PosSaleShop)) {
    $extra += @('-e', 'posSaleShop', $PosSaleShop)
}
if (-not [string]::IsNullOrWhiteSpace($PosSaleProduct)) {
    $extra += @('-e', 'posSaleProduct', $PosSaleProduct)
}
if (-not [string]::IsNullOrWhiteSpace($PosSaleSourceProduct)) {
    $extra += @('-e', 'posSaleSourceProduct', $PosSaleSourceProduct)
}
if (-not [string]::IsNullOrWhiteSpace($PosSaleDecantProduct)) {
    $extra += @('-e', 'posSaleDecantProduct', $PosSaleDecantProduct)
}
if (-not [string]::IsNullOrWhiteSpace($PosSaleToken)) {
    $extra += @('-e', 'posSaleToken', $PosSaleToken)
}
if ([string]::IsNullOrWhiteSpace($ClassFilter)) {
    $instrumentOutput = @(& $adb -s $Serial shell am instrument -w -r @extra -e package $PackageFilter $target 2>&1)
} else {
    $instrumentOutput = @(& $adb -s $Serial shell am instrument -w -r @extra -e class $ClassFilter $target 2>&1)
}
$instrumentExitCode = $LASTEXITCODE
$instrumentOutput | ForEach-Object { Write-Output $_ }

if (-not [string]::IsNullOrWhiteSpace($OutputFile)) {
    $outputParent = Split-Path -Parent $OutputFile
    if (-not [string]::IsNullOrWhiteSpace($outputParent)) {
        New-Item -ItemType Directory -Force -Path $outputParent | Out-Null
    }
    $instrumentOutput | Set-Content -LiteralPath $OutputFile -Encoding UTF8
    Write-Host "[QA] Evidencia guardada en: $OutputFile"
}

if ($instrumentExitCode -ne 0) {
    throw "La bateria instrumentada termino con codigo $instrumentExitCode."
}

$failureMarkers = @($instrumentOutput | Where-Object {
        $_ -match 'FAILURES!!!|INSTRUMENTATION_STATUS_CODE: -2|FATAL EXCEPTION|Process crashed'
    })
if ($failureMarkers.Count -gt 0) {
    throw "La bateria contiene fallos de pruebas o del proceso instrumentado. Revisa la evidencia guardada antes de aceptar el resultado."
}

$successMarkers = @($instrumentOutput | Where-Object { $_ -match '^OK \(\d+ tests?\)$' })
if ($successMarkers.Count -eq 0) {
    throw "La bateria no produjo un resumen OK verificable; no se acepta como PASS silencioso."
}

$assumptionLines = @($instrumentOutput | Where-Object { $_ -match 'AssumptionViolatedException|assumption' })
if ($assumptionLines.Count -gt 0) {
    Write-Warning "La bateria contiene $($assumptionLines.Count) linea(s) de pruebas omitidas por assumptions; no se cuentan como PASS completo."
    if ($FailOnAssumptions) {
        throw "QA estricto: existen pruebas omitidas por assumptions. Configura sus fixtures aislados antes de aceptar la suite."
    }
}
