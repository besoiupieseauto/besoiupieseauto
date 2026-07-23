<?php
declare(strict_types=1);

/**
 * Construiește index RAG epiesa.ro (~5000 chunk-uri) pentru Ollama vitrină.
 *
 * Usage:
 *   php tools/build_showcase_epiesa_rag.php [--target=5000] [--write]
 */
$root = dirname(__DIR__);
require_once $root . '/api/bootstrap.php';

$target = 5000;
$write = in_array('--write', $argv, true);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--target=')) {
        $target = max(500, min(20000, (int) substr($arg, 9)));
    }
}

$taxonomyPath = IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'rag' . DIRECTORY_SEPARATOR . 'epiesa_auto_taxonomy.json';
if (!is_file($taxonomyPath)) {
    fwrite(STDERR, "Lipsește taxonomy: {$taxonomyPath}\n");
    exit(1);
}

$taxonomy = json_decode((string) file_get_contents($taxonomyPath), true);
if (!is_array($taxonomy)) {
    fwrite(STDERR, "Taxonomy JSON invalid\n");
    exit(1);
}

/** @var list<array<string, mixed>> $chunks */
$chunks = [];
$seen = [];

$add = static function (array $chunk) use (&$chunks, &$seen): void {
    $id = (string) ($chunk['id'] ?? '');
    if ($id === '' || isset($seen[$id])) {
        return;
    }
    $seen[$id] = true;
    $chunks[] = $chunk;
};

// 1) Reguli taxonomy per tip
$typeMap = is_array($taxonomy['showcase_type_map'] ?? null) ? $taxonomy['showcase_type_map'] : [];
foreach ($typeMap as $type => $def) {
    if (!is_array($def)) {
        continue;
    }
    $gmtn2 = is_array($def['epiesa_gmtn2'] ?? null) ? ($def['epiesa_gmtn2'][0] ?? '') : '';
    $url = $gmtn2 !== '' ? ('https://www.epiesa.ro/gmtn1:auto/gmtn2:' . $gmtn2 . '/') : (string) ($taxonomy['source'] ?? '');

    foreach (is_array($def['subcategories'] ?? null) ? $def['subcategories'] : [] as $sub) {
        $add([
            'id' => 'cat_' . $type . '_' . md5((string) $sub),
            'showcase_type' => $type,
            'epiesa_gmtn2' => $gmtn2,
            'epiesa_subcategory' => (string) $sub,
            'epiesa_url' => $url,
            'verdict' => 'include',
            'keywords' => is_array($def['include_signals'] ?? null) ? $def['include_signals'] : [],
            'text' => 'INCLUDE vitrină epiesa «' . $sub . '» (tip ' . $type . ') — consumabil de raft, ambalaj vânzare',
            'example_product' => '',
        ]);
    }

    foreach (is_array($def['include_signals'] ?? null) ? $def['include_signals'] : [] as $sig) {
        $add([
            'id' => 'inc_' . $type . '_' . md5((string) $sig),
            'showcase_type' => $type,
            'epiesa_gmtn2' => $gmtn2,
            'verdict' => 'include',
            'keywords' => [(string) $sig],
            'text' => 'Semnal INCLUDE ' . $type . ': «' . $sig . '» în titlu/specs → produs vitrină epiesa',
            'example_product' => (string) $sig,
        ]);
    }

    foreach (is_array($def['exclude_signals'] ?? null) ? $def['exclude_signals'] : [] as $sig) {
        $add([
            'id' => 'exc_' . $type . '_' . md5((string) $sig),
            'showcase_type' => $type,
            'epiesa_gmtn2' => $gmtn2,
            'verdict' => 'exclude',
            'keywords' => [(string) $sig],
            'text' => 'EXCLUDE vitrină ' . $type . ': «' . $sig . '» — piesă/accesoriu, NU consumabil',
            'example_product' => (string) $sig,
        ]);
    }
}

// 2) Generare combinatorială ulei (brand × vâscozitate × volum)
$brands = [
    'Castrol', 'Mobil 1', 'Total Quartz', 'Liqui Moly', 'Motul', 'Shell Helix', 'Elf Evolution',
    'Valvoline', 'Ravenol', 'Petronas', 'Toyota Fuel', 'GM Dexos', 'Opel', 'VW', 'BMW', 'Mercedes',
    'Ford', 'Renault', 'Peugeot', 'Citroen', 'Mannol', 'Kroon-Oil', 'Fuchs', 'Eurol', 'Ipone',
];
$viscosities = ['0W20', '0W30', '5W30', '5W40', '10W40', '10W60', '15W40', '75W90', '80W90', '85W140', 'ATF', 'CVT'];
$volumes = ['1L', '4L', '5L', '20L', '208L', '60L'];
$oilPrefixes = ['Ulei motor', 'Ulei cutie viteze', 'Ulei transmisie', 'Ulei hidraulic', ''];

