<?php
declare(strict_types=1);

require_once __DIR__ . '/note-html.php';
require_once __DIR__ . '/product_dual_description.php';
require_once __DIR__ . '/tecdoc_stock.php';

if (!function_exists('tecdoc_catalog_lang_id')) {
    function tecdoc_catalog_lang_id(): int
    {
        return 21;
    }
}

if (!function_exists('tecdoc_catalog_country_id')) {
    function tecdoc_catalog_country_id(): int
    {
        return 63;
    }
}

if (!function_exists('tecdoc_catalog_type_id')) {
    function tecdoc_catalog_type_id(): int
    {
        return 1;
    }
}

if (!function_exists('tecdoc_desc_h')) {
    function tecdoc_desc_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('tecdoc_api_json')) {
    function tecdoc_api_json(string $path, int $ttl = 604800): array
    {
        if (tecdoc_api_is_unavailable()) {
            return [];
        }

        $path = '/' . ltrim($path, '/');
        $url = 'https://' . BESOiu_TECDOC_HOST . $path;
        $raw = tecdoc_cached_response($url, $ttl);
        if ($raw === '' || tecdoc_cache_body_is_error($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tecdoc_article_id_from')) {
    function tecdoc_article_id_from(array $article): int
    {
        foreach (['articleId', 'article_id', 'id'] as $key) {
            $id = (int)($article[$key] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }
}

if (!function_exists('tecdoc_find_article_id')) {
    function tecdoc_find_article_id(string $code, string $brand = ''): int
    {
        $code = trim($code);
        if ($code === '') {
            return 0;
        }

        $article = tecdoc_find_article_for_import($code, $brand);
        return $article ? tecdoc_article_id_from($article) : 0;
    }
}

if (!function_exists('tecdoc_fetch_article_criteria')) {
    function tecdoc_fetch_article_criteria(int $articleId): array
    {
        if ($articleId <= 0) {
            return [];
        }

        $lang = tecdoc_catalog_lang_id();
        $country = tecdoc_catalog_country_id();

        return tecdoc_api_json(
            "/articles/selection-of-all-specifications-criterias-for-the-article/article-id/$articleId/lang-id/$lang/country-filter-id/$country"
        );
    }
}

if (!function_exists('tecdoc_fetch_article_complete_details')) {
    function tecdoc_fetch_article_complete_details(int $articleId): array
    {
        if ($articleId <= 0) {
            return [];
        }

        $type = tecdoc_catalog_type_id();
        $lang = tecdoc_catalog_lang_id();
        $country = tecdoc_catalog_country_id();

        return tecdoc_api_json(
            "/articles/article-complete-details/type-id/$type/article-id/$articleId/lang-id/$lang/country-filter-id/$country"
        );
    }
}

if (!function_exists('tecdoc_fetch_article_cross_references')) {
    function tecdoc_fetch_article_cross_references(int $articleId): array
    {
        if ($articleId <= 0) {
            return [];
        }

        $lang = tecdoc_catalog_lang_id();

        return tecdoc_api_json(
            "/artlookup/select-article-cross-references/article-id/$articleId/lang-id/$lang"
        );
    }
}

if (!function_exists('tecdoc_desc_walk_arrays')) {
    function tecdoc_desc_walk_arrays(array $payload, callable $matcher): array
    {
        $items = [];
        $seen = [];
        $walk = static function ($node) use (&$walk, &$items, &$seen, $matcher): void {
            if (!is_array($node)) {
                return;
            }

            $matched = $matcher($node);
            if ($matched !== null) {
                $key = mb_strtolower($matched['label'] . '|' . $matched['value']);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $items[] = $matched;
                }
            }

            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };

        $walk($payload);

        return $items;
    }
}

if (!function_exists('tecdoc_desc_extract_criteria')) {
    function tecdoc_desc_extract_criteria(array ...$payloads): array
    {
        $items = [];
        foreach ($payloads as $payload) {
            if ($payload === []) {
                continue;
            }
            foreach (tecdoc_desc_walk_arrays($payload, static function (array $node): ?array {
                $label = trim((string)($node['criteriaDescription'] ?? $node['criteriaName'] ?? $node['name'] ?? ''));
                $value = trim((string)($node['formattedValue'] ?? $node['rawValue'] ?? $node['value'] ?? ''));
                if ($label === '' || $value === '') {
                    return null;
                }

                return ['label' => $label, 'value' => $value];
            }) as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }
}

if (!function_exists('tecdoc_desc_extract_oem_codes')) {
    function tecdoc_desc_extract_oem_codes(array ...$payloads): array
    {
        $lines = [];
        $seen = [];

        $append = static function (string $brand, string $number) use (&$lines, &$seen): void {
            $brand = trim($brand);
            $number = trim($number);
            if ($brand === '' || $number === '') {
                return;
            }
            $key = mb_strtoupper($brand . '|' . $number);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $lines[] = ['brand' => $brand, 'number' => $number];
        };

        foreach ($payloads as $payload) {
            if ($payload === []) {
                continue;
            }

            foreach (tecdoc_desc_walk_arrays($payload, static function (array $node) use ($append): ?array {
                $number = trim((string)($node['articleNumber'] ?? $node['oemNumber'] ?? $node['articleNo'] ?? $node['number'] ?? ''));
                $brand = trim((string)($node['brandName'] ?? $node['manufacturerName'] ?? $node['supplierName'] ?? $node['brand'] ?? ''));
                if ($number !== '' && $brand !== '') {
                    $append($brand, $number);
                }

                return null;
            }) as $_) {
            }

            foreach (['oemNumbers', 'oem', 'oeNumbers', 'references', 'articles', 'crossReferences'] as $key) {
                if (!isset($payload[$key]) || !is_array($payload[$key])) {
                    continue;
                }
                foreach ($payload[$key] as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $append(
                        (string)($entry['brandName'] ?? $entry['manufacturerName'] ?? $entry['supplierName'] ?? ''),
                        (string)($entry['articleNumber'] ?? $entry['oemNumber'] ?? $entry['articleNo'] ?? '')
                    );
                }
            }
        }

        return $lines;
    }
}

if (!function_exists('tecdoc_desc_extract_compat')) {
    function tecdoc_desc_extract_compat(array ...$payloads): array
    {
        $entries = [];
        $seen = [];

        foreach ($payloads as $payload) {
            if ($payload === []) {
                continue;
            }

            foreach (tecdoc_desc_walk_arrays($payload, static function (array $node): ?array {
                $brand = trim((string)($node['manufacturerName'] ?? $node['brandName'] ?? $node['carBrand'] ?? ''));
                $model = trim((string)($node['modelName'] ?? $node['modelSeriesName'] ?? $node['carModel'] ?? ''));
                $type = trim((string)($node['typeName'] ?? $node['vehicleTypeDescription'] ?? $node['carTyp'] ?? $node['engineName'] ?? ''));
                if ($brand === '' && $model === '' && $type === '') {
                    return null;
                }

                $yearFrom = trim((string)($node['yearOfConstructionFrom'] ?? $node['yearFrom'] ?? $node['carOfYear'] ?? ''));
                $yearTo = trim((string)($node['yearOfConstructionTo'] ?? $node['yearTo'] ?? $node['carToYear'] ?? ''));
                if ($yearFrom !== '' && strlen($yearFrom) > 4) {
                    $yearFrom = substr($yearFrom, 0, 4);
                }
                if ($yearTo !== '' && strlen($yearTo) > 4) {
                    $yearTo = substr($yearTo, 0, 4);
                }

                $power = trim((string)($node['powerKw'] ?? $node['powerKW'] ?? $node['carKw'] ?? $node['kw'] ?? ''));
                if ($power !== '' && !str_contains($power, 'KW')) {
                    $power .= ' KW';
                }

                return [
                    'brand' => $brand,
                    'model' => $model,
                    'type' => $type,
                    'yearFrom' => $yearFrom,
                    'yearTo' => $yearTo !== '' ? $yearTo : 'Prezent',
                    'power' => $power,
                ];
            }) as $entry) {
                $key = mb_strtolower(implode('|', [
                    $entry['brand'],
                    $entry['model'],
                    $entry['type'],
                    $entry['yearFrom'],
                    $entry['yearTo'],
                    $entry['power'],
                ]));
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }
}

if (!function_exists('tecdoc_desc_normalize_label')) {
    function tecdoc_desc_normalize_label(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        return str_ends_with($label, ':') ? $label : ($label . ':');
    }
}

if (!function_exists('tecdoc_desc_extract_eans')) {
    function tecdoc_desc_extract_eans(array ...$payloads): array
    {
        $eans = [];
        $seen = [];

        foreach ($payloads as $payload) {
            if ($payload === []) {
                continue;
            }

            foreach (tecdoc_desc_walk_arrays($payload, static function (array $node): ?array {
                foreach (['ean', 'eanNumber', 'gtin', 'barcode'] as $key) {
                    $value = trim((string)($node[$key] ?? ''));
                    if ($value !== '' && preg_match('/^\d{8,14}$/', $value)) {
                        return ['label' => 'ean', 'value' => $value];
                    }
                }

                return null;
            }) as $item) {
                if (!isset($seen[$item['value']])) {
                    $seen[$item['value']] = true;
                    $eans[] = $item['value'];
                }
            }

            foreach (['eans', 'eanNumbers', 'gtins'] as $key) {
                if (!isset($payload[$key]) || !is_array($payload[$key])) {
                    continue;
                }
                foreach ($payload[$key] as $entry) {
                    $value = trim(is_scalar($entry) ? (string)$entry : (string)($entry['ean'] ?? $entry['number'] ?? ''));
                    if ($value !== '' && !isset($seen[$value])) {
                        $seen[$value] = true;
                        $eans[] = $value;
                    }
                }
            }
        }

        return $eans;
    }
}

if (!function_exists('tecdoc_desc_rows_append')) {
    function tecdoc_desc_rows_append(array &$rows, array &$seen, string $label, string $value): void
    {
        $label = tecdoc_desc_normalize_label($label);
        $value = trim($value);
        if ($label === '' || $value === '') {
            return;
        }

        $key = mb_strtolower(rtrim($label, ': '));
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $rows[] = ['label' => $label, 'value' => $value];
    }
}

if (!function_exists('tecdoc_desc_build_sheet_html')) {
    function tecdoc_desc_build_sheet_html(array $rows, array $oemLines = []): string
    {
        if ($rows === [] && $oemLines === []) {
            return '';
        }

        $html = '<h3>Descrierea</h3>';
        if ($rows !== []) {
            $html .= '<dl class="tecdoc-desc-sheet">';
            foreach ($rows as $row) {
                $html .= '<dt>' . tecdoc_desc_h(tecdoc_desc_normalize_label((string)$row['label'])) . '</dt>';
                $html .= '<dd>' . tecdoc_desc_h((string)$row['value']) . '</dd>';
            }
            $html .= '</dl>';
        }

        if ($oemLines !== []) {
            $html .= '<h4>Coduri OEM compatibile</h4>';
            $html .= '<dl class="tecdoc-desc-sheet tecdoc-desc-sheet--oem">';
            foreach (array_slice($oemLines, 0, 40) as $line) {
                $html .= '<dt>' . tecdoc_desc_h((string)$line['brand']) . '</dt>';
                $html .= '<dd>' . tecdoc_desc_h((string)$line['number']) . '</dd>';
            }
            $html .= '</dl>';
        }

        return $html;
    }
}

if (!function_exists('tecdoc_desc_plain_text_to_rows')) {
    function tecdoc_desc_plain_text_to_rows(string $text): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return [];
        }

        $rows = [];
        $seen = [];
        $lines = explode("\n", $text);
        $pendingLabel = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(.{2,120}?):\s*(.*)$/u', $line, $matches)) {
                $label = trim($matches[1]);
                $value = trim($matches[2]);
                if ($value !== '') {
                    tecdoc_desc_rows_append($rows, $seen, $label, $value);
                    $pendingLabel = '';
                    continue;
                }
                $pendingLabel = $label;
                continue;
            }

            if ($pendingLabel !== '') {
                tecdoc_desc_rows_append($rows, $seen, $pendingLabel, $line);
                $pendingLabel = '';
                continue;
            }
        }

        return $rows;
    }
}

if (!function_exists('tecdoc_desc_plain_text_to_html')) {
    function tecdoc_desc_plain_text_to_html(string $text): string
    {
        $rows = tecdoc_desc_plain_text_to_rows($text);
        if ($rows === []) {
            return '';
        }

        return tecdoc_desc_build_sheet_html($rows, []);
    }
}

if (!function_exists('tecdoc_desc_is_plain_spec_sheet')) {
    function tecdoc_desc_is_plain_spec_sheet(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || besoiu_note_is_html($text)) {
            return false;
        }

        return preg_match('/^[^\n:]{2,80}:\s*\S/m', $text) === 1;
    }
}

if (!function_exists('tecdoc_desc_build_compat_html')) {
    function tecdoc_desc_build_compat_html(array $entries): string
    {
        return '';
    }
}

if (!function_exists('tecdoc_desc_build_specs_html')) {
    function tecdoc_desc_build_specs_html(array $criteria): string
    {
        return '';
    }
}

if (!function_exists('tecdoc_desc_build_oem_html')) {
    function tecdoc_desc_build_oem_html(array $oemLines): string
    {
        return '';
    }
}

if (!function_exists('tecdoc_note_looks_complete')) {
    function tecdoc_note_looks_complete(string $note): bool
    {
        if (!besoiu_note_is_html($note)) {
            return tecdoc_desc_is_plain_spec_sheet($note);
        }

        return str_contains($note, 'tecdoc-desc-sheet')
            && str_contains($note, '<dt>')
            && str_contains($note, '<dd>');
    }
}

if (!function_exists('tecdoc_build_product_description')) {
    /**
     * @return array{html:string,article_id:int,source:string,error:string}
     */
    function tecdoc_build_product_description(string $code, string $brand = '', string $name = '', int $articleId = 0, string $oemCsv = ''): array
    {
        $code = trim($code);
        $brand = trim($brand);
        $name = trim($name);

        if ($articleId <= 0) {
            $articleId = tecdoc_find_article_id($code, $brand);
        }

        if ($articleId <= 0) {
            return [
                'html' => '',
                'article_id' => 0,
                'source' => 'missing',
                'error' => 'Nu s-a găsit articolul în TecDoc.',
            ];
        }

        $article = tecdoc_find_article_for_import($code, $brand) ?? [];
        $productName = $name !== '' ? $name : tecdoc_article_name($article);
        $brandName = $brand !== '' ? $brand : tecdoc_article_brand($article);
        $productCode = $code !== '' ? $code : tecdoc_article_number($article);

        $complete = tecdoc_fetch_article_complete_details($articleId);
        $criteriaPayload = tecdoc_fetch_article_criteria($articleId);
        $crossPayload = tecdoc_fetch_article_cross_references($articleId);

        $criteria = tecdoc_desc_extract_criteria($complete, $criteriaPayload, $article);
        if ($criteria === [] && tecdoc_article_specs($article) !== '') {
            foreach (preg_split('/\s*\|\s*/', tecdoc_article_specs($article)) ?: [] as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '' || !str_contains($chunk, ':')) {
                    continue;
                }
                [$label, $value] = array_pad(explode(':', $chunk, 2), 2, '');
                $criteria[] = ['label' => trim($label), 'value' => trim($value)];
            }
        }

        $oemLines = tecdoc_desc_extract_oem_codes($complete, $crossPayload, $article);
        $eans = tecdoc_desc_extract_eans($complete, $criteriaPayload, $article);

        $rows = [];
        $seen = [];
        $hasMount = false;

        foreach ($criteria as $item) {
            $label = (string)($item['label'] ?? '');
            if (stripos($label, 'montare') !== false) {
                $hasMount = true;
            }
            tecdoc_desc_rows_append($rows, $seen, $label, (string)($item['value'] ?? ''));
        }

        if (!$hasMount) {
            $mountValue = trim($brandName . ' ' . $productCode . ' ' . $productName);
            if ($mountValue !== '') {
                array_unshift($rows, ['label' => 'Partea de montare:', 'value' => $mountValue]);
                $seen['partea de montare'] = true;
            }
        }

        tecdoc_desc_rows_append($rows, $seen, 'Număr articol', $productCode);
        tecdoc_desc_rows_append($rows, $seen, 'Producătorul', $brandName);
        if ($eans !== []) {
            tecdoc_desc_rows_append($rows, $seen, 'Numere EAN', implode(', ', $eans));
        }
        tecdoc_desc_rows_append($rows, $seen, 'Condiție', 'Nou');

        if ($oemLines === [] && $oemCsv !== '') {
            foreach (preg_split('/\s*,\s*/', $oemCsv) ?: [] as $oemCode) {
                $oemCode = trim($oemCode);
                if ($oemCode !== '') {
                    $oemLines[] = ['brand' => 'OEM', 'number' => $oemCode];
                }
            }
        }
        if ($oemLines === []) {
            foreach (tecdoc_article_oem_codes($article) as $oemCode) {
                $oemCode = trim($oemCode);
                if ($oemCode !== '') {
                    $oemLines[] = ['brand' => 'OEM', 'number' => $oemCode];
                }
            }
        }

        $html = tecdoc_desc_build_sheet_html($rows, $oemLines);

        if ($html === '') {
            return [
                'html' => '',
                'article_id' => $articleId,
                'source' => 'empty',
                'error' => 'TecDoc nu a returnat specificații pentru acest articol.',
            ];
        }

        return [
            'html' => besoiu_note_sanitize_html($html),
            'article_id' => $articleId,
            'source' => 'tecdoc_api',
            'error' => '',
        ];
    }
}

if (!function_exists('tecdoc_resolve_product_description')) {
    function tecdoc_resolve_product_description(array $product): string
    {
        $websiteStored = besoiu_note_prepare(besoiu_resolve_product_description($product));
        if ($websiteStored !== '' && tecdoc_note_looks_complete($websiteStored)) {
            if (tecdoc_desc_is_plain_spec_sheet($websiteStored)) {
                $plainHtml = tecdoc_desc_plain_text_to_html($websiteStored);
                return $plainHtml !== '' ? besoiu_note_sanitize_html($plainHtml) : besoiu_note_render($websiteStored);
            }

            return besoiu_note_is_html($websiteStored) ? besoiu_note_sanitize_html($websiteStored) : besoiu_note_render($websiteStored);
        }

        if ($websiteStored !== '' && tecdoc_desc_is_plain_spec_sheet($websiteStored)) {
            $plainHtml = tecdoc_desc_plain_text_to_html($websiteStored);
            if ($plainHtml !== '') {
                return besoiu_note_sanitize_html($plainHtml);
            }
        }

        if ($websiteStored !== '') {
            return besoiu_note_render($websiteStored);
        }

        $stored = besoiu_note_prepare(trim((string)($product['pNote'] ?? '')));
        if ($stored !== '' && tecdoc_note_looks_complete($stored)) {
            if (tecdoc_desc_is_plain_spec_sheet($stored)) {
                $plainHtml = tecdoc_desc_plain_text_to_html($stored);
                return $plainHtml !== '' ? besoiu_note_sanitize_html($plainHtml) : besoiu_note_render($stored);
            }

            return besoiu_note_is_html($stored) ? besoiu_note_sanitize_html($stored) : besoiu_note_render($stored);
        }

        if ($stored !== '' && tecdoc_desc_is_plain_spec_sheet($stored)) {
            $plainHtml = tecdoc_desc_plain_text_to_html($stored);
            if ($plainHtml !== '') {
                return besoiu_note_sanitize_html($plainHtml);
            }
        }

        $code = trim((string)($product['pCode'] ?? ''));
        $brand = trim((string)($product['pBrand'] ?? ''));
        $name = trim((string)($product['pName'] ?? ''));

        if ($code === '') {
            return $stored !== '' ? besoiu_note_render($stored) : '';
        }

        $articleId = 0;
        $rawJson = json_decode((string)($product['raw_json'] ?? ''), true);
        if (is_array($rawJson)) {
            $articleId = (int)($rawJson['tecdoc_file']['ttc_art_id'] ?? $rawJson['tecdoc_api']['article_id'] ?? 0);
        }

        $built = tecdoc_build_product_description(
            $code,
            $brand,
            $name,
            $articleId,
            trim((string)($product['pOem'] ?? ''))
        );
        if (($built['html'] ?? '') !== '') {
            return $built['html'];
        }

        if ($stored !== '') {
            return besoiu_note_render($stored);
        }

        return 'Produs disponibil în catalogul Besoiu Piese Auto. Pentru compatibilitate exactă, recomandăm verificarea după VIN.';
    }
}
