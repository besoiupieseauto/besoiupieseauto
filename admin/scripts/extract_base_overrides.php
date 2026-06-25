<?php
declare(strict_types=1);

$htmlPath = $argv[1] ?? '';
if ($htmlPath === '' || !is_file($htmlPath)) {
    fwrite(STDERR, "Usage: php extract_base_overrides.php <path-to-html>\n");
    exit(1);
}

$html = file_get_contents($htmlPath);
$dataDir = dirname(__DIR__) . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}

if (preg_match('/const DEFAULT_NAME_OVERRIDES_TEXT = `(.*?)`;\s*const DEFAULT_ALLOWED_CAR_BRANDS/s', $html, $m)) {
    file_put_contents($dataDir . '/import_base_name_overrides.txt', trim($m[1]));
    echo "overrides: " . strlen($m[1]) . " bytes\n";
} else {
    echo "overrides: FAIL\n";
    exit(1);
}

if (preg_match('/const DEFAULT_ALLOWED_PART_BRANDS = `(.*?)`;\s*\/\/ Daca avem/s', $html, $m)) {
    file_put_contents($dataDir . '/import_base_allowed_part_brands.txt', trim($m[1]));
    echo "part brands: " . strlen($m[1]) . " bytes\n";
} else {
    echo "part brands: FAIL\n";
    exit(1);
}