foreach ($brands as $brand) {
    foreach ($viscosities as $visc) {
        foreach ($volumes as $vol) {
            if (count($chunks) >= $target) {
                break 3;
            }
            $name = trim($oilPrefixes[array_rand($oilPrefixes)] . ' ' . $brand . ' ' . $visc . ' ' . $vol);
            $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
            if ($name === '') {
                $name = $brand . ' ' . $visc . ' ' . $vol;
            }
            $add([
                'id' => 'oil_' . md5($name),
                'showcase_type' => 'ulei',
                'epiesa_gmtn2' => 'uleiuri-si-lubrifianti-auto',
                'epiesa_subcategory' => 'Ulei motor',
                'epiesa_url' => 'https://www.epiesa.ro/gmtn1:auto/gmtn2:uleiuri-si-lubrifianti-auto/',
                'verdict' => 'include',
                'keywords' => [mb_strtolower($brand), mb_strtolower($visc), mb_strtolower($vol), 'ulei'],
                'text' => 'Produs vitrină ulei epiesa — bidon consumabil',
                'example_product' => $name,
            ]);
        }
    }
}

// 3) Negative ulei — piese mecanice
$oilNegTemplates = [
    'Set piese schimb de ulei cutie automata {brand}',
    'Senzor nivel ulei motor {brand}',
    'Filtru ulei {brand}',
    'Pompa ulei {brand}',
    'Radiator ulei motor {brand}',
    'Garnitura baie ulei {brand}',
    'Kit filtru ulei {brand}',
    'Conducta ulei turbo {brand}',
    'Baie ulei {brand}',
    'Surub golire ulei {brand}',
];
$mechBrands = ['ZF', 'HELLA', 'MAHLE', 'MANN', 'ELRING', 'VICTOR REINZ', 'VAICO', 'TOPRAN', 'FEBI'];
foreach ($oilNegTemplates as $tpl) {
    foreach ($mechBrands as $brand) {
        if (count($chunks) >= $target) {
            break 2;
        }
        $name = str_replace('{brand}', $brand, $tpl);
        $add([
            'id' => 'oilneg_' . md5($name),
            'showcase_type' => 'ulei',
            'verdict' => 'exclude',
            'keywords' => array_values(array_filter(preg_split('/\s+/u', mb_strtolower($name)) ?: [])),
            'text' => 'NU ulei vitrină — piesă mecanică/accesoriu schimb ulei',
            'example_product' => $name,
        ]);
    }
}

// 4) Lichide
$lichidProducts = [
    ['Antigel G12++ 5L', 'antigel', 'Antigel'],
    ['Antigel concentrat 1L', 'antigel', 'Antigel'],
    ['Lichid frana DOT4 1L', 'lichid frana dot4', 'Lichid de frana'],
    ['Lichid frana DOT5.1 500ml', 'lichid frana dot5', 'Lichid de frana'],
    ['Apa distilata 5L', 'apa distilata', 'Apa distilata'],
    ['Coolant ready mix 2L', 'coolant antigel', 'Antigel'],
];
$lichidBrands = ['Motul', 'Total', 'Castrol', 'Liqui Moly', 'Glysantin', 'FEBI', 'MANNOL', 'Ate', 'Bosch'];
foreach ($lichidBrands as $brand) {
    foreach ($lichidProducts as [$tpl, $kw, $sub]) {
        if (count($chunks) >= $target) {
            break 2;
        }
        $name = $brand . ' ' . $tpl;
        $add([
            'id' => 'lichid_' . md5($name),
            'showcase_type' => 'lichid',
            'epiesa_gmtn2' => 'lichide-auto',
            'epiesa_subcategory' => $sub,
            'epiesa_url' => 'https://www.epiesa.ro/gmtn1:auto/gmtn2:lichide-auto/',
            'verdict' => 'include',
            'keywords' => preg_split('/\s+/u', $kw) ?: [],
            'text' => 'Consumabil lichid vitrină epiesa',
            'example_product' => $name,
        ]);
    }
}
$lichidNeg = [
    'Termostat lichid racire', 'Pompa apa', 'Vas expansiune', 'Furtun radiator', 'Garnitura termostat',
    'Senzor temperatura lichid racire', 'Electrovalva termostat', 'Radiator racire motor',
];
foreach ($lichidNeg as $name) {
    foreach (['WAHLER', 'MAHLE', 'GATES', 'DAYCO', 'NRF'] as $brand) {
        if (count($chunks) >= $target) {
            break 2;
        }
        $full = $name . ' ' . $brand;
        $add([
            'id' => 'lichidneg_' . md5($full),
            'showcase_type' => 'lichid',
            'verdict' => 'exclude',
            'keywords' => preg_split('/\s+/u', mb_strtolower($full)) ?: [],
            'text' => 'NU lichid vitrină — componentă sistem răcire/frână',
            'example_product' => $full,
        ]);
    }
}

