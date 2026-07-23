<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
define('BESOIU_ROOT', $root);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'supplier' => $argv[1] ?? 'autonet',
    'filename' => $argv[2] ?? 'Lista pret Autonet 27.01.2026.csv',
    'limit' => $argv[3] ?? '20',
    'offset' => '0',
    'scan_mode' => 'continue',
    'build_mode' => 'fast',
];

$t = microtime(true);
ob_start();
require dirname(__DIR__) . '/api/build-stored.php';
$out = ob_get_clean();
echo 'TIME=' . round(microtime(true) - $t, 2) . "s\n";
$data = json_decode($out, true);
if (!is_array($data)) {
    echo substr($out, 0, 800) . "\n";
    exit(1);
}
echo 'success=' . ($data['success'] ? '1' : '0') . ' count=' . ($data['count'] ?? 0) . "\n";
if (!empty($data['error'])) {
    echo 'error=' . $data['error'] . "\n";
}
