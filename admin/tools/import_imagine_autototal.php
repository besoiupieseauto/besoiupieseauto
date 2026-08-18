<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '2048M');

$project = dirname(__DIR__, 2);
$csv = 'C:/Users/Radu/Downloads/AUTOTOTAL 14,08,2026 - cu link poze.csv';
$outRoot = 'C:/laragon/www/besoiupieseimport/Autotal';
$mapOut = 'C:/Users/Radu/Desktop/autoparner/imagini_mapate_AUTOTAL.csv';
$missingOut = 'C:/Users/Radu/Desktop/autoparner/AUTOTAL_FARA_IMAGINE.csv';
$concurrency = 20;

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

function quote_sql(PDO $pdo, mixed $v): string
{
    if ($v === null || $v === '') {
        return 'NULL';
    }

    return $pdo->quote((string) $v);
}

function parse_urls(string $list): array
{
    $out = [];
    foreach (preg_split('/\s*,\s*/', trim($list)) ?: [] as $u) {
        $u = trim($u);
        if ($u !== '' && preg_match('~^https?://~i', $u)) {
            $out[] = $u;
        }
    }

    return array_values(array_unique($out));
}

function ext_from_url(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'jpg';
}

function file_ok(string $path): bool
{
    return is_file($path) && filesize($path) >= 512;
}

function download_batch(array $jobs, int $concurrency): array
{
    $ok = [];
    $pending = array_values($jobs);
    $active = [];
    $mh = curl_multi_init();

    $add = static function () use (&$pending, &$active, $mh, $concurrency): void {
        while (count($active) < $concurrency && $pending !== []) {
            $job = array_pop($pending);
            $dir = dirname($job['dest']);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $fp = fopen($job['dest'] . '.part', 'wb');
            if ($fp === false) {
                continue;
            }
            $ch = curl_init($job['url']);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
                CURLOPT_FAILONERROR => false,
            ]);
            curl_multi_add_handle($mh, $ch);
            $active[(int) $ch] = ['ch' => $ch, 'fp' => $fp, 'dest' => $job['dest'], 'url' => $job['url']];
        }
    };

    $add();
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 1.0);
        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $id = (int) $ch;
            $job = $active[$id];
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            fclose($job['fp']);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($active[$id]);
            $part = $job['dest'] . '.part';
            $good = $code === 200 && is_file($part) && filesize($part) >= 512;
            if ($good) {
                if (is_file($job['dest'])) {
                    @unlink($job['dest']);
                }
                rename($part, $job['dest']);
                $ok[$job['url']] = true;
            } else {
                @unlink($part);
                $ok[$job['url']] = false;
            }
        }
        $add();
    } while ($active !== [] || $pending !== []);
    curl_multi_close($mh);

    return $ok;
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
echo "Citesc catalog Autototal...\n";
$in = fopen($csv, 'rb');
fgetcsv($in, 0, ';');

$groups = [];
$seen = 0;
$noPic = 0;
$miss = fopen($missingOut, 'wb');
fwrite($miss, "itemkey;code_raw;code_norm;brand;name;price\n");

while (($row = fgetcsv($in, 0, ';')) !== false) {
    $seen++;
    $itemkey = trim((string) ($row[0] ?? ''));
    $codeRaw = trim((string) ($row[1] ?? ''));
    $brand = expand_brand((string) ($row[2] ?? ''), $brandMap);
    $code = norm_code($codeRaw !== '' ? $codeRaw : $itemkey);
    $price = trim((string) ($row[3] ?? ''));
    $echivCode = trim((string) ($row[4] ?? ''));
    $echivBrand = trim((string) ($row[5] ?? ''));
    $name = trim((string) ($row[6] ?? ''));
    $urls = parse_urls((string) ($row[7] ?? ''));
    if ($brand === '' || $code === '') {
        continue;
    }
    $gkey = $brand . '|' . $code;
    if (!isset($groups[$gkey])) {
        $groups[$gkey] = [
            'brand' => $brand,
            'code' => $code,
            'code_raw' => $codeRaw !== '' ? $codeRaw : $itemkey,
            'itemkey' => $itemkey,
            'name' => $name,
            'price' => $price !== '' ? $price : null,
            'echiv_code' => $echivCode,
            'echiv_brand' => $echivBrand,
            'urls' => [],
        ];
    }
    foreach ($urls as $u) {
        $groups[$gkey]['urls'][$u] = true;
    }
    if ($urls === []) {
        $noPic++;
        fputcsv($miss, [$itemkey, $codeRaw, $code, $brand, $name, $price], ';');
    }
    if ($seen % 50000 === 0) {
        echo "  csv {$seen}, produse " . count($groups) . ", fara poza {$noPic}...\n";
    }
}
fclose($in);
fclose($miss);
echo "randuri: {$seen}, produse unice: " . count($groups) . ", fara poza: {$noPic}\n";

