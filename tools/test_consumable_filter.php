<?php
declare(strict_types=1);

require dirname(__DIR__) . '/admin/src/Controllers/Produse/import_consumable_scan_lib.php';

function test_row(string $name, bool $expectMatch): void
{
    $p = ['pName' => $name, 'pSubcategory' => '', 'pCategory' => ''];
    $cats = import_consumable_detect_categories($p);
    $ok = $expectMatch ? $cats !== [] : $cats === [];
    echo ($ok ? 'OK' : 'FAIL') . ' | ' . $name . ' => ' . json_encode($cats) . PHP_EOL;
}

$reject = [
    'Lampa Depo 7731908LUE',
    'Alternator Bosch 0986037410',
    'Releu Gp Bosch 0332209152',
    '2XW003146001 Lampa Avarie HELLA',
    'Motor Ventilator Bosch 0130111130',
    'Senzor ABS Wabco 4410328080',
    'Filtru Ulei Bosch 0451103227',
    'Sabot Frana Textar 91045901',
    'Comutator Lumini Frana Febi FE10419',
    'Bujie Incandescenta Iskra 11721577',
    'GH846 Bujie Incandescenta Borgwarner (Beru) GH846',
    'Bujie Scanteie Bosch 0242235666',
    'Papuc Fisa Bujie Beru By Driv VES0116',
    'Supapa Siguranta Wabco 4346120040',
    'Conducta Apa Antigel Sachs 1142390001',
    'Sortimente Becuri Bosch 1 987 301 113',
];

$accept = [
    'Ulei motor Castrol GTX 5W30 4L',
    'Antigel concentrat G12 5L',
    'Lichid de frana DOT4 1L',
    'Bec H7 Osram 64210',
    'Baterie auto 74Ah 680A',
    'Set sigurante auto 10 buc',
];

echo "=== REJECT ===\n";
foreach ($reject as $n) {
    test_row($n, false);
}
echo "\n=== ACCEPT ===\n";
foreach ($accept as $n) {
    test_row($n, true);
}
