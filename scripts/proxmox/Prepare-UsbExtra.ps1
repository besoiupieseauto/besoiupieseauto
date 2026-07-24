#Requires -Version 5.1
<#
.SYNOPSIS
  USB package for what was NOT migrated: extra DB dumps + heavy folders.

.PARAMETER Destination
  Example: F:\besoiu-migrate-extra

.EXAMPLE
  .\Prepare-UsbExtra.ps1 -Destination F:\besoiu-migrate-extra
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Destination,

    [string]$ProjectRoot = "",

    [switch]$SkipDump,

    [switch]$SkipHeavy,

    [string]$MysqlUser = "root",

    [string]$MysqlPassword = "",

    [string]$MysqlDumpPath = ""
)

$ErrorActionPreference = "Stop"

if (-not $ProjectRoot) {
    $ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
}

$dest = $Destination
New-Item -ItemType Directory -Path $dest -Force | Out-Null
$dest = (Resolve-Path $dest).Path
$dbDir = Join-Path $dest "db"
$filesDir = Join-Path $dest "files"
New-Item -ItemType Directory -Path $dbDir, $filesDir -Force | Out-Null

Write-Host "Project : $ProjectRoot"
Write-Host "USB dest: $dest"
Write-Host ""

function Find-MysqlDump {
    param([string]$Explicit)
    if ($Explicit -and (Test-Path $Explicit)) { return $Explicit }
    $fromPath = Get-Command mysqldump -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source
    if ($fromPath) { return $fromPath }
    return (Get-ChildItem "C:\laragon\bin\mysql" -Recurse -Filter "mysqldump.exe" -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending | Select-Object -First 1 -ExpandProperty FullName)
}

$databases = @(
    "besoiupieseauto.ro",
    "caietcom_comenzilv",
    "besoiu_tecdoc_base",
    "besoiu_tecdoc_y1998",
    "besoiupieseimport"
)