$pdo = new PDO('mysql:host=127.0.0.1;dbname=imagine_autototal;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => true,
]);
$pdo->exec('SET UNIQUE_CHECKS=0');
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('TRUNCATE TABLE aliases');
$pdo->exec('TRUNCATE TABLE images');
$pdo->exec('TRUNCATE TABLE products');
$pdo->exec('TRUNCATE TABLE meta');

$bufP = [];
$bufI = [];
$bufA = [];
$flushP = static function () use ($pdo, &$bufP): void {
    if ($bufP === []) {
        return;
    }
    $parts = [];
    foreach ($bufP as $r) {
        $parts[] = '(' . quote_sql($pdo, $r[0]) . ',' . quote_sql($pdo, $r[1]) . ',' . quote_sql($pdo, $r[2]) . ',' . quote_sql($pdo, $r[3]) . ',' . quote_sql($pdo, $r[4]) . ',' . quote_sql($pdo, $r[5]) . ',' . (int) $r[6] . ')';
    }
    $pdo->exec(
        'INSERT INTO products (code_norm, brand, code_raw, itemkey, name, price, image_count) VALUES '
        . implode(',', $parts)
        . ' ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price), image_count=VALUES(image_count), itemkey=VALUES(itemkey), code_raw=VALUES(code_raw)'
    );
    $bufP = [];
};
$flushI = static function () use ($pdo, &$bufI): void {
    if ($bufI === []) {
        return;
    }
    $parts = [];
    foreach ($bufI as $r) {
        $parts[] = '(' . quote_sql($pdo, $r[0]) . ',' . quote_sql($pdo, $r[1]) . ',' . quote_sql($pdo, $r[2]) . ',' . quote_sql($pdo, $r[3]) . ',' . quote_sql($pdo, $r[4]) . ',' . quote_sql($pdo, $r[5]) . ',0,\'autotal\')';
    }
    $pdo->exec('INSERT IGNORE INTO images (brand, code_norm, code_raw, original_url, disk_name, rel_path, downloaded, source) VALUES ' . implode(',', $parts));
    $bufI = [];
};
$flushA = static function () use ($pdo, &$bufA): void {
    if ($bufA === []) {
        return;
    }
    $parts = [];
    foreach ($bufA as $r) {
        $parts[] = '(' . quote_sql($pdo, $r[0]) . ',' . quote_sql($pdo, $r[1]) . ',' . quote_sql($pdo, $r[2]) . ',' . quote_sql($pdo, $r[3]) . ')';
    }
    $pdo->exec('INSERT IGNORE INTO aliases (alias_brand, alias_code_norm, brand, code_norm) VALUES ' . implode(',', $parts));
    $bufA = [];
};

$map = fopen($mapOut, 'wb');
fwrite($map, "brand;code_raw;code_norm;itemkey;url;nume_nou;rel_path\n");

$urlDests = [];
$prodRows = 0;
$imgRows = 0;
$aliasRows = 0;
$pdo->beginTransaction();

foreach ($groups as $g) {
    $urls = array_keys($g['urls']);
    $n = count($urls);
    $brand = $g['brand'];
    $code = $g['code'];
    $brandDir = $outRoot . DIRECTORY_SEPARATOR . $brand;
    if ($n > 0 && !is_dir($brandDir)) {
        mkdir($brandDir, 0777, true);
    }
    $bufP[] = [$code, $brand, $g['code_raw'], $g['itemkey'] ?: null, $g['name'] ?: null, $g['price'], $n];
    $prodRows++;

    $i = 1;
    foreach ($urls as $url) {
        $base = $n === 1 ? ($brand . '-' . $code) : ($brand . '-' . $code . '_' . $i);
        $disk = $base . '.' . ext_from_url($url);
        $rel = 'Autotal/' . $brand . '/' . $disk;
        $dest = $brandDir . DIRECTORY_SEPARATOR . $disk;
        $bufI[] = [$brand, $code, $g['code_raw'], $url, $disk, $rel];
        $imgRows++;
        $urlDests[$url][] = $dest;
        fputcsv($map, [$brand, $g['code_raw'], $code, $g['itemkey'], $url, $disk, $rel], ';');
        $i++;
    }

    $abn = expand_brand((string) $g['echiv_brand'], $brandMap);
    $acn = norm_code((string) $g['echiv_code']);
    if ($abn !== '' && $acn !== '') {
        $bufA[] = [$abn, $acn, $brand, $code];
        $aliasRows++;
    }

    if (count($bufP) >= 400) {
        $flushP();
    }
    if (count($bufI) >= 400) {
        $flushI();
    }
    if (count($bufA) >= 400) {
        $flushA();
    }
    if ($prodRows % 20000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        echo "  index produse {$prodRows}, imagini {$imgRows}...\n";
    }
}
$flushP();
$flushI();
$flushA();
$pdo->commit();
fclose($map);
echo "Index gata. Descarc " . count($urlDests) . " URL-uri unice in {$outRoot} ...\n";

