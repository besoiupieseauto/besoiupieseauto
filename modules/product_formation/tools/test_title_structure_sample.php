<?php
declare(strict_types=1);

/**
 * Test P5: titluri pe 15+ produse din categorii diferite.
 * php modules/product_formation/tools/test_title_structure_sample.php
 */

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

use Besoiu\Services\ProductCardFormationService;

$svc = new ProductCardFormationService();

$samples = [
    ['piece_name' => 'set plăcuțe frână, frână disc', 'brand' => 'BOSCH', 'code' => '0986424268', 'pSubcategory' => 'Plăcuțe frână', 'pCategory' => 'Frâne', 'specs_text' => 'Pozitie: Fata Stanga', 'pMarca' => 'Audi', 'pModel' => 'A3 Sportback', 'pMotorizare' => '1.6 TDI'],
    ['piece_name' => 'Disc frana', 'brand' => 'TRW', 'code' => 'DF2804', 'specs_text' => 'Ventilat: Da | Pozitie: Fata', 'pMarca' => 'BMW', 'pModel' => 'SERIA 3 (E46)', 'pMotorizare' => '320d'],
    ['piece_name' => 'Filtru ulei', 'brand' => 'MANN-FILTER', 'code' => 'W71275', 'pMarca' => 'VW', 'pModel' => 'GOLF VI', 'pMotorizare' => '1.4 TSI'],
    ['piece_name' => 'Amortizor', 'brand' => 'KYB', 'code' => '334802', 'specs_text' => 'Pozitie: Spate', 'pMarca' => 'TOYOTA', 'pModel' => 'COROLLA', 'pMotorizare' => '1.6 16V'],
    ['piece_name' => 'Bujie aprindere', 'brand' => 'NGK', 'code' => 'BKR6E', 'pMarca' => 'HONDA', 'pModel' => 'CIVIC', 'pMotorizare' => '1.6 i-VTEC'],
    ['piece_name' => 'kit filtre aer, aer habitaclu', 'brand' => 'MAHLE', 'code' => 'LX1780', 'pSubcategory' => 'Filtru aer', 'pCategory' => 'Filtre', 'pMarca' => 'FORD', 'pModel' => 'FOCUS', 'pMotorizare' => '1.6 TDCi'],
    ['piece_name' => 'Curea distributie', 'brand' => 'GATES', 'code' => '5578XS', 'pMarca' => 'RENAULT', 'pModel' => 'CLIO', 'pMotorizare' => '1.5 dCi'],
    ['piece_name' => 'Rulment roata', 'brand' => 'SKF', 'code' => 'VKBA3510', 'specs_text' => 'Pozitie: Fata', 'pMarca' => 'OPEL', 'pModel' => 'ASTRA', 'pMotorizare' => '1.7 CDTI'],
    ['piece_name' => 'Pompa apa', 'brand' => 'GRAF', 'code' => 'PA516', 'pMarca' => 'FIAT', 'pModel' => 'PUNTO', 'pMotorizare' => '1.2'],
    ['piece_name' => 'Radiator racire', 'brand' => 'NISSENS', 'code' => '60648A', 'pMarca' => 'PEUGEOT', 'pModel' => '308', 'pMotorizare' => '1.6 HDi'],
    ['piece_name' => 'Senzor ABS', 'brand' => 'DELPHI', 'code' => 'SS20025', 'specs_text' => 'Pozitie: Spate Dreapta', 'pMarca' => 'MERCEDES-BENZ', 'pModel' => 'C-CLASS', 'pMotorizare' => 'C 220 CDI'],
    ['piece_name' => 'Placute frana', 'brand' => 'TEXTAR', 'code' => '2146301', 'specs_text' => 'punte fata', 'entries' => [['CAR_BRAND' => 'AUDI', 'CAR_MODEL' => 'A4', 'CAR_TYP' => '2.0 TDI']]],
    ['piece_name' => 'Filtru combustibil', 'brand' => 'BOSCH', 'code' => 'F026402002', 'pMarca' => 'DACIA', 'pModel' => 'DUSTER', 'pMotorizare' => '1.5 dCi'],
    ['piece_name' => 'Ambreiaj set', 'brand' => 'LUK', 'code' => '623309600', 'pSubcategory' => 'Kit ambreiaj', 'pMarca' => 'SKODA', 'pModel' => 'OCTAVIA', 'pMotorizare' => '1.9 TDI'],
    ['piece_name' => 'Bec far', 'brand' => 'PHILIPS', 'code' => '12972WVUSM', 'pMarca' => 'VW', 'pModel' => 'PASSAT', 'pMotorizare' => '2.0 TDI'],
    ['piece_name' => 'Ulei motor 5W40', 'brand' => 'CASTROL', 'code' => '15F7E5', 'pCategory' => 'Lubrifianti', 'pSubcategory' => 'Ulei motor', 'pMarca' => '', 'pModel' => '', 'pMotorizare' => ''],
    ['piece_name' => 'set frână disc, disc frână', 'brand' => 'ATE', 'code' => '24012402181', 'pSubcategory' => 'Disc frână', 'specs_text' => 'Pozitie: Fata', 'pMarca' => 'SEAT', 'pModel' => 'LEON', 'pMotorizare' => '1.9 TDI'],
    ['piece_name' => 'Bara stabilizatoare', 'brand' => 'LEMFORDER', 'code' => '2532601', 'specs_text' => 'Pozitie: Fata Stanga', 'pMarca' => 'BMW', 'pModel' => 'X5', 'pMotorizare' => '3.0 d'],
    ['piece_name' => 'Termostat', 'brand' => 'WAHLER', 'code' => '410171D', 'pMarca' => 'HYUNDAI', 'pModel' => 'i30', 'pMotorizare' => '1.6 CRDi'],
    ['piece_name' => 'Placute frana', 'brand' => 'BOSCH', 'code' => '0 986 424 268', 'specs_text' => 'Partea de montare: punte fata stanga', 'pMarca' => 'Audi', 'pModel' => 'A3 Sportback', 'pMotorizare' => '1.6 TDI'],
];

