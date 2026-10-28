<?php
declare(strict_types=1);

/**
 * Verificare fișier: Autonet/QWP (ArtNr → brand QWP) sau Autototal
 * (ITEMKEY / ART_ARTICLE_NR + SUP_BRAND → produs TecDoc clonat).
 * Acceptă upload (.xlsx/.csv/.txt) sau fișier deja din bibliotecă.
 */

require_once __DIR__ . '/bootstrap.php';

$qwpEquivLib = __DIR__ . '/lib/ImportQwpEquivMap.php';
if (is_file($qwpEquivLib)) {
    require_once $qwpEquivLib;
}

@ini_set('memory_limit', '1024M');
@set_time_limit(180);
$ipVerifyStartedAt = microtime(true);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_json_response(['success' => false, 'error' => 'Metoda POST obligatorie'], 405);
}

$limit = isset($_POST['limit']) ? max(5, min(120, (int) $_POST['limit'])) : 40;
$sampleRows = isset($_POST['sample_rows']) ? max(50, min(5000, (int) $_POST['sample_rows'])) : 800;
$buildCards = filter_var($_POST['build_cards'] ?? true, FILTER_VALIDATE_BOOLEAN);
$saveToLibrary = filter_var($_POST['save_library'] ?? true, FILTER_VALIDATE_BOOLEAN);

$supplierHint = strtolower(trim((string) ($_POST['supplier'] ?? '')));
$supplierHint = preg_replace('/[^a-z0-9_]+/', '', $supplierHint) ?? '';
if ($supplierHint === '') {
    $supplierHint = 'autonet';
}

$tmpPath = null;
$sourceLabel = '';
$cleanup = false;
$librarySaved = null;

try {
    if (!empty($_FILES['file']) && is_array($_FILES['file'])) {
        $err = (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            import_json_response([
                'success' => false,
                'error' => 'Fișier prea mare pentru upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
            ], 400);
        }
        if ($err === UPLOAD_ERR_OK && !empty($_FILES['file']['tmp_name']) && is_uploaded_file((string) $_FILES['file']['tmp_name'])) {
            $orig = (string) ($_FILES['file']['name'] ?? 'upload.bin');
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'csv', 'txt', 'tsv'], true)) {
                import_json_response(['success' => false, 'error' => 'Format acceptat: .xlsx, .csv, .txt'], 400);
            }
            $tmpPath = sys_get_temp_dir() . '/ip_verify_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file((string) $_FILES['file']['tmp_name'], $tmpPath)) {
                import_json_response(['success' => false, 'error' => 'Nu am putut salva upload-ul'], 500);
            }
            $sourceLabel = $orig;
            $cleanup = true;

            if ($saveToLibrary) {
                $saveSupplier = $supplierHint !== '' ? $supplierHint : 'autonet';
                $origLower = strtolower($orig);
                if (str_contains($origLower, 'autototal')) {
                    $saveSupplier = 'autototal';
                }
                $librarySaved = ip_verify_save_to_library($tmpPath, $orig, $saveSupplier);
                if (is_string($librarySaved) && is_file($librarySaved)) {
                    $sourceLabel = $saveSupplier . '/' . basename($librarySaved);
                    $supplierHint = $saveSupplier;
                }
            }
        }
    }

    if ($tmpPath === null) {
        $supplier = trim((string) ($_POST['supplier'] ?? 'autonet'));
        $filename = trim((string) ($_POST['filename'] ?? ''));
        if ($filename === '') {
            import_json_response(['success' => false, 'error' => 'Încarcă un fișier sau specifică filename din bibliotecă'], 400);
        }
        $path = ip_verify_resolve_library_file($supplier, $filename);
        if ($path === null) {
            import_json_response(['success' => false, 'error' => 'Fișier negăsit în bibliotecă'], 404);
        }
        $tmpPath = $path;
        $sourceLabel = $supplier . '/' . $filename;
    }

    $parsed = ip_verify_parse_file($tmpPath, $sampleRows);
    if (!$parsed['ok']) {
        import_json_response(['success' => false, 'error' => $parsed['error'] ?? 'Parse eșuat'], 400);
    }

    $settings = json_decode(
        (string) @file_get_contents(IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'settings.json'),
        true
    ) ?: [];
    $pdo = import_tecdoc_mysql_pdo();

    $rows = $parsed['rows'];
    $profile = (string) ($parsed['profile'] ?? 'generic_artnr');
    $isAutototal = str_starts_with($profile, 'autototal')
        || $supplierHint === 'autototal'
        || str_contains(strtolower($sourceLabel), 'autototal');
    $lookup = $isAutototal
        ? ip_verify_lookup_own_brands($pdo, $rows, $limit, $profile)
        : ip_verify_lookup_qwp($pdo, $rows, $limit, $profile);
    $cardSupplier = $isAutototal ? 'autototal' : 'autonet';
    $forceBrandDefault = $isAutototal ? '' : 'QWP';

    $cards = [];
    $cardsSkipped = 0;
    $cardsSkippedDetails = [];
    if ($buildCards && $lookup['products'] !== []) {
        require_once __DIR__ . '/lib/ImportFastCardPipeline.php';
        $matchProducts = [];
        foreach ($lookup['products'] as $prod) {
            if (!is_array($prod)) {
                continue;
            }
            $artNr = trim((string) ($prod['art_nr_file'] ?? $prod['art_nr'] ?? ''));
            $productId = (int) ($prod['product_id'] ?? 0);
            if ($artNr === '' || $productId <= 0) {
                continue;
            }
            $priceNet = isset($prod['price_net']) && is_numeric($prod['price_net'])
                ? (float) $prod['price_net']
                : null;
            $refBrand = trim((string) ($prod['reference_brand'] ?? ''));
            $refNr = trim((string) ($prod['ref_nr_raw'] ?? $prod['ref_nr'] ?? ''));
            $equivForTemplate = ($refBrand !== '' && $refNr !== '' && !ip_verify_is_vehicle_oem_brand($refBrand))
                ? $refBrand
                : '';
            $matchProducts[] = [
                'status' => 'exact',
                'match_method' => (string) ($prod['match_method'] ?? 'qwp_artnr_exact'),
                'sku_supplier' => $artNr,
                'art_nr' => $artNr,
                'ref_nr' => $equivForTemplate !== '' ? $refNr : '',
                'brand' => $equivForTemplate,
                'oem_art_nr' => trim((string) ($prod['oem_art_nr'] ?? '')),
                'oem_brand' => $refBrand,
                'oem_code' => $refNr,
                'force_brand' => trim((string) ($prod['brand'] ?? $forceBrandDefault)),
                'matched_brand' => trim((string) ($prod['matched_brand'] ?? $prod['brand'] ?? $forceBrandDefault)),
                'matched_product_id' => $productId,
                'matched_name' => trim((string) ($prod['name'] ?? '')),
                'matched_ean' => trim((string) ($prod['ean'] ?? '')),
                'matched_internal_sku' => 'TEC-' . $productId,
                'price' => $priceNet,
                'price_purchase_net' => $priceNet,
                'price_csv' => $priceNet,
                'supplier' => $cardSupplier,
                'source_file' => $cardSupplier . '/' . basename((string) $sourceLabel),
                'source_filename' => basename((string) $sourceLabel),
            ];
        }
        $enriched = ImportFastCardPipeline::enrichProductsToCards($matchProducts, false, [
            'max_entries' => 60,
            'allow_cross_donor' => false,
            'skip_compat_rebuild' => true,
            'deadline' => $ipVerifyStartedAt + 70,
        ]);
        $cards = is_array($enriched['cards'] ?? null) ? $enriched['cards'] : [];
        $cardsSkipped = (int) ($enriched['skipped'] ?? 0);
        $cardsSkippedDetails = is_array($enriched['skippedDetails'] ?? null) ? $enriched['skippedDetails'] : [];
        $cards = ip_verify_finalize_cards($cards, $lookup['products'], (string) $sourceLabel, $isAutototal);
    }

    $matched = (int) ($lookup['matched_qwp'] ?? 0);
    import_json_response([
        'success' => true,
        'source' => $sourceLabel,
        'library_saved' => $librarySaved,
        'headers' => $parsed['headers'],
        'profile' => $parsed['profile'],
        'mode' => $isAutototal ? 'autototal' : 'qwp',
        'stats' => [
            'rows_read' => $parsed['rows_read'],
            'rows_with_artnr' => $parsed['rows_with_artnr'],
            'unique_artnr' => $lookup['unique_artnr'],
            'matched_qwp' => $matched,
            'matched' => $matched,
            'missing_qwp' => $lookup['missing_qwp'],
            'match_label' => $isAutototal ? 'Match Autototal' : 'Match QWP',
            'match_pct' => $lookup['unique_artnr'] > 0
                ? round(100 * $matched / $lookup['unique_artnr'], 1)
                : 0,
            'cards_built' => count($cards),
            'cards_skipped' => $cardsSkipped,
            'cross_upgraded' => (int) ($lookup['cross_upgraded'] ?? 0),
            'elapsed_ms' => (int) round((microtime(true) - $ipVerifyStartedAt) * 1000),
        ],
        'cards' => $cards,
        'products' => $lookup['products'],
        'missing_sample' => $lookup['missing_sample'],
        'cards_skipped_details' => $cardsSkippedDetails,
        'hint' => ($isAutototal && $cards === []
            ? 'Lista Autototal: primele rânduri sunt adesea fără CODE_ECHIV (CORGHI/OE). Folosește Autototal-branduri-proprii.csv sau crește rândurile.'
            : null),
    ]);
} catch (Throwable $e) {
    import_json_response(['success' => false, 'error' => $e->getMessage()], 500);
} finally {
    if ($cleanup && $tmpPath && is_file($tmpPath)) {
        @unlink($tmpPath);
    }
}

/**
 * @return array<string, mixed>
 */
