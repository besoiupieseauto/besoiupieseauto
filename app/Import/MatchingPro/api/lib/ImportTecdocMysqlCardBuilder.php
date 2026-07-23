<?php
declare(strict_types=1);

/**
 * Card Import Pro din MySQL TecDoc (besoiu_tecdoc_base / tabele unificate).
 * Completează titlu, parametri, descriere, compat — imagine opțională (Poze/Autopartner).
 */
final class ImportTecdocMysqlCardBuilder
{
    /**
     * @param list<array<string, string>> $entries Rânduri Base/CoreDbLookup (ART_*)
     * @param array<string, mixed> $match Rând match scan (sku, matched_name, …)
     * @return array<string, mixed>|null
     */
    public static function fromEntries(
        array $entries,
        string $canonicalBrand,
        string $code1,
        float $priceNet,
        string $supplierType,
        array $match = []
    ): ?array {
        if ($entries === []) {
            return null;
        }

        $first = $entries[0];
        $artName = trim((string) ($first['ART_NAME'] ?? $match['matched_name'] ?? $match['name'] ?? ''));
        if ($artName === '') {
            return null;
        }

        $effectiveCode = trim((string) ($first['ART_CODE_1'] ?? $code1));
        if ($effectiveCode === '') {
            $effectiveCode = trim((string) ($match['sku_supplier'] ?? $code1));
        }

        $parameters = self::parsePartsInfo((string) ($first['PARTS_INFO'] ?? ''));
        $compatEntries = self::entriesWithVehicle($entries);
        $compatFromOe = false;
        foreach ($entries as $entry) {
            if (is_array($entry) && trim((string) ($entry['_compat_from_oe'] ?? '')) !== '') {
                $compatFromOe = true;
                break;
            }
        }
        // Același skeleton HTML ca în „Procesare fisier import Base.html” / import_base_generate_description.
        $description = self::buildDescriptionFromEntries($entries);
        $title = self::buildTitle($artName, $canonicalBrand, $parameters);

        $pricePurchaseVat = $priceNet > 0 ? (int) ceil($priceNet * 1.21) : null;
        $priceFinal = $priceNet > 0 ? (int) ceil($priceNet * 1.21 * 1.35) : null;

        $card = [
            'sku' => $effectiveCode,
            'brand' => $canonicalBrand,
            'ttcArtId' => trim((string) ($first['TTC_ART_ID'] ?? $match['matched_ttc_art_id'] ?? '')),
            'autopartnerCode' => '',
            'imageSource' => '',
            'hasImage' => false,
            'scrapeQuery' => $title,
            'ean' => trim((string) ($first['ART_EAN'] ?? $match['matched_ean'] ?? '')),
            'name' => trim(str_replace('OPC', '', $artName)),
            'title' => $title,
            'description' => $description,
            'parameters' => $parameters,
            'termsOfUse' => str_replace('|', '; ', (string) ($first['TERMS_OF_USE'] ?? '')),
            'oemCodes' => (string) ($first['ART_CROSS'] ?? ''),
            'priceNet' => $priceNet > 0 ? round($priceNet, 2) : null,
            'priceFinal' => $priceFinal,
            'pricePurchaseVat' => $pricePurchaseVat,
            'priceRecommended' => $priceFinal !== null ? (int) ceil($priceFinal * 1.35) : null,
            'supplier' => $supplierType,
            'compatCount' => count($compatEntries),
            'compatFromOe' => $compatFromOe,
            // Pentru regenerare / staging — aceleași rânduri ca generateDescription() din Base.html
            'sourceRows' => $entries,
            'imageUrl' => '',
            'pozeFolder' => '',
            'scrapedImageUrl' => '',
            'scrapedImageSource' => '',
            'cardBuild' => 'mysql_besoiu_tecdoc_base',
            'isPreview' => false,
            'tecdocDataComplete' => true,
            'matchStatus' => (string) ($match['status'] ?? ''),
            'matchMethod' => (string) ($match['match_method'] ?? ''),
        ];

        if (function_exists('import_attach_local_image_to_card')) {
            $card = import_attach_local_image_to_card($card);
        }
        if (function_exists('import_normalize_card_image_fields')) {
            $card = import_normalize_card_image_fields($card);
        }
        if (function_exists('import_motor_normalize_card_purchase_prices')) {
            $card = import_motor_normalize_card_purchase_prices($card);
        }

        return $card;
    }

    /**
     * @param list<array<string, string>> $entries
     * @return list<array{key:string,value:string}>
     */
    private static function parsePartsInfo(string $partsInfo): array
    {
        if (trim($partsInfo) === '') {
            return [];
        }

        $out = [];
        foreach (preg_split('/\s*\|\s*/', $partsInfo) ?: [] as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (str_contains($chunk, '::')) {
                [$key, $value] = array_map('trim', explode('::', $chunk, 2));
                $out[] = ['key' => $key, 'value' => $value];
            } else {
                $out[] = ['key' => $chunk, 'value' => ''];
            }
        }

        return array_slice($out, 0, 24);
    }

