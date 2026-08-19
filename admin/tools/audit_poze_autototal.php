<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '2048M');

$project = dirname(__DIR__, 2);
$report = 'C:/Users/Radu/Desktop/autoparner/RAPORT_POZE_AUTOTAL.txt';
$pozeCsv = 'C:/Users/Radu/Downloads/image_brand_codes_by_image_20260817_120909.csv';
$atCsv = 'C:/Users/Radu/Downloads/AUTOTOTAL 14,08,2026 - cu link poze.csv';
$pozeRoot = 'C:/laragon/www/besoiupieseimport';
$pozeRen = 'C:/laragon/www/besoiupieseimport/Poze_RENUMITE';
$atRen = 'C:/laragon/www/besoiupieseimport/Autotal';

function clean_tecdoc(string $c): string
{
    return strtoupper(str_replace([' ', '-', '.', '/'], '', trim($c)));
}

function clean_photo(string $c): string
{
    return strtoupper(str_replace([' ', '-', '.', '/', '_'], '', trim($c)));
}

function brand_token(string $b): string
{
    $b = strtoupper(trim($b));
    $b = str_replace([' ', '-', '.', '/', '_', ':'], '', $b);

    return preg_replace('/[^A-Z0-9]/', '', $b) ?? '';
}

function load_brand_map(string $project): array
{
    $map = [
        'MAX' => 'MAXGEAR', 'OPT' => 'OPTIMAL', 'BOS' => 'BOSCH', 'FEB' => 'FEBIBILSTEIN',
        'QBK' => 'QUICKBRAKE', 'MEY' => 'MEYLE', 'MAG' => 'MAGNETIMARELLI', 'VAL' => 'VALEO',
        'DAY' => 'DAYCO', 'SAC' => 'SACHS', 'MAN' => 'MANNFILTER', 'REI' => 'VICTORREINZ',
        'CON' => 'CONTINENTALCTAM', 'LMI' => 'LEMFORDER', 'BLP' => 'BLUEPRINT', 'JPA' => 'JPGROUP',
    ];
    $aliasFile = $project . '/app/Import/MatchingPro/config/tecdoc_brand_aliases.json';
    if (is_file($aliasFile)) {
        $json = json_decode((string) file_get_contents($aliasFile), true);
        foreach (($json['aliases'] ?? []) as $from => $to) {
            $fk = brand_token((string) $from);
            $tk = brand_token((string) $to);
            if ($fk !== '' && $tk !== '' && !isset($map[$fk])) {
                $map[$fk] = $tk;
            }
        }
    }

    return $map;
}

function expand_brand(string $raw, array $map): string
{
    $tok = brand_token($raw);

    return $tok === '' ? '' : ($map[$tok] ?? $tok);
}

function file_ok(string $p): bool
{
    return is_file($p) && filesize($p) >= 512;
}

$brandMap = load_brand_map($project);
$td = [];
try {
    $tp = new PDO('mysql:host=127.0.0.1;dbname=besoiu_tecdoc_base;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $q = $tp->query('SELECT code_norm FROM product_codes');
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        $td[strtoupper((string) $r['code_norm'])] = true;
    }
} catch (PDOException) {
}

$lines = [];
$lines[] = 'RAPORT VERIFICARE imagine_poze + imagine_autototal';
$lines[] = 'Algoritm comun: curatare cod spatiu - . / (+ _ la poze). Brand din sursa, extins. Nume BRAND-COD.';
$lines[] = 'Data: ' . date('c');
$lines[] = '';

echo "=== POZE / TTC ===\n";
$pdo = new PDO('mysql:host=127.0.0.1;dbname=imagine_poze;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$p = [
    'csv_rows' => 0,
    'csv_valid' => 0,
    'orig_missing' => 0,
    'ren_missing' => 0,
    'code_mismatch' => 0,
    'brand_mismatch' => 0,
    'name_mismatch' => 0,
    'db_orphan' => 0,
    'td_hit' => 0,
    'td_miss' => 0,
    'empty_brand' => 0,
    'empty_code' => 0,
    'overstrip' => 0,
];
$exCode = [];
$exBrand = [];
$exName = [];
$exMiss = [];

