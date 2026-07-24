#Requires -Version 5.1
<#
.SYNOPSIS
  Pregătește pe USB pachetul minim pentru migrare Proxmox LXC (dump DB + .env + uploads).

.DESCRIPTION
  Copiază secretele (.env), face mysqldump pentru cele 2 baze și arhivează uploads.
  NU copiază imports/tecdoc/vendor (~8 GB regenerabile).

.PARAMETER Destination
  Calea folderului pe USB, ex. E:\besoiu-migrate

.PARAMETER ProjectRoot
  Rădăcina proiectului Laragon (implicit: părintele scripts/proxmox).

.PARAMETER SkipDump
  Nu rulează mysqldump (doar .env + uploads).

.PARAMETER MysqlUser
  Utilizator MySQL Laragon (implicit root).

.PARAMETER MysqlPassword
  Parolă MySQL (implicit goală, tipic Laragon).

.EXAMPLE
  .\Prepare-UsbMigrate.ps1 -Destination E:\besoiu-migrate
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Destination,

    [string]$ProjectRoot = "",

    [switch]$SkipDump,

    [string]$MysqlUser = "root",

    [string]$MysqlPassword = "",

    [string]$MysqlDumpPath = ""
)

$ErrorActionPreference = "Stop"

if (-not $ProjectRoot) {
    $ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
}

$dest = $Destination
if (-not (Test-Path $dest)) {
    New-Item -ItemType Directory -Path $dest -Force | Out-Null
}
$dest = (Resolve-Path $dest).Path

Write-Host "Proiect : $ProjectRoot"
Write-Host "USB dest: $dest"
Write-Host ""

# --- 1) .env (redenumite pentru deploy-lxc.sh) ---
$envMap = @(
    @{ Src = "app\Config\.env";   Dst = "app-Config.env" },
    @{ Src = "app\Backend\.env";  Dst = "app-Backend.env" },
    @{ Src = "admin\.env";        Dst = "admin.env" },
    @{ Src = "robot\.env";        Dst = "robot.env" }
)

$copiedEnv = @()
foreach ($item in $envMap) {
    $srcPath = Join-Path $ProjectRoot $item.Src
    if (Test-Path $srcPath) {
        Copy-Item -LiteralPath $srcPath -Destination (Join-Path $dest $item.Dst) -Force
        $copiedEnv += $item.Dst
        Write-Host "[OK] .env -> $($item.Dst)"
    }
    else {
        Write-Host "[--] lipsește $($item.Src)"
    }
}

# --- 2) uploads zip (minim; folderul poate fi aproape gol) ---
$uploadsDir = Join-Path $ProjectRoot "app\Storage\uploads"
$uploadsZip = Join-Path $dest "uploads.zip"
if (Test-Path $uploadsDir) {
    if (Test-Path $uploadsZip) { Remove-Item $uploadsZip -Force }
    Compress-Archive -Path (Join-Path $uploadsDir "*") -DestinationPath $uploadsZip -Force -ErrorAction SilentlyContinue
    if (-not (Test-Path $uploadsZip)) {
        # folder gol: creează zip minimal
        $tmpEmpty = Join-Path $env:TEMP "besoiu-uploads-empty"
        if (Test-Path $tmpEmpty) { Remove-Item $tmpEmpty -Recurse -Force }
        New-Item -ItemType Directory -Path $tmpEmpty | Out-Null
        Set-Content -Path (Join-Path $tmpEmpty ".gitkeep") -Value ""
        Compress-Archive -Path (Join-Path $tmpEmpty "*") -DestinationPath $uploadsZip -Force
        Remove-Item $tmpEmpty -Recurse -Force
    }
    $zipMb = [math]::Round((Get-Item $uploadsZip).Length / 1MB, 2)
    Write-Host "[OK] uploads.zip ($zipMb MB)"
}
else {
    Write-Host "[--] app\Storage\uploads lipsește"
}

# --- 3) mysqldump ---
$dbMain = "besoiupieseauto.ro"
$dbLegacy = "caietcom_comenzilv"

function Find-MysqlDump {
    param([string]$Explicit)
    if ($Explicit -and (Test-Path $Explicit)) { return $Explicit }
    $fromPath = Get-Command mysqldump -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source
    if ($fromPath) { return $fromPath }
    $candidate = Get-ChildItem "C:\laragon\bin\mysql" -Recurse -Filter "mysqldump.exe" -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending |
        Select-Object -First 1 -ExpandProperty FullName
    if ($candidate) { return $candidate }
    return $null
}

