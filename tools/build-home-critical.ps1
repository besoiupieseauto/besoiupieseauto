# Regenerează assets/css/home-critical.css (shell + layout + home-index)
# IMPORTANT: editează home-index.css (hero-promo, grid scraper etc.) — NU home-critical.css direct.
$cssDir = Join-Path $PSScriptRoot '..\assets\css'
$out = Join-Path $cssDir 'home-critical.css'
$parts = @('site-shell.css', 'site-layout.css', 'home-index.css')
$sb = New-Object System.Text.StringBuilder
[void]$sb.AppendLine('/* home-critical.css — generat; nu edita manual */')
foreach ($p in $parts) {
    [void]$sb.AppendLine("/* --- $p --- */")
    [void]$sb.Append((Get-Content (Join-Path $cssDir $p) -Raw -Encoding UTF8))
    [void]$sb.AppendLine()
}
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($out, $sb.ToString(), $utf8NoBom)
Write-Host "OK: home-critical.css ($([math]::Round((Get-Item $out).Length/1KB, 1)) KB)"
