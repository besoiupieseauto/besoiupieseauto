param(
    [Parameter(Mandatory = $true)][string]$XlsxPath
)

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression.FileSystem

function Get-SharedStrings([System.IO.Compression.ZipArchive]$zip) {
    $entry = $zip.GetEntry('xl/sharedStrings.xml')
    if (-not $entry) { return @() }
    $reader = New-Object System.IO.StreamReader($entry.Open())
    $xml = $reader.ReadToEnd()
    $reader.Close()
    $shared = @()
    [regex]::Matches($xml, '<t[^>]*>([^<]*)') | ForEach-Object {
        $shared += $_.Groups[1].Value
    }
    return $shared
}

function Get-CellValue([string]$attrs, [string]$raw, [string[]]$shared) {
    if ($attrs -match 't="s"' -and $raw -ne '' -and [int]$raw -lt $shared.Count) {
        return $shared[[int]$raw]
    }
    return $raw
}

if (-not (Test-Path -LiteralPath $XlsxPath)) {
    Write-Output '[]'
    exit 0
}

$zip = [System.IO.Compression.ZipFile]::OpenRead($XlsxPath)
try {
    $shared = Get-SharedStrings $zip
    $sheetEntry = $zip.GetEntry('xl/worksheets/sheet1.xml')
    if (-not $sheetEntry) {
        Write-Output '[]'
        exit 0
    }
    $sr = New-Object System.IO.StreamReader($sheetEntry.Open())
    $sheet = $sr.ReadToEnd()
    $sr.Close()

    $grid = @{}
    [regex]::Matches($sheet, '<c r="([A-Z]+)(\d+)"([^>]*)>(?:<v>([^<]*)</v>)?') | ForEach-Object {
        $col = $_.Groups[1].Value
        if ($col -notin @('A', 'B', 'C')) { return }
        $rowNum = [int]$_.Groups[2].Value
        $attrs = $_.Groups[3].Value
        $raw = $_.Groups[4].Value
        $val = Get-CellValue $attrs $raw $shared
        if (-not $grid.ContainsKey($rowNum)) {
            $grid[$rowNum] = @{}
        }
        $grid[$rowNum][$col] = $val.Trim()
    }

    if ($grid.Count -eq 0) {
        Write-Output '[]'
        exit 0
    }

    $headerRow = ($grid.Keys | Sort-Object | Select-Object -First 1)
    $headerCols = $grid[$headerRow]
    $headers = [ordered]@{
        a = if ($headerCols.ContainsKey('A') -and $headerCols['A']) { $headerCols['A'] } else { 'Coloana A' }
        b = if ($headerCols.ContainsKey('B') -and $headerCols['B']) { $headerCols['B'] } else { 'Coloana B' }
        c = if ($headerCols.ContainsKey('C') -and $headerCols['C']) { $headerCols['C'] } else { 'Coloana C' }
    }

    $rows = New-Object System.Collections.Generic.List[object]
    foreach ($rowNum in ($grid.Keys | Sort-Object)) {
        if ($rowNum -eq $headerRow) { continue }
        $cols = $grid[$rowNum]
        $rows.Add([ordered]@{
            row = $rowNum
            a   = if ($cols.ContainsKey('A')) { $cols['A'] } else { '' }
            b   = if ($cols.ContainsKey('B')) { $cols['B'] } else { '' }
            c   = if ($cols.ContainsKey('C')) { $cols['C'] } else { '' }
        }) | Out-Null
    }

    [ordered]@{
        headers = $headers
        rows    = $rows
    } | ConvertTo-Json -Compress -Depth 5
}
finally {
    $zip.Dispose()
}
