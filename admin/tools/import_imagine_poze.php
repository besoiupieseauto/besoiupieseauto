<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '2048M');

$project = dirname(__DIR__, 2);
$csv = 'C:/Users/Radu/Downloads/image_brand_codes_by_image_20260817_120909.csv';
$pozeRoot = 'C:/laragon/www/besoiupieseimport';
$outRoot = 'C:/laragon/www/besoiupieseimport/Poze_RENUMITE';
$mapOut = 'C:/Users/Radu/Desktop/autoparner/imagini_mapate_POZE.csv';

function norm_code(string $c): string
{
    $c = trim($c);
    $c = str_replace([' ', '-', '.', '/', '_'], '', $c);

    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c) ?? '');
}

function brand_token(string $b): string
{
    $b = strtoupper(trim($b));
    $b = str_replace([' ', '-', '.', '/', '_', ':'], '', $b);

    return preg_replace('/[^A-Z0-9]/', '', $b) ?? '';
}

function load_brand_map(string $project): array
{
    $map = [];
    $aliasFile = $project . '/app/Import/MatchingPro/config/tecdoc_brand_aliases.json';
    if (is_file($aliasFile)) {
        $json = json_decode((string) file_get_contents($aliasFile), true);
        foreach (($json['aliases'] ?? []) as $from => $to) {
            $fk = brand_token((string) $from);
            $tk = brand_token((string) $to);
            if ($fk !== '' && $tk !== '') {
                $map[$fk] = $tk;
            }
        }
    }
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=besoiu_tecdoc_base;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $rows = $pdo->query('SELECT name FROM brands')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $full = brand_token((string) ($row['name'] ?? ''));
            if ($full !== '') {
                $map[$full] = $full;
            }
        }
    } catch (PDOException) {
    }

    return $map;
}

function expand_brand(string $raw, array $map): string
{
    $tok = brand_token($raw);
    if ($tok === '') {
        return '';
    }

    return $map[$tok] ?? $tok;
}

function resolve_poze_path(string $root, string $rel): string
{
    $rel = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, ltrim($rel, '/\\'));

    return $root . DIRECTORY_SEPARATOR . $rel;
}

if (!is_file($csv)) {
    fwrite(STDERR, "Lipsește CSV: {$csv}\n");
    exit(1);
}
if (!is_dir($outRoot) && !mkdir($outRoot, 0777, true)) {
    fwrite(STDERR, "Nu pot crea {$outRoot}\n");
    exit(1);
}

$brandMap = load_brand_map($project);
echo "Citesc CSV și verific fișierele din Poze...\n";
$in = fopen($csv, 'rb');
$header = fgetcsv($in, 0, ';');
$col = array_flip($header ?: []);
$iPath = $col['file_path'] ?? 2;
$iTtc = $col['ttc_art_id'] ?? 6;
$iBrand = $col['owner_brand'] ?? 8;
$iCode = $col['owner_code'] ?? 9;
$iName = $col['art_name'] ?? 11;
$iAll = $col['all_brand_codes'] ?? 13;

$groups = [];
$missing = 0;
$seen = 0;
while (($row = fgetcsv($in, 0, ';')) !== false) {
    $seen++;
    $rel = trim((string) ($row[$iPath] ?? ''));
    $brand = expand_brand((string) ($row[$iBrand] ?? ''), $brandMap);
    $codeRaw = trim((string) ($row[$iCode] ?? ''));
    $code = norm_code($codeRaw);
    if ($rel === '' || $brand === '' || $code === '') {
        continue;
    }
    $abs = resolve_poze_path($pozeRoot, $rel);
    if (!is_file($abs) || filesize($abs) < 512) {
        $missing++;
        continue;
    }
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) ?: 'jpg';
    $gkey = $brand . '|' . $code;
    $groups[$gkey][] = [
        'abs' => $abs,
        'rel' => str_replace('\\', '/', $rel),
        'brand' => $brand,
        'code' => $code,
        'code_raw' => $codeRaw,
        'name' => trim((string) ($row[$iName] ?? '')),
        'ttc' => trim((string) ($row[$iTtc] ?? '')),
        'ext' => $ext,
        'aliases' => (string) ($row[$iAll] ?? ''),
    ];
    if ($seen % 50000 === 0) {
        echo "  csv {$seen}, grupuri " . count($groups) . ", lipsa {$missing}...\n";
    }
}
fclose($in);
echo "randuri csv: {$seen}, grupuri: " . count($groups) . ", fisiere lipsa: {$missing}\n";