$jobs = [];
$already = 0;
foreach ($urlDests as $url => $dests) {
    $have = null;
    foreach ($dests as $dest) {
        if (file_ok($dest)) {
            $have = $dest;
            break;
        }
    }
    if ($have !== null) {
        foreach ($dests as $dest) {
            if ($dest !== $have && !file_ok($dest)) {
                @link($have, $dest) || @copy($have, $dest);
            }
        }
        $already++;
        continue;
    }
    $jobs[] = ['url' => $url, 'dest' => $dests[0]];
}

$done = $already;
$okDl = 0;
$failDl = 0;
$chunk = 400;
$totalJobs = count($jobs);
echo "de descarcat: {$totalJobs}, deja pe disc: {$already}\n";

for ($o = 0; $o < $totalJobs; $o += $chunk) {
    $slice = array_slice($jobs, $o, $chunk);
    $result = download_batch($slice, $concurrency);
    foreach ($slice as $job) {
        $url = $job['url'];
        $first = $job['dest'];
        $good = !empty($result[$url]) && file_ok($first);
        if ($good) {
            $okDl++;
            foreach ($urlDests[$url] as $dest) {
                if ($dest !== $first && !file_ok($dest)) {
                    @link($first, $dest) || @copy($first, $dest);
                }
            }
        } else {
            $failDl++;
        }
    }
    echo "  download " . ($already + $okDl + $failDl) . '/' . ($already + $totalJobs) . " ok {$okDl} fail {$failDl}...\n";
}

$mark = [];
$flushMark = static function () use ($pdo, &$mark): void {
    if ($mark === []) {
        return;
    }
    $in = implode(',', array_map(static fn (string $p): string => quote_sql($pdo, $p), $mark));
    $pdo->exec('UPDATE images SET downloaded=1 WHERE rel_path IN (' . $in . ')');
    $mark = [];
};
foreach ($urlDests as $dests) {
    foreach ($dests as $dest) {
        if (!file_ok($dest)) {
            continue;
        }
        $mark[] = 'Autotal/' . str_replace('\\', '/', substr($dest, strlen($outRoot) + 1));
        if (count($mark) >= 400) {
            $flushMark();
        }
    }
}
$flushMark();

$meta = $pdo->prepare('REPLACE INTO meta (k,v) VALUES (?,?)');
foreach ([
    'source_csv' => $csv,
    'out_dir' => $outRoot,
    'imported_at' => date('c'),
    'products' => (string) $prodRows,
    'images' => (string) $imgRows,
    'aliases' => (string) $aliasRows,
    'without_picture' => (string) $noPic,
    'unique_urls' => (string) count($urlDests),
    'downloaded_ok' => (string) ($already + $okDl),
    'download_fail' => (string) $failDl,
] as $k => $v) {
    $meta->execute([$k, $v]);
}

echo "\nBaza imagine_autototal\n";
echo "  produse: {$prodRows}\n";
echo "  imagini in catalog: {$imgRows}\n";
echo "  aliasuri: {$aliasRows}\n";
echo "  fara poza in CSV: {$noPic}\n";
echo "  descarcate ok: " . ($already + $okDl) . ", fail: {$failDl}\n";
echo "  folder: {$outRoot}\n";
echo "  mapare: {$mapOut}\n";
$ex = $pdo->query("SELECT brand, code_raw, disk_name, rel_path FROM images WHERE brand='BOSCH' LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
foreach ($ex as $r) {
    echo '  exemplu: ' . $r['brand'] . ' ' . $r['code_raw'] . ' ' . $r['disk_name'] . "\n";
}
