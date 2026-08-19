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
    $brands = [];
    foreach ($pdo->query($sql) as $row) {
        $b = brand_token((string) $row['brand']);
        $c = norm_code((string) $row['code_norm']);
        if ($b !== '' && $c !== '') {
            $keys[$b . '|' . $c] = true;
            $brands[$b] = true;
        }
    }

    return [$keys, $brands];
}

[$union, $libBrands] = load_keys($pdo, "
    SELECT brand, code_norm FROM imagine_produse.images
    UNION
    SELECT brand, code_norm FROM imagine_poze.images
    UNION
    SELECT brand, code_norm FROM imagine_autototal.images WHERE downloaded=1
");

[$atDead, ] = load_keys($pdo, 'SELECT brand, code_norm FROM imagine_autototal.images WHERE downloaded=0');

$hit = static function (array $keys, string $brand, string $code): bool {
    return $brand !== '' && $code !== '' && isset($keys[$brand . '|' . $code]);
};

$gap = 0;
$gapNoCross = 0;
$gapHasCross = 0;
$gapEquivBrandInLib = 0;
$gapDeadAtOwn = 0;
$gapDeadAtEquiv = 0;
$byBrand = [];
$byBrandRecoverable = [];

$sql = 'SELECT b.name AS brand, p.art_code_1, p.art_code_2, p.art_cross
        FROM besoiu_tecdoc_base.products p
        JOIN besoiu_tecdoc_base.brands b ON b.id = p.brand_id';

foreach ($pdo->query($sql, PDO::FETCH_ASSOC) as $row) {
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

    $covered = false;
    foreach (array_merge($ownPairs, $eqPairs) as [$b, $c]) {
        if ($hit($union, $b, $c)) {
            $covered = true;
            break;
        }
    }
    if ($covered) {
        continue;
    }

    $gap++;
    $label = $brand !== '' ? $brand : '(fara-brand)';
    $byBrand[$label] = ($byBrand[$label] ?? 0) + 1;

    if ($eqPairs === []) {
        $gapNoCross++;
    } else {
        $gapHasCross++;
        $equivBrandKnown = false;
        foreach ($eqPairs as [$b]) {
            if (isset($libBrands[$b])) {
                $equivBrandKnown = true;
                break;
            }
        }
        if ($equivBrandKnown) {
            $gapEquivBrandInLib++;
            $byBrandRecoverable[$label] = ($byBrandRecoverable[$label] ?? 0) + 1;
        }
    }

    $deadOwn = false;
    foreach ($ownPairs as [$b, $c]) {
        if ($hit($atDead, $b, $c)) {
            $deadOwn = true;
            break;
        }
    }
    $deadEq = false;
    foreach ($eqPairs as [$b, $c]) {
        if ($hit($atDead, $b, $c)) {
            $deadEq = true;
            break;
        }
    }
    if ($deadOwn) {
        $gapDeadAtOwn++;
    }
    if ($deadEq) {
        $gapDeadAtEquiv++;
    }
}

arsort($byBrand);
arsort($byBrandRecoverable);

$pct = static function (int $n, int $d): string {
    return $d === 0 ? '0,00%' : number_format(100 * $n / $d, 2, ',', '.') . '%';
};

echo "GAP fara nicio imagine: {$gap}\n";
echo "  fara echivalente: {$gapNoCross} (" . $pct($gapNoCross, $gap) . " din gap)\n";
echo "  cu echivalente: {$gapHasCross} (" . $pct($gapHasCross, $gap) . ")\n";
echo "  echivalent din brand DEJA in biblioteci (lipseste SKU-ul): {$gapEquivBrandInLib} (" . $pct($gapEquivBrandInLib, $gap) . ")\n";
echo "  Autototal URL mort pe cod propriu: {$gapDeadAtOwn}\n";
echo "  Autototal URL mort pe echivalent: {$gapDeadAtEquiv}\n\n";

echo "Top 25 branduri FARA imagine (propriu sau echiv):\n";
$i = 0;
foreach ($byBrand as $b => $n) {
    echo '  ' . str_pad($b, 22) . $n . ' (' . $pct($n, $gap) . ")\n";
    if (++$i >= 25) {
        break;
    }
}

echo "\nTop 15 branduri unde un echivalent e deja in biblioteci, dar SKU-ul lipseste:\n";
$i = 0;
foreach ($byBrandRecoverable as $b => $n) {
    echo '  ' . str_pad($b, 22) . $n . "\n";
    if (++$i >= 15) {
        break;
    }
}
