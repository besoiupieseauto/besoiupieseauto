<?php
declare(strict_types=1);

function clean_text($value): string
{
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim(strip_tags($value));
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function normalize_key(string $value): string
{
    $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
    $value = strtr($value, [
        'Дѓ' => 'a', 'Гў' => 'a', 'Г®' => 'i', 'И™' => 's', 'Еџ' => 's', 'И›' => 't', 'ЕЈ' => 't',
        '-' => ' ', '_' => ' ', '.' => ' ', '/' => ' ',
        '(' => ' ', ')' => ' ', '[' => ' ', ']' => ' ', ':' => ' ',
    ]);
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function first_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        $key = normalize_key($key);
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') return trim((string)$row[$key]);
    }
    return '';
}

function first_value_by_patterns(array $row, array $patterns): string
{
    foreach ($row as $key => $value) {
        $normalizedKey = normalize_key((string)$key);
        if (trim((string)$value) === '') {
            continue;
        }
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedKey)) {
                return trim((string)$value);
            }
        }
    }

    return '';
}

function normalize_amount_text(string $value): string
{
    $value = clean_text($value);
    $value = str_ireplace(['ron', 'lei', 'eur', 'usd'], '', $value);
    $value = preg_replace('/\s+/u', '', $value) ?? $value;
    $value = str_replace(',', '.', $value);
    $value = preg_replace('/[^0-9.\-]/', '', $value) ?? $value;

    return trim($value, '.');
}

function extract_primary_label(string $value): string
{
    $value = clean_text($value);
    if ($value === '') {
        return '';
    }

    $parts = preg_split('/(::|\\||,|;)/u', $value) ?: [];
    $skipPatterns = [
        '/^an fabricatie/ui',
        '/^conform brosurii/ui',
        '/^culoare/ui',
        '/^diametru/ui',
        '/^lungime/ui',
        '/^material/ui',
        '/^de la /ui',
        '/^pana la /ui',
    ];

    foreach ($parts as $part) {
        $label = clean_text((string)$part);
        if ($label === '') {
            continue;
        }

        $labelLength = function_exists('mb_strlen') ? mb_strlen($label, 'UTF-8') : strlen($label);
        if ($labelLength < 3 || $labelLength > 70) {
            continue;
        }

        if (preg_match('/^[0-9\/.\- ]+$/', $label)) {
            continue;
        }

        $skip = false;
        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $label)) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }

        return $label;
    }

    return '';
}

function extract_price(array $row): string
{
    $price = first_value($row, [
        'pret unitar',
        'pret',
        'price',
        'pret achizitie',
        'pret vanzare',
        'pret de vanzare',
        'pret cu tva',
        'pret fara tva',
        'pret net',
        'pret brut',
        'pret lista',
        'selling price',
        'sale price',
        'list price',
        'net price',
        'gross price',
        'buy price',
        'purchase price',
        'dealer price',
        'unit price',
        'price with vat',
        'price without vat',
        'retail price',
        'rrp',
        'msrp',
        'amount',
        'valoare',
        'cost',
    ]);

    if ($price === '') {
        $price = first_value_by_patterns($row, [
            '/(^| )pret( |$)/u',
            '/price/u',
            '/cost/u',
            '/valoare/u',
            '/amount/u',
            '/net/u',
            '/gross/u',
            '/\\brrp\\b/u',
            '/\\bmsrp\\b/u',
        ]);
    }

    return normalize_amount_text($price);
}

function detect_delimiter(string $line): string
{
    $candidates = [";" => substr_count($line, ';'), "," => substr_count($line, ','), "\t" => substr_count($line, "\t")];
    arsort($candidates);
    return (string)array_key_first($candidates);
}

function read_csv_rows(string $path): array
{
    $first = file_get_contents($path, false, null, 0, 4096) ?: '';
    $delimiter = detect_delimiter($first);
    $handle = fopen($path, 'r');
    if (!$handle) return [];
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) return [];
    $headers = array_map('normalize_key', $headers);
    $rows = [];
    while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
        $row = [];
        foreach ($headers as $i => $header) $row[$header] = $values[$i] ?? '';
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function column_index(string $cellRef): int
{
    $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellRef));
    $num = 0;
    for ($i = 0; $i < strlen($letters); $i++) $num = $num * 26 + (ord($letters[$i]) - 64);
    return max(0, $num - 1);
}

function read_xlsx_rows(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [];
    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sx = simplexml_load_string($sharedXml);
        if ($sx) foreach ($sx->si as $si) {
            $text = '';
            if (isset($si->t)) $text = (string)$si->t;
            elseif (isset($si->r)) foreach ($si->r as $r) $text .= (string)$r->t;
            $shared[] = $text;
        }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) return [];
    $xml = simplexml_load_string($sheetXml);
    if (!$xml) return [];
    $rawRows = [];
    foreach ($xml->sheetData->row as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $cell) {
            $attrs = $cell->attributes();
            $index = column_index((string)($attrs['r'] ?? 'A'));
            $type = (string)($attrs['t'] ?? '');
            $value = isset($cell->v) ? (string)$cell->v : '';
            if ($type === 's') $value = $shared[(int)$value] ?? '';
            elseif (($type === 'str' || $type === 'inlineStr') && isset($cell->is->t)) $value = (string)$cell->is->t;
            $row[$index] = $value;
        }
        if ($row) { ksort($row); $rawRows[] = $row; }
    }
    if (!$rawRows) return [];
    $headers = array_map('normalize_key', array_values($rawRows[0]));
    $rows = [];
    for ($r = 1; $r < count($rawRows); $r++) {
        $row = [];
        foreach ($headers as $i => $header) $row[$header] = $rawRows[$r][$i] ?? '';
        $rows[] = $row;
    }
    return $rows;
}

