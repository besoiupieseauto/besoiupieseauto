$ErrorActionPreference = "Stop"

$toolsDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$targetDir = Join-Path $toolsDir "rclone"
$zipPath = Join-Path $env:TEMP "rclone-current-windows-amd64.zip"
$url = "https://downloads.rclone.org/rclone-current-windows-amd64.zip"

Write-Host "Descarc rclone..."
Invoke-WebRequest -Uri $url -OutFile $zipPath

if (Test-Path $targetDir) {
    Remove-Item $targetDir -Recurse -Force
}
New-Item -ItemType Directory -Path $targetDir | Out-Null

Expand-Archive -Path $zipPath -DestinationPath $targetDir -Force
$exe = Get-ChildItem -Path $targetDir -Recurse -Filter "rclone.exe" | Select-Object -First 1
if (-not $exe) {
    throw "rclone.exe nu a fost gasit in arhiva."
}

Copy-Item $exe.FullName (Join-Path $targetDir "rclone.exe") -Force
Write-Host "Instalat: $(Join-Path $targetDir 'rclone.exe')"
& (Join-Path $targetDir "rclone.exe") version

Write-Host ""
Write-Host "Pasii urmatori:"
Write-Host "  1. php admin/tools/generate_rclone_config.php"
Write-Host "  2. admin\scripts\run_supplier_rclone_sync.bat"