    /**
     * @param list<array{key:string,value:string}> $parameters
     */
    private static function buildTitle(string $artName, string $brand, array $parameters): string
    {
        $title = trim($artName);
        if ($title === '') {
            return '';
        }

        $brandUp = strtoupper(trim($brand));
        if ($brandUp !== '' && !str_contains(strtoupper($title), $brandUp)) {
            $title .= ' ' . trim($brand);
        }

        return mb_strlen($title) > 150 ? (mb_substr($title, 0, 147) . '...') : $title;
    }

    /**
     * Descriere HTML publică — identică cu generateDescription() din
     * „Procesare fisier import Base.html” / htmlviewer.
     *
     * @param list<array<string, string>> $entries
     */
    public static function descriptionFromEntries(array $entries): string
    {
        return self::buildDescriptionFromEntries($entries);
    }

    /**
     * Descriere HTML — skeleton fix din Base.html (p / ul / li).
     *
     * @param list<array<string, string>> $entries
     */
    private static function buildDescriptionFromEntries(array $entries): string
    {
        self::ensureImportBaseLib();
        if (function_exists('import_base_generate_description')) {
            $html = trim((string) import_base_generate_description($entries));
            if ($html !== '') {
                return $html;
            }
        }

        return self::buildDescriptionHtmlFallback($entries);
    }

    private static function ensureImportBaseLib(): void
    {
        if (function_exists('import_base_generate_description')) {
            return;
        }

        $candidates = [
            dirname(__DIR__, 4) . '/Backend/src/Controllers/Produse/import_base_lib.php',
            dirname(__DIR__, 5) . '/app/Backend/src/Controllers/Produse/import_base_lib.php',
        ];
        if (defined('BESOIU_ROOT')) {
            array_unshift(
                $candidates,
                rtrim((string) BESOIU_ROOT, '/\\') . '/app/Backend/src/Controllers/Produse/import_base_lib.php'
            );
        }
        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
    }