function read_import_rows(string $tmp, string $filename): array
{
    return str_ends_with(strtolower($filename), '.xlsx') ? read_xlsx_rows($tmp) : read_csv_rows($tmp);
}

function map_product_row(array $row, string $supplier): array
{
    $name = first_value($row, [
        'denumire articol', 'titlu', 'title', 'denumire', 'product name',
        'art name', 'article name', 'articol', 'nume produs'
    ]);
    $code = first_value($row, [
        'cod articol', 'cod produs sku', 'cod produs', 'sku', 'cod',
        'art code 1', 'art code 2', 'article code', 'article no'
    ]);
    $brand = first_value($row, [
        'producator', 'brand', 'manufacturer',
        'art brand', 'supplier name'
    ]);
    $price = extract_price($row);
    $stock = '';
    if (function_exists('import_supplier_row_stock_raw') && function_exists('import_parse_supplier_stock')) {
        $stockRaw = import_supplier_row_stock_raw($row);
        $parsedStock = import_parse_supplier_stock($stockRaw !== '' ? $stockRaw : null);
        if ($parsedStock !== null) {
            $stock = (string) (int) $parsedStock;
        } elseif ($stockRaw !== '') {
            $stock = clean_text($stockRaw);
        } else {
            $stock = '0';
        }
    } else {
        $stock = first_value($row, ['stoc cantitativ', 'stoc', 'stock', 'qty', 'quantity']);
        if ($stock === '') {
            $stock = '0';
        }
    }
    $category = first_value($row, [
        'grupa articol', 'categorie', 'category'
    ]);
    $note = first_value($row, [
        'descriere', 'description', 'coduri echivalente',
        'parts info', 'terms of use', 'art cross', 'car typ'
    ]);
    $image = first_value($row, ['imagine', 'image', 'picture']);
    $subCategory = first_value($row, ['subcategorie', 'subcategory']);
    if ($subCategory === '') {
        $subCategory = extract_primary_label(first_value($row, ['terms of use', 'parts info']));
    }
    $carMarca = first_value($row, ['car brand', 'marca', 'make']);
    $carModel = first_value($row, ['car model', 'model']);
    $carMotorizare = first_value($row, ['car typ', 'motorizare', 'engine']);

    if ($name === '') {
        $name = first_value($row, ['parts info']);
    }
    if ($code === '') {
        $code = first_value($row, ['ttc art id']);
    }
    if ($note === '') {
        $note = trim(implode(' | ', array_filter([
            first_value($row, ['parts info']),
            first_value($row, ['terms of use']),
            first_value($row, ['art cross']),
            first_value($row, ['car typ']),
            first_value($row, ['car body']),
            first_value($row, ['car of year']),
            first_value($row, ['car to year']),
            first_value($row, ['car kw']),
            first_value($row, ['car pm']),
        ])));
    }

    $image = first_value($row, ['imagine', 'image', 'picture']);
    $imageJson = '[]';
    if ($image !== '') {
        require_once dirname(__DIR__, 4) . '/system/import-image-validate.php';
        $cleanImage = clean_text($image);
        if (besoiu_import_image_url_is_trusted($cleanImage, 'caietcomenzi')
            || besoiu_import_image_url_is_trusted($cleanImage, 'tecdoc_api')) {
            $imageJson = json_encode([$cleanImage], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    return [
        'pName' => clean_text($name),
        'pCode' => clean_text($code),
        'pBrand' => clean_text($brand),
        'pMarca' => clean_text($carMarca),
        'pModel' => clean_text($carModel),
        'pMotorizare' => clean_text($carMotorizare),
        'pCar' => clean_text($brand),
        'pPrice' => $price,
        'pStock' => clean_text($stock),
        'pCategory' => clean_text($category),
        'pSubcategory' => clean_text($subCategory),
        'pSupplier' => clean_text($supplier),
        'pState' => 'Nou',
        'pCity' => '',
        'pNote' => clean_text($note),
        'pImages' => $imageJson,
        'pImageSource' => $imageJson !== '[]' ? 'caietcomenzi' : '',
        'pShipping' => '',
        'pWarranty' => '',
        'pReturn' => '',
        'pWhatsapp' => '',
        'raw_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
    ];
}

function supplier_from_row(array $row): string
{
    $name = first_value($row, ['furnizor', 'supplier', 'nume', 'name', 'denumire', 'producator', 'brand']);
    if ($name === '') {
        $values = array_values($row);
        $name = (string)($values[0] ?? '');
    }
    return clean_text($name);
}