$dbImgs = [];
$q = $pdo->query('SELECT id, brand, code_norm, code_raw, disk_name, rel_path, original_path FROM images');
while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    $dbImgs[$r['original_path']][] = $r;
}

$in = fopen($pozeCsv, 'rb');
$header = fgetcsv($in, 0, ';');
$col = array_flip($header ?: []);
$iPath = $col['file_path'] ?? 2;
$iBrand = $col['owner_brand'] ?? 8;
$iCode = $col['owner_code'] ?? 9;
$seenCsv = [];
while (($row = fgetcsv($in, 0, ';')) !== false) {
    $p['csv_rows']++;
    $rel = str_replace('\\', '/', trim((string) ($row[$iPath] ?? '')));
    $brandRaw = (string) ($row[$iBrand] ?? '');
    $codeRaw = trim((string) ($row[$iCode] ?? ''));
    $brand = expand_brand($brandRaw, $brandMap);
    $codeAlgo = clean_photo($codeRaw);
    $codeStrict = clean_tecdoc($codeRaw);
    if ($rel === '' || $brand === '' || $codeAlgo === '') {
        continue;
    }
    $p['csv_valid']++;
    $seenCsv[$rel] = true;
    if ($codeAlgo !== $codeStrict && preg_replace('/[^A-Z0-9]/', '', $codeStrict) === $codeAlgo) {
        $p['overstrip']++;
    }
    $abs = $pozeRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!file_ok($abs)) {
        $p['orig_missing']++;
        if (count($exMiss) < 8) {
            $exMiss[] = $rel;
        }
    }
    $rows = $dbImgs[$rel] ?? [];
    if ($rows === []) {
        $p['db_orphan']++;
        continue;
    }
    foreach ($rows as $im) {
        if (clean_photo((string) $im['code_raw']) !== $codeAlgo && $im['code_norm'] !== $codeAlgo) {
            $p['code_mismatch']++;
            if (count($exCode) < 8) {
                $exCode[] = $rel . ' csv=' . $codeRaw . ' db=' . $im['code_norm'];
            }
        }
        if ($im['brand'] !== $brand) {
            $p['brand_mismatch']++;
            if (count($exBrand) < 8) {
                $exBrand[] = $rel . ' csv=' . $brandRaw . '=>' . $brand . ' db=' . $im['brand'];
            }
        }
        $expectPrefix = $im['brand'] . '-' . $im['code_norm'];
        $diskBase = pathinfo((string) $im['disk_name'], PATHINFO_FILENAME);
        if ($diskBase !== $expectPrefix && !preg_match('/^' . preg_quote($expectPrefix, '/') . '_[0-9]+$/', $diskBase)) {
            $p['name_mismatch']++;
            if (count($exName) < 8) {
                $exName[] = $im['disk_name'] . ' vs ' . $expectPrefix;
            }
        }
        $ren = $pozeRen . '/' . $im['brand'] . '/' . $im['disk_name'];
        if (!file_ok($ren) && !file_ok($pozeRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) $im['rel_path']))) {
            $p['ren_missing']++;
        }
        if (isset($td[$im['code_norm']])) {
            $p['td_hit']++;
        } else {
            $p['td_miss']++;
        }
    }
    if ($p['csv_rows'] % 100000 === 0) {
        echo "  poze csv {$p['csv_rows']}...\n";
    }
}
fclose($in);

$q = $pdo->query("SELECT COUNT(*) FROM images WHERE brand='' OR brand IS NULL");
$p['empty_brand'] = (int) $q->fetchColumn();
$q = $pdo->query("SELECT COUNT(*) FROM images WHERE code_norm='' OR code_norm IS NULL");
$p['empty_code'] = (int) $q->fetchColumn();
$q = $pdo->query('SELECT COUNT(*) FROM images');
$p['db_images'] = (int) $q->fetchColumn();
$q = $pdo->query('SELECT COUNT(*) FROM products');
$p['db_products'] = (int) $q->fetchColumn();