function ip_verify_parse_file(string $path, int $maxRows): array
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $scanRows = $maxRows + 1;
    if ($ext === 'xlsx') {
        $grid = ip_verify_read_xlsx($path, $scanRows);
    } else {
        $grid = ip_verify_read_delimited($path, $scanRows);
    }
    if ($grid === null || count($grid) < 2) {
        return ['ok' => false, 'error' => 'Fișier gol sau necitibil'];
    }

    $headers = array_map(static function ($h) {
        $h = (string) $h;
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
        return trim($h);
    }, $grid[0]);
    $norm = [];
    foreach ($headers as $i => $h) {
        $norm[$i] = ip_verify_norm_header($h);
    }
    $idxArt = ip_verify_find_col($norm, ['itemkey', 'artarticlenr', 'artnr', 'art_nr', 'codarticol', 'cod_articol', 'sku']);
    $idxOwnBrand = ip_verify_find_col($norm, ['supbrand']);
    $idxEquivBrand = ip_verify_find_col($norm, ['brandsursa', 'supbrandechiv', 'referencebrand', 'reference_brand']);
    $idxBrandFallback = ip_verify_find_col($norm, ['producator', 'brand']);
    $idxRef = ip_verify_find_col($norm, ['codeechiv', 'codsursa', 'refnr', 'ref_nr']);
    $idxPrice = ip_verify_find_col($norm, ['pretunitar', 'pret_unitar', 'price', 'purchaseprice', 'pret']);
    $idxName = ip_verify_find_col($norm, ['denumirearticol', 'denumire_articol', 'artname', 'name', 'denumire']);

    if ($idxArt === null) {
        return [
            'ok' => false,
            'error' => 'Nu găsesc coloana ArtNr / COD ARTICOL. Header: ' . implode(', ', $headers)
                . ' | norm=' . implode(',', $norm),
        ];
    }

    $hasArtNrHeader = in_array('ARTNR', $norm, true);
    $hasRefPair = ($idxEquivBrand !== null || $idxBrandFallback !== null) && $idxRef !== null && $hasArtNrHeader;
    $hasLista = in_array('CODARTICOL', $norm, true) && in_array('PRETUNITAR', $norm, true);
    $hasItemkey = in_array('ITEMKEY', $norm, true);
    $hasArtArticle = in_array('ARTARTICLENR', $norm, true);
    $hasSupBrand = in_array('SUPBRAND', $norm, true);
    if ($hasItemkey && $hasSupBrand) {
        $profile = 'autototal_own';
    } elseif ($hasArtArticle && $hasSupBrand) {
        $profile = 'autototal_lista';
    } elseif ($hasRefPair) {
        $profile = 'autonet_qwp';
    } elseif ($hasLista) {
        $profile = 'autonet_lista';
    } else {
        $profile = 'generic_artnr';
    }

    $rows = [];
    $withArt = 0;
    $rowsRead = max(0, count($grid) - 1);
    for ($i = 1, $n = count($grid); $i < $n; $i++) {
        $r = $grid[$i];
        $art = trim((string) ($r[$idxArt] ?? ''));
        if ($art === '') {
            continue;
        }
        $ownBrand = $idxOwnBrand !== null ? trim((string) ($r[$idxOwnBrand] ?? '')) : '';
        $equivBrand = $idxEquivBrand !== null ? trim((string) ($r[$idxEquivBrand] ?? '')) : '';
        if ($equivBrand === '' && $idxBrandFallback !== null) {
            $equivBrand = trim((string) ($r[$idxBrandFallback] ?? ''));
        }
        // Lista pret Autonet: doar rândurile QWP (COD ARTICOL = ArtNr QWP)
        if ($profile === 'autonet_lista') {
            $listaBrand = strtoupper($ownBrand !== '' ? $ownBrand : $equivBrand);
            if ($listaBrand !== '' && $listaBrand !== 'QWP') {
                continue;
            }
            if ($ownBrand === '') {
                $ownBrand = 'QWP';
            }
        }
        if ($profile === 'autonet_qwp' && $ownBrand === '') {
            $ownBrand = 'QWP';
        }
        // QWP / Autototal: denumiri brand echivalent → TecDoc (TRW_OLD → TRW, etc.)
        if ($equivBrand !== '' && ($profile === 'autonet_qwp' || str_starts_with($profile, 'autototal'))) {
            $equivBrand = ip_verify_map_reference_brand($equivBrand);
        }
        $brand = $equivBrand;
        if (str_starts_with($profile, 'autototal')) {
            $refPeek = $idxRef !== null ? trim((string) ($r[$idxRef] ?? '')) : '';
            $ownK = ip_verify_norm_brand_key($ownBrand);
            if (ip_verify_is_vehicle_oem_brand($equivBrand)) {
                $equivBrand = '';
                $refPeek = '';
            }
            $knownOwn = ip_verify_autototal_own_brand_keys();
            $junkOwn = [
                'AUTOTOTALOE' => true, 'PIESESCHIMBAGRI' => true, 'ORIGINALEAGRI' => true,
                'CORGHI' => true, 'COLADHAMACH' => true, 'CAROSERIEAFTERMARKET' => true,
            ];
            if ($equivBrand === '' && $refPeek === '' && !isset($knownOwn[$ownK]) && isset($junkOwn[$ownK])) {
                continue;
            }
        }
        $price = null;
        if ($idxPrice !== null) {
            $rawPrice = str_replace([',', ' '], ['.', ''], trim((string) ($r[$idxPrice] ?? '')));
            if (is_numeric($rawPrice)) {
                $price = round((float) $rawPrice, 2);
            }
        }
        $withArt++;
        $nameFile = $idxName !== null ? trim((string) ($r[$idxName] ?? '')) : '';
        $refRaw = $idxRef !== null ? trim((string) ($r[$idxRef] ?? '')) : '';
        $refNorm = ip_verify_norm_code($refRaw);
        $rows[] = [
            'art_nr' => $art,
            'art_nr_norm' => ip_verify_norm_code($art),
            'own_brand' => $ownBrand,
            'reference_brand' => $brand,
            'ref_nr' => $refNorm !== '' ? $refNorm : $refRaw,
            'ref_nr_raw' => $refRaw,
            'price_net' => $price,
            'name_file' => $nameFile,
        ];
    }

    return [
        'ok' => true,
        'headers' => $headers,
        'profile' => $profile,
        'rows' => $rows,
        'rows_read' => $rowsRead,
        'rows_with_artnr' => $withArt,
    ];
}

function ip_verify_norm_header(string $h): string
{
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
    $h = trim($h);
    if (function_exists('mb_strtoupper')) {
        $h = mb_strtoupper($h, 'UTF-8');
    } else {
        $h = strtoupper($h);
    }
    $h = preg_replace('/[^A-Z0-9]+/', '', $h) ?? $h;
    return $h;
}

function ip_verify_norm_code(string $code): string
{
    $code = strtoupper(trim($code));
    $code = preg_replace('/\s+/', ' ', $code) ?? $code;
    $compact = preg_replace('/[^A-Z0-9]/', '', $code) ?? $code;
    return $compact;
}

/** Cod afișat: forma TecDoc (art_code_1) când e același articol, nu ITEMKEY-ul cu zerouri/puncte extra. */
function ip_verify_canonical_artnr(string $fileCode, string $tecdocCode): string
{
    $fileCode = trim($fileCode, " \t\"'");
    $tecdocCode = trim($tecdocCode);
    if ($tecdocCode !== '' && ip_verify_norm_code($fileCode) === ip_verify_norm_code($tecdocCode)) {
        return $tecdocCode;
    }
    return $fileCode;
}

/** @return array<string, true> */
function ip_verify_autototal_own_brand_keys(): array
{
    return [
        'DREISSNER' => true, 'WAHLBERG' => true, 'KROTTENDORF' => true, 'BOHRMANN' => true,
        'HARTWINN' => true, 'KALTSTADT' => true, 'ROTTLER' => true, 'KERGEN' => true,
        'HAGER' => true, 'ATTAUTOTOTALTURBO' => true, 'AUTOTOTALPRODUCTS' => true,
        'MADDOX' => true, 'RESIGILATE' => true,
    ];
}

/** Normalize brand key for map lookup (without diacritics). */
function ip_verify_norm_brand_key(string $brand): string
{
    $b = trim($brand);
    if ($b === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $b);
        if (is_string($t) && $t !== '') {
            $b = $t;
        }
    }
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($b)) ?? '';
}

/** CODE_ECHIV de tip marcă auto (nu donor TecDoc de piese). */
function ip_verify_is_vehicle_oem_brand(string $brand): bool
{
    $k = ip_verify_norm_brand_key($brand);
    if ($k === '') {
        return false;
    }
    static $oem = [
        'MERCEDESBENZ' => true, 'MERCEDES' => true, 'MB' => true, 'VW' => true, 'VOLKSWAGEN' => true,
        'AUDI' => true, 'SEAT' => true, 'SKODA' => true, 'VAG' => true, 'FORD' => true,
        'OPEL' => true, 'VAUXHALL' => true, 'BMW' => true, 'MINI' => true, 'RENAULT' => true,
        'DACIA' => true, 'PEUGEOT' => true, 'CITROEN' => true, 'CITROEN' => true, 'DS' => true,
        'FIAT' => true, 'ALFAROMEO' => true, 'LANCIA' => true, 'IVECO' => true, 'VOLVO' => true,
        'TOYOTA' => true, 'LEXUS' => true, 'HONDA' => true, 'NISSAN' => true, 'MAZDA' => true,
        'HYUNDAI' => true, 'KIA' => true, 'SUZUKI' => true, 'MITSUBISHI' => true, 'SUBARU' => true,
        'JEEP' => true, 'CHRYSLER' => true, 'DODGE' => true, 'CHEVROLET' => true, 'GM' => true,
        'PORSCHE' => true, 'LANDROVER' => true, 'JAGUAR' => true, 'SAAB' => true, 'SMART' => true,
        'TESLA' => true, 'MAN' => true, 'SCANIA' => true, 'DAF' => true, 'KTMHUSQVARNA' => true,
    ];

    return isset($oem[$k]);
}

/**
 * Mapare denumiri brand QWP Autonet → TecDoc art_cross (tabel client).
 * @return array<string, string> keys: exact, UPPER, compact
 */