$pdo = new PDO('mysql:host=127.0.0.1;dbname=imagine_poze;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => true,
]);
$pdo->exec('SET UNIQUE_CHECKS=0');
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('TRUNCATE TABLE aliases');
$pdo->exec('TRUNCATE TABLE images');
$pdo->exec('TRUNCATE TABLE products');
$pdo->exec('TRUNCATE TABLE meta');

$q = static function (PDO $pdo, mixed $v): string {
    if ($v === null || $v === '') {
        return 'NULL';
    }

    return $pdo->quote((string) $v);
};

$bufP = [];
$bufI = [];
$bufA = [];
$flushP = static function () use ($pdo, &$bufP, $q): void {
    if ($bufP === []) {
        return;
    }
    $parts = [];
    foreach ($bufP as $r) {
        $parts[] = '(' . $q($pdo, $r[0]) . ',' . $q($pdo, $r[1]) . ',' . $q($pdo, $r[2]) . ',' . $q($pdo, $r[3]) . ',' . $q($pdo, $r[4]) . ',' . (int) $r[5] . ')';
    }
    $pdo->exec(
        'INSERT INTO products (code_norm, brand, code_raw, name, ttc_art_id, image_count) VALUES '
        . implode(',', $parts)
        . ' ON DUPLICATE KEY UPDATE name=VALUES(name), ttc_art_id=VALUES(ttc_art_id), image_count=VALUES(image_count), code_raw=VALUES(code_raw)'
    );
    $bufP = [];
};
$flushI = static function () use ($pdo, &$bufI, $q): void {
    if ($bufI === []) {
        return;
    }
    $parts = [];
    foreach ($bufI as $r) {
        $parts[] = '(' . $q($pdo, $r[0]) . ',' . $q($pdo, $r[1]) . ',' . $q($pdo, $r[2]) . ',' . $q($pdo, $r[3]) . ',' . $q($pdo, $r[4]) . ',' . $q($pdo, $r[5]) . ',' . $q($pdo, $r[6]) . ',\'ttc_poze\')';
    }
    $pdo->exec('INSERT IGNORE INTO images (brand, code_norm, code_raw, ttc_art_id, original_path, disk_name, rel_path, source) VALUES ' . implode(',', $parts));
    $bufI = [];
};
$flushA = static function () use ($pdo, &$bufA, $q): void {
    if ($bufA === []) {
        return;
    }
    $parts = [];
    foreach ($bufA as $r) {
        $parts[] = '(' . $q($pdo, $r[0]) . ',' . $q($pdo, $r[1]) . ',' . $q($pdo, $r[2]) . ',' . $q($pdo, $r[3]) . ')';
    }
    $pdo->exec('INSERT IGNORE INTO aliases (alias_brand, alias_code_norm, brand, code_norm) VALUES ' . implode(',', $parts));
    $bufA = [];
};

$map = fopen($mapOut, 'wb');
fwrite($map, "brand;code_raw;code_norm;ttc_art_id;fisier_original;nume_nou;rel_path;status\n");

$linked = 0;
$copied = 0;
$failed = 0;
$imgRows = 0;
$prodRows = 0;
$aliasRows = 0;
$pdo->beginTransaction();

