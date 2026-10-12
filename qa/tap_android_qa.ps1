param(
    [Parameter(Mandatory=$true)][string]$Text,
    [int]$Index=0,
    [string]$TypeText='',
    [switch]$ReplaceText
)
$ErrorActionPreference='Stop'
$adb='C:\Users\waila\AppData\Local\Android\Sdk\platform-tools\adb.exe'
$serial='emulator-5554'
$package='com.bsolutions.micatalogo.offlinecheck.emulatorqa'
if ((& $adb -s $serial shell getprop ro.kernel.qemu).Trim() -ne '1') { throw 'QA interactions require the emulator.' }
$remote='/sdcard/qa-interaction-'+[guid]::NewGuid().ToString('N')+'.xml'
& $adb -s $serial shell uiautomator dump $remote | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Could not capture current UI.' }
$info=New-Object System.Diagnostics.ProcessStartInfo
$info.FileName=$adb
$info.Arguments="-s $serial shell cat $remote"
$info.UseShellExecute=$false
$info.RedirectStandardOutput=$true
$info.StandardOutputEncoding=New-Object System.Text.UTF8Encoding($false)
$process=New-Object System.Diagnostics.Process
$process.StartInfo=$info
[void]$process.Start()
[xml]$tree=$process.StandardOutput.ReadToEnd()
$process.WaitForExit()
$nodes=@($tree.SelectNodes('//node') | Where-Object {
    $_.package -eq $package -and ($_.text -eq $Text -or $_.'content-desc' -eq $Text)
})
if ($nodes.Count -le $Index) { throw "QA control not found: $Text; no interaction performed." }
$node=$nodes[$Index]
if ($node.enabled -eq 'false') { throw "QA control is disabled: $Text" }
if ($node.bounds -notmatch '^\[(\d+),(\d+)\]\[(\d+),(\d+)\]$') { throw 'Invalid bounds.' }
$x=[int](([int]$Matches[1]+[int]$Matches[3])/2)
$y=[int](([int]$Matches[2]+[int]$Matches[4])/2)
& $adb -s $serial shell input tap $x $y
if ($TypeText -ne '') {
    if ($TypeText -notmatch '^[a-zA-Z0-9@.!_ -]+$') { throw 'QA input requires simple literal text.' }
    if ($ReplaceText) {
        & $adb -s $serial shell input keycombination 113 29
        & $adb -s $serial shell input keyevent 67
    }
    & $adb -s $serial shell input text $TypeText.Replace(' ','%s')
}
"QA interaction: $Text at $x,$y"