function ip_verify_brand_map(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }
    $map = [];
    $pairs = [
        'FAG' => 'Schaeffler FAG',
        'GKN-Löbro-CN' => 'LÖBRO',
        'GKN-Lobro-CN' => 'LÖBRO',
        'INA' => 'Schaeffler INA',
        'KYB-AU' => 'KYB',
        'KYB-CN' => 'KYB',
        'LuK' => 'Schaeffler LuK',
        'LUK' => 'Schaeffler LuK',
        'TRW_OLD' => 'TRW',
        'SCHAEFFLER GRUPPE' => 'Schaeffler INA',
        'MANN-FILTER' => 'MANN-FILTER',
        'FEBI BILSTEIN' => 'FEBI BILSTEIN',
        'MEAT & DORIA' => 'MEAT & DORIA',
        'AUTOFREN SEINSA' => 'AUTOFREN SEINSA',
        'BTS TURBO' => 'BTS TURBO',
    ];
    $jsonCandidates = [
        dirname(__DIR__) . '/state/qwp-brand-map.json',
        (defined('IMPORT_ROOT') ? IMPORT_ROOT : dirname(__DIR__, 2)) . '/state/qwp-brand-map.json',
        '/var/www/besoiupieseauto.ro/app/Import/MatchingPro/state/qwp-brand-map.json',
    ];
    foreach ($jsonCandidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        $j = json_decode((string) @file_get_contents($path), true);
        if (!is_array($j)) {
            continue;
        }
        $src = $j['by_exact'] ?? $j['pairs'] ?? $j;
        if (isset($j['pairs']) && is_array($j['pairs'])) {
            foreach ($j['pairs'] as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $from = trim((string) ($p['from'] ?? ''));
                $to = trim((string) ($p['to'] ?? ''));
                if ($from !== '' && $to !== '') {
                    $pairs[$from] = $to;
                }
            }
        } elseif (is_array($src)) {
            foreach ($src as $from => $to) {
                if (is_string($from) && (is_string($to) || is_numeric($to))) {
                    $pairs[$from] = (string) $to;
                }
            }
        }
        break;
    }
    foreach ($pairs as $from => $to) {
        $from = trim((string) $from);
        $to = trim((string) $to);
        if ($from === '' || $to === '') {
            continue;
        }
        $map[$from] = $to;
        $map[strtoupper($from)] = $to;
        $k = ip_verify_norm_brand_key($from);
        if ($k !== '') {
            $map[$k] = $to;
        }
    }
    return $map;
}

function ip_verify_map_reference_brand(string $brand): string
{
    $brand = trim($brand);
    if ($brand === '') {
        return '';
    }
    $map = ip_verify_brand_map();
    if (isset($map[$brand])) {
        return $map[$brand];
    }
    $up = strtoupper($brand);
    if (isset($map[$up])) {
        return $map[$up];
    }
    $k = ip_verify_norm_brand_key($brand);
    if ($k !== '' && isset($map[$k])) {
        return $map[$k];
    }
    return $brand;
}

/**
 * @param list<string> $normHeaders
 * @param list<string> $aliases
 */
function ip_verify_find_col(array $normHeaders, array $aliases): ?int
{
    $aliasNorm = [];
    foreach ($aliases as $a) {
        $aliasNorm[] = ip_verify_norm_header((string) $a);
    }
    foreach ($normHeaders as $i => $h) {
        $h = (string) $h;
        if ($h === '') {
            continue;
        }
        foreach ($aliasNorm as $a) {
            if ($a !== '' && $h === $a) {
                return (int) $i;
            }
        }
    }
    return null;
}

/**
 * Salvează upload-ul în biblioteca furnizorului (supplier_feeds).
 */
function ip_verify_save_to_library(string $tmpPath, string $originalName, string $supplier = 'autonet'): ?string
{
    $supplier = strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '', $supplier) ?? ''));
    if ($supplier === '') {
        $supplier = 'autonet';
    }
    $safe = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $originalName));
    $safe = preg_replace('/[^\w.\- ()\[\]]+/u', '_', $safe) ?? $safe;
    if ($safe === '' || $safe === '.' || $safe === '..') {
        $safe = $supplier === 'autototal' ? 'Autototal-upload.csv' : 'Autonet-QWP-upload.xlsx';
    }

    $dirs = [];
    if (function_exists('import_motor_supplier_storage_dirs')) {
        foreach (import_motor_supplier_storage_dirs($supplier) as $dir) {
            $dirs[] = $dir;
        }
    }
    $dirs[] = '/var/www/besoiupieseauto.ro/admin/storage/supplier_feeds/' . $supplier;
    $dirs[] = '/mnt/store/mutable/imports/supplier_feeds/' . $supplier;

    foreach ($dirs as $dir) {
        if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
            continue;
        }
        $dest = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safe;
        if (@copy($tmpPath, $dest)) {
            @chmod($dest, 0664);
            return $dest;
        }
    }
    return null;
}

function ip_verify_save_to_autonet_library(string $tmpPath, string $originalName): ?string
{
    return ip_verify_save_to_library($tmpPath, $originalName, 'autonet');
}

/**
 * Resolve CSV/XLSX from supplier_feeds (xlsx allowed for verification only).
 */
function ip_verify_resolve_library_file(string $supplier, string $filename): ?string
{
    $supplier = strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '', $supplier) ?? ''));
    $filename = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filename));
    if ($supplier === '' || $filename === '' || $filename === '.' || $filename === '..') {
        return null;
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt', 'xlsx', 'tsv'], true)) {
        return null;
    }

    $dirs = [];
    if (function_exists('import_motor_supplier_storage_dirs')) {
        foreach (import_motor_supplier_storage_dirs($supplier) as $dir) {
            $dirs[] = $dir;
        }
    }
    $dirs[] = '/var/www/besoiupieseauto.ro/admin/storage/supplier_feeds/' . $supplier;
    $dirs[] = '/mnt/store/mutable/imports/supplier_feeds/' . $supplier;

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

/**
 * @return list<list<string>>|null
 */
function ip_verify_read_delimited(string $path, int $maxRows): ?array
{
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        return null;
    }
    $first = fgets($fh);
    if ($first === false) {
        fclose($fh);
        return null;
    }
    $delim = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
    rewind($fh);
    $grid = [];
    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
        $grid[] = array_map(static fn($v) => trim((string) $v), $row);
        if (count($grid) >= $maxRows) {
            break;
        }
    }
    fclose($fh);
    return $grid;
}

/**
 * Minimal XLSX reader (sharedStrings + first sheet), enough for Autonet QWP.
 *
 * @return list<list<string>>|null
 */
