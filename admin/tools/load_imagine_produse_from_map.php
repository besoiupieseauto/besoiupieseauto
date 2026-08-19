<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '2048M');

$project = dirname(__DIR__, 2);
$catalog = 'C:/Users/Radu/Desktop/autoparner/3208129.csv';
$mapCsv = 'C:/Users/Radu/Desktop/autoparner/imagini_mapate_BRAND.csv';

function clean_tecdoc(string $c): string
{
    return strtoupper(str_replace([' ', '-', '.', '/'], '', trim($c)));
}

function clean_photo_a(string $c): string
{
    return strtoupper(str_replace([' ', '-', '.', '/', '_'], '', trim($c)));
}

function brand_token(string $b): string
{
    $b = strtoupper(trim($b));
    $b = str_replace([' ', '-', '.', '/', '_', ':'], '', $b);

    return preg_replace('/[^A-Z0-9]/', '', $b) ?? '';
}

function to_utf8(string $s): string
{
    if ($s === '') {
        return '';
    }
    $c = @iconv('Windows-1250', 'UTF-8//IGNORE', $s);

    return $c !== false ? $c : $s;
}

function quote_sql(PDO $pdo, mixed $v): string
{
    if ($v === null || $v === '') {
        return 'NULL';
    }

    return $pdo->quote((string) $v);
}

$apBrand = [
    'MAX' => 'MAXGEAR', 'OPT' => 'OPTIMAL', 'BOS' => 'BOSCH', 'FEB' => 'FEBIBILSTEIN',
    'QBK' => 'QUICKBRAKE', 'MEY' => 'MEYLE', 'MAG' => 'MAGNETIMARELLI', 'VAL' => 'VALEO',
    'DAY' => 'DAYCO', 'SAC' => 'SACHS', 'MAN' => 'MANNFILTER', 'REI' => 'VICTORREINZ',
    'CON' => 'CONTINENTALCTAM', 'LMI' => 'LEMFORDER', 'BLP' => 'BLUEPRINT', 'JPA' => 'JPGROUP',
    'ORGVAG' => 'OEVAG', 'ORGBMW' => 'OEBMW', 'ORGREN' => 'OERENAULT', 'ORGPSA' => 'OEPSA',
    'ORGFORD' => 'OEFORD',
];
$aliasFile = $project . '/app/Import/MatchingPro/config/tecdoc_brand_aliases.json';
if (is_file($aliasFile)) {
    $json = json_decode((string) file_get_contents($aliasFile), true);
    foreach (($json['aliases'] ?? []) as $from => $to) {
        $fk = brand_token((string) $from);
        $tk = brand_token((string) $to);
        if ($fk !== '' && $tk !== '' && !isset($apBrand[$fk])) {
            $apBrand[$fk] = $tk;
        }
    }
}

function expand_brand(string $raw, array $map): string
{
    $tok = brand_token($raw);

    return $tok === '' ? 'NECUNOSCUT' : ($map[$tok] ?? $tok);
}

echo "Incarc catalog + mapare...\n";
$products = [];
$in = fopen($catalog, 'rb');
while (($row = fgetcsv($in, 0, ';')) !== false) {
    if (!is_array($row) || count($row) < 4) {
        continue;
    }
    $a = to_utf8(trim((string) $row[0]));
    $name = to_utf8(trim((string) ($row[1] ?? '')));
    $c = to_utf8(trim((string) ($row[2] ?? '')));
    $d = to_utf8(trim((string) ($row[3] ?? '')));
    $price = trim((string) ($row[5] ?? ''));
    $cNorm = clean_tecdoc($c !== '' ? $c : $a);
    if ($cNorm === '') {
        continue;
    }
    $brand = expand_brand($d, $apBrand);
    $pkey = $brand . '|' . $cNorm;
    if (isset($products[$pkey])) {
        continue;
    }
    $products[$pkey] = [
        'brand' => $brand,
        'code_norm' => $cNorm,
        'code_a' => $a,
        'code_c' => $c,
        'a_norm' => clean_photo_a($a !== '' ? $a : $c),
        'name' => $name,
        'price' => $price !== '' ? $price : null,
        'image_count' => 0,
    ];
}
fclose($in);

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

