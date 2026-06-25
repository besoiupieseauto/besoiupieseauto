<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/system/product_dual_description.php';

function import_base_min_car_year(): int
{
    return 2000;
}

function import_base_allowed_car_brands(): array
{
    static $brands = null;
    if ($brands !== null) {
        return $brands;
    }

    $brands = [];
    foreach (explode('|', 'ALFA ROMEO|AUDI|BMW|BYD|CHEVROLET|CHRYSLER|CITROËN|CUPRA|DACIA|DAEWOO|DS|FIAT|FORD|HONDA|HYUNDAI|ISUZU|IVECO|JAGUAR|JEEP|KIA|LANCIA|LAND ROVER|LEXUS|MAZDA|MERCEDES-BENZ|MG|MINI|MITSUBISHI|NISSAN|OPEL|PEUGEOT|RENAULT|SKODA|SMART|SSANGYONG|SUBARU|SUZUKI|TESLA|TOYOTA|VOLVO|VW') as $brand) {
        $brand = import_base_normalize_car_brand($brand);
        $brandKey = function_exists('mb_strtoupper') ? mb_strtoupper($brand, 'UTF-8') : strtoupper($brand);
        if ($brandKey !== '') {
            $brands[$brandKey] = true;
        }
    }

    return $brands;
}

function import_base_allowed_part_brands(): array
{
    static $brands = null;
    if ($brands !== null) {
        return $brands;
    }

    $brands = [];
    $path = dirname(__DIR__, 3) . '/data/import_base_allowed_part_brands.txt';
    if (is_file($path)) {
        foreach (preg_split('/\r?\n/', (string)file_get_contents($path)) ?: [] as $line) {
            $line = import_base_normalize_car_brand($line);
            $lineKey = function_exists('mb_strtoupper') ? mb_strtoupper($line, 'UTF-8') : strtoupper($line);
            if ($lineKey !== '') {
                $brands[$lineKey] = true;
            }
        }
    }

    foreach (import_base_allowed_car_brands() as $brand => $_) {
        $brands[$brand] = true;
    }

    return $brands;
}

function import_base_name_overrides_path(): string
{
    return dirname(__DIR__, 3) . '/data/import_base_name_overrides.txt';
}

function import_base_load_name_overrides(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    $path = import_base_name_overrides_path();
    if (!is_file($path)) {
        return $cache;
    }

    foreach (preg_split('/\r?\n/', (string)file_get_contents($path)) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '#')) {
            continue;
        }
        [$left, $right] = explode('#', $line, 2);
        $right = trim($right);
        if ($right === '') {
            continue;
        }
        foreach (preg_split('/\|/u', $left) ?: [] as $alias) {
            $key = import_base_normalize_name_key($alias);
            if ($key !== '') {
                $cache[$key] = $right;
            }
        }
    }

    return $cache;
}