$lines[] = '=== imagine_poze (TTC / folder Poze) ===';
$lines[] = 'Regula: owner_code (echivalent C) curat = code_norm; owner_brand = brand; fisier din file_path; nume BRAND-COD.';
$lines[] = 'csv randuri: ' . $p['csv_rows'] . ', valide brand+cod: ' . $p['csv_valid'];
$lines[] = 'BD imagini: ' . $p['db_images'] . ', produse: ' . $p['db_products'];
$lines[] = 'cod CSV != BD: ' . $p['code_mismatch'];
$lines[] = 'brand CSV != BD: ' . $p['brand_mismatch'];
$lines[] = 'nume disc != BRAND-COD: ' . $p['name_mismatch'];
$lines[] = 'original lipsa pe disc: ' . $p['orig_missing'];
$lines[] = 'redenumit lipsa: ' . $p['ren_missing'];
$lines[] = 'in CSV dar nu in BD (si fisier exista, sarite): ' . $p['db_orphan'];
$lines[] = 'brand gol in BD: ' . $p['empty_brand'] . ', cod gol: ' . $p['empty_code'];
$lines[] = 'TecDoc hit pe code_norm imagini: ' . $p['td_hit'] . ', miss: ' . $p['td_miss'];
$lines[] = 'coduri unde _ extra fata de algoritm strict: ' . $p['overstrip'];
foreach (['exCode' => $exCode, 'exBrand' => $exBrand, 'exName' => $exName, 'exMiss' => $exMiss] as $k => $arr) {
    if ($arr !== []) {
        $lines[] = $k . ':';
        foreach ($arr as $e) {
            $lines[] = '  ' . $e;
        }
    }
}
$lines[] = '';

echo "=== AUTOTAL ===\n";
$pdoA = new PDO('mysql:host=127.0.0.1;dbname=imagine_autototal;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$a = [
    'csv_rows' => 0,
    'code_mismatch' => 0,
    'brand_mismatch' => 0,
    'name_mismatch' => 0,
    'file_ok_flag_0' => 0,
    'file_missing_flag_1' => 0,
    'empty_brand' => 0,
    'empty_code' => 0,
    'td_hit' => 0,
    'td_miss' => 0,
    'overstrip' => 0,
    'b_empty' => 0,
];
$exA = [];
$dbA = [];
$q = $pdoA->query('SELECT id, brand, code_norm, code_raw, disk_name, rel_path, downloaded FROM images');
while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    $dbA[$r['brand'] . '|' . $r['code_norm']][] = $r;
}

$in = fopen($atCsv, 'rb');
fgetcsv($in, 0, ';');
$seenP = [];
while (($row = fgetcsv($in, 0, ';')) !== false) {
    $a['csv_rows']++;
    $codeRaw = trim((string) ($row[1] ?? ''));
    $brandRaw = (string) ($row[2] ?? '');
    if ($codeRaw === '') {
        $a['b_empty']++;
        continue;
    }
    $brand = expand_brand($brandRaw, $brandMap);
    $codeAlgo = clean_photo($codeRaw);
    $codeStrict = clean_tecdoc($codeRaw);
    if ($codeAlgo !== $codeStrict) {
        $a['overstrip']++;
    }
    $key = $brand . '|' . $codeAlgo;
    $seenP[$key] = true;
    $rows = $dbA[$key] ?? $dbA[expand_brand($brandRaw, $brandMap) . '|' . $codeStrict] ?? [];
    if ($rows === []) {
        // try db key by code only later
        continue;
    }
    foreach ($rows as $im) {
        if ($im['code_norm'] !== $codeAlgo && $im['code_norm'] !== $codeStrict) {
            $a['code_mismatch']++;
            if (count($exA) < 10) {
                $exA[] = 'cod B=' . $codeRaw . ' algo=' . $codeAlgo . ' db=' . $im['code_norm'];
            }
        }
        if ($im['brand'] !== $brand) {
            $a['brand_mismatch']++;
        }
        $expectPrefix = $im['brand'] . '-' . $im['code_norm'];
        $diskBase = pathinfo((string) $im['disk_name'], PATHINFO_FILENAME);
        if ($diskBase !== $expectPrefix && !preg_match('/^' . preg_quote($expectPrefix, '/') . '_[0-9]+$/', $diskBase)) {
            $a['name_mismatch']++;
        }
    }
    if ($a['csv_rows'] % 100000 === 0) {
        echo "  autotal csv {$a['csv_rows']}...\n";
    }
}
fclose($in);

