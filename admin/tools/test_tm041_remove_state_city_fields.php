<?php
declare(strict_types=1);

/**
 * Smoke test tm_041 — Eliminare câmpuri Stare și Oraș din formularul produs
 */
require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);

$fail = static function (string $msg): void {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

$ok = static function (string $msg): void {
    echo "OK: {$msg}\n";
};

$addTpl = file_get_contents($admin . '/Templates/admin/pages/produse/addproduse.php');
$editTpl = file_get_contents($admin . '/Templates/admin/pages/produse/editproduse.php');

foreach ([['add', $addTpl], ['edit', $editTpl]] as [$label, $tpl]) {
    foreach (["row_start('Stare')", "row_start('Oras')", "select_field('pState'", "select_field('pCity'", 'name="pState"', 'name="pCity"'] as $needle) {
        if (str_contains($tpl, $needle)) {
            $fail("Formular {$label} inca contine: {$needle}");
        }
    }
    if (!str_contains($tpl, "row_start('Status produs'")) {
        $fail("Formular {$label} trebuie sa pastreze Status produs.");
    }
}
$ok('Formulare add/edit — fara Stare si Oras');

$cruduSrc = file_get_contents($admin . '/src/Controllers/Produse/crudu.php');
if (!preg_match("/'pState'\s*=>\s*'Nou'/", $cruduSrc)) {
    $fail('crudu.php trebuie sa seteze implicit pState=Nou la salvare.');
}
if (!preg_match("/'pCity'\s*=>\s*''/", $cruduSrc)) {
    $fail('crudu.php trebuie sa seteze implicit pCity gol la salvare.');
}
$ok('crudu.php — valori implicite pState=Nou, pCity=');

echo "ALL TESTS PASSED tm_041\n";
