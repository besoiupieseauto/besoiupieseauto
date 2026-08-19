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

$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

function load_keys(PDO $pdo, string $sql): array
{
    $keys = [];
    foreach ($pdo->query($sql) as $row) {
        $b = brand_token((string) $row['brand']);
        $c = norm_code((string) $row['code_norm']);
        if ($b !== '' && $c !== '') {
            $keys[$b . '|' . $c] = true;
        }
    }

    return $keys;
}

echo "Incarc cheile de imagini...\n";
$src = [
    'autopartner' => load_keys($pdo, 'SELECT DISTINCT brand, code_norm FROM imagine_produse.images'),
    'poze' => load_keys($pdo, 'SELECT DISTINCT brand, code_norm FROM imagine_poze.images'),
    'autototal' => load_keys($pdo, 'SELECT DISTINCT brand, code_norm FROM imagine_autototal.images WHERE downloaded=1'),
];
$union = $src['autopartner'] + $src['poze'] + $src['autototal'];
echo '  AP=' . count($src['autopartner']) . ' Poze=' . count($src['poze']) . ' AT=' . count($src['autototal']) . ' union=' . count($union) . "\n";

$hit = static function (array $keys, string $brand, string $code): bool {
    return $brand !== '' && $code !== '' && isset($keys[$brand . '|' . $code]);
};

$names = ['autopartner', 'poze', 'autototal', 'oricare'];
$own = array_fill_keys($names, 0);
$equiv = array_fill_keys($names, 0);
$combo = array_fill_keys($names, 0);
$withCross = 0;
$total = 0;

echo "Scanez produsele TecDoc...\n";
$sql = 'SELECT b.name AS brand, p.art_code_1, p.art_code_2, p.art_cross
        FROM besoiu_tecdoc_base.products p
        JOIN besoiu_tecdoc_base.brands b ON b.id = p.brand_id';
foreach ($pdo->query($sql, PDO::FETCH_ASSOC) as $row) {
    $total++;
    $brand = brand_token((string) $row['brand']);
    $c1 = norm_code((string) $row['art_code_1']);
    $c2 = norm_code((string) ($row['art_code_2'] ?? ''));
    $cross = trim((string) ($row['art_cross'] ?? ''));

    $ownPairs = [];
    if ($c1 !== '') {
        $ownPairs[] = [$brand, $c1];
    }
    if ($c2 !== '' && $c2 !== $c1) {
        $ownPairs[] = [$brand, $c2];
    }

    $eqPairs = [];
    if ($cross !== '') {
        $withCross++;
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
            $eqPairs[] = [$eb, $ec];
        }
    }

    $sets = $src + ['oricare' => $union];
    foreach ($sets as $name => $keys) {
        $ownHit = false;
        foreach ($ownPairs as [$b, $c]) {
            if ($hit($keys, $b, $c)) {
                $ownHit = true;
                break;
            }
        }
        $eqHit = false;
        foreach ($eqPairs as [$b, $c]) {
            if ($hit($keys, $b, $c)) {
                $eqHit = true;
                break;
            }
        }
        if ($ownHit) {
            $own[$name]++;
        }
        if ($eqHit) {
            $equiv[$name]++;
        }
        if ($ownHit || $eqHit) {
            $combo[$name]++;
        }
    }
}

$pct = static function (int $n, int $d): string {
    return $d === 0 ? '0,00%' : number_format(100 * $n / $d, 2, ',', '.') . '%';
};

echo "\nProduse TecDoc (baza ta): {$total}\n";
echo "Cu lista de echivalente (art_cross): {$withCross} (" . $pct($withCross, $total) . ")\n\n";
echo str_pad('Sursa', 14) . str_pad('Cod propriu', 22) . str_pad('Echivalente', 22) . str_pad('Propriu SAU echiv.', 22) . "\n";
foreach ($names as $name) {
    echo str_pad($name, 14)
        . str_pad($own[$name] . ' (' . $pct($own[$name], $total) . ')', 22)
        . str_pad($equiv[$name] . ' (' . $pct($equiv[$name], $total) . ')', 22)
        . str_pad($combo[$name] . ' (' . $pct($combo[$name], $total) . ')', 22)
        . "\n";
}