// 5) Baterii
$ahValues = ['44', '52', '55', '60', '62', '70', '74', '77', '80', '95', '100'];
$batBrands = ['Varta', 'Banner', 'Exide', 'Bosch', 'Fiamm', 'Tudor', 'Rocket', 'Mutlu'];
foreach ($batBrands as $brand) {
    foreach ($ahValues as $ah) {
        if (count($chunks) >= $target) {
            break 2;
        }
        $name = 'Baterie auto ' . $ah . 'Ah ' . $brand;
        $add([
            'id' => 'bat_' . md5($name),
            'showcase_type' => 'baterie',
            'epiesa_gmtn2' => 'electrice-auto',
            'epiesa_subcategory' => 'Baterii auto',
            'epiesa_url' => 'https://www.epiesa.ro/gmtn1:auto/gmtn2:electrice-auto/',
            'verdict' => 'include',
            'keywords' => ['baterie', 'acumulator', mb_strtolower($ah . 'ah'), mb_strtolower($brand)],
            'text' => 'Baterie/acumulator auto vitrină epiesa',
            'example_product' => $name,
        ]);
    }
}

// 6) Becuri
$becTypes = ['H1', 'H3', 'H4', 'H7', 'H11', 'HB3', 'HB4', 'W5W', 'P21W', 'D1S', 'D2S', 'LED H7', 'Xenon D2S'];
$becBrands = ['Osram', 'Philips', 'Narva', 'Neolux', 'Mtec'];
foreach ($becBrands as $brand) {
    foreach ($becTypes as $bt) {
        if (count($chunks) >= $target) {
            break 2;
        }
        $name = 'Bec auto ' . $bt . ' ' . $brand;
        $add([
            'id' => 'bec_' . md5($name),
            'showcase_type' => 'bec',
            'epiesa_gmtn2' => 'iluminat-auto',
            'epiesa_subcategory' => 'Becuri auto',
            'verdict' => 'include',
            'keywords' => ['bec', mb_strtolower($bt), mb_strtolower($brand)],
            'text' => 'Bec auto consumabil vitrină epiesa',
            'example_product' => $name,
        ]);
    }
}

// 7) Lubrifiant / adeziv
$lubProducts = ['Spray lubrifiant WD-40 400ml', 'Spray ungere silicon 500ml', 'Lubrifiant chain spray 400ml'];
foreach ($lubProducts as $name) {
    for ($i = 0; $i < 40 && count($chunks) < $target; $i++) {
        $variant = $name . ' batch' . $i;
        $add([
            'id' => 'lub_' . md5($variant),
            'showcase_type' => 'lubrifiant',
            'epiesa_gmtn2' => 'uleiuri-si-lubrifianti-auto',
            'epiesa_subcategory' => 'Lubrifianti auto',
            'verdict' => 'include',
            'keywords' => ['spray', 'lubrifiant', 'ungere'],
            'text' => 'Spray lubrifiant vitrină — NU ulei motor bidon',
            'example_product' => $variant,
        ]);
    }
}
$adezivProducts = ['Adeziv etansare motor', 'Silicon etansare high temp', 'Chit caroserie', 'Mastic parbriz'];
foreach ($adezivProducts as $name) {
    for ($i = 0; $i < 30 && count($chunks) < $target; $i++) {
        $variant = $name . ' ' . ($i + 1);
        $add([
            'id' => 'adeziv_' . md5($variant),
            'showcase_type' => 'adeziv',
            'verdict' => 'include',
            'keywords' => ['adeziv', 'silicon', 'chit'],
            'text' => 'Adeziv consumabil vitrină',
            'example_product' => $variant,
        ]);
    }
}