function import_base_normalize_special_chars(string $text): string
{
    if ($text === '') {
        return '';
    }

    $mojibakeMap = [
        'Ã®' => 'i', 'ÃŽ' => 'I', 'Ã¢' => 'a', 'Ã‚' => 'A', 'Äƒ' => 'a', 'Ä‚' => 'A',
        'ÅŸ' => 's', 'Åž' => 'S', 'Å£' => 't', 'Å¢' => 'T', 'Ã¤' => 'a', 'Ã„' => 'A',
        'Ã¶' => 'o', 'Ã–' => 'O', 'Ã¼' => 'u', 'Ãœ' => 'U', 'Ã¡' => 'a', 'Ã©' => 'e',
        'Ã¨' => 'e', 'Ã®' => 'i', 'Ã³' => 'o', 'Ãº' => 'u', 'Ã±' => 'n', 'ÃŸ' => 'ss',
        'Â®' => '(r)', 'Â©' => '(c)', 'Â°' => ' grade',
    ];
    foreach ($mojibakeMap as $from => $to) {
        $text = str_replace($from, $to, $text);
    }

    $text = preg_replace('/fr_?i+na/ui', 'frana', $text) ?? $text;
    $text = preg_replace('/\bOPC\b/u', '', $text) ?? $text;
    $text = preg_replace('/i{2,}/u', 'i', $text) ?? $text;

    $charMap = [
        'ă' => 'a', 'Ă' => 'A', 'â' => 'a', 'Â' => 'A', 'î' => 'i', 'Î' => 'I',
        'ș' => 's', 'Ș' => 'S', 'ş' => 's', 'Ş' => 'S', 'ț' => 't', 'Ț' => 'T', 'ţ' => 't', 'Ţ' => 'T',
        'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'ñ' => 'n',
    ];
    $text = strtr($text, $charMap);

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function import_base_cleanup_product_name(string $value): string
{
    $value = import_base_normalize_special_chars($value);
    $value = str_replace('_', ' ', $value);
    $value = preg_replace('/[.,;:(){}\[\]!?\'"\/\\-]+/u', ' ', $value) ?? $value;

    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function import_base_normalize_name_key(string $value): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower(import_base_cleanup_product_name($value), 'UTF-8')
        : strtolower(import_base_cleanup_product_name($value));
}

function import_base_get_name_override(string $rawName): string
{
    $key = import_base_normalize_name_key($rawName);
    if ($key === '') {
        return '';
    }

    return import_base_load_name_overrides()[$key] ?? '';
}

function import_base_tecdoc_name_patterns(): array
{
    return [
        ['/\bdiscuri?\s+frana\b/ui', 'disc frana'],
        ['/\bplacute\s+frana\b/ui', 'placute frana'],
        ['/\betrier\b/ui', 'etrier frana'],
        ['/\bset\s+frana\b/ui', 'set frana'],
        ['/\bkit\s+distributie\b/ui', 'kit distributie'],
        ['/\bcurea\s+distributie\b/ui', 'curea distributie'],
        ['/\bintinzator\b/ui', 'intinzator'],
        ['/\bpompa\s+apa\b/ui', 'pompa apa'],
        ['/\btermostat\b/ui', 'termostat'],
        ['/\bfiltru\s+aer\b/ui', 'filtru aer'],
        ['/\bfiltru\s+ulei\b/ui', 'filtru ulei'],
        ['/\bfiltru\s+combustibil\b/ui', 'filtru combustibil'],
        ['/\bfiltru\s+polen\b/ui', 'filtru polen'],
        ['/\bamortizor\b/ui', 'amortizor'],
        ['/\bplanetara\b/ui', 'planetara'],
        ['/\brulment\s+roata\b/ui', 'rulment roata'],
        ['/\bcap\s+bara\b/ui', 'cap bara'],
        ['/\bbieleta\s+directie\b/ui', 'bieleta directie'],
        ['/\bambreiaj\b/ui', 'ambreiaj'],
        ['/\bturbo\b/ui', 'turbo'],
        ['/\bturbina\b/ui', 'turbina'],
        ['/\bintercooler\b/ui', 'intercooler'],
        ['/\bsonda\s+lambda\b/ui', 'sonda lambda'],
        ['/\bcatalizator\b/ui', 'catalizator'],
        ['/\bradiator\b/ui', 'radiator'],
        ['/\bcompresor\s+clima\b/ui', 'compresor clima'],
        ['/\balternator\b/ui', 'alternator'],
        ['/\belectromotor\b/ui', 'electromotor'],
        ['/\bfar\b/ui', 'far'],
        ['/\bstop\b/ui', 'stop'],
        ['/\besapament\b/ui', 'esapament'],
        ['/\btoba\b/ui', 'toba esapament'],
        ['/\bvolan\b/ui', 'volan'],
        ['/\bulei\s+motor\b/ui', 'ulei motor'],
        ['/\bantigel\b/ui', 'antigel'],
        ['/\bperna\s+de\s+aer\b/ui', 'perna aer'],
        ['/\barc\s+pneumatic\b/ui', 'perna aer'],
    ];
}

function import_base_normalize_product_name(string $rawName): string
{
    if (trim($rawName) === '') {
        return '';
    }

    $override = import_base_get_name_override($rawName);
    $name = import_base_cleanup_product_name($override !== '' ? $override : $rawName);
    if ($name === '') {
        return '';
    }

    if ($override === '') {
        foreach (import_base_tecdoc_name_patterns() as [$pattern, $replacement]) {
            $name = preg_replace($pattern, $replacement, $name) ?? $name;
        }
    }

    $abbreviations = ['ABS', 'EGR', 'DPF', 'AC', 'OEM', 'CV', 'TDI', 'TSI', 'FSI'];
    $lowerWords = ['de', 'cu', 'pentru', 'si', 'sau', 'la', 'din', 'pe', 'in', 'a', 'al', 'ale', 'ai'];
    $words = preg_split('/\s+/u', function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name)) ?: [];
    $formatted = [];
    foreach ($words as $index => $word) {
        $upper = function_exists('mb_strtoupper') ? mb_strtoupper($word, 'UTF-8') : strtoupper($word);
        if (in_array($upper, $abbreviations, true)) {
            $formatted[] = $upper;
        } elseif ($index > 0 && in_array($word, $lowerWords, true)) {
            $formatted[] = $word;
        } else {
            $formatted[] = function_exists('mb_convert_case')
                ? mb_convert_case($word, MB_CASE_TITLE, 'UTF-8')
                : ucfirst($word);
        }
    }

    return trim(preg_replace('/\s+/u', ' ', implode(' ', $formatted)) ?? '');
}

function import_base_normalize_car_brand(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', import_base_normalize_special_chars($value)) ?? '');
}

function import_base_format_car_year($value, string $fallback = ''): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return $fallback;
    }
    $numeric = (int)$value;
    $year = intdiv($numeric, 100);
    if ($year < 1900 || $year > 2100) {
        return $fallback;
    }

    return (string)$year;
}

function import_base_row_get(array $row, string $key): string
{
    $row = array_change_key_case($row, CASE_LOWER);
    $normalizedKey = normalize_key($key);
    if (isset($row[$normalizedKey])) {
        return trim((string)$row[$normalizedKey]);
    }

    $upper = strtoupper(str_replace(' ', '_', normalize_key($key)));
    foreach ($row as $column => $value) {
        $columnUpper = strtoupper(str_replace(' ', '_', normalize_key((string)$column)));
        if ($columnUpper === $upper) {
            return trim((string)$value);
        }
    }

    return '';
}