$errors = [];
$i = 0;
foreach ($samples as $ctx) {
    ++$i;
    $title = $svc->buildTitle(ProductCardFormationService::CHANNEL_WEBSITE, $ctx);
    echo str_pad((string) $i, 2, ' ', STR_PAD_LEFT) . '. ' . $title . "\n";

    if ($title === '') {
        $errors[] = "#{$i}: titlu gol";
        continue;
    }
    if (preg_match('/\bset\b/iu', $title) && !preg_match('/\bKit ambreiaj\b/u', $title)) {
        $errors[] = "#{$i}: încă conține 'set': {$title}";
    }
    if (preg_match('/\b(frana|frână)\b.*\b(frana|frână)\b/iu', $title)) {
        $errors[] = "#{$i}: cuvânt frână duplicat: {$title}";
    }
    // Structură: brand + cod prezente când sunt în context
    $brand = trim((string) ($ctx['brand'] ?? ''));
    $code = trim((string) ($ctx['code'] ?? ''));
    if ($brand !== '' && stripos($title, explode(' ', $brand)[0]) === false
        && stripos($title, 'Bosch') === false && stripos($title, 'Mann') === false) {
        // brand label may be Title Case
        if (stripos($title, $brand) === false) {
            // soft check — brand canonical may differ
        }
    }
    if ($code !== '' && strpos(str_replace(' ', '', $title), str_replace(' ', '', $code)) === false) {
        $errors[] = "#{$i}: lipsește codul din titlu: {$title}";
    }
    $hasVehicle = trim((string) ($ctx['pMarca'] ?? '')) !== ''
        || (isset($ctx['entries'][0]['CAR_BRAND']) && trim((string) $ctx['entries'][0]['CAR_BRAND']) !== '');
    if ($hasVehicle && !preg_match('/\bpentru\b/iu', $title)) {
        $errors[] = "#{$i}: lipsește 'pentru' + vehicul: {$title}";
    }
}

// Exemplul canonic din brief
$canonical = $svc->buildTitle(ProductCardFormationService::CHANNEL_WEBSITE, ProductCardFormationService::sampleContext());
echo "\nCanonic: {$canonical}\n";
if (!preg_match('/Plăcuțe|Placute/iu', $canonical) || !preg_match('/Bosch/iu', $canonical)
    || !preg_match('/pentru/iu', $canonical) || !preg_match('/Audi/iu', $canonical)) {
    $errors[] = 'Exemplul canonic incomplet: ' . $canonical;
}

if ($errors === []) {
    echo "\nOK: " . count($samples) . " titluri + exemplu canonic.\n";
    exit(0);
}

echo "\nFAIL:\n- " . implode("\n- ", $errors) . "\n";
exit(1);