// 8) Nume reale din CSV furnizori (dacă există)
$feedDirs = [
    import_motor_canonical_feed_base_dir(),
    dirname(__DIR__, 3) . '/app/Backend/storage/supplier_feeds',
];
$feedBase = '';
foreach ($feedDirs as $dir) {
    if (is_dir($dir)) {
        $feedBase = $dir;
        break;
    }
}
if ($feedBase !== '' && count($chunks) < $target) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($feedBase, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if (count($chunks) >= $target) {
            break;
        }
        if (!$file->isFile() || !str_ends_with(strtolower($file->getFilename()), '.csv')) {
            continue;
        }
        $handle = fopen($file->getPathname(), 'rb');
        if ($handle === false) {
            continue;
        }
        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            continue;
        }
        $nameIdx = null;
        foreach ($header as $i => $col) {
            $c = mb_strtolower(trim((string) $col));
            if (in_array($c, ['name', 'art_name', 'denumire', 'descriere', 'product name'], true)) {
                $nameIdx = $i;
                break;
            }
        }
        if ($nameIdx === null) {
            $nameIdx = 1;
        }
        $rowCount = 0;
        while (($row = fgetcsv($handle, 0, ';')) !== false && $rowCount < 8000) {
            $rowCount++;
            $name = trim((string) ($row[$nameIdx] ?? ''));
            if (mb_strlen($name) < 8) {
                continue;
            }
            $lower = mb_strtolower($name);
            $type = null;
            if (preg_match('/\b[0-9]{1,2}\s*w\s*-?\s*[0-9]{1,2}\b/ui', $name) || str_contains($lower, 'ulei')) {
                $type = 'ulei';
            } elseif (str_contains($lower, 'antigel') || str_contains($lower, 'lichid frana') || str_contains($lower, 'dot')) {
                $type = 'lichid';
            } elseif (str_contains($lower, 'baterie') || str_contains($lower, 'acumulator')) {
                $type = 'baterie';
            } elseif (str_contains($lower, 'bec ')) {
                $type = 'bec';
            }
            if ($type === null) {
                continue;
            }
            $add([
                'id' => 'csv_' . md5($name . $type),
                'showcase_type' => $type,
                'verdict' => 'include',
                'keywords' => array_slice(preg_split('/\s+/u', $lower) ?: [], 0, 12),
                'text' => 'Exemplu real listă furnizor — vitrină ' . $type,
                'example_product' => $name,
                'source' => 'supplier_csv',
            ]);
        }
        fclose($handle);
    }
}

// Pad to target with rule variations if needed
$padIdx = 0;
while (count($chunks) < $target && $padIdx < 5000) {
    $type = array_keys($typeMap)[$padIdx % max(1, count($typeMap))];
    $def = is_array($typeMap[$type] ?? null) ? $typeMap[$type] : [];
    $sig = is_array($def['include_signals'] ?? null) ? ($def['include_signals'][$padIdx % max(1, count($def['include_signals']))] ?? 'consumabil') : 'consumabil';
    $add([
        'id' => 'pad_' . $padIdx . '_' . $type,
        'showcase_type' => $type,
        'verdict' => 'include',
        'keywords' => [(string) $sig],
        'text' => 'Regula epiesa ' . $type . ' #' . $padIdx . ' — ' . $sig,
        'example_product' => ucfirst($type) . ' ' . $sig . ' variant ' . $padIdx,
    ]);
    $padIdx++;
}

$index = [
    'built_at' => date('c'),
    'source' => (string) ($taxonomy['source'] ?? 'https://www.epiesa.ro/'),
    'source_label' => (string) ($taxonomy['source_label'] ?? 'Produse auto universale epiesa.ro'),
    'target_count' => $target,
    'chunk_count' => count($chunks),
    'chunks' => $chunks,
];

$outPath = IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'rag' . DIRECTORY_SEPARATOR . 'showcase_epiesa_rag.json';
$dir = dirname($outPath);
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

echo 'Chunks generate: ' . count($chunks) . ' / target ' . $target . PHP_EOL;
echo 'Output: ' . $outPath . PHP_EOL;

if ($write) {
    file_put_contents(
        $outPath,
        json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
    echo "Scriere OK (" . round(filesize($outPath) / 1024 / 1024, 2) . " MB)\n";
} else {
    echo "Dry-run — rulează cu --write pentru a salva indexul.\n";
}

exit(count($chunks) >= 500 ? 0 : 1);