function import_base_entries_from_rows(array $rows): array
{
    $entries = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $entries[] = [
            'ART_CODE_1' => import_base_row_get($row, 'art code 1'),
            'ART_CODE_2' => import_base_row_get($row, 'art code 2'),
            'ART_BRAND' => import_base_row_get($row, 'art brand'),
            'ART_NAME' => import_base_row_get($row, 'art name'),
            'ART_EAN' => import_base_row_get($row, 'art ean'),
            'ART_CROSS' => import_base_row_get($row, 'art cross'),
            'PARTS_INFO' => import_base_row_get($row, 'parts info'),
            'TTC_ART_ID' => import_base_row_get($row, 'ttc art id'),
            'CAR_BRAND' => import_base_row_get($row, 'car brand'),
            'CAR_MODEL' => import_base_row_get($row, 'car model'),
            'CAR_TYP' => import_base_row_get($row, 'car typ'),
            'CAR_OF_YEAR' => import_base_row_get($row, 'car of year'),
            'CAR_TO_YEAR' => import_base_row_get($row, 'car to year'),
            'CAR_KW' => import_base_row_get($row, 'car kw'),
        ];
    }

    return $entries;
}

function import_base_has_vehicle_data(array $rows): bool
{
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (import_base_row_get($row, 'car brand') !== '' || import_base_row_get($row, 'car model') !== '') {
            return true;
        }
    }

    return false;
}

function import_base_filter_entries_by_brands(array $entries): array
{
    $allowed = import_base_allowed_car_brands();
    if ($allowed === []) {
        return $entries;
    }

    return array_values(array_filter($entries, static function (array $entry) use ($allowed): bool {
        $brand = import_base_normalize_car_brand((string)($entry['CAR_BRAND'] ?? ''));
        $brandKey = function_exists('mb_strtoupper') ? mb_strtoupper($brand, 'UTF-8') : strtoupper($brand);

        return $brandKey !== '' && isset($allowed[$brandKey]);
    }));
}

function import_base_is_entry_year_allowed(array $entry, int $minYear): bool
{
    $yearTo = (int)import_base_format_car_year($entry['CAR_TO_YEAR'] ?? '', '0');
    $effectiveMax = $yearTo > 0 ? $yearTo : (int)date('Y');

    return $effectiveMax >= $minYear;
}

function import_base_filter_entries(array $entries, int $minYear = 0): array
{
    $byBrand = import_base_filter_entries_by_brands($entries);

    // -1 = fără filtru an (consumabile / ulei unde compatibilitățile vechi sunt utile)
    if ($minYear < 0) {
        return array_values($byBrand);
    }

    if ($minYear <= 0) {
        $minYear = import_base_min_car_year();
    }

    return array_values(array_filter($byBrand, static fn(array $entry): bool => import_base_is_entry_year_allowed($entry, $minYear)));
}

function import_base_extract_mounting_side(array $entries): string
{
    $rawSpecs = trim((string)($entries[0]['PARTS_INFO'] ?? ''));
    if ($rawSpecs === '') {
        return '';
    }

    $text = function_exists('mb_strtolower')
        ? mb_strtolower(import_base_normalize_special_chars($rawSpecs), 'UTF-8')
        : strtolower(import_base_normalize_special_chars($rawSpecs));

    $hasLeft = (bool)preg_match('/\b(stanga|stg|st\.|sx|left|lh|l\.h\.)\b/u', $text);
    $hasRight = (bool)preg_match('/\b(dreapta|dr|dr\.|dx|right|rh|r\.h\.)\b/u', $text);

    if ($hasLeft && $hasRight) {
        return '';
    }
    if ($hasLeft) {
        return 'Stanga';
    }
    if ($hasRight) {
        return 'Dreapta';
    }

    return '';
}

function import_base_process_oem_codes(string $oemString): string
{
    $oemString = trim($oemString);
    if ($oemString === '') {
        return '';
    }

    $oemString = import_base_normalize_special_chars($oemString);
    $lines = [];
    foreach (preg_split('/\r?\n/u', $oemString) ?: [] as $line) {
        foreach (preg_split('/\|/u', $line) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '') {
                $lines[] = $part;
            }
        }
    }

    $knownBrands = [
        'FEBI', 'FORD', 'BMW', 'AUDI', 'VW', 'MERCEDES', 'OPEL', 'FIAT', 'RENAULT', 'PEUGEOT', 'CITROEN',
        'TOYOTA', 'NISSAN', 'HONDA', 'MAZDA', 'MITSUBISHI', 'SUZUKI', 'HYUNDAI', 'KIA', 'DACIA', 'SKODA',
        'SEAT', 'VOLVO', 'MINI', 'SMART', 'ALFA', 'LANCIA', 'JEEP', 'CHRYSLER', 'CHEVROLET', 'VAICO', 'GATES',
    ];

    $processed = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (str_contains($line, '::')) {
            [$brandPart, $codePart] = array_pad(explode('::', $line, 2), 2, '');
            $processed[] = trim($brandPart) . ' : ' . preg_replace('/\s+/u', '', trim($codePart));
            continue;
        }

        if (str_contains($line, ':')) {
            [$brandPart, $codePart] = array_pad(explode(':', $line, 2), 2, '');
            $processed[] = trim($brandPart) . ' : ' . preg_replace('/\s+/u', '', trim($codePart));
            continue;
        }

        $matched = false;
        foreach ($knownBrands as $brand) {
            if (stripos($line, $brand) === 0) {
                $codePart = trim(substr($line, strlen($brand)));
                $processed[] = $brand . ' : ' . preg_replace('/\s+/u', '', $codePart);
                $matched = true;
                break;
            }
        }
        if ($matched) {
            continue;
        }

        if (preg_match('/^([A-Za-z]+)([0-9].*)$/u', $line, $matches)) {
            $processed[] = $matches[1] . ' : ' . preg_replace('/\s+/u', '', $matches[2]);
            continue;
        }

        $spaceIndex = strpos($line, ' ');
        if ($spaceIndex !== false && $spaceIndex > 0) {
            $processed[] = substr($line, 0, $spaceIndex) . ' : ' . preg_replace('/\s+/u', '', trim(substr($line, $spaceIndex + 1)));
            continue;
        }

        $processed[] = $line;
    }

    return implode("\n", array_values(array_filter($processed)));
}