function ip_verify_read_xlsx(string $path, int $maxRows): ?array
{
    if (!class_exists('ZipArchive')) {
        return null;
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return null;
    }

    $shared = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if (is_string($ssXml) && $ssXml !== '') {
        if (preg_match_all('/<si\b[^>]*>(.*?)<\/si>/s', $ssXml, $sis)) {
            foreach ($sis[1] as $si) {
                if (preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $si, $ts)) {
                    $shared[] = html_entity_decode(implode('', $ts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                } else {
                    $shared[] = '';
                }
            }
        }
    }

    $sheetName = 'xl/worksheets/sheet1.xml';
    $wb = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if (is_string($wb) && is_string($rels) && preg_match('/<sheet[^>]*r:id="([^"]+)"[^>]*>/', $wb, $m)) {
        $rid = $m[1];
        if (preg_match('/Id="' . preg_quote($rid, '/') . '"[^>]*Target="([^"]+)"/', $rels, $tm)) {
            $target = $tm[1];
            if (!str_starts_with($target, '/')) {
                $sheetName = 'xl/' . ltrim($target, '/');
            }
        }
    }

    $sheetXml = $zip->getFromName($sheetName);
    $zip->close();
    if (!is_string($sheetXml) || $sheetXml === '') {
        return null;
    }

    $grid = [];
    if (!preg_match_all('/<row\b[^>]*>(.*?)<\/row>/s', $sheetXml, $rowMatches)) {
        return null;
    }
    foreach ($rowMatches[1] as $rowXml) {
        $cells = [];
        if (preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $rowXml, $cellMatches, PREG_SET_ORDER)) {
            foreach ($cellMatches as $cm) {
                $attrs = $cm[1];
                $inner = $cm[2];
                $col = 0;
                if (preg_match('/\br="([A-Z]+)(\d+)"/', $attrs, $rm)) {
                    $col = ip_verify_col_index($rm[1]);
                }
                $t = '';
                if (preg_match('/\bt="([^"]+)"/', $attrs, $tm)) {
                    $t = $tm[1];
                }
                $v = '';
                if (preg_match('/<v>([^<]*)<\/v>/', $inner, $vm)) {
                    $v = $vm[1];
                } elseif (preg_match('/<t[^>]*>([^<]*)<\/t>/', $inner, $tm2)) {
                    $v = html_entity_decode($tm2[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
                if ($t === 's') {
                    $v = $shared[(int) $v] ?? '';
                }
                $cells[$col] = (string) $v;
            }
        }
        if ($cells === []) {
            continue;
        }
        $maxCol = max(array_keys($cells));
        $row = [];
        for ($i = 0; $i <= $maxCol; $i++) {
            $row[] = trim((string) ($cells[$i] ?? ''));
        }
        $grid[] = $row;
        if (count($grid) >= $maxRows) {
            break;
        }
    }

    return $grid;
}

function ip_verify_col_index(string $letters): int
{
    $n = 0;
    $letters = strtoupper($letters);
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return max(0, $n - 1);
}

/**
 * Cache prețuri Lista pret Autonet.
 * Cheie = ArtNr / COD ARTICOL (= SKU pe cartelă QWP). NICIODATĂ RefNr/OEM.
 * @return array{by_code: array<string, float>, by_norm: array<string, float>}
 */
function ip_verify_load_price_cache(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $cache = ['by_code' => [], 'by_norm' => []];
    $candidates = [
        dirname(__DIR__) . '/state/autonet-qwp-prices.json',
        (defined('IMPORT_ROOT') ? IMPORT_ROOT : dirname(__DIR__, 2)) . '/state/autonet-qwp-prices.json',
        '/var/www/besoiupieseauto.ro/app/Import/MatchingPro/state/autonet-qwp-prices.json',
    ];
    $path = null;
    foreach ($candidates as $cand) {
        if (is_string($cand) && is_file($cand)) {
            $path = $cand;
            break;
        }
    }
    if ($path === null) {
        return $cache;
    }
    $j = json_decode((string) @file_get_contents($path), true);
    if (!is_array($j)) {
        return $cache;
    }
    $map = null;
    foreach (['by_code', 'by_artnr', 'prices', 'data', 'map'] as $k) {
        if (isset($j[$k]) && is_array($j[$k])) {
            $map = $j[$k];
            break;
        }
    }
    if ($map === null) {
        $map = $j;
    }
    if (isset($j['by_norm']) && is_array($j['by_norm'])) {
        foreach ($j['by_norm'] as $k => $v) {
            if (is_numeric($v)) {
                $cache['by_norm'][(string) $k] = round((float) $v, 2);
            } elseif (is_array($v) && isset($v['price']) && is_numeric($v['price'])) {
                $cache['by_norm'][(string) $k] = round((float) $v['price'], 2);
            }
        }
    }
    foreach ($map as $k => $v) {
        $code = trim((string) $k);
        if ($code === '') {
            continue;
        }
        $price = null;
        if (is_numeric($v)) {
            $price = (float) $v;
        } elseif (is_array($v)) {
            foreach (['price', 'price_net', 'net', 'pret', 'pret_unitar'] as $pk) {
                if (isset($v[$pk]) && is_numeric($v[$pk])) {
                    $price = (float) $v[$pk];
                    break;
                }
            }
        }
        if ($price === null) {
            continue;
        }
        $price = round($price, 2);
        $cache['by_code'][$code] = $price;
        $cache['by_code'][strtoupper($code)] = $price;
        $cache['by_norm'][ip_verify_norm_code($code)] = $price;
    }
    return $cache;
}

function ip_verify_lookup_price(string $artNr, ?float $rowPrice = null): ?float
{
    if ($rowPrice !== null && $rowPrice > 0) {
        return round($rowPrice, 2);
    }
    $artNr = trim($artNr);
    if ($artNr === '') {
        return null;
    }
    $cache = ip_verify_load_price_cache();
    if (isset($cache['by_code'][$artNr])) {
        return $cache['by_code'][$artNr];
    }
    $up = strtoupper($artNr);
    if (isset($cache['by_code'][$up])) {
        return $cache['by_code'][$up];
    }
    $norm = ip_verify_norm_code($artNr);
    if ($norm !== '' && isset($cache['by_norm'][$norm])) {
        return $cache['by_norm'][$norm];
    }
    return null;
}

/**
 * Aliasuri: denumire art_cross (client) → nume în tabela brands.
 * @return list<string>
 */
function ip_verify_brand_db_aliases(string $brandName): array
{
    $brandName = trim($brandName);
    if ($brandName === '') {
        return [];
    }
    $aliases = [$brandName];
    $compact = ip_verify_norm_brand_key($brandName);
    $compactMap = [
        'SCHAEFFLERINA' => ['INA', 'Schaeffler INA'],
        'SCHAEFFLERFAG' => ['FAG', 'Schaeffler FAG'],
        'SCHAEFFLERLUK' => ['LUK', 'LuK', 'Schaeffler LuK'],
        'LOBRO' => ['LÖBRO', 'LOBRO', 'GKN'],
        'MAGNETIMARELLI' => ['MAGNETIMARELLI', 'MAGNETI MARELLI'],
        'ACROLCAR' => ['Ac Rolcar', 'AC ROLCAR'],
        'INA' => ['INA'],
        'FAG' => ['FAG'],
        'LUK' => ['LUK', 'LuK'],
        'KYB' => ['KYB'],
    ];
    if (isset($compactMap[$compact])) {
        foreach ($compactMap[$compact] as $a) {
            $aliases[] = $a;
        }
    }
    return array_values(array_unique($aliases));
}

/**
 * Stub TecDoc (0 compat) → template din ART_CROSS pe branduri de piese (VALEO/BOSCH…), fără LIKE.
 *
 * @param array<string, mixed> $stub
 * @return array<string, mixed>|null
 */
function ip_verify_upgrade_stub_from_cross(PDO $pdo, array $stub, string $nameFile = ''): ?array
{
    $pid = (int) ($stub['id'] ?? 0);
    if ($pid <= 0 || !class_exists('ImportQwpEquivMap')) {
        return null;
    }
    $cross = trim((string) ($stub['art_cross'] ?? ''));
    if ($cross === '') {
        try {
            $st = $pdo->prepare('SELECT art_cross FROM products WHERE id = ? LIMIT 1');
            $st->execute([$pid]);
            $cross = trim((string) $st->fetchColumn());
        } catch (Throwable) {
            $cross = '';
        }
    }
    if ($cross === '') {
        return null;
    }
    $refs = [];
    // Format canonic în TecDoc: Brand:Cod (nu „Brand :: Cod”)
    if (class_exists('CoreDbLookup', false) && method_exists('CoreDbLookup', 'parseArtCrossPairs')) {
        foreach (CoreDbLookup::parseArtCrossPairs($cross) as $pair) {
            $b = trim((string) ($pair['brand'] ?? ''));
            $c = trim((string) ($pair['code'] ?? ''));
            if ($b === '' || $c === '' || ip_verify_is_vehicle_oem_brand($b)) {
                continue;
            }
            if (ImportQwpEquivMap::refBrandRank($b) < 70) {
                continue;
            }
            $refs[] = ['brand' => $b, 'ref' => $c];
        }
    } else {
        $norm = preg_replace('/\s*::\s*/u', ':', $cross) ?? $cross;
        $norm = preg_replace('/\s*:\s*/u', ':', $norm) ?? $norm;
        foreach (preg_split('/\s*\|\s*/u', $norm) ?: [] as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk === '' || !str_contains($chunk, ':')) {
                continue;
            }
            [$b, $c] = array_map('trim', explode(':', $chunk, 2));
            if ($b === '' || $c === '' || ip_verify_is_vehicle_oem_brand($b)) {
                continue;
            }
            if (ImportQwpEquivMap::refBrandRank($b) < 70) {
                continue;
            }
            $refs[] = ['brand' => $b, 'ref' => $c];
        }
    }
    if ($refs === []) {
        return null;
    }
    $picked = ImportQwpEquivMap::pickBestFileRef($pdo, $refs, 0, null, false);
    if ($picked === null || (int) ($picked['hit']['compat_count'] ?? 0) <= 0) {
        return null;
    }
    $equivName = trim((string) ($picked['hit']['art_name'] ?? ''));
    if ($nameFile !== '' && $equivName !== '' && ip_verify_piece_names_conflict($nameFile, $equivName)) {
        return null;
    }

    return $picked;
}

function ip_verify_format_equiv_pair(string $brand, string $code): string
{
    if (class_exists('ImportQwpEquivMap')) {
        return ImportQwpEquivMap::formatPair($brand, $code);
    }
    $brand = trim($brand);
    $code = trim($code);
    if ($brand === '' || $code === '') {
        return '';
    }
    return $brand . ':' . $code;
}

/**
 * @param array<string, mixed> $src
 * @return array{score:int,hits:int,row:array<string,mixed>,ref:array<string,mixed>}|null
 */
function ip_verify_best_file_equiv(PDO $pdo, array $src, int $excludeBrandId, string $fallbackBrand, string $fallbackRef, bool $allowCrossDonor = true): ?array
{
    $refs = is_array($src['refs'] ?? null) ? $src['refs'] : [];
    if ($refs === [] && $fallbackBrand !== '' && $fallbackRef !== '') {
        $raw = trim((string) ($src['ref_nr_raw'] ?? $fallbackRef));
        $refs[$fallbackBrand . '|' . ip_verify_norm_code($fallbackRef)] = [
            'reference_brand' => $fallbackBrand,
            'ref_nr' => ip_verify_norm_code($fallbackRef),
            'ref_nr_raw' => $raw !== '' ? $raw : $fallbackRef,
        ];
    }
    if (class_exists('ImportQwpEquivMap')) {
        $normRefs = [];
        foreach ($refs as $ref) {
            if (!is_array($ref)) {
                continue;
            }
            $normRefs[] = [
                'brand' => (string) ($ref['reference_brand'] ?? ''),
                'ref' => trim((string) ($ref['ref_nr_raw'] ?? $ref['ref_nr'] ?? '')),
                'reference_brand' => (string) ($ref['reference_brand'] ?? ''),
                'ref_nr_raw' => trim((string) ($ref['ref_nr_raw'] ?? $ref['ref_nr'] ?? '')),
                'ref_nr' => (string) ($ref['ref_nr'] ?? ''),
            ];
        }
        $picked = ImportQwpEquivMap::pickBestFileRef($pdo, $normRefs, $excludeBrandId, null, $allowCrossDonor);
        if ($picked === null) {
            return null;
        }
        return [
            'score' => (int) ($picked['hit']['score'] ?? 1),
            'hits' => 1,
            'row' => $picked['hit'],
            'ref' => [
                'reference_brand' => $picked['ref']['brand'],
                'ref_nr' => $picked['ref']['ref'],
                'ref_nr_raw' => $picked['ref']['ref'],
            ],
        ];
    }
    $best = null;
    foreach ($refs as $ref) {
        if (!is_array($ref)) {
            continue;
        }
        $code = trim((string) ($ref['ref_nr_raw'] ?? $ref['ref_nr'] ?? ''));
        $cross = ip_verify_find_brand_code_product(
            $pdo,
            (string) ($ref['reference_brand'] ?? ''),
            $code,
            $excludeBrandId
        );
        if ($cross === null) {
            continue;
        }
        $score = (int) ($cross['score'] ?? 1);
        $cand = [
            'score' => $score,
            'hits' => 1,
            'row' => $cross,
            'ref' => $ref,
        ];
        if ($best === null
            || $cand['score'] > $best['score']
            || ($cand['score'] === $best['score'] && (int) ($cross['compat_count'] ?? 0) > (int) ($best['row']['compat_count'] ?? 0))
        ) {
            $best = $cand;
        }
    }
    return $best;
}

/**
 * Găsește produs TecDoc după Brand:Cod echivalent din fișierul QWP.
 * 1) art_code_1/2 al brandului  2) product_codes pe brand  3) donor cu perechea în art_cross.
 * @return array{id:int,art_code_1:string,art_name:string,art_ean:string,brand:string,score:int}|null
 */
function ip_verify_find_brand_code_product(PDO $pdo, string $brandName, string $refNorm, int $excludeBrandId): ?array
{
    if (!function_exists('import_tecdoc_matc_find_card')) {
        $connect = __DIR__ . '/lib/ImportTecdocMysqlConnect.php';
        if (is_file($connect)) {
            require_once $connect;
        }
    }
    if (function_exists('import_tecdoc_matc_find_card') && import_tecdoc_uses_matc_cards($pdo)) {
        return import_tecdoc_matc_find_card($pdo, $brandName, $refNorm);
    }
    if (class_exists('ImportQwpEquivMap')) {
        return ImportQwpEquivMap::findProduct($pdo, $brandName, $refNorm, $excludeBrandId, false);
    }
    return ip_verify_find_brand_code_product_legacy($pdo, $brandName, $refNorm, $excludeBrandId);
}

/**
 * @return array{id:int,art_code_1:string,art_name:string,art_ean:string,brand:string,score:int}|null
 */
function ip_verify_find_brand_code_product_legacy(PDO $pdo, string $brandName, string $refNorm, int $excludeBrandId): ?array
{
    $brandName = trim($brandName);
    $refNorm = ip_verify_norm_code($refNorm);
    if ($brandName === '' || $refNorm === '') {
        return null;
    }
    static $brandIdCache = [];
    static $allBrands = null;

    $resolveBrandId = static function (string $name) use ($pdo, &$brandIdCache, &$allBrands, $excludeBrandId): int {
        $bk = strtoupper($name);
        if (isset($brandIdCache[$bk])) {
            return (int) $brandIdCache[$bk];
        }
        $st = $pdo->prepare('SELECT id FROM brands WHERE name = ? LIMIT 1');
        $st->execute([$name]);
        $id = (int) $st->fetchColumn();
        if ($id <= 0) {
            if ($allBrands === null) {
                $allBrands = [];
                $q = $pdo->query('SELECT id, name FROM brands');
                while ($row = $q->fetch()) {
                    $allBrands[] = $row;
                }
            }
            $want = ip_verify_norm_brand_key($name);
            foreach ($allBrands as $row) {
                if (ip_verify_norm_brand_key((string) $row['name']) === $want) {
                    $id = (int) $row['id'];
                    break;
                }
            }
        }
        if ($id === $excludeBrandId) {
            $id = 0;
        }
        $brandIdCache[$bk] = $id;
        return $id;
    };

    $best = null;
    foreach (ip_verify_brand_db_aliases($brandName) as $alias) {
        $bid = $resolveBrandId($alias);
        if ($bid <= 0) {
            continue;
        }

        // 1) art_code_1 al brandului
        $st = $pdo->prepare("
            SELECT p.id, p.art_code_1, p.art_name, p.art_ean, b.name AS brand
            FROM products p
            JOIN brands b ON b.id = p.brand_id
            WHERE p.brand_id = ?
              AND REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(p.art_code_1)),' ',''),'-',''),'/',''),'.','') = ?
            ORDER BY p.id
            LIMIT 1
        ");
        $st->execute([$bid, $refNorm]);
        $hit = $st->fetch();
        if ($hit) {
            $hit['score'] = 10;
            // bonus dacă denumirea din fișier e cea din art_cross (Schaeffler *)
            if (stripos($brandName, 'Schaeffler') !== false || stripos($brandName, 'LÖBRO') !== false || stripos($brandName, 'LOBRO') !== false) {
                $hit['score'] = 12;
            }
            return $hit;
        }

        // 2) product_codes slab
        $st = $pdo->prepare("
            SELECT p.id, p.art_code_1, p.art_name, p.art_ean, b.name AS brand
            FROM product_codes pc
            JOIN products p ON p.id = pc.product_id
            JOIN brands b ON b.id = p.brand_id
            WHERE pc.code_norm = ? AND p.brand_id = ?
            ORDER BY p.id
            LIMIT 1
        ");
        $st->execute([$refNorm, $bid]);
        $hit = $st->fetch();
        if ($hit && $best === null) {
            $hit['score'] = 1;
            $best = $hit;
        }
    }
    return $best;
}

