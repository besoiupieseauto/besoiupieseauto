<?php
declare(strict_types=1);

/**
 * Suite verificare instrument vitrină (matcher, CSV, config, RAG).
 * Usage: php tools/run_showcase_suite.php
 */
$root = dirname(__DIR__);
require_once $root . '/api/bootstrap.php';
require_once $root . '/api/lib/ImportShowcaseTypeMatcher.php';
require_once $root . '/api/lib/ImportShowcaseCsvReader.php';
require_once $root . '/api/lib/ImportShowcaseConfig.php';
require_once $root . '/api/lib/ImportShowcaseEpiesaRag.php';

$failed = 0;

function assert_true(bool $ok, string $label): void
{
    global $failed;
    echo ($ok ? 'OK  ' : 'FAIL') . ' | ' . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

// Matcher
$cases = [
    ['Set piese, schimb de ulei cutie ZF', 'ulei', false],
    ['Castrol Edge 5W30 5L', 'ulei', true],
    ['Antigel G12 5L', 'lichid', true],
    ['Baterie auto 74Ah VARTA', 'baterie', true],
];
foreach ($cases as [$name, $type, $expected]) {
    $m = ImportShowcaseTypeMatcher::matchKeyword(['title' => $name, 'name' => $name], [$type], 72, true);
    assert_true(($m !== null) === $expected, 'matcher ' . $type . ' | ' . $name);
}

// CSV delimiter
$semicolon = ImportShowcaseCsvReader::detectDelimiter('a;b;c;d');
$comma = ImportShowcaseCsvReader::detectDelimiter('a,b,c,d');
assert_true($semicolon === ';', 'delimiter semicolon');
assert_true($comma === ',', 'delimiter comma');

$feed = import_motor_canonical_feed_base_dir() . '/materom/Lista pret Materom 16.01.2026.csv';
if (is_file($feed)) {
    $opened = ImportShowcaseCsvReader::open($feed);
    assert_true($opened !== null && ($opened['delimiter'] ?? '') === ';', 'open materom CSV');
    if ($opened !== null) {
        fclose($opened['handle']);
    }
}

// Config
$config = ImportShowcaseConfig::load();
assert_true(is_array($config['types'] ?? null) && $config['types'] !== [], 'config types');
assert_true(is_array($config['scan_enabled'] ?? null), 'config scan_enabled');
assert_true(is_array($config['type_defs'] ?? null) && $config['type_defs'] !== [], 'config type_defs epiesa');

// RAG
$rag = ImportShowcaseEpiesaRag::stats();
assert_true(!empty($rag['available']) && (int) ($rag['count'] ?? 0) >= 500, 'RAG epiesa index');

$chunks = ImportShowcaseEpiesaRag::retrieveForProduct('Mobil 1 Esp 5W30 5L', ['ulei'], 3);
assert_true($chunks !== [], 'RAG retrieve ulei');

echo PHP_EOL . ($failed === 0 ? 'SUITE OK' : ('SUITE FAIL: ' . $failed)) . PHP_EOL;
exit($failed > 0 ? 1 : 0);