foreach ($groups as $list) {
    $n = count($list);
    $i = 1;
    $brand = $list[0]['brand'];
    $code = $list[0]['code'];
    $brandDir = $outRoot . DIRECTORY_SEPARATOR . $brand;
    if (!is_dir($brandDir) && !mkdir($brandDir, 0777, true) && !is_dir($brandDir)) {
        $failed += $n;
        continue;
    }
    $bufP[] = [$code, $brand, $list[0]['code_raw'], $list[0]['name'] ?: null, $list[0]['ttc'] ?: null, $n];
    $prodRows++;

    foreach ($list as $row) {
        $baseName = $n === 1 ? ($brand . '-' . $code) : ($brand . '-' . $code . '_' . $i);
        $disk = $baseName . '.' . $row['ext'];
        $dest = $brandDir . DIRECTORY_SEPARATOR . $disk;
        $relNew = 'Poze_RENUMITE/' . $brand . '/' . $disk;
        $ok = false;
        if (is_file($dest) && filesize($dest) >= 512) {
            $ok = true;
        } elseif (@link($row['abs'], $dest)) {
            $ok = true;
            $linked++;
        } elseif (@copy($row['abs'], $dest)) {
            $ok = true;
            $copied++;
        } else {
            $failed++;
        }
        if ($ok) {
            $bufI[] = [$brand, $code, $row['code_raw'], $row['ttc'] ?: null, $row['rel'], $disk, $relNew];
            $imgRows++;
        }
        fputcsv($map, [
            $brand,
            $row['code_raw'],
            $code,
            $row['ttc'],
            $row['rel'],
            $brand . ':' . $baseName . '.' . $row['ext'],
            $relNew,
            $ok ? 'ok' : 'fail',
        ], ';');
        $i++;
    }

    $seenAlias = [];
    foreach (preg_split('/\s*\|\s*/', (string) ($list[0]['aliases'] ?? '')) ?: [] as $part) {
        $part = trim($part);
        if ($part === '' || !str_contains($part, '::')) {
            continue;
        }
        [$ab, $ac] = array_map('trim', explode('::', $part, 2));
        $abn = expand_brand($ab, $brandMap);
        $acn = norm_code($ac);
        $akey = $abn . '|' . $acn;
        if ($abn === '' || $acn === '' || isset($seenAlias[$akey])) {
            continue;
        }
        $seenAlias[$akey] = true;
        $bufA[] = [$abn, $acn, $brand, $code];
        $aliasRows++;
    }

    if (count($bufP) >= 400) {
        $flushP();
    }
    if (count($bufI) >= 400) {
        $flushI();
    }
    if (count($bufA) >= 800) {
        $flushA();
    }
    if ($prodRows % 2000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        echo "  produse {$prodRows}, imagini {$imgRows}, hardlink {$linked}, copy {$copied}, alias {$aliasRows}...\n";
    }
}
$flushP();
$flushI();
$flushA();
$pdo->commit();
fclose($map);

$meta = $pdo->prepare('REPLACE INTO meta (k,v) VALUES (?,?)');
foreach ([
    'source_csv' => $csv,
    'poze_root' => $pozeRoot,
    'out_dir' => $outRoot,
    'imported_at' => date('c'),
    'images' => (string) $imgRows,
    'products' => (string) $prodRows,
    'aliases' => (string) $aliasRows,
    'missing_files' => (string) $missing,
    'failed' => (string) $failed,
] as $k => $v) {
    $meta->execute([$k, $v]);
}

echo "\nBaza imagine_poze\n";
echo "  produse: {$prodRows}\n";
echo "  imagini: {$imgRows}\n";
echo "  aliasuri: {$aliasRows}\n";
echo "  hardlink: {$linked}, copiate: {$copied}, fail: {$failed}, lipsa disc: {$missing}\n";
echo "  folder: {$outRoot}\n";
echo "  mapare: {$mapOut}\n";
$ex = $pdo->query("SELECT brand, code_raw, disk_name, rel_path FROM images WHERE code_norm='CAM749' LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
foreach ($ex as $r) {
    echo '  exemplu: ' . $r['brand'] . ' ' . $r['code_raw'] . ' ' . $r['disk_name'] . "\n";
}
