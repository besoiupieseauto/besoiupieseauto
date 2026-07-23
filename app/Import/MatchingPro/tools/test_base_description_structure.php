<?php
declare(strict_types=1);

/**
 * Test P1: descrierea generată respectă skeleton-ul Base.html pe 5 produse fictive.
 * Rulează: php app/Import/MatchingPro/tools/test_base_description_structure.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/app/Backend/src/Controllers/Produse/import_base_lib.php';
require_once $root . '/app/Import/MatchingPro/api/lib/ImportTecdocMysqlCardBuilder.php';

/**
 * @return list<string>
 */
function assert_base_skeleton(string $html, string $label): array
{
    $errors = [];
    if (!preg_match('/<p><b>.+?<\/b>\s*\(Cod:\s*.+?\)<\/p>/u', $html)) {
        $errors[] = "$label: lipsește header <p><b>…</b> (Cod: …)</p>";
    }
    if (str_contains($html, 'tecdoc-desc-sheet') || str_contains($html, '<dt>') || str_contains($html, '<dd>')) {
        $errors[] = "$label: conține format dl/dt vechi";
    }
    if (str_contains($html, 'Specificații tehnice (TecDoc)') || str_contains($html, 'Compatibil cu:</b>')) {
        $errors[] = "$label: etichete non-Base (TecDoc)/(Compatibil cu:)";
    }
    if (str_contains($html, 'Specificatii tehnice:') && !str_contains($html, '<p><b>Specificatii tehnice:</b></p><ul>')) {
        $errors[] = "$label: Specificatii fără <p><b>…</b></p><ul>";
    }
    if (str_contains($html, 'Compatibil cu urmatoarele modele auto:')
        && !str_contains($html, '<p><b>Compatibil cu urmatoarele modele auto:</b></p><ul>')) {
        $errors[] = "$label: Compatibil fără skeleton corect";
    }
    if (str_contains($html, 'Coduri OE echivalente:')
        && !str_contains($html, '<p><b>Coduri OE echivalente:</b></p><ul>')) {
        $errors[] = "$label: OEM fără skeleton corect";
    }
    // Dubluri pe aceeași cheie în specificații
    if (preg_match_all('/<li>([^<:]+):/u', $html, $m)) {
        $keys = array_map(static fn($k) => mb_strtolower(trim($k), 'UTF-8'), $m[1]);
        $dup = array_diff_assoc($keys, array_unique($keys));
        if ($dup !== []) {
            $errors[] = "$label: specificații duplicate: " . implode(', ', array_unique($dup));
        }
    }

    return $errors;
}

