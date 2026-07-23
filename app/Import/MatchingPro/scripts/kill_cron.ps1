# Oprește procesele Python ale modulului import/cron
Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
    Where-Object {
        $cl = $_.CommandLine
        $cl -and (
            $cl -match 'cron_watcher\.py' -or
            ($cl -match 'run_import\.py' -and $cl -match '\bcron\b')
        )
    } |
    ForEach-Object {
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
        $_.ProcessId
    }
