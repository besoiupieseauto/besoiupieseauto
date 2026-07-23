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
        $description = self::buildDescriptionHtml($first, $parameters, $compatEntries);
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
     * @param array<string, string> $first
     * @param list<array{key:string,value:string}> $parameters
     * @param list<array<string, string>> $compatEntries
     */
    private static function buildDescriptionHtml(array $first, array $parameters, array $compatEntries): string
    {
        $name = htmlspecialchars(trim((string) ($first['ART_NAME'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $brand = htmlspecialchars(trim((string) ($first['ART_BRAND'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $code = htmlspecialchars(trim((string) ($first['ART_CODE_1'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<p><b>' . $name;
        if ($brand !== '') {
            $html .= ' ' . $brand;
        }
        $html .= '</b>';
        if ($code !== '') {
            $html .= ' (Cod: ' . $code . ')';
        }
        $html .= '</p>';

        if ($parameters !== []) {
            $html .= '<p><b>Specificații tehnice (TecDoc):</b></p><ul>';
            foreach ($parameters as $spec) {
                $key = htmlspecialchars((string) ($spec['key'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $value = htmlspecialchars((string) ($spec['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if ($value !== '') {
                    $html .= '<li>' . $key . ': ' . $value . '</li>';
                } else {
                    $html .= '<li>' . $key . '</li>';
                }
            }
            $html .= '</ul>';
        }

        if ($compatEntries !== []) {
            $html .= '<p><b>Compatibil cu:</b></p><ul>';
            foreach (array_slice($compatEntries, 0, 25) as $entry) {
                $line = self::formatCompatLine($entry);
                if ($line !== '') {
                    $html .= '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
                }
            }
            if (count($compatEntries) > 25) {
                $html .= '<li>… +' . (count($compatEntries) - 25) . ' vehicule</li>';
            }
            $html .= '</ul>';
        }

        return $html;
    }

    /** @param array<string, string> $entry */
    private static function formatCompatLine(array $entry): string
    {
        $parts = array_filter([
            trim((string) ($entry['CAR_BRAND'] ?? '')),
            trim((string) ($entry['CAR_MODEL'] ?? '')),
            trim((string) ($entry['CAR_TYP'] ?? '')),
        ]);
        $line = implode(' ', $parts);
        $yf = trim((string) ($entry['CAR_OF_YEAR'] ?? ''));
        $yt = trim((string) ($entry['CAR_TO_YEAR'] ?? ''));
        if ($yf !== '' || $yt !== '') {
            $line .= ' (' . ($yf !== '' ? $yf : '?') . ' - ' . ($yt !== '' ? $yt : 'Prezent') . ')';
        }
        $kw = trim((string) ($entry['CAR_KW'] ?? ''));
        if ($kw !== '') {
            $line .= ' · ' . $kw . ' kW';
        }

        return trim($line);
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