$chk = $pdoA->query('SELECT brand, code_norm, disk_name, downloaded, rel_path FROM images');
while ($im = $chk->fetch(PDO::FETCH_ASSOC)) {
    $path = $atRen . '/' . $im['brand'] . '/' . $im['disk_name'];
    $ok = file_ok($path);
    if ($ok && (int) $im['downloaded'] === 0) {
        $a['file_ok_flag_0']++;
    }
    if (!$ok && (int) $im['downloaded'] === 1) {
        $a['file_missing_flag_1']++;
    }
    if (isset($td[$im['code_norm']])) {
        $a['td_hit']++;
    } else {
        $a['td_miss']++;
    }
}
$a['empty_brand'] = (int) $pdoA->query("SELECT COUNT(*) FROM images WHERE brand='' OR brand IS NULL")->fetchColumn();
$a['empty_code'] = (int) $pdoA->query("SELECT COUNT(*) FROM images WHERE code_norm='' OR code_norm IS NULL")->fetchColumn();
$a['db_images'] = (int) $pdoA->query('SELECT COUNT(*) FROM images')->fetchColumn();
$a['db_products'] = (int) $pdoA->query('SELECT COUNT(*) FROM products')->fetchColumn();
$a['prod_empty_b'] = (int) $pdoA->query("SELECT COUNT(*) FROM products WHERE brand='' OR code_norm=''")->fetchColumn();

$lines[] = '=== imagine_autototal (coloana B = TecDoc, SUP_BRAND, folder Autotal) ===';
$lines[] = 'Regula: ART_ARTICLE_NR (B) curat = code_norm; SUP_BRAND = brand; poze din PICTURE_LIST; nume BRAND-COD in Autotal/.';
$lines[] = 'csv randuri: ' . $a['csv_rows'] . ', B gol: ' . $a['b_empty'];
$lines[] = 'BD imagini: ' . $a['db_images'] . ', produse: ' . $a['db_products'];
$lines[] = 'cod B != BD: ' . $a['code_mismatch'];
$lines[] = 'brand SUP_BRAND != BD: ' . $a['brand_mismatch'];
$lines[] = 'nume disc != BRAND-COD: ' . $a['name_mismatch'];
$lines[] = 'fisier exista dar downloaded=0: ' . $a['file_ok_flag_0'];
$lines[] = 'downloaded=1 dar fisier lipsa: ' . $a['file_missing_flag_1'];
$lines[] = 'brand/cod gol imagini: ' . $a['empty_brand'] . '/' . $a['empty_code'] . ', produse goale: ' . $a['prod_empty_b'];
$lines[] = 'TecDoc hit imagini: ' . $a['td_hit'] . ', miss: ' . $a['td_miss'];
$lines[] = 'B cu _ fata de curatare stricta spatiu-./ : ' . $a['overstrip'];
if ($exA !== []) {
    $lines[] = 'exemple cod:';
    foreach ($exA as $e) {
        $lines[] = '  ' . $e;
    }
}

$json = json_encode(['poze' => $p, 'autotal' => $a], JSON_PRETTY_PRINT);
file_put_contents($report, implode("\n", $lines) . "\n\nJSON\n" . $json);
echo implode("\n", $lines) . "\n";
echo "\nRaport: {$report}\n";
