<?php
declare(strict_types=1);

/**
 * Instalează dependențele stealth-browser-mcp în venv-ul local.
 *
 *   php admin/tools/setup_stealth_browser.php
 */
$root = dirname(__DIR__, 2);
$venvPy = $root . '/tools/stealth-browser-mcp/venv/Scripts/python.exe';
$venvPip = $root . '/tools/stealth-browser-mcp/venv/Scripts/pip.exe';
$mcpSrc = $root . '/tools/stealth-browser-mcp/src';

if (!is_file($venvPy)) {
    fwrite(STDERR, "Lipsește venv. Rulează:\n");
    fwrite(STDERR, "  py -3.12 -m venv tools/stealth-browser-mcp/venv\n");
    fwrite(STDERR, "  (recomandat Python 3.11–3.12; 3.14 necesită wheel-uri pydantic recente)\n");
    exit(1);
}

$steps = [
    escapeshellarg($venvPip) . ' install psutil python-dotenv nodriver==0.47.0',
    escapeshellarg($venvPip) . ' install "pydantic>=2.11" --only-binary=:all:',
];

foreach ($steps as $i => $cmd) {
    echo '=== pas ' . ($i + 1) . " ===\n";
    passthru($cmd . ' 2>&1', $code);
    if ($code !== 0) {
        fwrite(STDERR, "\nEșec instalare. Pe Python 3.14 fără wheel pydantic: instalează Python 3.12 și recreează venv.\n");
        exit($code);
    }
}

$test = 'cd /d ' . escapeshellarg($mcpSrc) . ' && '
    . escapeshellarg($venvPy) . ' -c "from browser_manager import BrowserManager; print(\'OK BrowserManager\')" 2>&1';
echo "\n=== test BrowserManager ===\n";
passthru($test, $code2);
exit($code2 === 0 ? 0 : 1);