function import_base_brand_title_case(string $brand): string
{
    $brand = trim($brand);
    if ($brand === '') {
        return '';
    }

    $upper = function_exists('mb_strtoupper')
        ? mb_strtoupper($brand, 'UTF-8')
        : strtoupper($brand);

    if ($upper === 'BOSCH') {
        return 'Bosch';
    }

    $acronyms = [
        'BMW', 'NGK', 'SKF', 'FAG', 'INA', 'TRW', 'LUK', 'GKN', 'NTN', 'NSK', 'NTY', 'NTK', 'ATE',
        'CTR', 'DAYCO', 'VALEO', 'HELLA', 'BERU', 'MAHLE', 'GATES', 'RIDEX', 'GLYCO', 'MAN',
    ];
    if (in_array($upper, $acronyms, true)) {
        return $upper;
    }

    return function_exists('mb_convert_case')
        ? mb_convert_case(mb_strtolower($upper, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')
        : ucwords(strtolower($upper));
}

function import_base_title_brand_label(string $brand, string $articleCode = ''): string
{
    if (function_exists('import_resolve_brand_display_name')) {
        return import_resolve_brand_display_name($brand, $articleCode);
    }

    $brand = trim($brand);
    if ($brand === '' || preg_match('/^\d{2,5}$/', preg_replace('/\s+/u', '', $brand) ?? '')) {
        return '';
    }

    return import_base_brand_title_case($brand);
}

function import_base_title_code_label(string $code, string $brand = ''): string
{
    if (function_exists('import_format_article_code_display')) {
        return import_format_article_code_display($code, $brand);
    }

    return trim($code);
}

/**
 * Elimină brand/cod deja prezente la finalul denumirii (evită titluri duble la enrich).
 */
function import_base_strip_redundant_title_suffix(string $pieceName, string $brand, string $code): string
{
    $pieceName = trim(preg_replace('/\s+/u', ' ', $pieceName) ?? '');
    if ($pieceName === '') {
        return '';
    }

    $brandLabel = import_base_title_brand_label($brand, $code);
    $codeLabel = import_base_title_code_label($code, $brand);
    $suffix = trim(implode(' ', array_filter([$brandLabel, $codeLabel])));

    $canonical = function_exists('import_resolve_brand_canonical_name')
        ? import_resolve_brand_canonical_name($brand, $code)
        : '';
    $candidates = array_values(array_unique(array_filter([
        $suffix,
        trim($brandLabel . ' ' . trim($code)),
        trim($canonical . ' ' . $codeLabel),
        trim($canonical . ' ' . $code),
        $codeLabel,
        trim($code),
    ])));

    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $pieceLen = mb_strlen($pieceName, 'UTF-8');
            $candidateLen = mb_strlen($candidate, 'UTF-8');
            if ($pieceLen <= $candidateLen) {
                continue;
            }
            $tail = mb_substr($pieceName, -$candidateLen, null, 'UTF-8');
            if (mb_strtolower($tail, 'UTF-8') === mb_strtolower($candidate, 'UTF-8')) {
                $pieceName = trim(mb_substr($pieceName, 0, $pieceLen - $candidateLen, 'UTF-8'));
                $changed = true;
            }
        }
    }

    return trim($pieceName);
}

/**
 * Titlu clar pentru magazin: „Plăcuțe frână Bosch 0 986 424 268”.
 */
function import_base_build_display_title(
    string $pieceName,
    string $brand,
    string $code,
    string $vehicleSuffix = ''
): string {
    $pieceName = import_base_strip_redundant_title_suffix(trim($pieceName), $brand, $code);
    $brandLabel = import_base_title_brand_label($brand, $code);
    $codeLabel = import_base_title_code_label($code, $brand);
    $suffix = trim(implode(' ', array_filter([$brandLabel, $codeLabel])));
    $title = trim(implode(' ', array_filter([$pieceName, $brandLabel, $codeLabel, trim($vehicleSuffix)])));

    if ($suffix !== '' && $pieceName !== '') {
        $escaped = preg_quote($suffix, '/');
        $title = preg_replace('/(\s+' . $escaped . ')+$/iu', ' ' . $suffix, $title) ?? $title;
        $title = trim($title);
    }

    if (function_exists('mb_strlen') && mb_strlen($title, 'UTF-8') > 150) {
        return mb_substr($title, 0, 147, 'UTF-8') . '...';
    }
    if (strlen($title) > 150) {
        return substr($title, 0, 147) . '...';
    }

    return $title;
}