if (-not $SkipDump) {
    $dumpExe = Find-MysqlDump -Explicit $MysqlDumpPath
    if (-not $dumpExe) {
        Write-Warning "mysqldump not found"
    }
    else {
        Write-Host "mysqldump: $dumpExe"
        $passPart = if ($MysqlPassword -ne "") { " -p$MysqlPassword" } else { "" }
        foreach ($db in $databases) {
            $outFile = Join-Path $dbDir ($db + ".sql")
            Write-Host "Dump $db ..."
            $cmd = "`"$dumpExe`" -u$MysqlUser$passPart --single-transaction --routines --triggers --default-character-set=utf8mb4 `"$db`" > `"$outFile`" 2> `"$outFile.err`""
            cmd /c $cmd
            if (Test-Path ($outFile + ".err")) {
                Remove-Item ($outFile + ".err") -Force -ErrorAction SilentlyContinue
            }
            if ((Test-Path $outFile) -and ((Get-Item $outFile).Length -gt 50)) {
                $mb = [math]::Round((Get-Item $outFile).Length / 1MB, 2)
                Write-Host ("OK db/{0}.sql size_MB={1}" -f $db, $mb)
            }
            else {
                Write-Warning ("Dump failed or empty: {0}" -f $db)
                if (Test-Path $outFile) { Remove-Item $outFile -Force }
            }
        }
    }
}
else {
    Write-Host "SkipDump"
}

$heavy = @(
    @{ Rel = "app\Backend\storage\imports";           Dst = "imports" },
    @{ Rel = "app\Backend\storage\tecdoc";            Dst = "tecdoc" },
    @{ Rel = "app\Backend\storage\supplier_feeds";    Dst = "supplier_feeds" },
    @{ Rel = "app\Backend\storage\ttc_image_library"; Dst = "ttc_image_library" },
    @{ Rel = "admin\storage\imports";                 Dst = "admin_imports" },
    @{ Rel = "admin\storage\tecdoc";                  Dst = "admin_tecdoc" },
    @{ Rel = "admin\storage\supplier_feeds";          Dst = "admin_supplier_feeds" },
    @{ Rel = "admin\storage\ttc_image_library";       Dst = "admin_ttc_image_library" },
    @{ Rel = "app\Storage\uploads";                   Dst = "uploads" },
    @{ Rel = "storage\scraper";                       Dst = "storage_scraper" }
)

if (-not $SkipHeavy) {
    foreach ($h in $heavy) {
        $src = Join-Path $ProjectRoot $h.Rel
        $dstPath = Join-Path $filesDir $h.Dst
        if (-not (Test-Path $src)) {
            Write-Host ("skip missing {0}" -f $h.Rel)
            continue
        }
        Write-Host ("Copy {0} -> files/{1}" -f $h.Rel, $h.Dst)
        New-Item -ItemType Directory -Path $dstPath -Force | Out-Null
        $null = robocopy $src $dstPath /E /R:1 /W:1 /NFL /NDL /NJH /NJS /XD ".git" "__pycache__"
        if ($LASTEXITCODE -ge 8) {
            Write-Warning ("robocopy exit {0} for {1}" -f $LASTEXITCODE, $h.Rel)
        }
        else {
            $sum = (Get-ChildItem $dstPath -Recurse -File -ErrorAction SilentlyContinue | Measure-Object Length -Sum).Sum
            $mb = [math]::Round(($sum / 1MB), 1)
            Write-Host ("OK files/{0} size_MB={1}" -f $h.Dst, $mb)
        }
    }
}
else {
    Write-Host "SkipHeavy"
}

$restoreSh = @'
#!/usr/bin/env bash
set -euo pipefail
EXTRA_DIR="${EXTRA_DIR:-/root/migrate-extra}"
APP_DIR="${APP_DIR:-/var/www/besoiupieseauto.ro}"

log() { echo "[extra] $*"; }
[[ -d "$EXTRA_DIR" ]] || { echo "Missing $EXTRA_DIR"; exit 1; }

if [[ -d "$EXTRA_DIR/db" ]]; then
  for f in "$EXTRA_DIR/db"/*.sql; do
    [[ -f "$f" ]] || continue
    [[ $(stat -c%s "$f") -gt 50 ]] || continue
    name=$(basename "$f" .sql)
    log "Import DB $name ..."
    mysql -e "CREATE DATABASE IF NOT EXISTS \`$name\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql --force --default-character-set=utf8mb4 "$name" < "$f" || log "WARN errors in $name (continue)"
  done
fi

map_copy() {
  local src="$1" dst="$2"
  [[ -d "$src" ]] || return 0
  mkdir -p "$dst"
  log "copy $src -> $dst"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a "$src"/ "$dst"/
  else
    cp -a "$src"/. "$dst"/
  fi
  chown -R www-data:www-data "$dst" || true
}

map_copy "$EXTRA_DIR/files/imports" "$APP_DIR/app/Backend/storage/imports"
map_copy "$EXTRA_DIR/files/tecdoc" "$APP_DIR/app/Backend/storage/tecdoc"
map_copy "$EXTRA_DIR/files/supplier_feeds" "$APP_DIR/app/Backend/storage/supplier_feeds"
map_copy "$EXTRA_DIR/files/ttc_image_library" "$APP_DIR/app/Backend/storage/ttc_image_library"
map_copy "$EXTRA_DIR/files/admin_imports" "$APP_DIR/admin/storage/imports"
map_copy "$EXTRA_DIR/files/admin_tecdoc" "$APP_DIR/admin/storage/tecdoc"
map_copy "$EXTRA_DIR/files/admin_supplier_feeds" "$APP_DIR/admin/storage/supplier_feeds"
map_copy "$EXTRA_DIR/files/admin_ttc_image_library" "$APP_DIR/admin/storage/ttc_image_library"
map_copy "$EXTRA_DIR/files/uploads" "$APP_DIR/app/Storage/uploads"
map_copy "$EXTRA_DIR/files/storage_scraper" "$APP_DIR/storage/scraper"

if [[ -f /root/besoiu-db-credentials.txt ]]; then
  set -a
  # shellcheck disable=SC1091
  source /root/besoiu-db-credentials.txt
  set +a
  if [[ -n "${DB_USER:-}" ]]; then
    for name in besoiu_tecdoc_base besoiu_tecdoc_y1998 besoiupieseimport; do
      mysql -e "GRANT ALL PRIVILEGES ON \`$name\`.* TO '${DB_USER}'@'localhost';" 2>/dev/null || true
    done
    mysql -e "FLUSH PRIVILEGES;" || true
  fi
fi

log "Done."
'@

$restorePath = Join-Path $dest "restore-extra.sh"
$restoreLf = $restoreSh -replace "`r`n", "`n" -replace "`r", "`n"
[IO.File]::WriteAllBytes($restorePath, [Text.UTF8Encoding]::new($false).GetBytes($restoreLf))
Write-Host "OK restore-extra.sh"

$readme = @"
Besoiu EXTRA migrate package
Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')

CONTENTS
- db/*.sql
- files/* (imports, tecdoc, feeds, images, uploads, scraper)
- restore-extra.sh

ON PROXMOX CT (besoiu / 192.168.1.50)
1) Copy this folder to CT as /root/migrate-extra
   Example from Proxmox host after mounting USB:
     pct push 101 /mnt/usb/besoiu-migrate-extra /root/migrate-extra

2) Resize disk if needed (CT is 40G; package can be large):
     pct resize 101 rootfs +40G

3) In CT console:
     chmod +x /root/migrate-extra/restore-extra.sh
     EXTRA_DIR=/root/migrate-extra bash /root/migrate-extra/restore-extra.sh
"@
Set-Content -Path (Join-Path $dest "README-EXTRA.txt") -Value $readme -Encoding UTF8

$lines = New-Object System.Collections.Generic.List[string]
Get-ChildItem $dbDir -File -ErrorAction SilentlyContinue | ForEach-Object {
    if ($_.Extension -eq ".sql") {
        [void]$lines.Add(("db/{0}`t{1:N1} MB" -f $_.Name, ($_.Length / 1MB)))
    }
}
Get-ChildItem $filesDir -Directory -ErrorAction SilentlyContinue | ForEach-Object {
    $sum = (Get-ChildItem $_.FullName -Recurse -File -ErrorAction SilentlyContinue | Measure-Object Length -Sum).Sum
    [void]$lines.Add(("files/{0}`t{1:N1} MB" -f $_.Name, ($sum / 1MB)))
}
$manifestBody = ($lines -join "`n")
Set-Content -Path (Join-Path $dest "MANIFEST-EXTRA.txt") -Value $manifestBody -Encoding UTF8
Write-Host ""
Write-Host "=== EXTRA ready: $dest ==="
Write-Host $manifestBody
$total = (Get-ChildItem $dest -Recurse -File -ErrorAction SilentlyContinue | Measure-Object Length -Sum).Sum
Write-Host ("Total_GB={0:N2}" -f ($total / 1GB))
