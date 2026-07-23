<?php
declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../api/lib/ImportTecdocMysqlCardBuilder.php';

import_motor_boot_furnizori_libs();
import_require_prelucrare_lib('CoreDbLookup.php');
import_require_prelucrare_lib('BaseIndexLookup.php');

$tests = [
    ['brand' => 'INA', 'code' => '558009210', 'sku' => '558 0092 10'],
    ['brand' => 'FAG', 'code' => '821085010', 'sku' => '821 0850 10'],
];

foreach ($tests as $t) {
    echo "=== {$t['brand']} / {$t['sku']} ===\n";
    $entries = BaseIndexLookup::findEntries($t['brand'], [$t['code'], $t['sku']]);
    if ($entries === null) {
        echo "  NEGĂSIT în MySQL\n\n";
        continue;
    }
    $card = ImportTecdocMysqlCardBuilder::fromEntries(
        $entries,
        $t['brand'],
        (string) ($entries[0]['ART_CODE_1'] ?? $t['sku']),
        316.08,
        'ELIT',
        ['status' => 'exact', 'match_method' => 'tecdoc_brand_code', 'sku_supplier' => $t['sku']]
    );
    if ($card === null) {
        echo "  Card NULL\n\n";
        continue;
    }
    echo '  Titlu: ' . ($card['title'] ?? '—') . "\n";
    echo '  Parametri: ' . count($card['parameters'] ?? []) . "\n";
    echo '  Compat: ' . (int) ($card['compatCount'] ?? 0) . "\n";
    echo '  Descriere: ' . mb_strlen((string) ($card['description'] ?? '')) . " chars\n";
    echo '  Imagine: ' . (!empty($card['hasImage']) ? 'DA' : 'NU') . "\n";
    echo '  cardBuild: ' . ($card['cardBuild'] ?? '—') . "\n\n";
}
