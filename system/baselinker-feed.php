<?php

declare(strict_types=1);

/**
 * tm_108 — Feed permanent XML/JSON BaseLinker (fragmentat sub 30MB).
 */

const BASELINKER_FEED_MAX_FRAGMENT_BYTES = 28_311_552; // ~27 MB — sub limita 30MB BaseLinker

/** @return string */
function baselinker_feed_storage_dir(): string
{
    return dirname(__DIR__) . '/storage/feeds/baselinker';
}

function baselinker_feed_ensure_storage(): void
{
    $dir = baselinker_feed_storage_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/** @return array<string, mixed> */
function baselinker_feed_default_meta(): array
{
    return [
        'token' => '',
        'generated_at' => null,
        'product_count' => 0,
        'parts' => [],
        'xml_max_bytes' => BASELINKER_FEED_MAX_FRAGMENT_BYTES,
    ];
}

/** @return array<string, mixed> */
function baselinker_feed_load_meta(): array
{
    baselinker_feed_ensure_storage();
    $path = baselinker_feed_storage_dir() . '/meta.json';
    if (!is_readable($path)) {
        return baselinker_feed_default_meta();
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded)
        ? array_merge(baselinker_feed_default_meta(), $decoded)
        : baselinker_feed_default_meta();
}

/** @param array<string, mixed> $meta */
function baselinker_feed_save_meta(array $meta): void
{
    baselinker_feed_ensure_storage();
    $path = baselinker_feed_storage_dir() . '/meta.json';
    file_put_contents(
        $path,
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function baselinker_feed_resolve_token(PDO $pdo): string
{
    $meta = baselinker_feed_load_meta();
    $token = trim((string) ($meta['token'] ?? ''));
    if ($token !== '') {
        return $token;
    }

    $envToken = trim((string) (getenv('BASELINKER_FEED_TOKEN') ?: ($_ENV['BASELINKER_FEED_TOKEN'] ?? '')));
    if ($envToken !== '') {
        $meta['token'] = $envToken;
        baselinker_feed_save_meta($meta);

        return $envToken;
    }

    $token = bin2hex(random_bytes(16));
    $meta['token'] = $token;
    baselinker_feed_save_meta($meta);

    return $token;
}

function baselinker_feed_site_base_url(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        $host = 'besoiupieseauto.ro.test';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    return $scheme . '://' . $host;
}

/** @return array<string, string> */
function baselinker_feed_default_mapping(): array
{
    return [
        'name' => 'pName',
        'sku' => 'pCode',
        'price_brutto' => 'pPrice',
        'description' => 'pNoteMarketplace',
        'quantity' => 'pStock',
        'images' => 'pImages',
    ];
}

/** @return array<string, string> */
function baselinker_feed_load_mapping(PDO $pdo): array
{
    $defaults = baselinker_feed_default_mapping();

    try {
        $stmt = $pdo->query(
            "SELECT field_mapping FROM marketplace
             WHERE LOWER(TRIM(platform)) = 'baselinker'
             ORDER BY id DESC LIMIT 1"
        );
        $raw = $stmt ? $stmt->fetchColumn() : false;
        if (!is_string($raw) || trim($raw) === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        foreach ($defaults as $field => $defaultSource) {
            if (!isset($decoded[$field]) || trim((string) $decoded[$field]) === '') {
                $decoded[$field] = $defaultSource;
            }
        }

        /** @var array<string, string> $normalized */
        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = trim((string) $value);
            }
        }

        return $normalized !== [] ? $normalized : $defaults;
    } catch (Throwable) {
        return $defaults;
    }
}

/** @param array<string, mixed> $product @param array<string, string> $mapping */
function baselinker_feed_read_field(array $product, string $source): string
{
    if ($source === '' || str_starts_with($source, 'json:')) {
        return '';
    }

    return trim((string) ($product[$source] ?? ''));
}

/** @param array<string, mixed> $product @return list<string> */
function baselinker_feed_product_images(array $product, string $source, string $siteBaseUrl): array
{
    $raw = $product[$source] ?? '[]';
    if (!is_string($raw)) {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $urls = [];
    foreach ($decoded as $item) {
        if (!is_string($item) || trim($item) === '') {
            continue;
        }
        $path = trim($item);
        if (preg_match('#^https?://#i', $path) !== 1) {
            $path = rtrim($siteBaseUrl, '/') . '/' . ltrim(str_replace('\\', '/', $path), '/');
        }
        $urls[] = $path;
    }

    return array_values(array_unique($urls));
}

/** @param array<string, mixed> $product @param array<string, string> $mapping @return array<string, mixed> */
function baselinker_feed_product_entry(array $product, array $mapping, string $siteBaseUrl): array
{
    $sku = baselinker_feed_read_field($product, $mapping['sku'] ?? 'pCode');
    if ($sku === '') {
        $sku = trim((string) ($product['randomn_id'] ?? ''));
    }

    $name = baselinker_feed_read_field($product, $mapping['name'] ?? 'pName');
    if ($name === '') {
        $name = 'Produs #' . (string) ($product['randomn_id'] ?? $product['id'] ?? '');
    }

    $priceRaw = baselinker_feed_read_field($product, $mapping['price_brutto'] ?? 'pPrice');
    $price = is_numeric(str_replace(',', '.', $priceRaw)) ? round((float) str_replace(',', '.', $priceRaw), 2) : 0.0;

    $qtyRaw = baselinker_feed_read_field($product, $mapping['quantity'] ?? 'pStock');
    $quantity = max(0, (int) $qtyRaw);

    $description = baselinker_feed_read_field($product, $mapping['description'] ?? 'pNoteMarketplace');
    if ($description === '') {
        $description = baselinker_feed_read_field($product, 'pNoteWebsite');
    }
    if ($description === '') {
        $description = baselinker_feed_read_field($product, 'pNote');
    }

    $images = baselinker_feed_product_images(
        $product,
        $mapping['images'] ?? 'pImages',
        $siteBaseUrl
    );

    return [
        'sku' => $sku,
        'name' => $name,
        'price_brutto' => $price,
        'quantity' => $quantity,
        'description' => $description,
        'brand' => baselinker_feed_read_field($product, 'pBrand'),
        'oem' => baselinker_feed_read_field($product, 'pOem'),
        'category' => baselinker_feed_read_field($product, 'pCategory'),
        'images' => $images,
        'product_url' => rtrim($siteBaseUrl, '/') . '/produs?id=' . rawurlencode((string) ($product['randomn_id'] ?? '')),
    ];
}

function baselinker_feed_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** @param array<string, mixed> $entry */
function baselinker_feed_product_to_xml(array $entry): string
{
    $xml = "  <product>\n";
    $xml .= '    <sku>' . baselinker_feed_xml_escape((string) ($entry['sku'] ?? '')) . "</sku>\n";
    $xml .= '    <name>' . baselinker_feed_xml_escape((string) ($entry['name'] ?? '')) . "</name>\n";
    $xml .= '    <price_brutto>' . baselinker_feed_xml_escape(number_format((float) ($entry['price_brutto'] ?? 0), 2, '.', '')) . "</price_brutto>\n";
    $xml .= '    <quantity>' . (int) ($entry['quantity'] ?? 0) . "</quantity>\n";

    $description = (string) ($entry['description'] ?? '');
    if ($description !== '') {
        $xml .= "    <description><![CDATA[{$description}]]></description>\n";
    }

    foreach (['brand', 'oem', 'category'] as $extraField) {
        $extraValue = trim((string) ($entry[$extraField] ?? ''));
        if ($extraValue !== '') {
            $xml .= '    <' . $extraField . '>' . baselinker_feed_xml_escape($extraValue) . '</' . $extraField . ">\n";
        }
    }

    $productUrl = trim((string) ($entry['product_url'] ?? ''));
    if ($productUrl !== '') {
        $xml .= '    <url>' . baselinker_feed_xml_escape($productUrl) . "</url>\n";
    }

    $images = $entry['images'] ?? [];
    if (is_array($images) && $images !== []) {
        $xml .= "    <images>\n";
        foreach ($images as $imageUrl) {
            if (!is_string($imageUrl) || trim($imageUrl) === '') {
                continue;
            }
            $xml .= '      <image url="' . baselinker_feed_xml_escape(trim($imageUrl)) . "\"/>\n";
        }
        $xml .= "    </images>\n";
    }

    $xml .= "  </product>\n";

    return $xml;
}

function baselinker_feed_xml_header(string $generatedAt): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<products version="1.0" source="besoiupieseauto" generated="' . baselinker_feed_xml_escape($generatedAt) . "\">\n";
}

function baselinker_feed_xml_footer(): string
{
    return "</products>\n";
}

/** @param list<array{part:int,file:string,products:int,bytes:int,url:string}> $parts */
function baselinker_feed_build_index_xml(string $generatedAt, array $parts): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<baselinker_feed index="true" source="besoiupieseauto" generated="' . baselinker_feed_xml_escape($generatedAt) . '" parts="' . count($parts) . "\">\n";
    foreach ($parts as $part) {
        $xml .= '  <part number="' . (int) ($part['part'] ?? 0) . '" products="' . (int) ($part['products'] ?? 0) . '" bytes="' . (int) ($part['bytes'] ?? 0) . '" url="' . baselinker_feed_xml_escape((string) ($part['url'] ?? '')) . "\"/>\n";
    }
    $xml .= "</baselinker_feed>\n";

    return $xml;
}

/** @return array{success:bool,product_count:int,parts:int,generated_at:string,message:string} */
function baselinker_feed_regenerate(PDO $pdo): array
{
    baselinker_feed_ensure_storage();
    $token = baselinker_feed_resolve_token($pdo);
    $mapping = baselinker_feed_load_mapping($pdo);
    $siteBaseUrl = baselinker_feed_site_base_url();
    $generatedAt = gmdate('Y-m-d\TH:i:s\Z');

    foreach (glob(baselinker_feed_storage_dir() . '/catalog-*.xml') ?: [] as $oldFile) {
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
    foreach (glob(baselinker_feed_storage_dir() . '/catalog-*.json') ?: [] as $oldFile) {
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
    @unlink(baselinker_feed_storage_dir() . '/catalog-index.xml');
    @unlink(baselinker_feed_storage_dir() . '/catalog.json');

    $stmt = $pdo->prepare('SELECT * FROM produse WHERE status <> :inactive ORDER BY id ASC');
    $stmt->execute([':inactive' => '0']);

    $partNumber = 1;
    $partProducts = 0;
    $totalProducts = 0;
    /** @var list<array{part:int,file:string,products:int,bytes:int,url:string}> $partsMeta */
    $partsMeta = [];

    $xmlBody = '';
    $jsonItems = [];

    $openXmlPart = static function () use (&$xmlBody, $generatedAt): void {
        $xmlBody = baselinker_feed_xml_header($generatedAt);
    };

    $closeXmlPart = static function (int $partNumber, int $partProducts) use (&$xmlBody, &$partsMeta, $token): void {
        $xmlBody .= baselinker_feed_xml_footer();
        $fileName = sprintf('catalog-%03d.xml', $partNumber);
        $filePath = baselinker_feed_storage_dir() . '/' . $fileName;
        file_put_contents($filePath, $xmlBody, LOCK_EX);
        $bytes = strlen($xmlBody);
        $partsMeta[] = [
            'part' => $partNumber,
            'file' => $fileName,
            'products' => $partProducts,
            'bytes' => $bytes,
            'url' => baselinker_feed_public_urls($token)['xml_part'] . $partNumber,
        ];
    };

    $openXmlPart();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!is_array($row)) {
            continue;
        }

        $entry = baselinker_feed_product_entry($row, $mapping, $siteBaseUrl);
        $productXml = baselinker_feed_product_to_xml($entry);
        $candidateSize = strlen($xmlBody) + strlen($productXml) + strlen(baselinker_feed_xml_footer());

        if ($partProducts > 0 && $candidateSize > BASELINKER_FEED_MAX_FRAGMENT_BYTES) {
            $closeXmlPart($partNumber, $partProducts);
            ++$partNumber;
            $partProducts = 0;
            $openXmlPart();
        }

        $xmlBody .= $productXml;
        $jsonItems[] = $entry;
        ++$partProducts;
        ++$totalProducts;
    }

    if ($partProducts > 0 || $totalProducts === 0) {
        $closeXmlPart($partNumber, $partProducts);
    }

    $indexPath = baselinker_feed_storage_dir() . '/catalog-index.xml';
    file_put_contents($indexPath, baselinker_feed_build_index_xml($generatedAt, $partsMeta), LOCK_EX);

    $jsonPayload = [
        'source' => 'besoiupieseauto',
        'generated_at' => $generatedAt,
        'product_count' => $totalProducts,
        'parts' => array_map(static function (array $part) use ($token): array {
            return [
                'part' => (int) ($part['part'] ?? 0),
                'products' => (int) ($part['products'] ?? 0),
                'bytes' => (int) ($part['bytes'] ?? 0),
                'url' => baselinker_feed_public_urls($token)['json_part'] . (int) ($part['part'] ?? 0),
            ];
        }, $partsMeta),
        'products' => $jsonItems,
    ];

    $jsonPath = baselinker_feed_storage_dir() . '/catalog.json';
    file_put_contents($jsonPath, json_encode($jsonPayload, JSON_UNESCAPED_UNICODE), LOCK_EX);

    $meta = [
        'token' => $token,
        'generated_at' => $generatedAt,
        'product_count' => $totalProducts,
        'parts' => $partsMeta,
        'xml_max_bytes' => BASELINKER_FEED_MAX_FRAGMENT_BYTES,
    ];
    baselinker_feed_save_meta($meta);

    return [
        'success' => true,
        'product_count' => $totalProducts,
        'parts' => count($partsMeta),
        'generated_at' => $generatedAt,
        'message' => 'Feed BaseLinker regenerat (' . $totalProducts . ' produse, ' . count($partsMeta) . ' fragmente).',
    ];
}

/** @return array{xml:string,json:string,xml_index:string,json_index:string,xml_part:string,json_part:string,token:string} */
function baselinker_feed_public_urls(string $token): array
{
    $base = rtrim(baselinker_feed_site_base_url(), '/');
    $query = http_build_query(['token' => $token]);

    return [
        'token' => $token,
        'xml' => $base . '/api/baselinker-feed.php?' . $query . '&format=xml',
        'json' => $base . '/api/baselinker-feed.php?' . $query . '&format=json',
        'xml_index' => $base . '/api/baselinker-feed.php?' . $query . '&format=xml&part=index',
        'json_index' => $base . '/api/baselinker-feed.php?' . $query . '&format=json&part=index',
        'xml_part' => $base . '/api/baselinker-feed.php?' . $query . '&format=xml&part=',
        'json_part' => $base . '/api/baselinker-feed.php?' . $query . '&format=json&part=',
    ];
}

function baselinker_feed_validate_token(string $provided, PDO $pdo): bool
{
    $expected = baselinker_feed_resolve_token($pdo);

    return $expected !== '' && hash_equals($expected, trim($provided));
}

/** @return array{success:bool,queued:bool,job_id:int} */
function baselinker_feed_queue_regenerate(PDO $pdo, int $delaySeconds = 8): array
{
    $jobQueuePath = dirname(__DIR__) . '/system/JobQueue.php';
    if (!is_file($jobQueuePath)) {
        baselinker_feed_regenerate($pdo);

        return ['success' => true, 'queued' => false, 'job_id' => 0];
    }

    require_once $jobQueuePath;

    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM queue_jobs
             WHERE job_type = 'baselinker_feed_regenerate'
               AND status IN ('pending', 'processing')
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute();
        $existingId = (int) ($stmt->fetchColumn() ?: 0);
        if ($existingId > 0) {
            return ['success' => true, 'queued' => true, 'job_id' => $existingId];
        }
    } catch (Throwable) {
        // queue_jobs poate lipsi în medii vechi — fallback direct
        baselinker_feed_regenerate($pdo);

        return ['success' => true, 'queued' => false, 'job_id' => 0];
    }

    $queue = new JobQueue($pdo, 'default');
    $jobId = $queue->push('baselinker_feed_regenerate', ['source' => 'import_publish'], max(0, $delaySeconds));

    return ['success' => true, 'queued' => true, 'job_id' => $jobId];
}

/** @return array{success:bool,message:string,meta:array<string,mixed>,urls:array<string,string>} */
function baselinker_feed_info(PDO $pdo): array
{
    $token = baselinker_feed_resolve_token($pdo);
    $meta = baselinker_feed_load_meta();

    return [
        'success' => true,
        'message' => 'Feed BaseLinker disponibil.',
        'meta' => $meta,
        'urls' => baselinker_feed_public_urls($token),
    ];
}
