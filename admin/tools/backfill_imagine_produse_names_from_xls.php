<?php

declare(strict_types=1);

/**
 * Umple imagine_produse.products.name din XLS/TecDoc (imagine_poze + besoiu_tecdoc_base).
 * Nu traduce. Doar brand + cod.
 */

set_time_limit(0);
ini_set('memory_limit', '2048M');

$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

function brand_key(string $b): string
{
    $b = strtoupper(trim($b));
    $b = str_replace([' ', '-', '.', '/', '_', ':'], '', $b);

    return preg_replace('/[^A-Z0-9]/', '', $b) ?? '';
}

function code_key(string $c): string
{
    $c = strtoupper(str_replace([' ', '-', '.', '/', '_'], '', trim($c)));

    return preg_replace('/[^A-Z0-9]/', '', $c) ?? '';
}

echo "Incarc denumiri din imagine_poze (XLS TTC)...\n";
$map = [];
foreach ($pdo->query('SELECT brand, code_norm, name FROM imagine_poze.products WHERE IFNULL(name,\'\') <> \'\'') as $row) {
    $key = brand_key((string) $row['brand']) . '|' . code_key((string) $row['code_norm']);
    $name = trim((string) $row['name']);
    if ($key !== '|' && $name !== '') {
        $map[$key] = $name;
    }
}
$fromPoze = count($map);
echo "  {$fromPoze} chei\n";

echo "Incarc denumiri din TecDoc (XLS)...\n";
$td = 0;
$sql = 'SELECT b.name AS brand, t.art_code_1, t.art_code_2, t.art_name
        FROM besoiu_tecdoc_base.products t
        JOIN besoiu_tecdoc_base.brands b ON b.id = t.brand_id
        WHERE IFNULL(t.art_name,\'\') <> \'\'';
foreach ($pdo->query($sql) as $row) {
    $name = trim((string) $row['art_name']);
    $brand = brand_key((string) $row['brand']);
    if ($name === '' || $brand === '') {
        continue;
    }
    foreach ([(string) $row['art_code_1'], (string) $row['art_code_2']] as $code) {
        $ck = code_key($code);
        if ($ck === '') {
            continue;
        }
        $key = $brand . '|' . $ck;
        if (!isset($map[$key])) {
            $map[$key] = $name;
            $td++;
        }
    }
}
echo "  +{$td} chei TecDoc, total " . count($map) . "\n";

echo "Actualizez imagine_produse...\n";
$upd = $pdo->prepare('UPDATE imagine_produse.products SET name = :name WHERE brand = :brand AND code_norm = :code');
$checked = 0;
$changed = 0;
foreach ($pdo->query('SELECT brand, code_norm, name FROM imagine_produse.products') as $row) {
    $checked++;
    $key = brand_key((string) $row['brand']) . '|' . code_key((string) $row['code_norm']);
    $xls = $map[$key] ?? '';
    if ($xls === '' || $xls === (string) $row['name']) {
        continue;
    }
    $upd->execute([
        ':name' => $xls,
        ':brand' => $row['brand'],
        ':code' => $row['code_norm'],
    ]);
    $changed += $upd->rowCount();
}

echo "Verificate {$checked}. Actualizate din XLS: {$changed}.\n";
