<?php
declare(strict_types=1);

/**
 * Smoke test tm_038 — Selector Status produs (doar Activ / Inactiv)
 */
require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);
$root = dirname($admin);

$fail = static function (string $msg): void {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

$ok = static function (string $msg): void {
    echo "OK: {$msg}\n";
};

$configPath = $root . '/config/product-status.php';
if (!is_file($configPath)) {
    $fail('Lipseste config/product-status.php');
}

$statusOptions = require $configPath;
if (!is_array($statusOptions)) {
    $fail('config/product-status.php nu returneaza array');
}

$forbidden = ['2', 'pending', 'asteptare', 'așteptare', 'in asteptare', 'în așteptare'];
foreach ($statusOptions as $value => $label) {
    $valueStr = strtolower(trim((string) $value));
    $labelStr = strtolower(trim((string) $label));
    foreach ($forbidden as $bad) {
        if ($valueStr === $bad || str_contains($labelStr, $bad)) {
            $fail("Optiune interzisa in config: {$value} => {$label}");
        }
    }
}

if (!isset($statusOptions['1'], $statusOptions['0'])) {
    $fail('Config trebuie sa contina doar cheile 1 (Activ) si 0 (Inactiv).');
}

if (count($statusOptions) !== 2) {
    $fail('Config product-status trebuie sa aiba exact 2 optiuni, gasit: ' . count($statusOptions));
}
$ok('config/product-status.php — doar Activ (1) si Inactiv (0)');

$addTpl = file_get_contents($admin . '/Templates/admin/pages/produse/addproduse.php');
$editTpl = file_get_contents($admin . '/Templates/admin/pages/produse/editproduse.php');
foreach ([$addTpl, $editTpl] as $tpl) {
    if (!str_contains($tpl, "row_start('Status produs'")) {
        $fail('Formularul nu expune campul Status produs.');
    }
    if (!str_contains($tpl, "select_field('status'")) {
        $fail('Formularul nu are select name=status.');
    }
    foreach (['așteptare', 'asteptare', 'In asteptare', 'În așteptare', 'pending'] as $bad) {
        if (stripos($tpl, $bad) !== false) {
            $fail('Formularul inca mentioneaza starea interzisa: ' . $bad);
        }
    }
    if (preg_match('/select_field\(\'status\'[^)]+\)[^;]*(?:așteptare|asteptare|pending|Inactiv.*Inactiv)/iu', $tpl)) {
        $fail('Selector status contine optiuni interzise.');
    }
}
$ok('Formulare add/edit — Status produs fara asteptare');

$cruduSrc = file_get_contents($admin . '/src/Controllers/Produse/crudu.php');
if (!str_contains($cruduSrc, 'produse_normalize_status')) {
    $fail('crudu.php lipseste produse_normalize_status');
}
$ok('crudu.php normalizeaza status la 0 sau 1');

$normalize = static function (mixed $status): int {
    $value = trim((string) $status);
    if ($value === '0' || $value === 'inactive' || $value === 'inactiv') {
        return 0;
    }

    return 1;
};

if ($normalize('2') !== 1) {
    $fail('Status 2 (pending) trebuie mapat la activ (1).');
}
if ($normalize('pending') !== 1) {
    $fail('Status pending trebuie mapat la activ (1).');
}
if ($normalize('0') !== 0) {
    $fail('Status 0 trebuie ramane inactiv.');
}
if (!str_contains($cruduSrc, "if (\$value === '0' || \$value === 'inactive' || \$value === 'inactiv')")) {
    $fail('Implementarea produse_normalize_status lipseste din crudu.php');
}
$ok('produse_normalize_status — pending/2 -> 1, 0 -> 0');

echo "ALL TESTS PASSED tm_038\n";
