<?php

declare(strict_types=1);

set_time_limit(0);
$csv = 'C:/Users/Radu/Downloads/image_brand_codes_by_image_20260817_120909.csv';
$pozeRoot = 'C:/laragon/www/besoiupieseimport';
$out = 'C:/Users/Radu/Desktop/autoparner/POZE_FARA_FISIER.csv';

$in = fopen($csv, 'rb');
$header = fgetcsv($in, 0, ';');
$col = array_flip($header ?: []);
$iPath = $col['file_path'] ?? 2;
$iTtc = $col['ttc_art_id'] ?? 6;
$iBrand = $col['owner_brand'] ?? 8;
$iCode = $col['owner_code'] ?? 9;
$iName = $col['art_name'] ?? 11;

$m = fopen($out, 'wb');
fwrite($m, "file_path;owner_brand;owner_code;ttc_art_id;art_name\n");
$n = 0;
while (($row = fgetcsv($in, 0, ';')) !== false) {
    $rel = trim((string) ($row[$iPath] ?? ''));
    if ($rel === '') {
        continue;
    }
    $abs = $pozeRoot . DIRECTORY_SEPARATOR . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, ltrim($rel, '/\\'));
    if (is_file($abs) && filesize($abs) >= 512) {
        continue;
    }
    fputcsv($m, [
        $rel,
        trim((string) ($row[$iBrand] ?? '')),
        trim((string) ($row[$iCode] ?? '')),
        trim((string) ($row[$iTtc] ?? '')),
        trim((string) ($row[$iName] ?? '')),
    ], ';');
    $n++;
}
fclose($in);
fclose($m);
echo "lipsesc {$n} -> {$out}\n";