$samples = [
    [
        [
            'ART_NAME' => 'Placute frana',
            'ART_BRAND' => 'BOSCH',
            'ART_CODE_1' => '0986424268',
            'PARTS_INFO' => 'Grosime: 17.5 mm | Grosime: 17.5 mm | Pozitie: Fata | Diametru: 64 mm',
            'ART_CROSS' => "AUDI: 8E0698151\nVW: 1K0698151",
            'CAR_BRAND' => 'AUDI',
            'CAR_MODEL' => 'A3 Sportback',
            'CAR_TYP' => '1.6 TDI',
            'CAR_OF_YEAR' => '2008',
            'CAR_TO_YEAR' => '2013',
            'CAR_KW' => '77',
        ],
    ],
    [
        [
            'ART_NAME' => 'Filtru ulei',
            'ART_BRAND' => 'MANN-FILTER',
            'ART_CODE_1' => 'W71275',
            'PARTS_INFO' => 'Inaltime: 80 mm | Diametru: 76 mm',
            'ART_CROSS' => 'OEM: 03C115561',
            'CAR_BRAND' => 'VW',
            'CAR_MODEL' => 'GOLF VI',
            'CAR_TYP' => '1.4 TSI',
            'CAR_OF_YEAR' => '2009',
            'CAR_TO_YEAR' => '2013',
            'CAR_KW' => '90',
        ],
        [
            'ART_NAME' => 'Filtru ulei',
            'ART_BRAND' => 'MANN-FILTER',
            'ART_CODE_1' => 'W71275',
            'PARTS_INFO' => 'Inaltime: 80 mm | Diametru: 76 mm',
            'ART_CROSS' => 'OEM: 03C115561',
            'CAR_BRAND' => 'VW',
            'CAR_MODEL' => 'GOLF VI',
            'CAR_TYP' => '1.6 TDI',
            'CAR_OF_YEAR' => '2009',
            'CAR_TO_YEAR' => '2013',
            'CAR_KW' => '77',
        ],
    ],
    [
        [
            'ART_NAME' => 'Amortizor',
            'ART_BRAND' => 'KYB',
            'ART_CODE_1' => '334802',
            'PARTS_INFO' => 'Pozitie: Spate | Tip: gaz',
            'ART_CROSS' => '',
            'CAR_BRAND' => 'TOYOTA',
            'CAR_MODEL' => 'COROLLA',
            'CAR_TYP' => '1.6 16V',
            'CAR_OF_YEAR' => '2002',
            'CAR_TO_YEAR' => '2007',
            'CAR_KW' => '81',
        ],
    ],
    [
        [
            'ART_NAME' => 'Disc frana',
            'ART_BRAND' => 'TRW',
            'ART_CODE_1' => 'DF2804',
            'PARTS_INFO' => 'Diametru: 280 mm | Grosime: 22 mm | Ventilat: Da',
            'ART_CROSS' => "BMW: 34116761251\nBMW: 34116761251",
            'CAR_BRAND' => 'BMW',
            'CAR_MODEL' => 'SERIA 3 (E46)',
            'CAR_TYP' => '320d',
            'CAR_OF_YEAR' => '2001',
            'CAR_TO_YEAR' => '2005',
            'CAR_KW' => '110',
        ],
    ],
    [
        [
            'ART_NAME' => 'Bujie aprindere',
            'ART_BRAND' => 'NGK',
            'ART_CODE_1' => 'BKR6E',
            'PARTS_INFO' => 'Filet: 14 mm',
            'ART_CROSS' => 'OEM: 90919-01164',
            'CAR_BRAND' => 'HONDA',
            'CAR_MODEL' => 'CIVIC',
            'CAR_TYP' => '1.6 i-VTEC',
            'CAR_OF_YEAR' => '2006',
            'CAR_TO_YEAR' => '2011',
            'CAR_KW' => '92',
        ],
    ],
];

$allErrors = [];
$i = 0;
foreach ($samples as $entries) {
    $i++;
    $viaBase = import_base_generate_description($entries);
    $card = ImportTecdocMysqlCardBuilder::fromEntries(
        $entries,
        (string) ($entries[0]['ART_BRAND'] ?? ''),
        (string) ($entries[0]['ART_CODE_1'] ?? ''),
        10.0,
        'autototal'
    );
    $viaCard = (string) ($card['description'] ?? '');

    echo "===== Produs #$i =====\n";
    echo "Base lib length: " . strlen($viaBase) . " | Card builder length: " . strlen($viaCard) . "\n";
    echo substr($viaBase, 0, 280) . (strlen($viaBase) > 280 ? "…\n" : "\n");

    $allErrors = array_merge($allErrors, assert_base_skeleton($viaBase, "base#$i"));
    $allErrors = array_merge($allErrors, assert_base_skeleton($viaCard, "card#$i"));

    // Structură identică (card trebuie să folosească aceeași funcție)
    if ($viaBase !== '' && $viaCard !== '' && $viaBase !== $viaCard) {
        // Permitem diferențe minore doar dacă filtrarea Base exclude toate entries
        $allErrors[] = "produs#$i: card description ≠ import_base_generate_description";
    }
}

if ($allErrors === []) {
    echo "\nOK: 5/5 produse respectă skeleton-ul Base.html, fără duplicate de specificații.\n";
    exit(0);
}

echo "\nFAIL:\n- " . implode("\n- ", $allErrors) . "\n";
exit(1);