function import_base_generate_title(array $entries): string
{
    if ($entries === []) {
        return '';
    }

    $firstEntry = $entries[0];
    $productName = import_base_normalize_product_name((string)($firstEntry['ART_NAME'] ?? ''));
    $productName = trim(preg_replace('/\bOPC\b/u', '', $productName) ?? $productName);
    $brand = import_base_normalize_special_chars((string)($firstEntry['ART_BRAND'] ?? ''));
    $productCode = (string)($firstEntry['ART_CODE_1'] ?? '');
    $mountingSide = import_base_extract_mounting_side($entries);

    $allowedEntries = import_base_filter_entries($entries);
    if ($allowedEntries === []) {
        return '';
    }

    $carBrands = [];
    $mainSeries = [];
    foreach ($allowedEntries as $entry) {
        $carBrand = import_base_normalize_special_chars((string)($entry['CAR_BRAND'] ?? ''));
        if ($carBrand !== '') {
            $carBrands[$carBrand] = true;
        }
        $model = import_base_normalize_special_chars((string)($entry['CAR_MODEL'] ?? ''));
        $series = trim((string)(preg_split('/\s+/u', $model)[0] ?? ''));
        if ($series !== '') {
            $mainSeries[$series] = true;
        }
    }

    $carBrandList = array_keys($carBrands);
    $seriesList = array_keys($mainSeries);
    $count = count($allowedEntries);
    if ($count > 10) {
        $seriesList = array_slice($seriesList, 0, 3);
    } elseif ($count > 3) {
        $seriesList = array_slice($seriesList, 0, 4);
    }

    $nameWithSide = trim($productName . ($mountingSide !== '' ? ' ' . $mountingSide : ''));
    $vehicleSuffix = trim(implode(' ', array_filter([
        $carBrandList !== [] ? 'pentru ' . implode('/', $carBrandList) : '',
        $seriesList !== [] ? implode('/', $seriesList) : '',
    ])));

    return import_base_build_display_title($nameWithSide, $brand, $productCode, $vehicleSuffix);
}