if (-not $SkipDump) {
    $dumpExe = Find-MysqlDump -Explicit $MysqlDumpPath
    if (-not $dumpExe) {
        Write-Warning "mysqldump.exe negăsit. Rulează manual (vezi README-MIGRATE.txt) sau -MysqlDumpPath."
    }
    else {
        Write-Host "[OK] mysqldump: $dumpExe"
        foreach ($db in @($dbMain, $dbLegacy)) {
            $outFile = Join-Path $dest "$db.sql"
            Write-Host "Dump $db ..."
            $passPart = if ($MysqlPassword -ne "") { " -p$MysqlPassword" } else { "" }
            # Nume DB cu punct → ghilimele; redirect via cmd pentru fișiere mari
            $cmd = "`"$dumpExe`" -u$MysqlUser$passPart --single-transaction --routines --triggers --default-character-set=utf8mb4 `"$db`" > `"$outFile`" 2> `"$outFile.err`""
            cmd /c $cmd
            $errText = ""
            if (Test-Path "$outFile.err") {
                $errText = Get-Content "$outFile.err" -Raw -ErrorAction SilentlyContinue
                Remove-Item "$outFile.err" -Force -ErrorAction SilentlyContinue
            }
            $ok = (Test-Path $outFile) -and ((Get-Item $outFile).Length -gt 50)
            if (-not $ok) {
                Write-Warning "Dump eșuat pentru $db. $errText"
                if (Test-Path $outFile) { Remove-Item $outFile -Force }
            }
            else {
                $mb = [math]::Round((Get-Item $outFile).Length / 1MB, 2)
                Write-Host "[OK] $db.sql ($mb MB)"
                if ($errText -and $errText.Trim()) { Write-Host "     (stderr: $($errText.Trim().Substring(0, [Math]::Min(120, $errText.Trim().Length))))" }
            }
        }
    }
}
else {
    Write-Host "[--] SkipDump: fără mysqldump"
}

# --- 4) Scripturi deploy (ca să poți rula din /root/migrate înainte de clone) ---
$deployFiles = @("deploy-lxc.sh", "apache-besoiu.conf")
foreach ($df in $deployFiles) {
    $srcDf = Join-Path $PSScriptRoot $df
    if (Test-Path $srcDf) {
        Copy-Item -LiteralPath $srcDf -Destination (Join-Path $dest $df) -Force
        Write-Host "[OK] script -> $df"
    }
}

# --- 5) README pe USB ---
$readme = @"
# Besoiu — pachet migrare USB → Proxmox LXC

Generat: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
Proiect: $ProjectRoot

## Conținut așteptat
- app-Config.env, app-Backend.env, admin.env (+ robot.env dacă există)
- besoiupieseauto.ro.sql
- caietcom_comenzilv.sql (dacă baza există pe Laragon)
- uploads.zip
- deploy-lxc.sh, apache-besoiu.conf

## Pe Proxmox (host)
1. Montează USB-ul / copiază acest folder în CT: /root/migrate/
2. În CT (ca root):
   chmod +x /root/migrate/deploy-lxc.sh
   APP_URL=http://IP_CT MIGRATE_DIR=/root/migrate bash /root/migrate/deploy-lxc.sh
   (detalii: docs/MIGRARE_PROXMOX_LXC.md în repo)

## mysqldump manual (dacă lipsește dump-ul)
cd /d C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin
mysqldump.exe -uroot --single-transaction --routines --triggers --default-character-set=utf8mb4 "besoiupieseauto.ro" > E:\besoiu-migrate\besoiupieseauto.ro.sql
mysqldump.exe -uroot --single-transaction --routines --triggers --default-character-set=utf8mb4 "caietcom_comenzilv" > E:\besoiu-migrate\caietcom_comenzilv.sql

## NU include (intenționat)
imports, tecdoc cache, supplier_feeds, vendor, node_modules, tools scraper
"@
Set-Content -Path (Join-Path $dest "README-MIGRATE.txt") -Value $readme -Encoding UTF8
Write-Host "[OK] README-MIGRATE.txt"

# --- 6) manifest ---
$files = Get-ChildItem $dest -File | Select-Object Name, @{N = 'MB'; E = { [math]::Round($_.Length / 1MB, 2) } }
$manifest = ($files | Format-Table -AutoSize | Out-String)
Set-Content -Path (Join-Path $dest "MANIFEST.txt") -Value $manifest -Encoding UTF8
Write-Host ""
Write-Host "=== Pachet gata: $dest ==="
Write-Host $manifest
Write-Host "Env copiate: $($copiedEnv -join ', ')"
Write-Host "Următorul pas: docs/MIGRARE_PROXMOX_LXC.md (Create CT pe Proxmox)."