    /**
     * Fallback local dacă import_base_lib nu e încărcat — aceeași structură Base.html.
     *
     * @param list<array<string, string>> $entries
     */
    private static function buildDescriptionHtmlFallback(array $entries): string
    {
        if ($entries === []) {
            return '';
        }

        $first = $entries[0];
        $name = htmlspecialchars(
            trim(preg_replace('/\bOPC\b/u', '', trim((string) ($first['ART_NAME'] ?? ''))) ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $brand = htmlspecialchars(trim((string) ($first['ART_BRAND'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $code = htmlspecialchars(trim((string) ($first['ART_CODE_1'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<p><b>' . trim($name . ' ' . $brand) . '</b>';
        if ($code !== '') {
            $html .= ' (Cod: ' . $code . ')';
        }
        $html .= '</p>';

        $specs = trim((string) ($first['PARTS_INFO'] ?? ''));
        if ($specs !== '') {
            $seen = [];
            $specLines = [];
            foreach (preg_split('/\s*\|\s*/u', $specs) ?: [] as $line) {
                $line = trim(str_replace('::', ': ', $line));
                if ($line === '') {
                    continue;
                }
                $normKey = function_exists('mb_strtolower')
                    ? mb_strtolower(preg_replace('/\s+/u', ' ', $line) ?? $line, 'UTF-8')
                    : strtolower(preg_replace('/\s+/u', ' ', $line) ?? $line);
                if (isset($seen[$normKey])) {
                    continue;
                }
                $seen[$normKey] = true;
                if (str_contains($line, ':')) {
                    [$key, $val] = array_pad(explode(':', $line, 2), 2, '');
                    $specLines[] = '<li>' . htmlspecialchars(trim($key) . ': ' . trim($val), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
                } else {
                    $specLines[] = '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
                }
            }
            if ($specLines !== []) {
                $html .= '<p><b>Specificatii tehnice:</b></p><ul>' . implode('', $specLines) . '</ul>';
            }
        }

        $compatEntries = self::entriesWithVehicle($entries);
        if ($compatEntries !== []) {
            $compHtml = self::buildCompatHtmlNested($compatEntries);
            if ($compHtml !== '') {
                $html .= '<p><b>Compatibil cu urmatoarele modele auto:</b></p><ul>' . $compHtml . '</ul>';
            }
        }

        $cross = trim((string) ($first['ART_CROSS'] ?? ''));
        if ($cross !== '') {
            $oemLines = [];
            $seenOem = [];
            foreach (preg_split('/\r?\n|\|/u', $cross) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (!str_contains($line, ':')) {
                    $line = 'OEM: ' . $line;
                }
                $norm = function_exists('mb_strtolower')
                    ? mb_strtolower($line, 'UTF-8')
                    : strtolower($line);
                if (isset($seenOem[$norm])) {
                    continue;
                }
                $seenOem[$norm] = true;
                $oemLines[] = '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
            }
            if ($oemLines !== []) {
                $html .= '<p><b>Coduri OE echivalente:</b></p><ul>' . implode('', $oemLines) . '</ul>';
            }
        }

        return $html;
    }

    /**
     * Compat nested: marcă → model → motorizări (ca în Base.html).
     *
     * @param list<array<string, string>> $entries
     */
    private static function buildCompatHtmlNested(array $entries): string
    {
        $compatMap = [];
        foreach ($entries as $entry) {
            $carBrand = trim((string) ($entry['CAR_BRAND'] ?? ''));
            $carModel = trim((string) ($entry['CAR_MODEL'] ?? ''));
            $carType = trim((string) ($entry['CAR_TYP'] ?? ''));
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
            $yfRaw = trim((string) ($entry['CAR_OF_YEAR'] ?? ''));
            $ytRaw = trim((string) ($entry['CAR_TO_YEAR'] ?? ''));
            $yearFrom = (int) preg_replace('/\D+/', '', substr($yfRaw, 0, 4));
            $yearTo = (int) preg_replace('/\D+/', '', substr($ytRaw, 0, 4));
            $effectiveMax = $yearTo > 1900 ? $yearTo : (int) date('Y');
            $clampedFrom = $yearFrom > 1900 ? max($yearFrom, 2000) : 2000;
            $grp['minYear'] = min($grp['minYear'], $clampedFrom);
            $grp['maxYear'] = max($grp['maxYear'], $effectiveMax);

            $engine = trim(preg_replace('/\([^)]*\)/u', '', $carType) ?? '');
            $engine = trim(preg_replace('/\s+/u', ' ', $engine) ?? '');
            if ($engine !== '') {
                $grp['engines'][$engine] = true;
            }

            $power = trim((string) ($entry['CAR_KW'] ?? '')) !== '' ? trim((string) $entry['CAR_KW']) . ' KW' : '';
            $yearToLabel = $yearTo > 1900 ? (string) $yearTo : 'Prezent';
            $yearFromLabel = (string) $clampedFrom;
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
            $compHtml .= '<li><b>' . htmlspecialchars((string) $carBrand, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b><ul>';
            ksort($models);
            foreach ($models as $modelKey => $data) {
                $modelKeyStr = (string) $modelKey;
                $n = count($data['entries']);
                if ($n === 1) {
                    $e = $data['entries'][0];
                    $modelTypeStr = (string) ($e['modelType'] ?? '');
                    $rest = trim(str_starts_with($modelTypeStr, $modelKeyStr) ? substr($modelTypeStr, strlen($modelKeyStr)) : $modelTypeStr);
                    $compHtml .= '<li><b>' . htmlspecialchars($modelKeyStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>'
                        . ($rest !== '' ? ' ' . htmlspecialchars($rest, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '')
                        . ($e['power'] !== '' ? ' ' . htmlspecialchars($e['power'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '')
                        . ' (' . htmlspecialchars($e['yearFrom'] . '-' . $e['yearTo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')</li>';
                } elseif ($n <= 12) {
                    $compHtml .= '<li><b>' . htmlspecialchars($modelKeyStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b><ul>';
                    foreach ($data['entries'] as $e) {
                        $modelTypeStr = (string) ($e['modelType'] ?? '');
                        $rest = trim(str_starts_with($modelTypeStr, $modelKeyStr) ? substr($modelTypeStr, strlen($modelKeyStr)) : $modelTypeStr);
                        $compHtml .= '<li>' . htmlspecialchars($rest !== '' ? $rest : $modelTypeStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                            . ($e['power'] !== '' ? ' ' . htmlspecialchars($e['power'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '')
                            . ' (' . htmlspecialchars($e['yearFrom'] . '-' . $e['yearTo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')</li>';
                    }
                    $compHtml .= '</ul></li>';
                } else {
                    $minY = $data['minYear'] === 9999 ? '?' : (string) $data['minYear'];
                    $maxY = $data['maxYear'] <= 0 ? 'Prezent' : (string) $data['maxYear'];
                    $yearStr = $minY === $maxY ? $minY : $minY . ' - ' . $maxY;
                    $engList = implode(', ', array_keys($data['engines']));
                    $compHtml .= '<li><b>' . htmlspecialchars($modelKeyStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b> ('
                        . htmlspecialchars($yearStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')';
                    if ($engList !== '') {
                        $compHtml .= '<br><i>Motorizari:</i> ' . htmlspecialchars($engList, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    }
                    $compHtml .= '</li>';
                }
            }
            $compHtml .= '</ul></li>';
        }

        return $compHtml;
    }

    /**
     * @param list<array<string, string>> $entries
     * @return list<array<string, string>>
     */
    private static function entriesWithVehicle(array $entries): array
    {
        $out = [];
        foreach ($entries as $entry) {
            if (trim((string) ($entry['CAR_BRAND'] ?? '')) !== '') {
                $out[] = $entry;
            }
        }

        return $out !== [] ? $out : $entries;
    }
}
