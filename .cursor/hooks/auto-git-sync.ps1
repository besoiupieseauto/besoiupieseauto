# Auto commit + push after agent stop (besoiupieseauto.ro)
$ErrorActionPreference = "Continue"
$git = "C:\laragon\bin\git\bin\git.exe"
if (-not (Test-Path $git)) { $git = "git" }

# Hook cwd is project root
$root = (Get-Location).Path
Set-Location $root

# Drain stdin JSON from Cursor hook (required)
try { [void][Console]::In.ReadToEnd() } catch {}

function Out-HookOk {
  Write-Output "{}"
  exit 0
}

$status = & $git status --porcelain 2>$null
if (-not $status) { Out-HookOk }

# Skip if only runtime junk changed
$lines = $status -split "`n" | Where-Object { $_.Trim() -ne "" }
$meaningful = $lines | Where-Object {
  $_ -notmatch 'storage/rate_limits/' -and
  $_ -notmatch 'Storage/ai_intel/' -and
  $_ -notmatch 'stealth-browser-mcp' -and
  $_ -notmatch '\.sqlite' -and
  $_ -notmatch '\.log$'
}
if (-not $meaningful) { Out-HookOk }

$branch = (& $git rev-parse --abbrev-ref HEAD 2>$null)
if ($branch -ne "upload/snapshot-20260723") {
  & $git checkout "upload/snapshot-20260723" 2>$null
  if ($LASTEXITCODE -ne 0) {
    & $git checkout -b "upload/snapshot-20260723" 2>$null
  }
}

& $git add -A 2>$null
$staged = & $git diff --cached --name-only 2>$null
if (-not $staged) { Out-HookOk }

$stamp = Get-Date -Format "yyyy-MM-dd HH:mm"
$msg = "sync: actualizare automata $stamp"
& $git commit -m $msg 2>$null
if ($LASTEXITCODE -ne 0) { Out-HookOk }

& $git push -u origin HEAD 2>$null
Out-HookOk