/**
 * Familia piesei din denumire — ca să nu luăm „Filtru aer” pe un Alternator.
 */
function ip_verify_piece_family(string $name): string
{
    $n = function_exists('mb_strtolower') ? mb_strtolower(trim($name), 'UTF-8') : strtolower(trim($name));
    if ($n === '') {
        return '';
    }
    if (preg_match('/\b(alternator|generator)\b/u', $n)) {
        return 'alternator';
    }
    if (preg_match('/\b(starter|demaror|electromotor)\b/u', $n)) {
        return 'starter';
    }
    if (preg_match('/\bfiltru\b/u', $n) && preg_match('/\baer\b/u', $n)) {
        return 'filtru_aer';
    }
    if (preg_match('/\b(filtru|filter)\b/u', $n) && preg_match('/\b(ulei|oil)\b/u', $n)) {
        return 'filtru_ulei';
    }
    if (preg_match('/\b(filtru|filter)\b/u', $n) && preg_match('/\b(combustibil|motorina|fuel)\b/u', $n)) {
        return 'filtru_combustibil';
    }
    if (preg_match('/\b(filtru|filter)\b/u', $n)) {
        return 'filtru';
    }
    if (preg_match('/\b(placut|placute|brake pad)\b/u', $n)) {
        return 'placute';
    }
    if (preg_match('/\b(disc)\b/u', $n) && preg_match('/\b(frana|brake)\b/u', $n)) {
        return 'disc_frana';
    }
    if (preg_match('/garnitur|gasket/u', $n) && preg_match('/\b(baie|sump|oil pan)\b/u', $n)) {
        return 'garnitura_baie';
    }
    if (preg_match('/garnitur|gasket/u', $n) && preg_match('/admisie|intake/u', $n)) {
        return 'garnitura_admisie';
    }
    if (preg_match('/garnitur|gasket/u', $n) && preg_match('/evacuare|esapament|exhaust/u', $n)) {
        return 'garnitura_evacuare';
    }
    if (preg_match('/\b(condensator|capacitor)\b/u', $n)) {
        return 'condensator';
    }

    return '';
}

function ip_verify_piece_names_conflict(string $a, string $b): bool
{
    $fa = ip_verify_piece_family($a);
    $fb = ip_verify_piece_family($b);

    return $fa !== '' && $fb !== '' && $fa !== $fb;
}

/** @return list<string> */
function ip_verify_qwp_cross_csv_paths(): array
{
    $names = ['Autonet QWP.csv', 'Autonet QWP-update filtrare.csv'];
    $dirs = [
        '/var/www/besoiupieseauto.ro/app/Backend/storage/supplier_feeds/autonet',
        '/var/www/besoiupieseauto.ro/admin/storage/supplier_feeds/autonet',
        '/mnt/store/mutable/imports/supplier_feeds/autonet',
    ];
    if (function_exists('import_motor_supplier_storage_dirs')) {
        foreach (import_motor_supplier_storage_dirs('autonet') as $dir) {
            $dirs[] = $dir;
        }
    }
    $out = [];
    foreach ($dirs as $dir) {
        foreach ($names as $name) {
            $out[] = rtrim((string) $dir, '/\\') . DIRECTORY_SEPARATOR . $name;
        }
    }

    return $out;
}

/**
 * Lista pret n-are ReferenceBrand/RefNr. Le luăm din Autonet QWP.csv (adevărul comercial).
 * Exemplu: WGE482N e Alternator (VALEO 440907), nu stub-ul TecDoc QWP „Filtru aer”.
 *
 * @param array<string, array<string, mixed>> $byNorm
 */
function ip_verify_attach_qwp_cross_refs(array &$byNorm, int $maxArts = 60): int
{
    $need = [];
    foreach ($byNorm as $n => $src) {
        if (!is_array($src)) {
            continue;
        }
        if (($src['refs'] ?? []) === []) {
            $need[(string) $n] = true;
        }
        if (count($need) >= $maxArts) {
            break;
        }
    }
    if ($need === []) {
        return 0;
    }
    $path = null;
    foreach (ip_verify_qwp_cross_csv_paths() as $cand) {
        if (is_file($cand) && is_readable($cand)) {
            $path = $cand;
            break;
        }
    }
    if ($path === null) {
        return 0;
    }
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        return 0;
    }
    $header = fgetcsv($fh, 0, ';');
    unset($header);
    $added = 0;
    $pending = $need;
    while ($pending !== [] && ($row = fgetcsv($fh, 0, ';')) !== false) {
        $art = trim((string) ($row[0] ?? ''));
        $n = ip_verify_norm_code($art);
        if ($n === '' || !isset($need[$n])) {
            continue;
        }
        $brand = ip_verify_map_reference_brand(trim((string) ($row[1] ?? '')));
        $ref = trim((string) ($row[2] ?? ''));
        if ($brand === '' || $ref === '' || strcasecmp($brand, 'QWP') === 0) {
            continue;
        }
        $rn = ip_verify_norm_code($ref);
        if ($rn === '') {
            continue;
        }
        if (class_exists('ImportQwpEquivMap') && ImportQwpEquivMap::refBrandRank($brand) < 70) {
            continue;
        }
        $key = $brand . '|' . $rn;
        if (!isset($byNorm[$n]['refs'][$key])) {
            $byNorm[$n]['refs'][$key] = [
                'reference_brand' => $brand,
                'ref_nr' => $rn,
                'ref_nr_raw' => $ref,
            ];
            $added++;
        }
        $strongCount = 0;
        foreach ($byNorm[$n]['refs'] as $refRow) {
            $rb = (string) ($refRow['reference_brand'] ?? '');
            if (class_exists('ImportQwpEquivMap') && ImportQwpEquivMap::refBrandRank($rb) >= 70) {
                $strongCount++;
            }
        }
        if ($strongCount >= 3) {
            unset($pending[$n]);
        }
    }
    fclose($fh);

    return $added;
}

/**
 * Match QWP pe ArtNr; template TecDoc = Brand:Cod din Autonet QWP.csv (nu stub-ul QWP).
 *
 * @param list<array{art_nr:string,art_nr_norm:string,reference_brand:string,ref_nr:string,price_net?:?float,name_file?:string}> $rows
 * @return array<string, mixed>
 */