$images = [];
$mp = fopen($mapCsv, 'rb');
fgetcsv($mp, 0, ';');
while (($row = fgetcsv($mp, 0, ';')) !== false) {
    if (($row[7] ?? '') !== 'ok') {
        continue;
    }
    $brand = (string) $row[3];
    $cNorm = (string) $row[4];
    $pkey = $brand . '|' . $cNorm;
    $images[] = [
        'brand' => $brand,
        'code_norm' => $cNorm,
        'stem' => (string) $row[1],
        'orig' => (string) $row[0],
        'disk' => (string) $row[5],
        'rel' => (string) $row[6],
    ];
    if (isset($products[$pkey])) {
        $products[$pkey]['image_count']++;
    }
}
fclose($mp);

$sql = (string) file_get_contents($project . '/SQL/imagine_produse.sql');
$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => true,
]);
$pdo->exec('SET NAMES utf8mb4');
foreach (preg_split('/;\s*\n/', $sql) ?: [] as $stmt) {
    $stmt = trim((string) $stmt);
    if ($stmt !== '') {
        $pdo->exec($stmt);
    }
}
$pdo->exec('USE `imagine_produse`');
$pdo->exec('SET UNIQUE_CHECKS=0');
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

$buf = [];
$flushP = static function () use ($pdo, &$buf): void {
    if ($buf === []) {
        return;
    }
    $pdo->exec('INSERT INTO products (code_norm,brand,code_a,code_c,a_norm,name,price,currency,image_count,tecdoc_match) VALUES ' . implode(',', $buf));
    $buf = [];
};
$pdo->beginTransaction();
$tdHit = 0;
$withImg = 0;
foreach ($products as $p) {
    $tdm = isset($td[$p['code_norm']]) ? 1 : 0;
    $tdHit += $tdm;
    if ($p['image_count'] > 0) {
        $withImg++;
    }
    $buf[] = '(' . quote_sql($pdo, $p['code_norm']) . ',' . quote_sql($pdo, $p['brand']) . ',' . quote_sql($pdo, $p['code_a']) . ',' . quote_sql($pdo, $p['code_c']) . ',' . quote_sql($pdo, $p['a_norm']) . ',' . quote_sql($pdo, $p['name']) . ',' . quote_sql($pdo, $p['price']) . ',\'RON\',' . (int) $p['image_count'] . ',' . $tdm . ')';
    if (count($buf) >= 400) {
        $flushP();
    }
}
$flushP();

$bufI = [];
$flushI = static function () use ($pdo, &$bufI): void {
    if ($bufI === []) {
        return;
    }
    $pdo->exec('INSERT IGNORE INTO images (brand,code_norm,code_after_strip,original_file,disk_name,rel_path,source) VALUES ' . implode(',', $bufI));
    $bufI = [];
};
foreach ($images as $im) {
    $bufI[] = '(' . quote_sql($pdo, $im['brand']) . ',' . quote_sql($pdo, $im['code_norm']) . ',' . quote_sql($pdo, $im['stem']) . ',' . quote_sql($pdo, $im['orig']) . ',' . quote_sql($pdo, $im['disk']) . ',' . quote_sql($pdo, $im['rel']) . ',\'catalog_A\')';
    if (count($bufI) >= 400) {
        $flushI();
    }
}
$flushI();
$pdo->commit();

$meta = $pdo->prepare('REPLACE INTO meta (k,v) VALUES (?,?)');
foreach ([
    'imported_at' => date('c'),
    'algorithm' => 'TecDoc/C clean space-./ ; photo until _N_large/_large = A clean space-_./',
    'products' => (string) count($products),
    'products_with_image' => (string) $withImg,
    'images' => (string) count($images),
    'tecdoc_hit' => (string) $tdHit,
] as $k => $v) {
    $meta->execute([$k, $v]);
}

echo 'produse: ' . count($products) . " cu imagine: {$withImg}\n";
echo 'imagini: ' . count($images) . "\n";
echo "tecdoc C: {$tdHit}\n";
$ex = $pdo->query("SELECT p.brand,p.code_a,p.code_c,p.name,i.disk_name FROM products p JOIN images i USING (brand,code_norm) WHERE p.a_norm='MGA5562' LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
foreach ($ex as $r) {
    echo 'MGA-5562: ' . $r['brand'] . ' C=' . $r['code_c'] . ' ' . $r['disk_name'] . ' | ' . $r['name'] . "\n";
}
