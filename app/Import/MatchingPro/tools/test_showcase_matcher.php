<?php
declare(strict_types=1);

/**
 * CLI: verifică regulile de matching vitrină (fără CSV / TecDoc).
 * Usage: php tools/test_showcase_matcher.php
 */
$root = dirname(__DIR__);
require_once $root . '/api/bootstrap.php';
require_once $root . '/api/lib/ImportShowcaseTypeMatcher.php';

/** @var list<array{0:string,1:string,2:bool}> $cases */
$cases = [
    ['Set piese, schimb de ulei cutie de viteze automata ZF', 'ulei', false],
    ['senzor, nivel ulei motor HELLA', 'ulei', false],
    ['Radiator ulei motor', 'ulei', false],
    ['Ulei motor 5W30 CASTROL EDGE 5L', 'ulei', true],
    ['CASTROL EDGE 5W30 C3 5L', 'ulei', true],
    ['Mobil 1 Esp 5W30 5L', 'ulei', true],
    ['POWER1 4T 10W40 1L - NOU', 'ulei', true],
    ['ULEI LONG TIME High Tec 5W30', 'ulei', true],
    ['Antigel G12++ 5L', 'lichid', true],
    ['Lichid frana DOT4 1L', 'lichid', true],
    ['Termostat lichid racire', 'lichid', false],
    ['Baterie auto 74Ah VARTA', 'baterie', true],
    ['Suport baterie', 'baterie', false],
    ['Set adeziv bara stabilizator', 'adeziv', false],
];

$failed = 0;
foreach ($cases as [$name, $type, $expected]) {
    $match = ImportShowcaseTypeMatcher::matchKeyword(['title' => $name, 'name' => $name], [$type], 72, true);
    $ok = ($match !== null) === $expected;
    if (!$ok) {
        $failed++;
    }
    echo ($ok ? 'OK  ' : 'FAIL') . ' | ' . $type . ' | ' . ($expected ? 'MATCH' : 'SKIP ') . ' | ' . $name . PHP_EOL;
}

exit($failed > 0 ? 1 : 0);
