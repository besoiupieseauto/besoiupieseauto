<?php
declare(strict_types=1);

/**
 * Smoke test tm_074 — Eliminare câmpuri Garanție, Retur și Stare din formularul produs
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
    foreach ([
        "row_start('Garantie')",
        "row_start('Retur')",
        "row_start('Stare')",
        "select_field('pWarranty'",
        "select_field('pReturn'",
        "select_field('pState'",
        'warrantyOptions',
        'returnOptions',
    ] as $needle) {
        if (str_contains($tpl, $needle)) {
            $fail("Formular {$label} inca contine: {$needle}");
        }
    }
    if (!str_contains($tpl, "row_start('Status produs'")) {
        $fail("Formular {$label} trebuie sa pastreze Status produs.");
    }
}
$ok('Formulare add/edit — fara Garantie, Retur si Stare');

$cruduSrc = file_get_contents($admin . '/src/Controllers/Produse/crudu.php');
if (!preg_match("/'pState'\s*=>\s*'Nou'/", $cruduSrc)) {
    $fail('crudu.php trebuie sa seteze implicit pState=Nou la salvare.');
}
if (!preg_match("/'pWarranty'\s*=>\s*'2 ani'/", $cruduSrc)) {
    $fail('crudu.php trebuie sa seteze implicit pWarranty=2 ani la salvare.');
}
if (!preg_match("/'pReturn'\s*=>\s*'14 zile'/", $cruduSrc)) {
    $fail('crudu.php trebuie sa seteze implicit pReturn=14 zile la salvare.');
}
if (preg_match("/'pWarranty'\s*=>\s*\\\$input\['pWarranty'\]/", $cruduSrc)) {
    $fail('crudu.php nu trebuie sa citeasca pWarranty din input.');
}
if (preg_match("/'pReturn'\s*=>\s*\\\$input\['pReturn'\]/", $cruduSrc)) {
    $fail('crudu.php nu trebuie sa citeasca pReturn din input.');
}
$ok('crudu.php — valori implicite pState=Nou, pWarranty=2 ani, pReturn=14 zile');

echo "ALL TESTS PASSED tm_074\n";
