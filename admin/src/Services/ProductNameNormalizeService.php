<?php

declare(strict_types=1);

namespace Besoiu\Services;

use PDO;
use Throwable;

/**
 * Normalizare denumiri TecDoc → magazin (reguli + Ollama + coadă import).
 */
final class ProductNameNormalizeService
{
    public function __construct(
        private readonly ?ModuleOllamaSupport $ollama = null,
    ) {
    }

    public static function create(): self
    {
        return new self(ModuleOllamaSupport::create());
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $this->bootLib();
        $map = import_base_load_name_overrides();
        $customPath = import_base_custom_name_overrides_path();
        $basePath = import_base_name_overrides_path();

        return [
            'rules_total' => count($map),
            'rules_custom_lines' => $this->countRuleLines($customPath),
            'rules_base_lines' => $this->countRuleLines($basePath),
            'base_path' => $basePath,
            'custom_path' => $customPath,
            'base_exists' => is_file($basePath),
            'custom_exists' => is_file($customPath),
            'ollama' => $this->ollama->readiness(),
            'ollama_filter' => [
                'enabled' => $this->isOllamaFilterEnabled(),
                'on_import' => $this->isOllamaFilterEnabledOnImport(),
                'min_confidence' => $this->ollamaFilterMinConfidence(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function preview(string $rawName, ?string $brand = null, bool $useOllamaFilter = true): array
    {
        $this->bootLib();
        $resolved = $this->normalizeWithOllamaFilter($rawName, trim((string) $brand), [], $useOllamaFilter);

        return [
            'ok' => true,
            'raw' => $resolved['raw'],
            'final' => $resolved['final'],
            'source' => $resolved['source'],
            'override' => $resolved['rule_detail']['override'] ?? '',
            'matched_key' => $resolved['rule_detail']['matched_key'] ?? '',
            'cleaned' => $resolved['rule_detail']['cleaned'] ?? '',
            'patterns_applied' => $resolved['rule_detail']['patterns_applied'] ?? [],
            'brand' => trim((string) $brand),
            'needs_review' => ($resolved['rule_detail']['source'] ?? '') !== 'override',
            'ollama_used' => !empty($resolved['ollama_used']),
            'ollama' => $resolved['ollama'] ?? null,
            'rule_source' => $resolved['rule_detail']['source'] ?? '',
        ];
    }

    /**
     * @param list<string> $names
     * @return list<array<string, mixed>>
     */
    public function previewBatch(array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $out[] = $this->preview($name);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function suggestWithOllama(string $rawName, string $brand = '', string $context = ''): array
    {
        $this->bootLib();
        $rawName = trim($rawName);
        if ($rawName === '') {
            return ['ok' => false, 'error' => 'Denumirea TecDoc este goală.'];
        }

        $readiness = $this->ollama->readiness();
        if (empty($readiness['ready'])) {
            return [
                'ok' => false,
                'error' => (string) ($readiness['message_ro'] ?? 'Ollama indisponibil.'),
                'readiness' => $readiness,
            ];
        }

        $current = import_base_normalize_product_name_detail($rawName);
        $samples = $this->sampleRulesForPrompt($rawName, 12);

        $prompt = "Denumire TecDoc (sursă): {$rawName}\n"
            . 'Brand piesă: ' . ($brand !== '' ? $brand : '(lipsă)') . "\n"
            . 'Normalizare automată curentă (reguli): ' . ($current['final'] !== '' ? $current['final'] : '(fără regulă)') . "\n"
            . 'Sursă reguli: ' . $current['source'] . "\n"
            . ($context !== '' ? "Context produs:\n{$context}\n" : '')
            . "\nExemple de mapări existente (alias TecDoc → denumire magazin):\n"
            . $samples
            . "\n\nPropune denumire scurtă pentru magazin online auto (română, 2-6 cuvinte, fără cod OEM).\n"
            . "Folosește termeni corecți în română — NU scurta sau distorsiona cuvinte valide (ex. Fulii rămâne Fulii, nu Fuli).\n"
            . "Dacă regulile au deja un rezultat bun, păstrează-l. Corectează doar când e clar greșit sau prea tehnic.\n"
            . "Include 1-3 aliasi TecDoc separați cu | dacă e cazul.\n"
            . 'Răspunde DOAR JSON: {"normalized":"...","aliases":["alias1","alias2"],"confidence":0.0-1.0,"reasoning":"..."}';

        $system = 'Ești expert în piese auto TecDoc pentru magazinul Besoiu Piese Auto. '
            . 'Normalizezi denumiri tehnice TecDoc în denumiri clare pentru clienți (română). '
            . 'Nu inventa coduri. Folosește stilul exemplelor furnizate. Răspuns strict JSON, fără markdown.';

        try {
            $response = $this->ollama->complete(
                'importreview',
                $prompt,
                $system,
                'product_name_normalize',
                ['temperature' => 0.15, 'timeout_sec' => 45]
            );
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
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

        $normalized = trim((string) ($parsed['normalized'] ?? ''));
        $aliases = array_values(array_filter(array_map(
            static fn($v): string => trim((string) $v),
            is_array($parsed['aliases'] ?? null) ? $parsed['aliases'] : []
        )));

        if ($aliases === [] && $rawName !== '') {
            $aliases = [$rawName];
        }

        return [
            'ok' => $normalized !== '',
            'normalized' => $normalized,
            'aliases' => $aliases,
            'confidence' => (float) ($parsed['confidence'] ?? 0.0),
            'reasoning' => (string) ($parsed['reasoning'] ?? ''),
            'model' => (string) ($response['model'] ?? ''),
            'current' => $current,
            'rule_line' => $this->buildRuleLine($aliases, $normalized),
        ];
    }

    /**
     * Pipeline complet: reguli din fișier → Ollama ca filtru pe cazurile incerte.
     *
     * @param array<string, mixed> $context brand, category, product_name, specs, oem…
     * @return array{
     *   ok: bool,
     *   raw: string,
     *   final: string,
     *   source: string,
     *   rule_detail: array<string, mixed>,
     *   ollama_used: bool,
     *   ollama?: array<string, mixed>|null
     * }
     */
    public function normalizeWithOllamaFilter(
        string $rawName,
        string $brand = '',
        array $context = [],
        bool $useOllama = true,
        bool $forceOllama = false
    ): array {
        $this->bootLib();
        $rawName = trim($rawName);
        $detail = import_base_normalize_product_name_detail($rawName);

        $base = [
            'ok' => true,
            'raw' => $detail['raw'],
            'final' => $detail['final'],
            'source' => (string) ($detail['source'] ?? 'unchanged'),
            'rule_detail' => $detail,
            'ollama_used' => false,
            'ollama' => null,
        ];

        if ($rawName === '' || !$useOllama || !$this->isOllamaFilterEnabled()) {
            return $base;
        }

        $needsOllama = $forceOllama || in_array($detail['source'], ['unchanged', 'patterns'], true);
        if (!$needsOllama) {
            return $base;
        }

        $readiness = $this->ollama->readiness();
        if (empty($readiness['ready'])) {
            $base['ollama_skip'] = (string) ($readiness['message_ro'] ?? 'Ollama indisponibil');

            return $base;
        }

        $contextText = $this->formatContextForPrompt($context);
        $suggest = $this->suggestWithOllama($rawName, $brand, $contextText);
        if (empty($suggest['ok'])) {
            $base['ollama_skip'] = (string) ($suggest['error'] ?? 'Eroare Ollama');

            return $base;
        }

        $confidence = (float) ($suggest['confidence'] ?? 0.0);
        $ollamaNorm = trim((string) ($suggest['normalized'] ?? ''));
        if ($ollamaNorm === '' || $confidence < $this->ollamaFilterMinConfidence()) {
            $base['ollama'] = $suggest;
            $base['ollama_skip'] = 'Confidence prea mic sau denumire goală';

            return $base;
        }

        $ruleFinal = trim((string) ($detail['final'] ?? ''));
        $ruleKey = import_base_normalize_name_key($ruleFinal);
        $ollamaKey = import_base_normalize_name_key($ollamaNorm);
        if ($ruleKey !== '' && $ruleKey === $ollamaKey) {
            $base['ollama'] = $suggest;

            return $base;
        }

        $base['final'] = $ollamaNorm;
        $base['source'] = 'ollama';
        $base['ollama_used'] = true;
        $base['ollama'] = $suggest;

        return $base;
    }

    /**
     * Aplică pipeline-ul (reguli + Ollama) pe un rând de staging / coadă.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function applyNormalizationPipelineToProduct(array $row, bool $useOllama = true): array
    {
        $this->bootLib();
        $rawArtName = function_exists('import_extract_tecdoc_art_name_from_product')
            ? import_extract_tecdoc_art_name_from_product($row)
            : $this->extractRawNameFromRow($row);
        if ($rawArtName === '') {
            return $row;
        }

        $context = $this->buildProductContextFromRow($row);
        $resolved = $this->normalizeWithOllamaFilter(
            $rawArtName,
            (string) ($context['brand'] ?? ''),
            $context,
            $useOllama
        );

        $normalized = trim((string) ($resolved['final'] ?? ''));
        if ($normalized === '') {
            return $row;
        }

        $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
        if (!is_array($raw)) {
            $raw = [];
        }

        $raw['__tecdoc_art_name'] = $rawArtName;
        $raw['__name_normalize_source'] = (string) ($resolved['source'] ?? '');
        $raw['__name_normalized_to'] = $normalized;
        $raw['__name_rule_source'] = (string) ($resolved['rule_detail']['source'] ?? '');
        if (!empty($resolved['ollama_used']) && is_array($resolved['ollama'] ?? null)) {
            $raw['__name_ollama'] = [
                'normalized' => $normalized,
                'confidence' => (float) (($resolved['ollama']['confidence'] ?? 0)),
                'reasoning' => (string) (($resolved['ollama']['reasoning'] ?? '')),
                'model' => (string) (($resolved['ollama']['model'] ?? '')),
                'at' => date('c'),
            ];
        }
        if (isset($raw['product_summary']) && is_array($raw['product_summary'])) {
            $raw['product_summary']['tecdoc_art_name'] = $rawArtName;
        }

        $row['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $row['pSubcategory'] = $normalized;

        return $row;
    }

    public function isOllamaFilterEnabled(): bool
    {
        $flag = getenv('OLLAMA_NAME_NORMALIZE');
        if ($flag === false || $flag === null || trim((string) $flag) === '') {
            $flag = $_ENV['OLLAMA_NAME_NORMALIZE'] ?? '1';
        }

        return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    }

    public function isOllamaFilterEnabledOnImport(): bool
    {
        if (!$this->isOllamaFilterEnabled()) {
            return false;
        }
        $flag = getenv('OLLAMA_NAME_NORMALIZE_ON_IMPORT');
        if ($flag === false || $flag === null || trim((string) $flag) === '') {
            $flag = $_ENV['OLLAMA_NAME_NORMALIZE_ON_IMPORT'] ?? '0';
        }

        return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    }

    private function ollamaFilterMinConfidence(): float
    {
        $v = getenv('OLLAMA_NAME_NORMALIZE_MIN_CONF');
        if ($v === false || $v === null || trim((string) $v) === '') {
            $v = $_ENV['OLLAMA_NAME_NORMALIZE_MIN_CONF'] ?? '0.65';
        }

        return max(0.0, min(1.0, (float) $v));
    }

    /** @param array<string, mixed> $row @return array<string, string> */
    private function buildProductContextFromRow(array $row): array
    {
        $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $summary = is_array($raw['product_summary'] ?? null) ? $raw['product_summary'] : [];
        $specs = trim((string) ($row['pSpecs'] ?? ''));
        if ($specs === '' && isset($summary['parameters']) && is_array($summary['parameters'])) {
            foreach ($summary['parameters'] as $parameter) {
                if (!is_array($parameter)) {
                    continue;
                }
                $specs .= ' ' . trim((string) ($parameter['key'] ?? '') . ' ' . (string) ($parameter['value'] ?? ''));
            }
        }

        return [
            'brand' => trim((string) ($row['pBrand'] ?? '')),
            'category' => trim((string) ($row['pCategory'] ?? '')),
            'subcategory' => trim((string) ($row['pSubcategory'] ?? '')),
            'product_name' => trim((string) ($row['pName'] ?? ($summary['title'] ?? ''))),
            'oem' => trim((string) ($row['pOem'] ?? '')),
            'specs' => trim($specs),
            'supplier' => trim((string) ($row['pSupplier'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $context */
    private function formatContextForPrompt(array $context): string
    {
        $lines = [];
        foreach ([
            'product_name' => 'Titlu produs (SEO)',
            'brand' => 'Brand',
            'category' => 'Categorie magazin',
            'subcategory' => 'Subcategorie curentă',
            'oem' => 'Cod OEM',
            'specs' => 'Specificații',
            'supplier' => 'Furnizor',
        ] as $key => $label) {
            $value = trim((string) ($context[$key] ?? ''));
            if ($value !== '') {
                $lines[] = "- {$label}: {$value}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{ok: bool, rules: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listRules(int $page = 1, int $perPage = 40, string $search = '', string $source = 'all'): array
    {
        $this->bootLib();
        $rules = [];
        foreach ($this->ruleSources($source) as $src => $path) {
            if (!is_file($path)) {
                continue;
            }
            foreach ($this->parseRuleFile($path, $src) as $rule) {
                $rules[] = $rule;
            }
        }

        $searchNorm = function_exists('import_base_normalize_name_key')
            ? import_base_normalize_name_key($search)
            : strtolower(trim($search));

        if ($searchNorm !== '') {
            $rules = array_values(array_filter($rules, static function (array $rule) use ($searchNorm): bool {
                $hay = import_base_normalize_name_key((string) ($rule['aliases_text'] ?? '') . ' ' . (string) ($rule['target'] ?? ''));
                return str_contains($hay, $searchNorm);
            }));
        }

        $total = count($rules);
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        return [
            'ok' => true,
            'rules' => array_slice($rules, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    /** @return array{ok: bool, message?: string, rule?: array<string, mixed>} */
    public function saveCustomRule(string $aliasesText, string $target, ?int $line = null): array
    {
        $this->bootLib();
        $aliasesText = trim($aliasesText);
        $target = trim($target);
        if ($aliasesText === '' || $target === '') {
            return ['ok' => false, 'message' => 'Aliasii și denumirea țintă sunt obligatorii.'];
        }

        $lineText = $this->buildRuleLine(
            array_values(array_filter(array_map('trim', preg_split('/\|/u', $aliasesText) ?: []))),
            $target
        );

        $path = import_base_custom_name_overrides_path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $lines = is_file($path)
            ? preg_split('/\r?\n/', (string) file_get_contents($path)) ?: []
            : [];

        if ($line !== null && $line > 0 && isset($lines[$line - 1])) {
            $lines[$line - 1] = $lineText;
            $message = 'Regulă actualizată.';
        } else {
            $lines[] = $lineText;
            $message = 'Regulă adăugată.';
        }

        file_put_contents($path, implode("\n", array_map('rtrim', $lines)) . "\n");
        import_base_clear_name_overrides_cache();

        return [
            'ok' => true,
            'message' => $message,
            'rule' => [
                'line' => $line ?? count($lines),
                'aliases_text' => $aliasesText,
                'target' => $target,
                'raw_line' => $lineText,
                'source' => 'custom',
                'editable' => true,
            ],
        ];
    }

    /** @return array{ok: bool, message: string} */
    public function deleteCustomRule(int $line): array
    {
        $this->bootLib();
        $path = import_base_custom_name_overrides_path();
        if (!is_file($path) || $line < 1) {
            return ['ok' => false, 'message' => 'Regula custom nu există.'];
        }

        $lines = preg_split('/\r?\n/', (string) file_get_contents($path)) ?: [];
        if (!isset($lines[$line - 1])) {
            return ['ok' => false, 'message' => 'Linie invalidă.'];
        }

        array_splice($lines, $line - 1, 1);
        file_put_contents($path, implode("\n", array_map('rtrim', $lines)) . (count($lines) ? "\n" : ''));
        import_base_clear_name_overrides_cache();

        return ['ok' => true, 'message' => 'Regulă ștearsă.'];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{ok: bool, items: list<array<string, mixed>>, total_scanned: int}
     */
    public function scanQueue(PDO $pdo, array $filters = [], int $limit = 50): array
    {
        $this->bootLib();
        $limit = max(5, min(200, $limit));
        $status = trim((string) ($filters['status'] ?? 'pending'));
        $supplier = trim((string) ($filters['supplier'] ?? ''));

        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($supplier !== '') {
            $where[] = 'pSupplier = ?';
            $params[] = $supplier;
        }

        $sql = 'SELECT id, pCode, pName, pBrand, pSubcategory, pCategory, raw_json, status
                FROM import_produse
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY id DESC
                LIMIT ' . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rawName = $this->extractRawNameFromRow($row);
            if ($rawName === '') {
                continue;
            }
            // Scan = doar reguli (rapid). Ollama la „Aplică selectate” — altfel timeout Cloudflare 524.
            $detail = import_base_normalize_product_name_detail($rawName);
            $final = (string) ($detail['final'] ?? '');
            $source = (string) ($detail['source'] ?? '');
            $currentSub = trim((string) ($row['pSubcategory'] ?? ''));
            $needsReview = $detail['source'] !== 'override'
                || ($currentSub !== '' && import_base_normalize_name_key($currentSub) !== import_base_normalize_name_key($final));

            if (!$needsReview && ($filters['only_gaps'] ?? true)) {
                continue;
            }

            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'pCode' => (string) ($row['pCode'] ?? ''),
                'pName' => (string) ($row['pName'] ?? ''),
                'pBrand' => (string) ($row['pBrand'] ?? ''),
                'pSubcategory' => $currentSub,
                'raw_art_name' => $rawName,
                'normalized' => $final,
                'source' => $source,
                'rule_source' => (string) ($detail['source'] ?? ''),
                'override' => $detail['override'],
                'needs_review' => $needsReview,
                'needs_ollama' => in_array($detail['source'], ['unchanged', 'patterns'], true),
                'ollama_used' => false,
                'ollama_confidence' => null,
            ];
        }

        return [
            'ok' => true,
            'items' => $items,
            'total_scanned' => count($rows),
        ];
    }

    /** @return array{ok: bool, message: string, row?: array<string, mixed>} */
    public function applyToQueueRow(PDO $pdo, int $id, string $normalizedName, bool $saveRule = false, string $rawArtName = ''): array
    {
        $this->bootLib();
        $normalizedName = trim($normalizedName);
        if ($id <= 0 || $normalizedName === '') {
            return ['ok' => false, 'message' => 'ID sau denumire invalidă.'];
        }

        $stmt = $pdo->prepare('SELECT * FROM import_produse WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return ['ok' => false, 'message' => 'Produs negăsit în coadă.'];
        }

        $rawArtName = trim($rawArtName) !== '' ? trim($rawArtName) : $this->extractRawNameFromRow($row);
        if ($saveRule && $rawArtName !== '') {
            $this->saveCustomRule($rawArtName, $normalizedName);
        }

        $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        if ($rawArtName !== '') {
            $raw['__tecdoc_art_name'] = $rawArtName;
        }
        $raw['__name_normalized_at'] = date('c');
        $raw['__name_normalized_to'] = $normalizedName;

        $update = $pdo->prepare(
            'UPDATE import_produse SET pSubcategory = ?, raw_json = ? WHERE id = ? LIMIT 1'
        );
        $update->execute([
            $normalizedName,
            json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $id,
        ]);

        $row['pSubcategory'] = $normalizedName;
        $row['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'ok' => true,
            'message' => 'Denumire aplicată în coadă' . ($saveRule ? ' + regulă salvată.' : '.'),
            'row' => [
                'id' => $id,
                'pSubcategory' => $normalizedName,
                'raw_art_name' => $rawArtName,
            ],
        ];
    }

    /** @param list<int> $ids */
    public function applyToSelected(
        PDO $pdo,
        array $ids,
        bool $saveRules = false,
        bool $useOllama = true,
        int $ollamaBatchMax = 3,
        float $timeBudgetSec = 75.0
    ): array {
        $applied = 0;
        $skipped = 0;
        $ollamaApplied = 0;
        $errors = [];
        $remainingIds = [];
        $deadline = microtime(true) + max(15.0, min(120.0, $timeBudgetSec));
        $ollamaBatchMax = max(1, min(8, $ollamaBatchMax));
        $ollamaSlots = $useOllama ? $ollamaBatchMax : 0;
        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));

        foreach ($ids as $index => $id) {
            if (microtime(true) >= $deadline) {
                $remainingIds = array_slice($ids, $index);
                break;
            }

            $stmt = $pdo->prepare('SELECT * FROM import_produse WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                ++$skipped;
                continue;
            }
            $rawName = $this->extractRawNameFromRow($row);
            if ($rawName === '') {
                ++$skipped;
                continue;
            }

            $context = $this->buildProductContextFromRow($row);
            $ruleDetail = import_base_normalize_product_name_detail($rawName);
            $needsOllama = $useOllama
                && $ollamaSlots > 0
                && in_array((string) ($ruleDetail['source'] ?? ''), ['unchanged', 'patterns'], true);

            if ($needsOllama && microtime(true) >= ($deadline - 8.0)) {
                $remainingIds = array_slice($ids, $index);
                break;
            }

            $resolved = $needsOllama
                ? $this->normalizeWithOllamaFilter(
                    $rawName,
                    (string) ($context['brand'] ?? ''),
                    $context,
                    true
                )
                : [
                    'ok' => true,
                    'final' => (string) ($ruleDetail['final'] ?? ''),
                    'source' => (string) ($ruleDetail['source'] ?? ''),
                    'rule_detail' => $ruleDetail,
                    'ollama_used' => false,
                    'ollama' => null,
                ];

            if ($needsOllama) {
                --$ollamaSlots;
            }

            $final = trim((string) ($resolved['final'] ?? ''));
            if ($final === '') {
                ++$skipped;
                continue;
            }
            if (!empty($resolved['ollama_used'])) {
                ++$ollamaApplied;
            }
            $result = $this->applyToQueueRow($pdo, $id, $final, $saveRules, $rawName);
            if (!empty($result['ok'])) {
                ++$applied;
                if (!empty($resolved['ollama_used'])) {
                    $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
                    if (is_array($raw) && is_array($resolved['ollama'] ?? null)) {
                        $raw['__name_ollama'] = [
                            'normalized' => $final,
                            'confidence' => (float) ($resolved['ollama']['confidence'] ?? 0),
                            'reasoning' => (string) ($resolved['ollama']['reasoning'] ?? ''),
                            'model' => (string) ($resolved['ollama']['model'] ?? ''),
                            'at' => date('c'),
                        ];
                        $pdo->prepare('UPDATE import_produse SET raw_json = ? WHERE id = ? LIMIT 1')
                            ->execute([json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id]);
                    }
                }
            } else {
                $errors[] = (string) ($result['message'] ?? 'Eroare');
            }
        }

        $hasMore = $remainingIds !== [];

        return [
            'ok' => true,
            'applied' => $applied,
            'ollama_applied' => $ollamaApplied,
            'skipped' => $skipped,
            'errors' => $errors,
            'remaining_ids' => $remainingIds,
            'has_more' => $hasMore,
            'message' => $applied > 0
                ? ('Aplicat pe ' . $applied . ' produse'
                    . ($ollamaApplied > 0 ? (' (' . $ollamaApplied . ' cu Ollama)') : '')
                    . ($saveRules ? ' + reguli salvate.' : '.')
                    . ($hasMore ? ' Mai rămân ' . count($remainingIds) . ' — continuă batch-ul.' : ''))
                : ($hasMore
                    ? 'Timeout batch — mai rămân ' . count($remainingIds) . ' produse.'
                    : 'Niciun produs aplicat.'),
        ];
    }

    /**
     * Statistici pentru normalizare masivă (coadă import).
     *
     * @param array<string, mixed> $filters
     * @return array{ok: bool, total: int, status: string, supplier: string}
     */
    public function bulkStats(PDO $pdo, array $filters = []): array
    {
        [$whereSql, $params] = $this->bulkFiltersSql($filters);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM import_produse WHERE ' . $whereSql);
        $stmt->execute($params);

        return [
            'ok' => true,
            'total' => (int) $stmt->fetchColumn(),
            'status' => (string) ($filters['status'] ?? 'pending'),
            'supplier' => (string) ($filters['supplier'] ?? ''),
        ];
    }

    /**
     * Pas 1 masiv: reguli din fișier pe chunk (keyset id ASC).
     *
     * @param array<string, mixed> $filters
     * @return array{processed:int, updated:int, skipped:int, cursor_id:int, done:bool}
     */
    public function processRulesBulkChunk(PDO $pdo, array $filters, int $cursorId, int $limit = 400): array
    {
        $this->bootLib();
        $limit = max(50, min(1000, $limit));
        [$whereSql, $params] = $this->bulkFiltersSql($filters);
        $onlyGaps = !array_key_exists('only_gaps', $filters) || !empty($filters['only_gaps']);

        $sql = 'SELECT * FROM import_produse WHERE ' . $whereSql . ' AND id > ? ORDER BY id ASC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([...$params, max(0, $cursorId)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $lastId = $cursorId;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lastId = (int) ($row['id'] ?? $lastId);
            ++$processed;

            $rawName = $this->extractRawNameFromRow($row);
            if ($rawName === '') {
                ++$skipped;
                continue;
            }

            $detail = import_base_normalize_product_name_detail($rawName);
            $final = trim((string) ($detail['final'] ?? ''));
            if ($final === '') {
                ++$skipped;
                continue;
            }

            $currentSub = trim((string) ($row['pSubcategory'] ?? ''));
            if ($onlyGaps) {
                $needs = ($detail['source'] ?? '') !== 'override'
                    || ($currentSub !== '' && import_base_normalize_name_key($currentSub) !== import_base_normalize_name_key($final));
                if (!$needs) {
                    ++$skipped;
                    continue;
                }
            }

            if ($currentSub !== '' && import_base_normalize_name_key($currentSub) === import_base_normalize_name_key($final)) {
                ++$skipped;
                continue;
            }

            $product = $this->applyNormalizationPipelineToProduct($row, false);
            if ($this->persistNormalizedRow($pdo, $lastId, $product, $rawName, $final, false)) {
                ++$updated;
            } else {
                ++$skipped;
            }
        }

        return [
            'processed' => $processed,
            'updated' => $updated,
            'skipped' => $skipped,
            'cursor_id' => $lastId,
            'done' => $rows === [] || count($rows) < $limit,
        ];
    }

    /**
     * Pas 2 masiv: Ollama pe produse fără regulă explicită (chunk cu limită apeluri AI).
     *
     * @param array<string, mixed> $filters
     * @return array{processed:int, updated:int, skipped:int, ollama_applied:int, cursor_id:int, done:bool}
     */
    public function processOllamaBulkChunk(
        PDO $pdo,
        array $filters,
        int $cursorId,
        int $maxOllama = 12,
        float $deadline = 0.0,
        bool $saveRules = false
    ): array {
        $this->bootLib();
        if ($deadline <= 0) {
            $deadline = microtime(true) + 240.0;
        }
        $maxOllama = max(1, min(30, $maxOllama));
        $fetchSize = 40;

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $ollamaApplied = 0;
        $lastId = $cursorId;
        $done = false;

        while ($ollamaApplied < $maxOllama && microtime(true) < $deadline) {
            [$whereSql, $params] = $this->bulkFiltersSql($filters);
            $sql = 'SELECT * FROM import_produse WHERE ' . $whereSql . ' AND id > ? ORDER BY id ASC LIMIT ' . $fetchSize;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([...$params, max(0, $lastId)]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($rows === []) {
                $done = true;
                break;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if ($ollamaApplied >= $maxOllama || microtime(true) >= $deadline) {
                    break 2;
                }

                $lastId = (int) ($row['id'] ?? $lastId);
                ++$processed;

                $rawName = $this->extractRawNameFromRow($row);
                if ($rawName === '') {
                    ++$skipped;
                    continue;
                }

                $ruleDetail = import_base_normalize_product_name_detail($rawName);
                if (!in_array((string) ($ruleDetail['source'] ?? ''), ['unchanged', 'patterns'], true)) {
                    ++$skipped;
                    continue;
                }

                $context = $this->buildProductContextFromRow($row);
                $resolved = $this->normalizeWithOllamaFilter(
                    $rawName,
                    (string) ($context['brand'] ?? ''),
                    $context,
                    true,
                    true
                );

                if (empty($resolved['ollama_used'])) {
                    ++$skipped;
                    continue;
                }

                $final = trim((string) ($resolved['final'] ?? ''));
                if ($final === '') {
                    ++$skipped;
                    continue;
                }

                $product = $row;
                $product = $this->applyNormalizationPipelineToProduct($product, false);
                $product['pSubcategory'] = $final;
                if ($this->persistNormalizedRow($pdo, $lastId, $product, $rawName, $final, $saveRules)) {
                    ++$updated;
                    ++$ollamaApplied;
                    if (is_array($resolved['ollama'] ?? null)) {
                        $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
                        if (is_array($raw)) {
                            $raw['__name_ollama'] = [
                                'normalized' => $final,
                                'confidence' => (float) ($resolved['ollama']['confidence'] ?? 0),
                                'reasoning' => (string) ($resolved['ollama']['reasoning'] ?? ''),
                                'model' => (string) ($resolved['ollama']['model'] ?? ''),
                                'at' => date('c'),
                            ];
                            $pdo->prepare('UPDATE import_produse SET raw_json = ? WHERE id = ? LIMIT 1')
                                ->execute([json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $lastId]);
                        }
                    }
                } else {
                    ++$skipped;
                }
            }

            if (count($rows) < $fetchSize) {
                $done = true;
                break;
            }
        }

        return [
            'processed' => $processed,
            'updated' => $updated,
            'skipped' => $skipped,
            'ollama_applied' => $ollamaApplied,
            'cursor_id' => $lastId,
            'done' => $done,
        ];
    }

    /** @param array<string, mixed> $filters @return array{0: string, 1: list<mixed>} */
    private function bulkFiltersSql(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        $status = trim((string) ($filters['status'] ?? 'pending'));
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $supplier = trim((string) ($filters['supplier'] ?? ''));
        if ($supplier !== '') {
            $where[] = 'pSupplier = ?';
            $params[] = $supplier;
        }

        return [implode(' AND ', $where), $params];
    }

    /** @param array<string, mixed> $product */
    private function persistNormalizedRow(
        PDO $pdo,
        int $id,
        array $product,
        string $rawArtName,
        string $normalized,
        bool $saveRule
    ): bool {
        if ($id <= 0 || $normalized === '') {
            return false;
        }

        if ($saveRule && $rawArtName !== '') {
            $this->saveCustomRule($rawArtName, $normalized);
        }

        $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        if ($rawArtName !== '') {
            $raw['__tecdoc_art_name'] = $rawArtName;
        }
        $raw['__name_normalized_at'] = date('c');
        $raw['__name_normalized_to'] = $normalized;

        $update = $pdo->prepare('UPDATE import_produse SET pSubcategory = ?, raw_json = ? WHERE id = ? LIMIT 1');

        return $update->execute([
            $normalized,
            json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $id,
        ]);
    }

    /** @return array<string, string> */
    private function ruleSources(string $source): array
    {
        $this->bootLib();
        $all = [
            'base' => import_base_name_overrides_path(),
            'custom' => import_base_custom_name_overrides_path(),
        ];
        if ($source === 'base' || $source === 'custom') {
            return [$source => $all[$source]];
        }

        return $all;
    }

    /** @return list<array<string, mixed>> */
    private function parseRuleFile(string $path, string $source): array
    {
        $rules = [];
        foreach (preg_split('/\r?\n/', (string) file_get_contents($path)) ?: [] as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '#')) {
                continue;
            }
            [$left, $right] = explode('#', $trimmed, 2);
            $target = trim($right);
            $aliases = array_values(array_filter(array_map('trim', preg_split('/\|/u', $left) ?: [])));
            if ($target === '' || $aliases === []) {
                continue;
            }
            $rules[] = [
                'line' => $index + 1,
                'aliases' => $aliases,
                'aliases_text' => implode(' | ', $aliases),
                'target' => $target,
                'raw_line' => $trimmed,
                'source' => $source,
                'editable' => $source === 'custom',
            ];
        }

        return $rules;
    }

    private function countRuleLines(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }
        $count = 0;
        foreach (preg_split('/\r?\n/', (string) file_get_contents($path)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && str_contains($line, '#')) {
                ++$count;
            }
        }

        return $count;
    }

    /** @param list<string> $aliases */
    private function buildRuleLine(array $aliases, string $target): string
    {
        $left = implode(' | ', array_values(array_filter(array_map('trim', $aliases))));

        return $left . '#' . trim($target);
    }

    private function sampleRulesForPrompt(string $rawName, int $limit): string
    {
        $this->bootLib();
        $key = import_base_normalize_name_key($rawName);
        $words = array_values(array_filter(explode(' ', $key)));
        $samples = [];
        $path = import_base_name_overrides_path();
        if (!is_file($path)) {
            return '(fără exemple)';
        }

        foreach (preg_split('/\r?\n/', (string) file_get_contents($path)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '#')) {
                continue;
            }
            $lineKey = import_base_normalize_name_key(explode('#', $line, 2)[0]);
            $score = 0;
            foreach ($words as $word) {
                if ($word !== '' && str_contains($lineKey, $word)) {
                    ++$score;
                }
            }
            if ($score > 0) {
                $samples[] = ['score' => $score, 'line' => $line];
            }
        }

        usort($samples, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $picked = array_slice($samples, 0, $limit);
        if ($picked === []) {
            foreach (array_slice(preg_split('/\r?\n/', (string) file_get_contents($path)) ?: [], 0, $limit) as $line) {
                $line = trim((string) $line);
                if ($line !== '') {
                    $picked[] = ['line' => $line];
                }
            }
        }

        return implode("\n", array_map(static fn(array $row): string => '- ' . (string) $row['line'], $picked));
    }

    /** @param array<string, mixed> $row */
    private function extractRawNameFromRow(array $row): string
    {
        $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
        if (!is_array($raw)) {
            $raw = [];
        }

        foreach (['__tecdoc_art_name', 'ART_NAME', 'art_name', '__art_name'] as $key) {
            $value = trim((string) ($raw[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        if (isset($raw['product_summary']) && is_array($raw['product_summary'])) {
            $summary = $raw['product_summary'];
            foreach (['tecdoc_art_name', 'art_name', 'raw_art_name'] as $key) {
                $value = trim((string) ($summary[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        if (isset($raw['raw_json']) && is_string($raw['raw_json'])) {
            $nested = json_decode($raw['raw_json'], true);
            if (is_array($nested)) {
                foreach (['__tecdoc_art_name', 'ART_NAME', 'art_name'] as $key) {
                    $value = trim((string) ($nested[$key] ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        $sub = trim((string) ($row['pSubcategory'] ?? ''));
        if ($sub !== '' && (str_contains($sub, ',') || str_contains($sub, '|'))) {
            return $sub;
        }

        return $sub;
    }

    /** @return array<string, mixed>|null */
    private function parseJsonObject(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $content = $matches[0];
        }
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function bootLib(): void
    {
        if (!function_exists('import_base_normalize_product_name_detail')) {
            require_once dirname(__DIR__) . '/Controllers/Produse/import_base_lib.php';
        }
    }
}
