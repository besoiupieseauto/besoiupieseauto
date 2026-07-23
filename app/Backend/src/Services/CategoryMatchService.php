<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Categorii\CategoriiModel;

/**
 * Potrivire categorie/subcategorie pentru produse — reguli locale + Ollama + TecDoc OEM.
 */
final class CategoryMatchService
{
    private CategoriiModel $model;
    private ModuleOllamaSupport $ollama;

    public function __construct(?CategoriiModel $model = null, ?ModuleOllamaSupport $ollama = null)
    {
        $this->model = $model ?? new CategoriiModel();
        $this->ollama = $ollama ?? ModuleOllamaSupport::create();
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function match(array $product): array
    {
        $name = trim((string) ($product['name'] ?? $product['pName'] ?? ''));
        $brand = trim((string) ($product['brand'] ?? $product['pBrand'] ?? ''));
        $description = trim((string) ($product['description'] ?? ''));
        $specs = trim((string) ($product['specs'] ?? ''));
        $oem = trim((string) ($product['oem'] ?? $product['pOem'] ?? ''));
        $useOllama = (bool) ($product['use_ollama'] ?? true);
        $useTecdoc = (bool) ($product['use_tecdoc'] ?? true);
        $artName = trim((string) ($product['art_name'] ?? $product['tecdoc_art_name'] ?? ''));

        // Import Pro / TecDoc: ART_NAME exact din arbore (nivel 3) → familie L2 + root L1.
        if ($artName !== '') {
            $exactArt = $this->matchByArtNameExact($artName);
            if (($exactArt['category'] ?? '') !== '' && ($exactArt['subcategory'] ?? '') !== '') {
                return $this->finalizeBesoiuMatch(
                    $exactArt,
                    $product,
                    null,
                    'art_name_exact',
                    'Potrivire ART_NAME TecDoc ↔ frunză arbore categorii.'
                );
            }
            $inArt = $this->matchByArtNameInProductName($artName);
            if (($inArt['category'] ?? '') !== '' && ($inArt['subcategory'] ?? '') !== '') {
                return $this->finalizeBesoiuMatch($inArt, $product, null);
            }
        }

        $synonymTarget = CategorySearchSynonymService::resolveTerm($name);
        if ($synonymTarget !== null && $synonymTarget !== $name) {
            $name = $synonymTarget;
        }

        $haystackName = trim(implode(' ', array_filter([$name, $brand, $description, $specs])));

        // Nivel 3 (ART_NAME) se găsește în denumirea produsului → urcă la nivel 2 și 1.
        $inName = $this->matchByArtNameInProductName($haystackName !== '' ? $haystackName : $name);
        if ($inName['category'] !== '' && $inName['subcategory'] !== '') {
            return $this->finalizeBesoiuMatch($inName, $product, $synonymTarget);
        }

        $exact = $this->matchByArtNameExact($name);
        if ($exact['category'] !== '' || $exact['subcategory'] !== '') {
            return $this->finalizeBesoiuMatch($exact, $product, $synonymTarget, 'art_name_exact', 'Potrivire exactă ART_NAME ↔ categorie frunză.');
        }

        $haystack = trim(implode(' ', array_filter([$name, $brand, $description, $specs, $oem])));
        $local = $this->matchLocal($haystack);

        $result = [
            'ok' => $local['category'] !== '' || $local['subcategory'] !== '',
            'category' => $local['category'],
            'subcategory' => $local['subcategory'],
            'category_id' => $local['category_id'],
            'subcategory_id' => $local['subcategory_id'],
            'method' => $local['method'],
            'confidence' => $local['confidence'],
            'local' => $local,
            'ollama' => null,
            'tecdoc' => null,
            'reasoning' => '',
        ];

        if (($result['category'] === '' || $result['subcategory'] === '') && $useOllama) {
            $ollama = $this->matchWithOllama([
                'name' => $name,
                'brand' => $brand,
                'description' => $description,
                'specs' => $specs,
                'oem' => $oem,
            ]);
            $result['ollama'] = $ollama;

            if (!empty($ollama['ok'])) {
                $result['ok'] = true;
                $result['category'] = (string) ($ollama['category'] ?? $result['category']);
                $result['subcategory'] = (string) ($ollama['subcategory'] ?? $result['subcategory']);
                $result['category_id'] = (int) ($ollama['category_id'] ?? $result['category_id']);
                $result['subcategory_id'] = (int) ($ollama['subcategory_id'] ?? $result['subcategory_id']);
                $result['method'] = 'ollama';
                $result['confidence'] = (float) ($ollama['confidence'] ?? 0.0);
                $result['reasoning'] = (string) ($ollama['reasoning'] ?? '');
            }
        }

        if (
            ($result['category'] === '' || $result['subcategory'] === '')
            && $useTecdoc
            && $oem !== ''
        ) {
            $tecdoc = $this->matchFromTecdocOem($oem, $brand, $useOllama);
            $result['tecdoc'] = $tecdoc;

            if (!empty($tecdoc['ok'])) {
                $result['ok'] = true;
                $result['category'] = (string) ($tecdoc['category'] ?? $result['category']);
                $result['subcategory'] = (string) ($tecdoc['subcategory'] ?? $result['subcategory']);
                $result['category_id'] = (int) ($tecdoc['category_id'] ?? $result['category_id']);
                $result['subcategory_id'] = (int) ($tecdoc['subcategory_id'] ?? $result['subcategory_id']);
                $result['method'] = (string) ($tecdoc['method'] ?? 'tecdoc_oem');
                $result['confidence'] = (float) ($tecdoc['confidence'] ?? $result['confidence']);
                if (($tecdoc['reasoning'] ?? '') !== '') {
                    $result['reasoning'] = (string) $tecdoc['reasoning'];
                }
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function ollamaStatus(): array
    {
        return $this->ollama->readiness();
    }

    /** @return array<string, mixed> */
    private function matchLocal(string $text): array
    {
        if ($text === '') {
            return $this->emptyMatch('none');
        }

        if (function_exists('import_match_taxonomy')) {
            $taxonomy = import_match_taxonomy($text);
            if (($taxonomy['category'] ?? '') !== '' || ($taxonomy['subcategory'] ?? '') !== '') {
                $ids = $this->resolveIds(
                    (string) ($taxonomy['category'] ?? ''),
                    (string) ($taxonomy['subcategory'] ?? '')
                );

                return [
                    'category' => (string) ($taxonomy['category'] ?? ''),
                    'subcategory' => (string) ($taxonomy['subcategory'] ?? ''),
                    'category_id' => $ids['category_id'],
                    'subcategory_id' => $ids['subcategory_id'],
                    'method' => 'local',
                    'confidence' => 0.75,
                ];
            }
        }

        return $this->matchLocalFromDb($text);
    }

    /** @return array<string, mixed> */
    private function matchLocalFromDb(string $text): array
    {
        $norm = $this->normalize($text);
        if ($norm === '') {
            return $this->emptyMatch('local');
        }

        $taxonomy = $this->buildTaxonomyIndex();
        foreach ($taxonomy['subcategories'] as $sub) {
            $subNorm = (string) ($sub['norm'] ?? '');
            $labelNorm = $this->normalize((string) ($sub['label'] ?? ''));
            $artNorm = (string) ($sub['art_norm'] ?? '');
            if ($artNorm !== '' && str_contains($norm, $artNorm)) {
                return [
                    'category' => (string) ($sub['level_1_label'] ?? $sub['category'] ?? ''),
                    'subcategory' => (string) ($sub['level_2_label'] ?? $sub['category'] ?? ''),
                    'category_id' => (int) ($sub['level_1_id'] ?? $sub['category_id'] ?? 0),
                    'subcategory_id' => (int) ($sub['level_2_id'] ?? 0),
                    'leaf_id' => (int) ($sub['id'] ?? 0),
                    'art_name' => (string) ($sub['art_name'] ?? ''),
                    'level_1' => (string) ($sub['level_1_label'] ?? ''),
                    'level_2' => (string) ($sub['level_2_label'] ?? ''),
                    'level_3' => (string) ($sub['art_name'] ?? ''),
                    'method' => 'local',
                    'confidence' => 0.72,
                ];
            }
            if ($subNorm !== '' && (str_contains($norm, $subNorm) || str_contains($subNorm, $norm))) {
                return [
                    'category' => (string) ($sub['level_1_label'] ?? $sub['category'] ?? ''),
                    'subcategory' => (string) ($sub['level_2_label'] ?? $sub['category'] ?? ''),
                    'category_id' => (int) ($sub['level_1_id'] ?? $sub['category_id'] ?? 0),
                    'subcategory_id' => (int) ($sub['level_2_id'] ?? 0),
                    'leaf_id' => (int) ($sub['id'] ?? 0),
                    'art_name' => (string) ($sub['art_name'] ?? ''),
                    'level_1' => (string) ($sub['level_1_label'] ?? ''),
                    'level_2' => (string) ($sub['level_2_label'] ?? ''),
                    'level_3' => (string) ($sub['art_name'] ?? ''),
                    'method' => 'local',
                    'confidence' => 0.7,
                ];
            }
            if ($labelNorm !== '' && str_contains($norm, $labelNorm)) {
                return [
                    'category' => (string) ($sub['level_1_label'] ?? $sub['category'] ?? ''),
                    'subcategory' => (string) ($sub['level_2_label'] ?? $sub['category'] ?? ''),
                    'category_id' => (int) ($sub['level_1_id'] ?? $sub['category_id'] ?? 0),
                    'subcategory_id' => (int) ($sub['level_2_id'] ?? 0),
                    'leaf_id' => (int) ($sub['id'] ?? 0),
                    'art_name' => (string) ($sub['art_name'] ?? ''),
                    'level_1' => (string) ($sub['level_1_label'] ?? ''),
                    'level_2' => (string) ($sub['level_2_label'] ?? ''),
                    'level_3' => (string) ($sub['art_name'] ?? ''),
                    'method' => 'local',
                    'confidence' => 0.65,
                ];
            }
        }

        return $this->emptyMatch('local');
    }

    /**
     * @param array{name:string,brand:string,description:string,specs:string,oem:string} $product
     * @return array<string, mixed>
     */
    private function matchWithOllama(array $product): array
    {
        $readiness = $this->ollama->readiness();
        if (empty($readiness['ready'])) {
            return [
                'ok' => false,
                'error' => (string) ($readiness['message_ro'] ?? 'Ollama indisponibil.'),
                'readiness' => $readiness,
            ];
        }

        $taxonomy = $this->buildTaxonomyTreeForPrompt();
        if ($taxonomy === []) {
            return [
                'ok' => false,
                'error' => 'Taxonomia este goală — importă catalogul PieseAuto în admin/categorii.',
            ];
        }

        $prompt = "Produs:\n"
            . '- Denumire: ' . ($product['name'] !== '' ? $product['name'] : '(lipsă)') . "\n"
            . '- Brand: ' . ($product['brand'] !== '' ? $product['brand'] : '(lipsă)') . "\n"
            . '- OEM: ' . ($product['oem'] !== '' ? $product['oem'] : '(lipsă)') . "\n"
            . '- Descriere: ' . ($product['description'] !== '' ? mb_substr($product['description'], 0, 400) : '(lipsă)') . "\n"
            . '- Specs: ' . ($product['specs'] !== '' ? mb_substr($product['specs'], 0, 400) : '(lipsă)') . "\n\n"
            . "Taxonomie disponibilă (categorie -> subcategorii):\n"
            . json_encode($taxonomy, JSON_UNESCAPED_UNICODE) . "\n\n"
            . "Alege EXACT o categorie și o subcategorie din listă. "
            . "Răspunde DOAR JSON: {\"category\":\"...\",\"subcategory\":\"...\",\"confidence\":0.0-1.0,\"reasoning\":\"...\"}";

        $system = 'Ești expert auto parts pentru magazinul Besoiu Piese Auto. '
            . 'Clasifici piese auto în categorie și subcategorie din taxonomia furnizată. '
            . 'Nu inventa etichete noi — folosește exact valorile din listă. '
            . 'Răspuns strict JSON, fără markdown.';

        try {
            $response = $this->ollama->complete(
                'categorii',
                $prompt,
                $system,
                'category_match',
                ['temperature' => 0.1, 'timeout_sec' => 90]
            );
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }

        $content = trim((string) ($response['content'] ?? $response['text'] ?? ''));
        $parsed = $this->parseJsonObject($content);
        if (!is_array($parsed)) {
            return [
                'ok' => false,
                'error' => 'Ollama nu a returnat JSON valid.',
                'raw' => $content,
            ];
        }

        $category = trim((string) ($parsed['category'] ?? ''));
        $subcategory = trim((string) ($parsed['subcategory'] ?? ''));
        $ids = $this->resolveIds($category, $subcategory);

        return [
            'ok' => $category !== '' && $subcategory !== '',
            'category' => $category,
            'subcategory' => $subcategory,
            'category_id' => $ids['category_id'],
            'subcategory_id' => $ids['subcategory_id'],
            'confidence' => (float) ($parsed['confidence'] ?? 0.0),
            'reasoning' => (string) ($parsed['reasoning'] ?? ''),
            'model' => (string) ($response['model'] ?? ''),
            'raw' => $parsed,
        ];
    }

    /**
     * Fallback TecDoc: lookup OEM → denumire articol → clasificare locală/Ollama.
     *
     * @return array<string, mixed>
     */
    private function matchFromTecdocOem(string $oem, string $brand, bool $useOllama = false): array
    {
        $record = $this->lookupTecdocRecord($oem, $brand);
        if ($record === null) {
            return [
                'ok' => false,
                'error' => 'Cod OEM negăsit în TecDoc (MySQL / API).',
                'oem' => $oem,
            ];
        }

        $artName = trim((string) ($record['art_name'] ?? ''));
        $partsInfo = trim((string) ($record['parts_info'] ?? ''));
        $artBrand = trim((string) ($record['art_brand'] ?? $brand));
        $haystack = trim(implode(' ', array_filter([$artName, $partsInfo, $artBrand])));

        $meta = [
            'oem' => $oem,
            'art_name' => $artName,
            'art_brand' => $artBrand,
            'source' => (string) ($record['source'] ?? 'tecdoc'),
            'lookup' => $record,
        ];

        if ($haystack === '') {
            return array_merge($meta, [
                'ok' => false,
                'error' => 'Articol TecDoc fără denumire utilă pentru clasificare.',
            ]);
        }

        $exact = $this->matchByArtNameExact($artName);
        if ($exact['category'] !== '' && $exact['subcategory'] !== '') {
            return array_merge($meta, [
                'ok' => true,
                'category' => $exact['category'],
                'subcategory' => $exact['subcategory'],
                'category_id' => $exact['category_id'],
                'subcategory_id' => $exact['subcategory_id'],
                'leaf_id' => $exact['leaf_id'],
                'art_name' => $exact['art_name'],
                'level_1' => $exact['level_1'],
                'level_2' => $exact['level_2'],
                'level_3' => $exact['level_3'],
                'method' => 'tecdoc_art_name_exact',
                'confidence' => 1.0,
                'reasoning' => 'TecDoc ART_NAME potrivit exact cu frunza din arborele Besoiu.',
            ]);
        }

        $local = $this->matchLocal($haystack);
        if ($local['category'] !== '' && $local['subcategory'] !== '') {
            return array_merge($meta, [
                'ok' => true,
                'category' => $local['category'],
                'subcategory' => $local['subcategory'],
                'category_id' => $local['category_id'],
                'subcategory_id' => $local['subcategory_id'],
                'method' => 'tecdoc_oem',
                'confidence' => min(0.85, (float) ($local['confidence'] ?? 0.7) + 0.05),
                'reasoning' => 'Clasificat din denumirea TecDoc: ' . $artName,
            ]);
        }

        if ($useOllama) {
            $ollama = $this->matchWithOllama([
                'name' => $artName,
                'brand' => $artBrand,
                'description' => $partsInfo,
                'specs' => '',
                'oem' => $oem,
            ]);
            if (!empty($ollama['ok'])) {
                return array_merge($meta, [
                    'ok' => true,
                    'category' => (string) ($ollama['category'] ?? ''),
                    'subcategory' => (string) ($ollama['subcategory'] ?? ''),
                    'category_id' => (int) ($ollama['category_id'] ?? 0),
                    'subcategory_id' => (int) ($ollama['subcategory_id'] ?? 0),
                    'method' => 'tecdoc_oem_ollama',
                    'confidence' => (float) ($ollama['confidence'] ?? 0.0),
                    'reasoning' => 'TecDoc: ' . $artName . ' — ' . (string) ($ollama['reasoning'] ?? ''),
                    'ollama' => $ollama,
                ]);
            }
        }

        return array_merge($meta, [
            'ok' => false,
            'error' => 'TecDoc găsit (' . $artName . ') dar fără potrivire în taxonomia locală.',
            'local' => $local,
        ]);
    }

    /**
     * @return array{art_name:string,art_brand:string,parts_info:string,source:string}|null
     */
    private function lookupTecdocRecord(string $oem, string $brand): ?array
    {
        $oem = trim($oem);
        if ($oem === '') {
            return null;
        }

        $record = $this->lookupTecdocUnifiedDb($oem, $brand);
        if ($record !== null) {
            return $record;
        }

        $record = $this->lookupTecdocLegacyDb($oem, $brand);
        if ($record !== null) {
            return $record;
        }

        return $this->lookupTecdocApi($oem, $brand);
    }

    /** @return array{art_name:string,art_brand:string,parts_info:string,source:string}|null */
    private function lookupTecdocUnifiedDb(string $oem, string $brand): ?array
    {
        if (!function_exists('besoiupieseimport_tecdoc_pdo')) {
            $lib = dirname(__DIR__, 2) . '/tools/besoiupieseimport_tecdoc_db.php';
            if (is_file($lib)) {
                require_once $lib;
            }
        }
        if (!function_exists('besoiupieseimport_tecdoc_pdo')
            || !function_exists('besoiupieseimport_tecdoc_table_exists')) {
            return null;
        }

        if (!function_exists('besoiu_normalize_product_code')) {
            $normLib = dirname(__DIR__, 3) . '/Legacy/product-code-normalize.php';
            if (is_file($normLib)) {
                require_once $normLib;
            }
        }

        try {
            $pdo = besoiupieseimport_tecdoc_pdo();
            foreach (['tecdoc_product_codes', 'tecdoc_products', 'tecdoc_brands'] as $table) {
                if (!besoiupieseimport_tecdoc_table_exists($pdo, $table)) {
                    return null;
                }
            }

            $codeNorm = function_exists('besoiu_normalize_product_code')
                ? besoiu_normalize_product_code($oem)
                : strtoupper(preg_replace('/[^A-Z0-9]/', '', $oem) ?? '');
            if ($codeNorm === '') {
                return null;
            }

            $brandNorm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $brand) ?? '');
            $sql = 'SELECT p.art_name, p.parts_info, b.name AS art_brand
                    FROM tecdoc_product_codes c
                    INNER JOIN tecdoc_products p ON p.id = c.product_id
                    INNER JOIN tecdoc_brands b ON b.id = p.brand_id
                    WHERE c.code_norm = ?
                    ORDER BY c.code_type ASC, c.product_id ASC
                    LIMIT 40';
            $stmt = function_exists('besoiupieseimport_tecdoc_prepare_with_timeout')
                ? besoiupieseimport_tecdoc_prepare_with_timeout($pdo, $sql, 4000)
                : $pdo->prepare($sql);
            $stmt->execute([$codeNorm]);

            $best = null;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $rowBrand = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($row['art_brand'] ?? '')) ?? '');
                if ($brandNorm !== '' && $rowBrand !== '' && $rowBrand !== $brandNorm
                    && !str_contains($rowBrand, $brandNorm) && !str_contains($brandNorm, $rowBrand)) {
                    continue;
                }
                $best = $row;
                if ($brandNorm === '' || $rowBrand === $brandNorm) {
                    break;
                }
            }

            if (!is_array($best)) {
                return null;
            }

            $artName = trim((string) ($best['art_name'] ?? ''));
            if ($artName === '') {
                return null;
            }

            return [
                'art_name' => $artName,
                'art_brand' => trim((string) ($best['art_brand'] ?? $brand)),
                'parts_info' => trim((string) ($best['parts_info'] ?? '')),
                'source' => 'tecdoc_mysql_unified',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{art_name:string,art_brand:string,parts_info:string,source:string}|null */
    private function lookupTecdocLegacyDb(string $oem, string $brand): ?array
    {
        if (!function_exists('besoiupieseimport_tecdoc_legacy_pdo')) {
            $lib = dirname(__DIR__, 2) . '/tools/besoiupieseimport_tecdoc_db.php';
            if (is_file($lib)) {
                require_once $lib;
            }
        }
        if (!function_exists('besoiupieseimport_tecdoc_legacy_pdo')) {
            return null;
        }

        if (!function_exists('besoiu_normalize_product_code')) {
            $normLib = dirname(__DIR__, 3) . '/Legacy/product-code-normalize.php';
            if (is_file($normLib)) {
                require_once $normLib;
            }
        }

        try {
            $pdo = besoiupieseimport_tecdoc_legacy_pdo();
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() "
                . "AND table_name IN ('product_codes','products','brands')"
            );
            if ((int) $stmt->fetchColumn() < 3) {
                return null;
            }

            $codeNorm = function_exists('besoiu_normalize_product_code')
                ? besoiu_normalize_product_code($oem)
                : strtoupper(preg_replace('/[^A-Z0-9]/', '', $oem) ?? '');
            if ($codeNorm === '') {
                return null;
            }

            $brandNorm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $brand) ?? '');
            $sql = 'SELECT p.art_name, p.parts_info, b.name AS art_brand
                    FROM product_codes c
                    INNER JOIN products p ON p.id = c.product_id
                    INNER JOIN brands b ON b.id = p.brand_id
                    WHERE c.code_norm = ?
                    ORDER BY c.product_id ASC
                    LIMIT 40';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codeNorm]);

            $best = null;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $rowBrand = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($row['art_brand'] ?? '')) ?? '');
                if ($brandNorm !== '' && $rowBrand !== '' && $rowBrand !== $brandNorm
                    && !str_contains($rowBrand, $brandNorm) && !str_contains($brandNorm, $rowBrand)) {
                    continue;
                }
                $best = $row;
                if ($brandNorm === '' || $rowBrand === $brandNorm) {
                    break;
                }
            }

            if (!is_array($best)) {
                return null;
            }

            $artName = trim((string) ($best['art_name'] ?? ''));
            if ($artName === '') {
                return null;
            }

            return [
                'art_name' => $artName,
                'art_brand' => trim((string) ($best['art_brand'] ?? $brand)),
                'parts_info' => trim((string) ($best['parts_info'] ?? '')),
                'source' => 'tecdoc_mysql_legacy',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{art_name:string,art_brand:string,parts_info:string,source:string}|null */
    private function lookupTecdocApi(string $oem, string $brand): ?array
    {
        if (!function_exists('tecdoc_find_article_for_import')) {
            $legacyRoot = dirname(__DIR__, 3) . '/Legacy';
            $stock = $legacyRoot . '/tecdoc_stock.php';
            if (is_file($stock)) {
                require_once $stock;
            }
        }
        if (!function_exists('tecdoc_find_article_for_import')) {
            return null;
        }

        try {
            $article = tecdoc_find_article_for_import($oem, $brand);
            if (!is_array($article) || $article === []) {
                return null;
            }

            $artName = function_exists('tecdoc_article_name')
                ? tecdoc_article_name($article)
                : trim((string) ($article['articleProductName'] ?? $article['articleName'] ?? ''));
            if ($artName === '') {
                return null;
            }

            $artBrand = function_exists('tecdoc_article_brand')
                ? tecdoc_article_brand($article)
                : trim((string) ($article['brandName'] ?? $brand));
            $specs = function_exists('tecdoc_article_specs')
                ? tecdoc_article_specs($article)
                : '';

            return [
                'art_name' => $artName,
                'art_brand' => $artBrand !== '' ? $artBrand : $brand,
                'parts_info' => $specs,
                'source' => 'tecdoc_rapidapi',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{category_id:int,subcategory_id:int} */
    private function resolveIds(string $category, string $subcategory): array
    {
        $categoryId = 0;
        $subcategoryId = 0;
        $categoryNorm = $this->normalize($category);
        $subNorm = $this->normalize($subcategory);

        foreach ($this->model->findActive() as $row) {
            $labelNorm = $this->normalize((string) ($row['label'] ?? ''));
            if (($row['type'] ?? '') === 'categorie' && $categoryId === 0 && $labelNorm === $categoryNorm) {
                $categoryId = (int) ($row['id'] ?? 0);
            }
            if (($row['type'] ?? '') === 'subcategorie' && $subNorm !== '' && $labelNorm === $subNorm) {
                $subcategoryId = (int) ($row['id'] ?? 0);
                if ($categoryId === 0) {
                    $categoryId = (int) ($row['parent_id'] ?? 0);
                }
            }
        }

        return ['category_id' => $categoryId, 'subcategory_id' => $subcategoryId];
    }

    /** @return array{subcategories:list<array<string, mixed>>} */
    private function buildTaxonomyIndex(): array
    {
        $rows = $this->model->findActive();
        $categoriesById = [];
        $subcategories = [];

        foreach ($rows as $row) {
            $categoriesById[(int) ($row['id'] ?? 0)] = $row;
        }

        foreach ($rows as $row) {
            if (($row['type'] ?? '') !== 'subcategorie') {
                continue;
            }
            $parent = $categoriesById[(int) ($row['parent_id'] ?? 0)] ?? null;
            $meta = $this->decodeMeta($row['meta'] ?? null);
            $chain = $this->resolveBesoiuChain($row, $categoriesById);
            $artName = trim((string) ($meta['art_name'] ?? $row['label'] ?? ''));
            $subcategories[] = [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'norm' => $this->normalize((string) ($row['label'] ?? '') . ' ' . (string) ($row['slug'] ?? '')),
                'art_name' => $artName,
                'art_norm' => $this->normalize($artName),
                'category' => (string) ($parent['label'] ?? ''),
                'category_id' => (int) ($parent['id'] ?? 0),
                'level_1_id' => (int) ($chain['level_1_id'] ?? 0),
                'level_1_label' => (string) ($chain['level_1_label'] ?? ''),
                'level_2_id' => (int) ($chain['level_2_id'] ?? 0),
                'level_2_label' => (string) ($chain['level_2_label'] ?? ''),
            ];
        }

        return ['subcategories' => $subcategories];
    }

    /** @return list<array<string, mixed>> */
    private function buildTaxonomyTreeForPrompt(): array
    {
        $rows = $this->model->findActive();
        $tree = [];
        $childrenByParent = [];

        foreach ($rows as $row) {
            if (($row['type'] ?? '') !== 'subcategorie') {
                continue;
            }
            $parentId = (int) ($row['parent_id'] ?? 0);
            $childrenByParent[$parentId][] = (string) ($row['label'] ?? '');
        }

        foreach ($rows as $row) {
            if (($row['type'] ?? '') !== 'categorie') {
                continue;
            }
            if ((int) ($row['parent_id'] ?? 0) !== 0) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $subs = $childrenByParent[$id] ?? [];
            if ($subs === []) {
                continue;
            }
            $tree[] = [
                'category' => (string) ($row['label'] ?? ''),
                'subcategories' => array_values(array_slice($subs, 0, 80)),
            ];
        }

        return array_slice($tree, 0, 40);
    }

    /**
     * Găsește ART_NAME (nivel 3) ca fragment în denumirea produsului și urcă la familie (L2) + root (L1).
     *
     * @return array<string, mixed>
     */
    public function matchByArtNameInProductName(string $productName): array
    {
        $productName = trim($productName);
        if ($productName === '') {
            return $this->emptyMatch('art_name_in_name');
        }

        $normHay = $this->normalize($productName);
        if ($normHay === '') {
            return $this->emptyMatch('art_name_in_name');
        }

        $best = null;
        $bestScore = 0;

        foreach ($this->buildArtNameIndex() as $entry) {
            $artNorm = (string) ($entry['art_norm'] ?? '');
            if ($artNorm === '' || mb_strlen($artNorm) < 4) {
                continue;
            }
            if (!str_contains($normHay, $artNorm)) {
                continue;
            }

            $score = mb_strlen($artNorm);
            if ($score <= $bestScore) {
                continue;
            }

            $bestScore = $score;
            $best = $entry;
        }

        if (!is_array($best)) {
            return $this->emptyMatch('art_name_in_name');
        }

        return $this->formatBesoiuChainMatch(
            $best,
            'art_name_in_name',
            min(0.98, 0.72 + ($bestScore / 120.0))
        );
    }

    /** @return array<string, mixed> */
    public function matchByArtNameExact(string $artName): array
    {
        $artName = trim($artName);
        if ($artName === '') {
            return $this->emptyMatch('art_name_exact');
        }

        foreach ($this->buildArtNameIndex() as $entry) {
            if ((string) ($entry['art_name'] ?? '') === $artName) {
                return $this->formatBesoiuChainMatch($entry, 'art_name_exact', 1.0);
            }
        }

        return $this->emptyMatch('art_name_exact');
    }

    /** @return list<array<string, mixed>> */
    private function buildArtNameIndex(): array
    {
        static $index = null;
        if (is_array($index)) {
            return $index;
        }

        $rows = $this->model->findActive();
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) ($row['id'] ?? 0)] = $row;
        }

        $index = [];
        foreach ($rows as $row) {
            $meta = $this->decodeMeta($row['meta'] ?? null);
            $isLeaf = !empty($meta['is_leaf']) || (string) ($row['type'] ?? '') === 'subcategorie';
            if (!$isLeaf) {
                continue;
            }

            $artName = trim((string) ($meta['art_name'] ?? $row['label'] ?? ''));
            if ($artName === '') {
                continue;
            }

            $root = $this->resolveRootCategory($row, $byId);
            $chain = $this->resolveBesoiuChain($row, $byId);
            $index[] = array_merge($chain, [
                'leaf_id' => (int) ($row['id'] ?? 0),
                'art_name' => $artName,
                'art_norm' => $this->normalize($artName),
                'subcategory' => (string) ($chain['level_2_label'] ?? ''),
                'category' => (string) ($chain['level_1_label'] ?? $root['label'] ?? ''),
                'category_id' => (int) ($chain['level_1_id'] ?? $root['id'] ?? 0),
            ]);
        }

        usort($index, static fn (array $a, array $b): int => mb_strlen((string) ($b['art_name'] ?? '')) <=> mb_strlen((string) ($a['art_name'] ?? '')));

        return $index;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function formatBesoiuChainMatch(array $entry, string $method, float $confidence): array
    {
        return [
            'category' => (string) ($entry['level_1_label'] ?? $entry['category'] ?? ''),
            'subcategory' => (string) ($entry['level_2_label'] ?? ''),
            'category_id' => (int) ($entry['level_1_id'] ?? $entry['category_id'] ?? 0),
            'subcategory_id' => (int) (($entry['level_2_id'] ?? 0) ?: ($entry['leaf_id'] ?? 0)),
            'leaf_id' => (int) ($entry['leaf_id'] ?? 0),
            'art_name' => (string) ($entry['art_name'] ?? ''),
            'level_1' => (string) ($entry['level_1_label'] ?? ''),
            'level_2' => (string) ($entry['level_2_label'] ?? ''),
            'level_3' => (string) ($entry['art_name'] ?? ''),
            'path' => (string) ($entry['path'] ?? ''),
            'method' => $method,
            'confidence' => $confidence,
        ];
    }

    /**
     * @param array<string, mixed> $match
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function finalizeBesoiuMatch(
        array $match,
        array $product,
        ?string $synonymTarget = null,
        ?string $methodOverride = null,
        ?string $reasoningOverride = null
    ): array {
        $method = $methodOverride ?? (string) ($match['method'] ?? 'besoiu_chain');
        $result = [
            'ok' => true,
            'category' => (string) ($match['category'] ?? ''),
            'subcategory' => (string) ($match['subcategory'] ?? ''),
            'category_id' => (int) ($match['category_id'] ?? 0),
            'subcategory_id' => (int) ($match['subcategory_id'] ?? 0),
            'leaf_id' => (int) ($match['leaf_id'] ?? 0),
            'art_name' => (string) ($match['art_name'] ?? $match['level_3'] ?? ''),
            'level_1' => (string) ($match['level_1'] ?? $match['category'] ?? ''),
            'level_2' => (string) ($match['level_2'] ?? $match['subcategory'] ?? ''),
            'level_3' => (string) ($match['level_3'] ?? $match['art_name'] ?? ''),
            'path' => (string) ($match['path'] ?? ''),
            'method' => $method,
            'confidence' => (float) ($match['confidence'] ?? 1.0),
            'local' => $match,
            'ollama' => null,
            'tecdoc' => null,
            'reasoning' => $reasoningOverride ?? sprintf(
                'Besoiu: L3 «%s» → L2 «%s» → L1 «%s».',
                (string) ($match['level_3'] ?? $match['art_name'] ?? ''),
                (string) ($match['level_2'] ?? $match['subcategory'] ?? ''),
                (string) ($match['level_1'] ?? $match['category'] ?? '')
            ),
        ];
        if ($synonymTarget !== null) {
            $result['synonym_from'] = trim((string) ($product['name'] ?? $product['pName'] ?? ''));
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $leafRow
     * @param array<int, array<string, mixed>> $byId
     * @return array<string, mixed>
     */
    private function resolveBesoiuChain(array $leafRow, array $byId): array
    {
        $meta = $this->decodeMeta($leafRow['meta'] ?? null);
        $leafId = (int) ($leafRow['id'] ?? 0);
        $artName = trim((string) ($meta['art_name'] ?? $leafRow['label'] ?? ''));
        $displayLabel = trim((string) ($meta['display_label'] ?? $leafRow['label'] ?? $artName));
        $path = trim((string) ($meta['path'] ?? ''));

        $root = $this->resolveRootCategory($leafRow, $byId);
        $level1Id = (int) ($root['id'] ?? 0);
        $level1Label = (string) ($root['label'] ?? '');
        $level2Id = 0;
        $level2Label = '';

        $parentId = (int) ($leafRow['parent_id'] ?? 0);
        if ($parentId > 0 && $parentId === $level1Id) {
            $level2Id = $leafId;
            $level2Label = $displayLabel !== '' ? $displayLabel : $artName;
        } elseif ($parentId > 0 && $parentId !== $level1Id) {
            $parent = $byId[$parentId] ?? null;
            if (is_array($parent)) {
                $pMeta = $this->decodeMeta($parent['meta'] ?? null);
                $level2Id = (int) ($parent['id'] ?? 0);
                $level2Label = trim((string) ($pMeta['display_label'] ?? $parent['label'] ?? ''));
            }
        }

        if ($path !== '' && str_contains($path, '>')) {
            $parts = array_values(array_filter(array_map('trim', explode('>', $path)), static fn (string $p): bool => $p !== ''));
            if (isset($parts[0])) {
                $level1Label = $parts[0];
            }
            if (count($parts) >= 3) {
                $level2Label = $parts[1];
                $artName = $parts[2];
            } elseif (count($parts) === 2) {
                $level2Label = $parts[1];
            }
        }

        if ($level2Label === '') {
            $level2Id = $level2Id > 0 ? $level2Id : $leafId;
            $level2Label = $displayLabel !== '' ? $displayLabel : $artName;
        } elseif ($level2Id === 0) {
            $level2Id = $leafId;
        }

        return [
            'level_1_id' => $level1Id,
            'level_1_label' => $level1Label,
            'level_2_id' => $level2Id,
            'level_2_label' => $level2Label,
            'path' => $path,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $byId
     * @return array{id:int,label:string}
     */
    private function resolveRootCategory(array $row, array $byId): array
    {
        $current = $row;
        $guard = 0;

        while ($guard < 24) {
            $parentId = $current['parent_id'] ?? null;
            if ($parentId === null || (int) $parentId === 0) {
                return [
                    'id' => (int) ($current['id'] ?? 0),
                    'label' => (string) ($current['label'] ?? ''),
                ];
            }

            $parent = $byId[(int) $parentId] ?? null;
            if (!is_array($parent)) {
                break;
            }
            $current = $parent;
            $guard++;
        }

        return ['id' => 0, 'label' => ''];
    }

    /** @return array<string, mixed> */
    private function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }
        if (!is_string($meta) || trim($meta) === '') {
            return [];
        }
        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function emptyMatch(string $method): array
    {
        return [
            'category' => '',
            'subcategory' => '',
            'category_id' => 0,
            'subcategory_id' => 0,
            'method' => $method,
            'confidence' => 0.0,
        ];
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ'],
            ['a', 'a', 'i', 's', 't', 's', 't'],
            $value
        );
        $value = preg_replace('/[^a-z0-9\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /** @return array<string, mixed>|null */
    private function parseJsonObject(string $content): ?array
    {
        $content = trim($content);
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/\{[\s\S]*\}/u', $content, $match)) {
            $decoded = json_decode($match[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