function import_base_generate_description(array $entries): string
{
    if ($entries === []) {
        return '';
    }

    $firstEntry = $entries[0];
    $productName = import_base_normalize_product_name((string)($firstEntry['ART_NAME'] ?? ''));
    $productName = trim(preg_replace('/\bOPC\b/u', '', $productName) ?? $productName);
    $brandName = import_base_normalize_special_chars((string)($firstEntry['ART_BRAND'] ?? ''));
    $productCode = (string)($firstEntry['ART_CODE_1'] ?? '');
    $specs = (string)($firstEntry['PARTS_INFO'] ?? '');

    $allowedEntries = import_base_filter_entries($entries);
    if ($allowedEntries === []) {
        return '';
    }

    $description = '<p><b>' . htmlspecialchars(
        trim($productName . ' ' . import_base_title_brand_label($brandName, $productCode)),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . '</b> (Cod: ' . htmlspecialchars(import_base_title_code_label($productCode, $brandName), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')</p>';

    if ($specs !== '') {
        $specLines = [];
        foreach (preg_split('/\s*\|\s*/u', import_base_normalize_special_chars($specs)) ?: [] as $line) {
            $line = trim(str_replace('::', ': ', $line));
            if ($line === '' || preg_match('/^conform brosurii\s*:?\s*$/ui', $line)) {
                continue;
            }
            if (str_contains($line, ':')) {
                [$key, $val] = array_pad(explode(':', $line, 2), 2, '');
                $specLines[] = '<li>' . htmlspecialchars(trim($key) . ': ' . trim($val), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
            } else {
                $specLines[] = '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
            }
        }
        if ($specLines !== []) {
            $description .= '<p><b>Specificatii tehnice:</b></p><ul>' . implode('', $specLines) . '</ul>';
        }
    }

    $compatMap = [];
    foreach ($allowedEntries as $entry) {
        $carBrand = import_base_normalize_special_chars((string)($entry['CAR_BRAND'] ?? ''));
        $carModel = import_base_normalize_special_chars((string)($entry['CAR_MODEL'] ?? ''));
        $carType = import_base_normalize_special_chars((string)($entry['CAR_TYP'] ?? ''));
        if ($carModel === '') {
            continue;
        }

        if (!isset($compatMap[$carBrand])) {
            $compatMap[$carBrand] = [];
        }
        if (!isset($compatMap[$carBrand][$carModel])) {
            $compatMap[$carBrand][$carModel] = [
                'minYear' => 9999,
                'maxYear' => 0,
                'engines' => [],
                'entries' => [],
                'entrySet' => [],
            ];
        }

        $grp =& $compatMap[$carBrand][$carModel];
        $yearFrom = (int)import_base_format_car_year($entry['CAR_OF_YEAR'] ?? '', '0');
        $yearTo = (int)import_base_format_car_year($entry['CAR_TO_YEAR'] ?? '', '0');
        $effectiveMax = $yearTo > 1900 ? $yearTo : (int)date('Y');
        $clampedFrom = $yearFrom > 1900 ? max($yearFrom, import_base_min_car_year()) : import_base_min_car_year();
        $grp['minYear'] = min($grp['minYear'], $clampedFrom);
        $grp['maxYear'] = max($grp['maxYear'], $effectiveMax);

        $engine = trim(preg_replace('/\([^)]*\)/u', '', $carType) ?? '');
        $engine = trim(preg_replace('/\s+/u', ' ', $engine) ?? '');
        if ($engine !== '') {
            $grp['engines'][$engine] = true;
        }

        $power = trim((string)($entry['CAR_KW'] ?? '')) !== '' ? trim((string)$entry['CAR_KW']) . ' KW' : '';
        $yearToLabel = import_base_format_car_year($entry['CAR_TO_YEAR'] ?? '', '') ?: 'Prezent';
        $yearFromLabel = $clampedFrom ? (string)$clampedFrom : '?';
        $modelType = trim($carModel . ' ' . $carType);
        $lineKey = $modelType . '|' . $power . '|' . $yearFromLabel . '|' . $yearToLabel;
        if (!isset($grp['entrySet'][$lineKey])) {
            $grp['entrySet'][$lineKey] = true;
            $grp['entries'][] = [
                'modelType' => $modelType,
                'power' => $power,
                'yearFrom' => $yearFromLabel,
                'yearTo' => $yearToLabel,
            ];
        }
        unset($grp);
    }

    $compHtml = '';
    ksort($compatMap);
    foreach ($compatMap as $carBrand => $models) {
        $compHtml .= '<li><b>' . htmlspecialchars($carBrand, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b><ul>';
        ksort($models);
        foreach ($models as $modelKey => $data) {
            $modelKeyStr = (string)$modelKey;
            $n = count($data['entries']);
            if ($n === 1) {
                $e = $data['entries'][0];
                $modelTypeStr = (string)($e['modelType'] ?? '');
                $rest = trim(str_starts_with($modelTypeStr, $modelKeyStr) ? substr($modelTypeStr, strlen($modelKeyStr)) : $modelTypeStr);
                $compHtml .= '<li><b>' . htmlspecialchars($modelKeyStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>'
                    . ($rest !== '' ? ' ' . htmlspecialchars($rest, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '')
                    . ($e['power'] !== '' ? ' ' . htmlspecialchars($e['power'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '')
                    . ' (' . htmlspecialchars($e['yearFrom'] . '-' . $e['yearTo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')</li>';
            } elseif ($n <= 3) {
                $compHtml .= '<li><b>' . htmlspecialchars($modelKeyStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b><ul>';
                foreach ($data['entries'] as $e) {
                    $modelTypeStr = (string)($e['modelType'] ?? '');
                    $rest = trim(str_starts_with($modelTypeStr, $modelKeyStr) ? substr($modelTypeStr, strlen($modelKeyStr)) : $modelTypeStr);
                    $compHtml .= '<li>' . htmlspecialchars($rest !== '' ? $rest : $modelTypeStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . ($e['power'] !== '' ? ' ' . htmlspecialchars($e['power'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '')
                        . ' (' . htmlspecialchars($e['yearFrom'] . '-' . $e['yearTo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')</li>';
                }
                $compHtml .= '</ul></li>';
            } else {
                $minY = $data['minYear'] === 9999 ? '?' : (string)$data['minYear'];
                $maxY = $data['maxYear'] <= 0 ? 'Prezent' : (string)$data['maxYear'];
                $yearStr = $minY === $maxY ? $minY : $minY . ' - ' . $maxY;
                $engList = implode(', ', array_keys($data['engines']));
                $compHtml .= '<li><b>' . htmlspecialchars($modelKeyStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b> (' . htmlspecialchars($yearStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')';
                if ($engList !== '') {
                    $compHtml .= '<br><i>Motorizari:</i> ' . htmlspecialchars($engList, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
                $compHtml .= '</li>';
            }
        }
        $compHtml .= '</ul></li>';
    }

    if ($compHtml !== '') {
        $description .= '<p><b>Compatibil cu urmatoarele modele auto:</b></p><ul>' . $compHtml . '</ul>';
    }

    $allowedOemBrands = import_base_allowed_part_brands();
    $oemRaw = import_base_process_oem_codes((string)($firstEntry['ART_CROSS'] ?? ''));
    if ($oemRaw !== '') {
        $oemLines = [];
        foreach (preg_split('/\r?\n/u', $oemRaw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($allowedOemBrands !== []) {
                $label = trim((string)(explode(':', $line)[0] ?? ''));
                $labelKey = function_exists('mb_strtoupper') ? mb_strtoupper($label, 'UTF-8') : strtoupper($label);
                if ($labelKey !== '' && !isset($allowedOemBrands[$labelKey])) {
                    continue;
                }
            }
            $oemLines[] = '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
        }
        if ($oemLines !== []) {
            $description .= '<p><b>Coduri OE echivalente:</b></p><ul>' . implode('', $oemLines) . '</ul>';
        }
    }

    return $description;
}

function import_base_image_url(string $brand, string $ttcArtId): string
{
    $brand = trim($brand);
    $ttcArtId = trim($ttcArtId);
    if ($brand === '' || $ttcArtId === '') {
        return '';
    }

    return 'https://www.caietcomenzi.ro/PozeEmag/' . rawurlencode($brand) . '/' . rawurlencode($ttcArtId) . '.jpg';
}

function import_base_first_ttc_pair(array $rows): array
{
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $ttcArtId = import_base_row_get($row, 'ttc art id');
        if ($ttcArtId === '') {
            continue;
        }

        return [
            'brand' => import_base_row_get($row, 'art brand'),
            'ttc_art_id' => $ttcArtId,
        ];
    }

    return ['brand' => '', 'ttc_art_id' => ''];
}

function import_base_resolve_image_from_rows(array $rows): array
{
    $pair = import_base_first_ttc_pair($rows);
    $ttcArtId = trim((string)($pair['ttc_art_id'] ?? ''));
    if ($ttcArtId === '') {
        return ['url' => '', 'source' => 'missing', 'brand' => '', 'ttc_art_id' => ''];
    }

    $brand = trim((string)($pair['brand'] ?? ''));
    $url = import_base_image_url($brand, $ttcArtId);
    if ($url === '') {
        return ['url' => '', 'source' => 'missing', 'brand' => $brand, 'ttc_art_id' => $ttcArtId];
    }

    return [
        'url' => $url,
        'source' => 'caietcomenzi',
        'brand' => $brand,
        'ttc_art_id' => $ttcArtId,
    ];
}

function import_base_should_skip_without_price(array $rows, array $priceIndex): bool
{
    if ($priceIndex === []) {
        return false;
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (import_lookup_supplier_price($priceIndex, $row) !== null) {
            return false;
        }
    }

    return true;
}

function import_base_extract_vehicle_summary(array $entries): array
{
    $allowed = import_base_filter_entries($entries);
    $marca = [];
    $model = [];
    $motorizare = [];

    foreach ($allowed as $entry) {
        $carBrand = import_base_normalize_special_chars((string)($entry['CAR_BRAND'] ?? ''));
        $carModel = import_base_normalize_special_chars((string)($entry['CAR_MODEL'] ?? ''));
        $carType = import_base_normalize_special_chars((string)($entry['CAR_TYP'] ?? ''));
        if ($carBrand !== '') {
            $marca[$carBrand] = true;
        }
        if ($carModel !== '') {
            $model[$carModel] = true;
        }
        if ($carType !== '') {
            $motorizare[$carType] = true;
        }
    }

    return [
        'marca' => implode(', ', array_keys($marca)),
        'model' => implode(', ', array_keys($model)),
        'motorizare' => implode("\n", array_keys($motorizare)),
        'allowed_count' => count($allowed),
    ];
}

function import_base_product_summary_from_rows(array $rows, array $base): array
{
    $entries = import_base_entries_from_rows($rows);
    $firstEntry = $entries[0] ?? [];
    $allowed = import_base_filter_entries($entries);
    $vehicle = import_base_extract_vehicle_summary($entries);
    $oemRaw = import_base_process_oem_codes((string)($firstEntry['ART_CROSS'] ?? ''));
    $oemList = [];
    foreach (preg_split('/\r?\n/u', $oemRaw) ?: [] as $line) {
        $line = trim($line);
        if ($line !== '') {
            $oemList[] = $line;
        }
    }

    return [
        'identity' => [
            'name' => (string)($base['pName'] ?? ''),
            'brand_produs' => (string)($base['pBrand'] ?? ''),
            'cod_produs' => (string)($base['pCode'] ?? ''),
            'furnizor' => (string)($base['pSupplier'] ?? ''),
        ],
        'vehicle' => [
            'marca_auto' => (string)($base['pMarca'] ?? $vehicle['marca']),
            'model_auto' => (string)($base['pModel'] ?? $vehicle['model']),
            'motorizare' => (string)($base['pMotorizare'] ?? $vehicle['motorizare']),
            'kilometraj_km' => null,
        ],
        'classification' => [
            'categorie' => (string)($base['pCategory'] ?? ''),
            'subcategorie' => (string)($base['pSubcategory'] ?? ''),
        ],
        'specs' => (string)($firstEntry['PARTS_INFO'] ?? ''),
        'ean' => trim((string)($firstEntry['ART_EAN'] ?? '')),
        'codes' => [
            'cod_principal' => (string)($base['pCode'] ?? ''),
            'coduri_oem' => $oemList,
            'coduri_alternative' => [],
            'toate_codurile' => $oemList,
        ],
        'technical_data' => import_base_filter_entries($entries) !== [] ? ['compatibilitati' => $vehicle['allowed_count']] : [],
        'import_base_applied' => true,
    ];
}

function import_base_simple_title(array $entries): string
{
    if ($entries === []) {
        return '';
    }

    $firstEntry = $entries[0];
    $productName = import_base_normalize_product_name((string)($firstEntry['ART_NAME'] ?? ''));
    $productName = trim(preg_replace('/\bOPC\b/u', '', $productName) ?? $productName);
    $brand = import_base_normalize_special_chars((string)($firstEntry['ART_BRAND'] ?? ''));
    $productCode = (string)($firstEntry['ART_CODE_1'] ?? '');
    $mountingSide = import_base_extract_mounting_side($entries);
    $nameWithSide = trim($productName . ($mountingSide !== '' ? ' ' . $mountingSide : ''));

    return import_base_build_display_title($nameWithSide, $brand, $productCode);
}

function import_base_simple_description(array $entries): string
{
    if ($entries === []) {
        return '';
    }

    $firstEntry = $entries[0];
    $productName = import_base_normalize_product_name((string)($firstEntry['ART_NAME'] ?? ''));
    $productName = trim(preg_replace('/\bOPC\b/u', '', $productName) ?? $productName);
    $brandName = import_base_normalize_special_chars((string)($firstEntry['ART_BRAND'] ?? ''));
    $productCode = (string)($firstEntry['ART_CODE_1'] ?? '');
    $specs = (string)($firstEntry['PARTS_INFO'] ?? '');

    $description = '<p><b>' . htmlspecialchars(
        trim($productName . ' ' . import_base_title_brand_label($brandName, $productCode)),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . '</b> (Cod: ' . htmlspecialchars(import_base_title_code_label($productCode, $brandName), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')</p>';

    if ($specs !== '') {
        $specLines = [];
        foreach (preg_split('/\s*\|\s*/u', import_base_normalize_special_chars($specs)) ?: [] as $line) {
            $line = trim(str_replace('::', ': ', $line));
            if ($line === '' || preg_match('/^conform brosurii\s*:?\s*$/ui', $line)) {
                continue;
            }
            if (str_contains($line, ':')) {
                [$key, $val] = array_pad(explode(':', $line, 2), 2, '');
                $specLines[] = '<li>' . htmlspecialchars(trim($key) . ': ' . trim($val), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
            } else {
                $specLines[] = '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
            }
        }
        if ($specLines !== []) {
            $description .= '<p><b>Specificatii tehnice:</b></p><ul>' . implode('', $specLines) . '</ul>';
        }
    }

    $allowedOemBrands = import_base_allowed_part_brands();
    $oemRaw = import_base_process_oem_codes((string)($firstEntry['ART_CROSS'] ?? ''));
    if ($oemRaw !== '') {
        $oemLines = [];
        foreach (preg_split('/\r?\n/u', $oemRaw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($allowedOemBrands !== []) {
                $label = trim((string)(explode(':', $line)[0] ?? ''));
                $labelKey = function_exists('mb_strtoupper') ? mb_strtoupper($label, 'UTF-8') : strtoupper($label);
                if ($labelKey !== '' && !isset($allowedOemBrands[$labelKey])) {
                    continue;
                }
            }
            $oemLines[] = '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
        }
        if ($oemLines !== []) {
            $description .= '<p><b>Coduri OE echivalente:</b></p><ul>' . implode('', $oemLines) . '</ul>';
        }
    }

    return $description;
}

function import_base_apply_without_vehicle(array &$base, array $rows): void
{
    $entries = import_base_entries_from_rows($rows);
    if ($entries === []) {
        if (trim((string)($base['pName'] ?? '')) !== '') {
            $base['pName'] = import_base_normalize_product_name((string)($base['pName'] ?? '')) ?: $base['pName'];
        }
        return;
    }

    $firstEntry = $entries[0];
    $normalizedName = import_base_normalize_product_name((string)($firstEntry['ART_NAME'] ?? ''));
    $seoTitle = import_base_simple_title($entries);
    $htmlDescription = import_base_simple_description($entries);
    $oemProcessed = import_base_process_oem_codes((string)($firstEntry['ART_CROSS'] ?? ''));

    if ($seoTitle !== '') {
        $base['pName'] = $seoTitle;
    } elseif ($normalizedName !== '') {
        $base['pName'] = $normalizedName;
    } elseif (trim((string)($base['pName'] ?? '')) !== '') {
        $base['pName'] = import_base_normalize_product_name((string)($base['pName'] ?? '')) ?: $base['pName'];
    }

    if ($normalizedName !== '') {
        $base['pSubcategory'] = $normalizedName;
    }

    if ($htmlDescription !== '') {
        besoiu_apply_product_description($base, $htmlDescription);
    }

    if ($oemProcessed !== '') {
        $base['pOem'] = str_replace("\n", ', ', $oemProcessed);
    }

    $imageMeta = import_base_resolve_image_from_rows($rows);
    if ($imageMeta['url'] !== '') {
        $base['pImages'] = json_encode([$imageMeta['url']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $base['pImageSource'] = 'caietcomenzi';
    }
}

function import_base_apply_to_product(array &$base, array $group, array $priceIndex = []): bool
{
    $rows = $group['rows'] ?? [];
    if ($rows === []) {
        return true;
    }

    if (!import_base_has_vehicle_data($rows)) {
        import_base_apply_without_vehicle($base, $rows);
        return true;
    }

    $entries = import_base_entries_from_rows($rows);
    $allowedEntries = import_base_filter_entries($entries);

    if ($allowedEntries === [] && import_base_has_vehicle_data($rows)) {
        return false;
    }

    if (import_base_should_skip_without_price($rows, $priceIndex)) {
        return false;
    }

    $workingEntries = $allowedEntries !== [] ? $allowedEntries : $entries;
    $firstEntry = $workingEntries[0] ?? $entries[0] ?? [];

    $seoTitle = import_base_simple_title($workingEntries);
    $normalizedName = import_base_normalize_product_name((string)($firstEntry['ART_NAME'] ?? ''));
    $htmlDescription = import_base_generate_description($workingEntries);
    $oemProcessed = import_base_process_oem_codes((string)($firstEntry['ART_CROSS'] ?? ''));

    if ($seoTitle !== '') {
        $base['pName'] = $seoTitle;
    } elseif ($normalizedName !== '') {
        $base['pName'] = $normalizedName;
    }

    if ($normalizedName !== '') {
        $base['pSubcategory'] = $normalizedName;
    }

    if ($htmlDescription !== '') {
        besoiu_apply_product_description($base, $htmlDescription);
    }

    if ($oemProcessed !== '') {
        $base['pOem'] = str_replace("\n", ', ', $oemProcessed);
    }

    $vehicle = import_base_extract_vehicle_summary($workingEntries);
    if ($vehicle['marca'] !== '') {
        $base['pMarca'] = $vehicle['marca'];
    }
    if ($vehicle['model'] !== '') {
        $base['pModel'] = $vehicle['model'];
    }
    if ($vehicle['motorizare'] !== '') {
        $base['pMotorizare'] = $vehicle['motorizare'];
    }
    if ($vehicle['marca'] !== '') {
        $base['pCar'] = $vehicle['marca'];
    }

    $imageMeta = import_base_resolve_image_from_rows($rows);
    if ($imageMeta['url'] !== '') {
        $base['pImages'] = json_encode([$imageMeta['url']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $base['pImageSource'] = 'caietcomenzi';
    }

    return true;
}
