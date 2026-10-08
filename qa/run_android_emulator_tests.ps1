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
    [string]$PosSaleToken = ''
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
    & $adb -s $Serial shell am instrument -w -r @extra -e package $PackageFilter $target
} else {
    & $adb -s $Serial shell am instrument -w -r @extra -e class $ClassFilter $target
}
if ($LASTEXITCODE -ne 0) {
    throw "La bateria instrumentada termino con codigo $LASTEXITCODE."
}