function ip_verify_lookup_qwp(PDO $pdo, array $rows, int $limit, string $profile = 'generic_artnr'): array
{
    $byNorm = [];
    foreach ($rows as $r) {
        $n = (string) ($r['art_nr_norm'] ?? '');
        if ($n === '') {
            continue;
        }
        if (!isset($byNorm[$n])) {
            $byNorm[$n] = $r;
            $byNorm[$n]['refs'] = [];
        }
        $bn = trim((string) ($r['reference_brand'] ?? ''));
        $rawRef = trim((string) ($r['ref_nr_raw'] ?? $r['ref_nr'] ?? ''));
        $rn = ip_verify_norm_code($rawRef !== '' ? $rawRef : (string) ($r['ref_nr'] ?? ''));
        if ($bn !== '' && $rn !== '' && strcasecmp($bn, 'QWP') !== 0) {
            $byNorm[$n]['refs'][$bn . '|' . $rn] = [
                'reference_brand' => $bn,
                'ref_nr' => $rn,
                'ref_nr_raw' => $rawRef !== '' ? $rawRef : $rn,
            ];
        }
    }
    try {
        ip_verify_attach_qwp_cross_refs($byNorm, max(20, $limit));
    } catch (Throwable) {
        // fără Autonet QWP.csv rămânem pe match QWP ArtNr
    }
    $unique = count($byNorm);
    if ($unique === 0) {
        return [
            'unique_artnr' => 0,
            'matched_qwp' => 0,
            'missing_qwp' => 0,
            'products' => [],
            'missing_sample' => [],
            'profile' => $profile,
            'cross_upgraded' => 0,
        ];
    }

    $brandId = (int) $pdo->query("SELECT id FROM brands WHERE name='QWP' LIMIT 1")->fetchColumn();
    if ($brandId <= 0) {
        throw new RuntimeException('Brandul QWP lipsește din TecDoc base');
    }

    $hasAnyRefs = false;
    foreach ($byNorm as $src) {
        if (($src['refs'] ?? []) !== []) {
            $hasAnyRefs = true;
            break;
        }
    }
    if ($hasAnyRefs && class_exists('ImportQwpEquivMap')) {
        $prefetch = [];
        $n = 0;
        foreach ($byNorm as $src) {
            if ($n >= $limit) {
                break;
            }
            $n++;
            foreach (is_array($src['refs'] ?? null) ? $src['refs'] : [] as $ref) {
                $prefetch[] = [
                    (string) ($ref['reference_brand'] ?? ''),
                    (string) ($ref['ref_nr_raw'] ?? $ref['ref_nr'] ?? ''),
                ];
            }
            if ($prefetch === [] && trim((string) ($src['reference_brand'] ?? '')) !== '') {
                $prefetch[] = [
                    (string) $src['reference_brand'],
                    (string) ($src['ref_nr_raw'] ?? $src['ref_nr'] ?? ''),
                ];
            }
        }
        if ($prefetch !== []) {
            ImportQwpEquivMap::prefetchFromProductCodes($pdo, $prefetch, $brandId);
        }
    }

    $matched = 0;
    $products = [];
    $missing = [];
    $norms = array_keys($byNorm);
    $foundNorms = [];
    $chunkSize = 200;
    $crossUpgraded = 0;

    for ($offset = 0, $total = count($norms); $offset < $total; $offset += $chunkSize) {
        $chunk = array_slice($norms, $offset, $chunkSize);
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $sql = "
            SELECT p.id, p.art_code_1, p.art_code_2, p.art_name, p.art_ean, p.is_qwp,
                   b.name AS brand,
                   REPLACE(REPLACE(UPPER(TRIM(p.art_code_1)), ' ', ''), '-', '') AS art_norm
            FROM products p
            JOIN brands b ON b.id = p.brand_id
            WHERE b.id = ?
              AND REPLACE(REPLACE(UPPER(TRIM(p.art_code_1)), ' ', ''), '-', '') IN ($ph)
            LIMIT 2000
        ";
        $st = $pdo->prepare($sql);
        $st->execute(array_merge([$brandId], $chunk));
        while ($row = $st->fetch()) {
            $artNorm = (string) ($row['art_norm'] ?? '');
            if ($artNorm === '' || isset($foundNorms[$artNorm])) {
                continue;
            }
            $foundNorms[$artNorm] = true;
            $matched++;
            if (count($products) >= $limit) {
                continue;
            }
            $src = $byNorm[$artNorm] ?? [
                'art_nr' => (string) $row['art_code_1'],
                'reference_brand' => '',
                'ref_nr' => '',
                'price_net' => null,
                'refs' => [],
            ];
            $artFile = trim((string) ($src['art_nr'] ?? ''));
            if ($artFile === '') {
                $artFile = trim((string) $row['art_code_1']);
            }
            $rowPrice = isset($src['price_net']) && is_numeric($src['price_net'])
                ? (float) $src['price_net']
                : null;
            $nameFile = trim((string) ($src['name_file'] ?? ''));
            $nameTecdoc = trim((string) ($row['art_name'] ?? ''));
            $productId = (int) $row['id'];
            $matchedBrand = 'QWP';
            $matchMethod = 'qwp_artnr_exact';
            $ean = (string) ($row['art_ean'] ?? '');
            $refUsed = (string) ($src['ref_nr'] ?? '');
            $refBrandUsed = (string) ($src['reference_brand'] ?? '');
            $refRawUsed = trim((string) ($src['ref_nr_raw'] ?? $refUsed));
            $oemArt = $artFile;

            // Template = Brand:Cod din Autonet QWP.csv — nu stub-ul QWP (ex. WGE482N „Filtru aer” vs Alternator).
            $nameConflict = ip_verify_piece_names_conflict($nameFile, $nameTecdoc);
            $best = null;
            $srcEquiv = $src;
            if (class_exists('ImportQwpEquivMap') && is_array($src['refs'] ?? null)) {
                $strong = [];
                foreach ($src['refs'] as $rk => $refRow) {
                    if (!is_array($refRow)) {
                        continue;
                    }
                    $rb = (string) ($refRow['reference_brand'] ?? '');
                    if (ImportQwpEquivMap::refBrandRank($rb) >= 70) {
                        $strong[$rk] = $refRow;
                    }
                }
                if ($strong !== []) {
                    $srcEquiv['refs'] = $strong;
                }
            }
            if (($srcEquiv['refs'] ?? []) !== [] || $refBrandUsed !== '') {
                $best = ip_verify_best_file_equiv($pdo, $srcEquiv, $brandId, $refBrandUsed, $refUsed, false);
            }
            if ($best !== null) {
                $cross = $best['row'];
                $ref = $best['ref'];
                $equivName = trim((string) ($cross['art_name'] ?? ''));
                $useEquiv = true;
                if ($nameFile !== '' && $equivName !== '' && ip_verify_piece_names_conflict($nameFile, $equivName)) {
                    $useEquiv = false;
                }
                if ($useEquiv) {
                    $productId = (int) $cross['id'];
                    $nameTecdoc = $equivName !== '' ? $equivName : $nameTecdoc;
                    $refBrandUsed = trim((string) ($ref['reference_brand'] ?? $refBrandUsed));
                    $refRawUsed = trim((string) ($ref['ref_nr_raw'] ?? $ref['ref_nr'] ?? $refRawUsed));
                    $refUsed = $refRawUsed !== '' ? $refRawUsed : (string) ($ref['ref_nr'] ?? $refUsed);
                    $matchedBrand = $refBrandUsed !== '' ? $refBrandUsed : (string) ($cross['brand'] ?? 'QWP');
                    $ean = (string) ($cross['art_ean'] ?? $ean);
                    $matchMethod = 'file_equiv_brand_code';
                    $crossUpgraded++;
                    $nameConflict = false;
                }
            }
            if ($nameConflict) {
                unset($foundNorms[$artNorm]);
                $matched--;
                continue;
            }
            $oemArt = ip_verify_format_equiv_pair($refBrandUsed, $refRawUsed !== '' ? $refRawUsed : $refUsed);
            if ($oemArt === '') {
                $oemArt = $artFile;
            }

            // Lista pret: denumirea din fișier e sursa de adevăr pentru titlu
            $name = ($profile === 'autonet_lista' && $nameFile !== '') ? $nameFile : $nameTecdoc;
            if ($name === '') {
                $name = $nameTecdoc !== '' ? $nameTecdoc : $artFile;
            }
            $products[] = [
                'product_id' => $productId,
                'brand' => 'QWP',
                'matched_brand' => $matchedBrand,
                'art_nr' => $artFile,
                'art_nr_tecdoc' => (string) $row['art_code_1'],
                'art_nr_file' => $artFile,
                'name' => $name,
                'name_file' => $nameFile,
                'name_tecdoc' => $nameTecdoc,
                'ean' => $ean,
                'reference_brand' => $refBrandUsed,
                'ref_nr' => $refUsed,
                'ref_nr_raw' => $refRawUsed,
                'oem_art_nr' => $oemArt,
                'price_net' => ip_verify_lookup_price($artFile, $rowPrice),
                'match_method' => $matchMethod,
                'is_qwp' => 1,
                'profile' => $profile,
            ];
        }
    }

    if ($hasAnyRefs) {
        foreach ($byNorm as $n => $src) {
            if (isset($foundNorms[$n])) {
                continue;
            }
            if (count($products) >= $limit) {
                break;
            }
            $best = ip_verify_best_file_equiv(
                $pdo,
                $src,
                $brandId,
                (string) ($src['reference_brand'] ?? ''),
                (string) ($src['ref_nr'] ?? ''),
                false
            );
            if ($best === null) {
                continue;
            }
            $foundNorms[$n] = true;
            $matched++;
            $crossUpgraded++;
            if (count($products) >= $limit) {
                continue;
            }
            $cross = $best['row'];
            $ref = $best['ref'];
            $artFile = trim((string) ($src['art_nr'] ?? ''));
            $refBrandUsed = trim((string) ($ref['reference_brand'] ?? $src['reference_brand'] ?? ''));
            $refRawUsed = trim((string) ($ref['ref_nr_raw'] ?? $ref['ref_nr'] ?? $src['ref_nr_raw'] ?? $src['ref_nr'] ?? ''));
            $nameFile = trim((string) ($src['name_file'] ?? ''));
            $nameTecdoc = trim((string) ($cross['art_name'] ?? ''));
            $rowPrice = isset($src['price_net']) && is_numeric($src['price_net'])
                ? (float) $src['price_net']
                : null;
            $oemArt = ip_verify_format_equiv_pair($refBrandUsed, $refRawUsed);
            $products[] = [
                'product_id' => (int) $cross['id'],
                'brand' => 'QWP',
                'matched_brand' => $refBrandUsed !== '' ? $refBrandUsed : (string) ($cross['brand'] ?? ''),
                'art_nr' => $artFile,
                'art_nr_tecdoc' => (string) ($cross['art_code_1'] ?? ''),
                'art_nr_file' => $artFile,
                'name' => $nameTecdoc !== '' ? $nameTecdoc : ($nameFile !== '' ? $nameFile : $artFile),
                'name_file' => $nameFile,
                'name_tecdoc' => $nameTecdoc,
                'ean' => (string) ($cross['art_ean'] ?? ''),
                'reference_brand' => $refBrandUsed,
                'ref_nr' => $refRawUsed,
                'ref_nr_raw' => $refRawUsed,
                'oem_art_nr' => $oemArt !== '' ? $oemArt : $artFile,
                'price_net' => ip_verify_lookup_price($artFile, $rowPrice),
                'match_method' => 'file_equiv_brand_code',
                'is_qwp' => 1,
                'profile' => $profile,
            ];
        }
    }

    foreach ($byNorm as $n => $src) {
        if (!isset($foundNorms[$n]) && count($missing) < 40) {
            $missing[] = [
                'art_nr' => $src['art_nr'],
                'reference_brand' => $src['reference_brand'] ?? '',
                'ref_nr' => $src['ref_nr'] ?? '',
            ];
        }
    }

    return [
        'unique_artnr' => $unique,
        'matched_qwp' => $matched,
        'missing_qwp' => max(0, $unique - $matched),
        'products' => $products,
        'missing_sample' => $missing,
        'profile' => $profile,
        'cross_upgraded' => $crossUpgraded,
    ];
}

