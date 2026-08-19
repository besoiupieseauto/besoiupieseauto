<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '2048M');

$destRoot = $argv[1] ?? 'C:\\laragon\\backup\\imagine_fisiere_renumite';
$apSrc = 'C:\\Users\\Radu\\Desktop\\autoparner\\IMAGINI_RENUMITE';
$pozeSrc = 'C:\\laragon\\www\\besoiupieseimport\\Poze_RENUMITE';
$atSrc = 'C:\\laragon\\www\\besoiupieseimport\\Autotal';

$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Nu pot crea ' . $dir);
    }
}

function hardlink_or_copy(string $src, string $dst): bool
{
    if (is_file($dst)) {
        return true;
    }
    ensure_dir(dirname($dst));
    if (@link($src, $dst)) {
        return true;
    }

    return @copy($src, $dst);
}

function first_existing(array $paths): ?string
{
    foreach ($paths as $p) {
        if ($p !== '' && is_file($p)) {
            return $p;
        }
    }

    return null;
}

$jobs = [
    'Autopartner' => [
        'sql' => 'SELECT brand, disk_name, rel_path FROM imagine_produse.images',
        'src_root' => $apSrc,
    ],
    'Poze' => [
        'sql' => 'SELECT brand, disk_name, rel_path FROM imagine_poze.images',
        'src_root' => $pozeSrc,
    ],
    'Autotal' => [
        'sql' => 'SELECT brand, disk_name, rel_path FROM imagine_autototal.images WHERE downloaded=1',
        'src_root' => $atSrc,
    ],
];

ensure_dir($destRoot);
$summary = [];

foreach ($jobs as $label => $job) {
    $ok = 0;
    $miss = 0;
    $n = 0;
    echo "Link {$label}...\n";
    foreach ($pdo->query($job['sql'], PDO::FETCH_ASSOC) as $row) {
        $n++;
        $disk = basename((string) $row['disk_name']);
        $brand = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $row['brand'])) ?? '';
        $rel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $row['rel_path']);
        $src = first_existing([
            $job['src_root'] . DIRECTORY_SEPARATOR . $brand . DIRECTORY_SEPARATOR . $disk,
            $job['src_root'] . DIRECTORY_SEPARATOR . $disk,
            $job['src_root'] . DIRECTORY_SEPARATOR . $rel,
            'C:\\laragon\\www\\besoiupieseimport' . DIRECTORY_SEPARATOR . $rel,
            'C:\\Users\\Radu\\Desktop\\autoparner' . DIRECTORY_SEPARATOR . $rel,
        ]);
        if ($src === null) {
            $miss++;
            continue;
        }
        $dst = $brand !== ''
            ? $destRoot . DIRECTORY_SEPARATOR . $label . DIRECTORY_SEPARATOR . $brand . DIRECTORY_SEPARATOR . $disk
            : $destRoot . DIRECTORY_SEPARATOR . $label . DIRECTORY_SEPARATOR . $disk;
        if (hardlink_or_copy($src, $dst)) {
            $ok++;
        } else {
            $miss++;
        }
    }
    $summary[$label] = compact('n', 'ok', 'miss');
    echo "  DB={$n} ok={$ok} lipsa={$miss}\n";
}

$txt = "BACKUP POZE REDENUMITE (BRAND-COD)\r\n"
    . date('Y-m-d H:i:s') . "\r\n\r\n";
foreach ($summary as $label => $s) {
    $txt .= "{$label}: in BD {$s['n']}, copiate {$s['ok']}, lipsa {$s['miss']}\r\n";
}
$txt .= "\r\nPe server dezarhiveaza in:\r\n"
    . "  Autopartner -> AUTOPARTNER_IMAGE_DIR / IMAGINI_RENUMITE\r\n"
    . "  Poze        -> TTC_POZE_RENAMED_DIR\r\n"
    . "  Autotal     -> AUTOTAL_IMAGE_DIR\r\n";
file_put_contents($destRoot . DIRECTORY_SEPARATOR . 'CITESTE-MA.txt', $txt);
echo $txt;
echo "DEST {$destRoot}\n";
