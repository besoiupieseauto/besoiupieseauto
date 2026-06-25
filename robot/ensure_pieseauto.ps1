# Porneste robot_pieseauto.py pentru acest proiect/canal (fara a opri alte instalari paralele).
$ErrorActionPreference = 'SilentlyContinue'
$robotDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$python = 'C:\laragon\bin\python\python-3.13\python.exe'
if (-not (Test-Path $python)) { $python = 'python' }

$port = 5011
$channel = 'besoiu'
$envFile = Join-Path (Split-Path $robotDir -Parent) 'admin\.env'
if (-not (Test-Path $envFile)) {
    $envFile = Join-Path (Split-Path $robotDir -Parent) '.env'
}
if (Test-Path $envFile) {
    $mPort = Select-String -Path $envFile -Pattern '^ROBOT_PIESEAUTO_PORT=(\d+)' | Select-Object -First 1
    if ($mPort) { $port = [int]$mPort.Matches[0].Groups[1].Value }
    $mCh = Select-String -Path $envFile -Pattern '^ROBOT_CHANNEL_ID=([^\r\n#]+)' | Select-Object -First 1
    if ($mCh) { $channel = ($mCh.Matches[0].Groups[1].Value.Trim()) }
}

$dataDir = Join-Path $robotDir 'data'
if (-not (Test-Path $dataDir)) { New-Item -ItemType Directory -Path $dataDir | Out-Null }
$listenerFile = Join-Path $dataDir ("listener_{0}.json" -f $channel)

function Test-RobotOnline([int]$testPort) {
    $pingUrl = "http://127.0.0.1:$testPort/verificare_sesiune"
    try {
        $headers = @{ 'X-Robot-Channel' = $channel }
        $r = Invoke-WebRequest -Uri $pingUrl -Headers $headers -TimeoutSec 3 -UseBasicParsing
        return ($r.StatusCode -eq 200 -and $r.Content -match 'online')
    } catch { return $false }
}

if (Test-Path $listenerFile) {
    try {
        $listener = Get-Content $listenerFile -Raw | ConvertFrom-Json
        if ($listener.channel -eq $channel -and $listener.port) {
            if (Test-RobotOnline ([int]$listener.port)) { exit 0 }
        }
    } catch { }
}

if (Test-RobotOnline $port) { exit 0 }

$robotDirNorm = $robotDir.TrimEnd('\')
Get-CimInstance Win32_Process -Filter "name='python.exe'" |
    Where-Object {
        $cl = $_.CommandLine
        $cl -and ($cl -like "*$robotDirNorm*robot_pieseauto.py*")
    } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

Start-Sleep -Seconds 2

$psi = New-Object System.Diagnostics.ProcessStartInfo
$psi.FileName = $python
$psi.Arguments = '-u robot_pieseauto.py'
$psi.WorkingDirectory = $robotDir
$psi.WindowStyle = [System.Diagnostics.ProcessWindowStyle]::Hidden
$psi.CreateNoWindow = $true
$psi.UseShellExecute = $false
[void]$psi.EnvironmentVariables.Add('ROBOT_PIESEAUTO_PORT', [string]$port)
[void]$psi.EnvironmentVariables.Add('ROBOT_CHANNEL_ID', [string]$channel)
[System.Diagnostics.Process]::Start($psi) | Out-Null

for ($i = 0; $i -lt 25; $i++) {
    Start-Sleep -Seconds 1
    if (Test-Path $listenerFile) {
        try {
            $listener = Get-Content $listenerFile -Raw | ConvertFrom-Json
            if ($listener.channel -eq $channel -and $listener.port -and (Test-RobotOnline ([int]$listener.port))) {
                exit 0
            }
        } catch { }
    }
    if (Test-RobotOnline $port) { exit 0 }
}
exit 1