/**
 * Match Autototal branduri proprii — același model ca QWP:
 * identitate = SUP_BRAND + ITEMKEY; template TecDoc = BRAND_SURSA/SUP_BRAND_ECHIV + COD_SURSA/CODE_ECHIV.
 * Fallback: produsul propriu în TecDoc (art_code_1/2) dacă există.
 *
 * @param list<array{art_nr:string,art_nr_norm:string,own_brand?:string,reference_brand:string,ref_nr:string,price_net?:?float,name_file?:string}> $rows
 * @return array<string, mixed>
 */
function ip_verify_lookup_own_brands(PDO $pdo, array $rows, int $limit, string $profile = 'autototal_lista'): array
{
    $byKey = [];
    foreach ($rows as $r) {
        $n = (string) ($r['art_nr_norm'] ?? '');
        $own = trim((string) ($r['own_brand'] ?? ''));
        if ($n === '') {
            continue;
        }
        $key = ip_verify_norm_brand_key($own) . ':' . $n;
        if (!isset($byKey[$key])) {
            $byKey[$key] = $r;
            $byKey[$key]['refs'] = [];
        }
        $bn = trim((string) ($r['reference_brand'] ?? ''));
        $rawRef = trim((string) ($r['ref_nr_raw'] ?? $r['ref_nr'] ?? ''));
        $rn = ip_verify_norm_code($rawRef !== '' ? $rawRef : (string) ($r['ref_nr'] ?? ''));
        if ($bn !== '' && $rn !== '' && ip_verify_norm_brand_key($bn) !== ip_verify_norm_brand_key($own)) {
            $byKey[$key]['refs'][$bn . '|' . $rn] = [
                'reference_brand' => $bn,
                'ref_nr' => $rn,
                'ref_nr_raw' => $rawRef !== '' ? $rawRef : $rn,
            ];
        }
    }
    $unique = count($byKey);
    if ($unique === 0) {
        return [
            'unique_artnr' => 0,
            'matched_qwp' => 0,
            'missing_qwp' => 0,
            'products' => [],
            'missing_sample' => [],
            'profile' => $profile,
            'cross_upgraded' => 0,
        ];
    }

    if (class_exists('ImportQwpEquivMap')) {
        $prefetch = [];
        $n = 0;
        foreach ($byKey as $src) {
            if ($n >= $limit) {
                break;
            }
            $n++;
            $rowRefs = is_array($src['refs'] ?? null) ? $src['refs'] : [];
            foreach ($rowRefs as $ref) {
                $prefetch[] = [
                    (string) ($ref['reference_brand'] ?? ''),
                    (string) ($ref['ref_nr_raw'] ?? $ref['ref_nr'] ?? ''),
                ];
            }
            if ($rowRefs === [] && trim((string) ($src['reference_brand'] ?? '')) !== '') {
                $ownK = ip_verify_norm_brand_key((string) ($src['own_brand'] ?? ''));
                $eqK = ip_verify_norm_brand_key((string) $src['reference_brand']);
                if ($eqK !== '' && $eqK !== $ownK) {
                    $prefetch[] = [
                        (string) $src['reference_brand'],
                        (string) ($src['ref_nr_raw'] ?? $src['ref_nr'] ?? ''),
                    ];
                }
            }
        }
        if ($prefetch !== []) {
            ImportQwpEquivMap::prefetchFromProductCodes($pdo, $prefetch, 0);
        }
    }

    $matched = 0;
    $products = [];
    $missing = [];
    $seenKey = [];
    $crossUpgraded = 0;
    foreach ($byKey as $key => $src) {
        if (isset($seenKey[$key])) {
            continue;
        }
        if (count($products) >= $limit) {
            break;
        }
        $ownBrand = trim((string) ($src['own_brand'] ?? ''));
        $artFile = trim((string) ($src['art_nr'] ?? ''));
        $n = (string) ($src['art_nr_norm'] ?? '');
        $equivBrand = trim((string) ($src['reference_brand'] ?? ''));
        $equivCode = trim((string) ($src['ref_nr_raw'] ?? $src['ref_nr'] ?? ''));
        $oemBrandDisp = $equivBrand;
        $oemCodeDisp = $equivCode;
        $equivIsVehicle = ip_verify_is_vehicle_oem_brand($equivBrand);
        if ($equivIsVehicle) {
            $equivBrand = '';
            $equivCode = '';
        }
        $ownPick = null;
        $ownCompat = 0;
        $best = null;
        $nameFile = trim((string) ($src['name_file'] ?? ''));
        if ($equivBrand !== '' && $equivCode !== ''
            && ip_verify_norm_brand_key($equivBrand) !== ip_verify_norm_brand_key($ownBrand)
        ) {
            $best = ip_verify_best_file_equiv($pdo, $src, 0, $equivBrand, $equivCode, false);
        }
        if ($best === null && $ownBrand !== '' && $n !== '' && class_exists('ImportQwpEquivMap')) {
            try {
                $ownHit = ImportQwpEquivMap::findProduct($pdo, $ownBrand, $artFile !== '' ? $artFile : $n, 0, false);
                if (is_array($ownHit) && (int) ($ownHit['id'] ?? 0) > 0) {
                    $ownPick = $ownHit;
                    $ownCompat = (int) ($ownHit['compat_count'] ?? 0);
                }
            } catch (Throwable) {
                $ownPick = null;
            }
        }

        $pick = null;
        $method = 'own_artnr_exact';
        $refBrandUsed = $oemBrandDisp;
        $refRawUsed = $oemCodeDisp;
        if ($best !== null && (int) ($best['row']['id'] ?? 0) > 0) {
            $pick = $best['row'];
            $ref = $best['ref'];
            $refBrandUsed = trim((string) ($ref['reference_brand'] ?? $equivBrand));
            $refRawUsed = trim((string) ($ref['ref_nr_raw'] ?? $ref['ref_nr'] ?? $equivCode));
            $method = 'file_equiv_brand_code';
            $crossUpgraded++;
        } elseif (is_array($ownPick) && (int) ($ownPick['id'] ?? 0) > 0 && $ownCompat > 0) {
            $pick = $ownPick;
            $method = 'own_artnr_exact';
        } elseif (is_array($ownPick) && (int) ($ownPick['id'] ?? 0) > 0) {
            $up = ip_verify_upgrade_stub_from_cross($pdo, $ownPick, $nameFile);
            if ($up !== null && (int) ($up['hit']['id'] ?? 0) > 0) {
                $pick = $up['hit'];
                if ($oemBrandDisp === '' || $oemCodeDisp === '') {
                    $refBrandUsed = trim((string) ($up['ref']['brand'] ?? ''));
                    $refRawUsed = trim((string) ($up['ref']['ref'] ?? ''));
                }
                $method = 'own_stub_cross_donor';
                $crossUpgraded++;
            }
        }

        if ($pick === null) {
            if (count($missing) < 40) {
                $missing[] = [
                    'art_nr' => $artFile,
                    'own_brand' => $ownBrand,
                    'reference_brand' => $equivBrand,
                    'ref_nr' => $equivCode,
                ];
            }
            continue;
        }

        $pid = (int) ($pick['id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $nameTecdoc = trim((string) ($pick['art_name'] ?? ''));
        if ($nameFile !== '' && $nameTecdoc !== '' && ip_verify_piece_names_conflict($nameFile, $nameTecdoc)) {
            if (count($missing) < 40) {
                $missing[] = [
                    'art_nr' => $artFile,
                    'own_brand' => $ownBrand,
                    'reference_brand' => $oemBrandDisp,
                    'ref_nr' => $oemCodeDisp,
                ];
            }
            continue;
        }
        $seenKey[$key] = true;
        $matched++;
        if (count($products) >= $limit) {
            continue;
        }
        $identityBrand = $ownBrand !== '' ? $ownBrand : trim((string) ($pick['brand'] ?? ''));
        $donorBrand = trim((string) ($pick['brand'] ?? $refBrandUsed));
        $rowPrice = isset($src['price_net']) && is_numeric($src['price_net']) ? (float) $src['price_net'] : null;
        $tecdocCode = trim((string) ($pick['art_code_1'] ?? ''));
        $isOwnLabel = isset(ip_verify_autototal_own_brand_keys()[ip_verify_norm_brand_key($ownBrand)]);
        $displayArt = $isOwnLabel ? $artFile : ip_verify_canonical_artnr($artFile, $tecdocCode);
        $oemArt = ip_verify_format_equiv_pair($refBrandUsed, $refRawUsed);
        if ($oemArt === '') {
            $oemArt = $isOwnLabel ? ($identityBrand . ':' . $artFile) : '';
        }
        $products[] = [
            'product_id' => $pid,
            'brand' => $identityBrand,
            'matched_brand' => $donorBrand !== '' ? $donorBrand : $identityBrand,
            'art_nr' => $displayArt,
            'art_nr_tecdoc' => $tecdocCode,
            'art_nr_file' => $displayArt,
            'itemkey' => $artFile,
            'name' => $nameFile !== '' ? $nameFile : $nameTecdoc,
            'name_file' => $nameFile,
            'name_tecdoc' => $nameTecdoc,
            'ean' => (string) ($pick['art_ean'] ?? ''),
            'reference_brand' => $refBrandUsed,
            'ref_nr' => $refRawUsed,
            'ref_nr_raw' => $refRawUsed,
            'oem_art_nr' => $oemArt,
            'price_net' => $rowPrice,
            'match_method' => $method,
            'is_qwp' => 0,
            'profile' => $profile,
        ];
    }

    return [
        'unique_artnr' => $unique,
        'matched_qwp' => $matched,
        'missing_qwp' => max(0, $unique - $matched),
        'products' => $products,
        'missing_sample' => $missing,
        'profile' => $profile,
        'cross_upgraded' => $crossUpgraded,
    ];
}

/**
 * Forțează pe cartelă: SKU = ArtNr, OEM = ArtNr, preț achiziție.
 *
 * @param list<array<string,mixed>> $cards
 * @param list<array<string,mixed>> $products
 * @return list<array<string,mixed>>
 */
function ip_verify_finalize_cards(array $cards, array $products, string $sourceLabel, bool $autototal = false): array
{
    $bySku = [];
    $byPid = [];
    foreach ($products as $p) {
        if (!is_array($p)) {
            continue;
        }
        $art = trim((string) ($p['art_nr_file'] ?? $p['art_nr'] ?? ''));
        $pid = (int) ($p['product_id'] ?? 0);
        if ($art !== '') {
            $bySku[ip_verify_norm_code($art)] = $p;
            $bySku[strtoupper($art)] = $p;
        }
        if ($pid > 0) {
            $byPid[$pid] = $p;
        }
    }

    $out = [];
    foreach ($cards as $i => $card) {
        if (!is_array($card)) {
            continue;
        }
        $sku = trim((string) ($card['sku'] ?? $card['skuSupplier'] ?? $card['art_nr'] ?? ''));
        $pid = (int) ($card['productId'] ?? $card['matched_product_id'] ?? $card['tecdocId'] ?? 0);
        $prod = ($pid > 0 ? ($byPid[$pid] ?? null) : null)
            ?? $bySku[ip_verify_norm_code($sku)]
            ?? $bySku[strtoupper($sku)]
            ?? ($products[$i] ?? null);
        $artNr = trim((string) ($prod['art_nr_file'] ?? $prod['art_nr'] ?? $sku));
        if ($artNr === '') {
            $artNr = $sku;
        }
        $price = isset($prod['price_net']) && is_numeric($prod['price_net'])
            ? (float) $prod['price_net']
            : ip_verify_lookup_price($artNr, null);

        $brand = $autototal
            ? trim((string) ($prod['brand'] ?? $card['brand'] ?? ''))
            : 'QWP';
        if ($brand === '') {
            $brand = 'QWP';
        }
        $srcSupplier = $autototal ? 'autototal' : 'autonet';
        $refBrand = trim((string) ($prod['reference_brand'] ?? ''));
        $refNr = trim((string) ($prod['ref_nr_raw'] ?? $prod['ref_nr'] ?? ''));
        $oemPair = ip_verify_format_equiv_pair($refBrand, $refNr);
        $oem = $autototal
            ? trim((string) ($prod['oem_art_nr'] ?? ($brand . ':' . $artNr)))
            : ($oemPair !== '' ? $oemPair : trim((string) ($prod['oem_art_nr'] ?? $artNr)));
        $card['brand'] = $brand;
        $card['pBrand'] = $brand;
        $card['force_brand'] = $brand;
        $card['forceBrand'] = $brand;
        $card['supplier'] = $card['supplier'] ?? ($autototal ? 'AUTOTOTAL' : 'AUTONET');
        $card['sourceSupplier'] = $srcSupplier;
        $card['sku'] = $artNr;
        $card['skuSupplier'] = $artNr;
        $card['artNr'] = $artNr;
        $card['art_nr'] = $artNr;
        $card['oem'] = $oem !== '' ? $oem : $artNr;
        $card['oemCode'] = $refNr !== '' ? $refNr : $artNr;
        $card['oemArtNr'] = $oem !== '' ? $oem : $artNr;
        $card['pOem'] = $oem !== '' ? $oem : $artNr;
        $card['matchStatus'] = $card['matchStatus'] ?: 'exact';
        $card['matchMethod'] = (string) ($prod['match_method'] ?? ($autototal ? 'own_artnr_exact' : 'qwp_artnr_exact'));
        if (empty($card['sourceFile'])) {
            $card['sourceFile'] = $srcSupplier . '/' . basename($sourceLabel);
        }

        if ($refNr !== '') {
            $card['refNr'] = $refNr;
            $card['referenceBrand'] = $refBrand;
            $card['equivPair'] = $oemPair !== '' ? $oemPair : ($refBrand . ':' . $refNr);
            $card['oemCodes'] = array_values(array_unique(array_filter([
                $oemPair !== '' ? $oemPair : ($refBrand !== '' ? ($refBrand . ':' . $refNr) : $refNr),
                $artNr,
            ])));
        } else {
            $card['oemCodes'] = $oem !== '' && $oem !== $artNr ? [$oem, $artNr] : [$artNr];
        }

        if ($price !== null && $price > 0) {
            $vat = 21.0;
            $withVat = round($price * (1 + $vat / 100), 2);
            $card['price'] = $price;
            $card['priceNet'] = $price;
            $card['pricePurchase'] = $price;
            $card['pricePurchaseNet'] = $price;
            $card['purchasePrice'] = $price;
            $card['priceCsv'] = $price;
            $card['priceWithVat'] = $withVat;
            $card['vatRate'] = $vat;
            $card['hasPrice'] = true;
        } else {
            $card['hasPrice'] = false;
        }

        // Titlu: denumirea din fișier (Autonet/Autototal), nu generic TecDoc greșit.
        $nameFile = trim((string) ($prod['name_file'] ?? ''));
        $namePref = trim((string) ($prod['name'] ?? ''));
        $title = trim((string) ($card['title'] ?? $card['name'] ?? ''));
        $titleSuffix = $autototal ? $brand : 'QWP';
        if ($nameFile !== '') {
            $title = trim($nameFile . ' ' . $titleSuffix);
        } elseif ($namePref !== '' && ($title === '' || stripos($title, $namePref) === false)) {
            if ($title === '' || preg_match('/\(Cod:\s*[^)]+\)/i', $title) || stripos($title, $artNr) !== false) {
                $title = trim($namePref . ' ' . $titleSuffix);
            }
        }
        if ($title !== '' && $artNr !== '' && stripos($title, $artNr) === false) {
            if (preg_match('/\(Cod:\s*[^)]+\)/i', $title)) {
                $title = preg_replace('/\(Cod:\s*[^)]+\)/i', '(Cod: ' . $artNr . ')', $title) ?? $title;
            } else {
                $title .= ' (Cod: ' . $artNr . ')';
            }
        }
        if ($title !== '') {
            $card['title'] = $title;
            $card['name'] = $title;
        }
        $card['nameFile'] = $nameFile;
        $card['name_file'] = $nameFile;
        $card['ownBrand'] = $brand;
        $card['identityBrand'] = $brand;
        $card = ip_verify_apply_formation_titles($card);
        $formed = trim((string) ($card['title'] ?? ''));
        if ($nameFile !== '' && $formed !== '' && ip_verify_piece_names_conflict($nameFile, $formed)) {
            $veh = '';
            if (preg_match('/\s+pentru\s+.+$/iu', $formed, $m)) {
                $veh = $m[0];
            }
            $card['title'] = trim($nameFile . ' ' . $titleSuffix . ($artNr !== '' ? ' ' . $artNr : '') . $veh);
            $card['name'] = $card['title'];
            $card['titleWebsite'] = $card['title'];
        }

        $out[] = $card;
    }
    return $out;
}

/**
 * Titluri website / marketplace din ProductCardFormationService (QWP + Autototal).
 *
 * @param array<string, mixed> $card
 * @return array<string, mixed>
 */
function ip_verify_apply_formation_titles(array $card): array
{
    $svcFile = dirname(__DIR__, 3) . '/Backend/src/Services/ProductCardFormationService.php';
    if (!class_exists(\Besoiu\Services\ProductCardFormationService::class) && is_file($svcFile)) {
        require_once $svcFile;
    }
    if (!class_exists(\Besoiu\Services\ProductCardFormationService::class)) {
        return $card;
    }
    try {
        $nameFile = trim((string) ($card['nameFile'] ?? $card['name_file'] ?? ''));
        $product = [
            'pName' => $nameFile !== '' ? $nameFile : trim((string) ($card['title'] ?? $card['name'] ?? '')),
            'pBrand' => trim((string) ($card['brand'] ?? $card['force_brand'] ?? '')),
            'pCode' => trim((string) ($card['sku'] ?? $card['artNr'] ?? '')),
            'pOem' => trim((string) ($card['oem'] ?? $card['pOem'] ?? $card['equivPair'] ?? '')),
            'pCategory' => trim((string) ($card['category'] ?? '')),
            'pSubcategory' => trim((string) ($card['subcategory'] ?? '')),
            'pCompatibilitati' => trim((string) ($card['compatText'] ?? '')),
        ];
        $raw = [
            'source_rows' => $card['sourceRows'] ?? $card['source_rows'] ?? [],
            'entries' => $card['sourceRows'] ?? $card['source_rows'] ?? [],
            'compat_text' => $card['compatText'] ?? '',
            'force_brand' => $card['force_brand'] ?? $card['forceBrand'] ?? '',
            'art_nr' => $card['artNr'] ?? $card['sku'] ?? '',
            '__piece_name_preferred' => $nameFile,
        ];
        $applied = (new \Besoiu\Services\ProductCardFormationService())->applyToImportProduct($product, $raw);
        $pf = is_array($applied['product_formation'] ?? null) ? $applied['product_formation'] : [];
        if (empty($pf['applied'])) {
            return $card;
        }
        $website = trim((string) ($pf['title_website'] ?? ''));
        $market = trim((string) ($pf['title_pieseauto'] ?? ''));
        if ($website !== '') {
            $card['title'] = $website;
            $card['name'] = $website;
            $card['titleWebsite'] = $website;
        }
        if ($market !== '') {
            $card['titleMarketplace'] = $market;
        }
        $card['titleOem'] = trim((string) ($pf['title_oem'] ?? ''));
        $card['pPozitie'] = trim((string) ($pf['pozitie'] ?? ''));
        $card['product_formation'] = $pf;
    } catch (Throwable) {
        // preview rămâne cu titlul din fișier / TecDoc
    }

    return $card;
}
