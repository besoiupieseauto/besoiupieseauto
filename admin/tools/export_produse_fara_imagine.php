<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '2048M');

function norm_code(string $c): string
{
    $c = strtoupper(str_replace([' ', '-', '.', '/', '_'], '', trim($c)));

    return preg_replace('/[^A-Z0-9]/', '', $c) ?? '';
}

function brand_token(string $b): string
{
    $b = strtoupper(str_replace([' ', '-', '.', '/', '_', ':'], '', trim($b)));

    return preg_replace('/[^A-Z0-9]/', '', $b) ?? '';
}

$outDir = $argv[1] ?? 'C:\\Users\\Radu\\Desktop';
$stamp = date('Y-m-d');
$prodCsv = $outDir . DIRECTORY_SEPARATOR . "_tmp_produse_fara_imagine.csv";
$brandCsv = $outDir . DIRECTORY_SEPARATOR . "_tmp_branduri_fara_imagine.csv";
$xlsx = $outDir . DIRECTORY_SEPARATOR . "produse_fara_imagine_{$stamp}.xlsx";

$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$keys = [];
$sqlKeys = "
    SELECT brand, code_norm FROM imagine_produse.images
    UNION
    SELECT brand, code_norm FROM imagine_poze.images
    UNION
    SELECT brand, code_norm FROM imagine_autototal.images WHERE downloaded=1
";
foreach ($pdo->query($sqlKeys) as $row) {
    $b = brand_token((string) $row['brand']);
    $c = norm_code((string) $row['code_norm']);
    if ($b !== '' && $c !== '') {
        $keys[$b . '|' . $c] = true;
    }
}
echo 'Chei imagini: ' . count($keys) . "\n";

$hit = static function (array $keys, string $brand, string $code): bool {
    return $brand !== '' && $code !== '' && isset($keys[$brand . '|' . $code]);
};

$fp = fopen($prodCsv, 'wb');
if ($fp === false) {
    throw new RuntimeException('Nu pot scrie ' . $prodCsv);
}
fwrite($fp, "\xEF\xBB\xBF");
fputcsv($fp, ['Brand', 'Cod 1', 'Cod 2', 'Denumire', 'EAN', 'Are echivalente', 'Nr echivalente'], ';');

$byBrand = [];
$gap = 0;
$sql = 'SELECT b.name AS brand, p.art_code_1, p.art_code_2, p.art_name, p.art_ean, p.art_cross
        FROM besoiu_tecdoc_base.products p
        JOIN besoiu_tecdoc_base.brands b ON b.id = p.brand_id';

foreach ($pdo->query($sql, PDO::FETCH_ASSOC) as $row) {
    $brand = brand_token((string) $row['brand']);
    $brandName = trim((string) $row['brand']);
    $c1 = norm_code((string) $row['art_code_1']);
    $c2 = norm_code((string) ($row['art_code_2'] ?? ''));
    $cross = trim((string) ($row['art_cross'] ?? ''));

    $pairs = [];
    if ($c1 !== '') {
        $pairs[] = [$brand, $c1];
    }
    if ($c2 !== '' && $c2 !== $c1) {
        $pairs[] = [$brand, $c2];
    }

    $eqCount = 0;
    if ($cross !== '') {
        foreach (explode('|', $cross) as $part) {
            $part = trim($part);
            if ($part === '' || !str_contains($part, '::')) {
                continue;
            }
            [$eb, $ec] = array_map('trim', explode('::', $part, 2));
            $eb = brand_token($eb);
            $ec = norm_code($ec);
            if ($eb === '' || $ec === '') {
                continue;
            }
            if ($eb === $brand && ($ec === $c1 || $ec === $c2)) {
                continue;
            }
            $pairs[] = [$eb, $ec];
            $eqCount++;
        }
    }

    $covered = false;
    foreach ($pairs as [$b, $c]) {
        if ($hit($keys, $b, $c)) {
            $covered = true;
            break;
        }
    }
    if ($covered) {
        continue;
    }

    $gap++;
    $label = $brandName !== '' ? $brandName : '(fara brand)';
    if (!isset($byBrand[$label])) {
        $byBrand[$label] = ['total' => 0, 'cu_echiv' => 0, 'fara_echiv' => 0];
    }
    $byBrand[$label]['total']++;
    if ($eqCount > 0) {
        $byBrand[$label]['cu_echiv']++;
    } else {
        $byBrand[$label]['fara_echiv']++;
    }

    fputcsv($fp, [
        $brandName,
        (string) $row['art_code_1'],
        (string) ($row['art_code_2'] ?? ''),
        (string) ($row['art_name'] ?? ''),
        (string) ($row['art_ean'] ?? ''),
        $eqCount > 0 ? 'Da' : 'Nu',
        $eqCount,
    ], ';');
}
fclose($fp);

uasort($byBrand, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
$fb = fopen($brandCsv, 'wb');
if ($fb === false) {
    throw new RuntimeException('Nu pot scrie ' . $brandCsv);
}
fwrite($fb, "\xEF\xBB\xBF");
fputcsv($fb, ['Brand', 'Produse fara imagine', 'Cu echivalente', 'Fara echivalente'], ';');
foreach ($byBrand as $name => $n) {
    fputcsv($fb, [$name, $n['total'], $n['cu_echiv'], $n['fara_echiv']], ';');
}
fclose($fb);

echo "Produse fara imagine: {$gap}\n";
echo "Branduri: " . count($byBrand) . "\n";
echo "CSV: {$prodCsv}\n";
echo "CSV: {$brandCsv}\n";
echo "XLSX: {$xlsx}\n";
