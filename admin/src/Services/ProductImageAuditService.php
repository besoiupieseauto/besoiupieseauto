<?php

declare(strict_types=1);

namespace Evasystem\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Auditor imagini produse — verifică dacă imaginea corespunde titlului și descrierii.
 */
final class ProductImageAuditService
{
    private const STORAGE_SUBDIR = '/storage/image_audit';

    public function __construct(
        private readonly string $projectRoot,
        private readonly ?Client $http = null,
    ) {
    }

    private function envModelValue(string $key, string $default): string
    {
        $envFile = rtrim($this->projectRoot, '/\\') . '/admin/system/env_settings.php';
        if (is_file($envFile)) {
            require_once $envFile;
            if (function_exists('besoiu_env_model_value')) {
                return besoiu_env_model_value($key, $default);
            }
        }

        $val = trim((string) ($_ENV[$key] ?? getenv($key) ?: ''));

        return $val !== '' ? $val : $default;
    }

    private function openAiAuditModel(): string
    {
        $audit = trim((string) ($_ENV['IMAGE_AUDIT_MODEL'] ?? getenv('IMAGE_AUDIT_MODEL') ?: ''));
        if ($audit !== '') {
            return $audit;
        }

        return $this->envModelValue('OPENAI_MODEL', 'gpt-4o-mini');
    }

    private function cursorAuditModel(): string
    {
        return $this->envModelValue('CURSOR_MODEL', 'composer-2.5');
    }

    /** @return array<string, mixed> */
    public function storagePaths(): array
    {
        $base = rtrim($this->projectRoot, '/\\') . '/admin' . self::STORAGE_SUBDIR;
        $batches = $base . '/batches';
        $reports = $base . '/reports';

        foreach ([$base, $batches, $reports, $base . '/by_product', $base . '/jobs', $base . '/logs'] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        return ['base' => $base, 'batches' => $batches, 'reports' => $reports, 'by_product' => $base . '/by_product', 'jobs' => $base . '/jobs', 'logs' => $base . '/logs'];
    }

    /**
     * @param array<int, string> $publicIds randomn_id
     * @return array<int, array<string, mixed>>
     */
    public function loadProductsByPublicIds(PDO $pdo, array $publicIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $publicIds))));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, randomn_id, pName, pCode, pBrand, pCategory, pSubcategory, pNote, pImages, pImageSource, pVitrina
                FROM produse WHERE status <> '0' AND randomn_id IN ($placeholders)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $byId = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $byId[(string) ($row['randomn_id'] ?? '')] = $this->normalizeProductRow($row);
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    public static function auditEngine(): string
    {
        $raw = strtolower(trim((string) ($_ENV['IMAGE_AUDIT_ENGINE'] ?? getenv('IMAGE_AUDIT_ENGINE') ?: 'cursor')));

        return $raw === 'openai' ? 'openai' : 'cursor';
    }

    public static function openAiFallbackEnabled(): bool
    {
        $raw = strtolower(trim((string) ($_ENV['IMAGE_AUDIT_OPENAI_FALLBACK'] ?? getenv('IMAGE_AUDIT_OPENAI_FALLBACK') ?: '')));

        return $raw === '1' || $raw === 'true' || $raw === 'yes';
    }

    /**
     * Pregătește lot pentru analiză în Cursor Composer (fără OpenAI/Gemini).
     *
     * @param array<int, array<string, mixed>> $products
     * @return array<string, mixed>
     */
    public function prepareCursorAuditBatch(array $products, array $meta = []): array
    {
        $manifest = $this->buildManifest($products, array_merge($meta, [
            'engine' => 'cursor-' . $this->cursorAuditModel(),
            'save_results_to' => 'admin/storage/image_audit/by_product/{randomn_id}.json',
        ]));
        $path = $this->saveManifest($manifest);
        $batchName = basename($path);

        $promptLines = [
            '@product-image-audit',
            '',
            'Analizează vizual lotul: **' . $batchName . '**',
            'Cale: `' . str_replace('\\', '/', $path) . '`',
            '',
            'Pentru fiecare produs din lot:',
            '1. Citește imaginea (local_image_path sau image_url)',
            '2. Compară cu title, category, description_excerpt',
            '3. Salvează verdict JSON în `admin/storage/image_audit/by_product/{randomn_id}.json`',
            '4. Scrie raport în `admin/storage/image_audit/reports/`',
            '',
            'Fără OpenAI — folosești Composer 2.5 cu vedere pe imagini.',
        ];

        return [
            'mode' => 'cursor',
            'batch_path' => $path,
            'batch_name' => $batchName,
            'cursor_prompt' => implode("\n", $promptLines),
            'products' => $products,
            'product_count' => count($products),
        ];
    }

    /**
     * @param array<int, string> $publicIds
     * @return array<int, array<string, mixed>>
     */
    public function loadAuditResultsForIds(array $publicIds): array
    {
        $out = [];
        foreach ($publicIds as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $row = $this->loadProductAuditResult($id);
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param array<int, string>          $ids
     * @return array{job_id: string, job_path: string}
     */
    public function createCursorAuditJob(array $prep, array $ids, array $products): array
    {
        $paths = $this->storagePaths();
        $jobsDir = (string) ($paths['jobs'] ?? $paths['base'] . '/jobs');
        if (!is_dir($jobsDir)) {
            mkdir($jobsDir, 0775, true);
        }

        $jobId = 'job_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $jobPath = $jobsDir . '/' . $jobId . '.json';

        $preview = [];
        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }
            $preview[] = [
                'randomn_id' => (string) ($product['randomn_id'] ?? ''),
                'title' => (string) ($product['title'] ?? $product['pName'] ?? 'Produs'),
                'code' => (string) ($product['code'] ?? $product['pCode'] ?? ''),
            ];
        }

        $job = [
            'job_id' => $jobId,
            'status' => 'starting',
            'phase' => 'Pregătesc lotul pentru analiză…',
            'batch_path' => (string) ($prep['batch_path'] ?? ''),
            'batch_name' => (string) ($prep['batch_name'] ?? ''),
            'ids' => array_values($ids),
            'products' => $preview,
            'total' => count($products),
            'done' => 0,
            'started_at' => time(),
            'updated_at' => time(),
        ];

        file_put_contents(
            $jobPath,
            json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return ['job_id' => $jobId, 'job_path' => $jobPath];
    }

    /** @param array<string, mixed> $fields */
    public function patchCursorAuditJob(string $jobId, array $fields): void
    {
        $jobId = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($jobId));
        if ($jobId === '') {
            return;
        }

        $paths = $this->storagePaths();
        $jobPath = (string) ($paths['jobs'] ?? $paths['base'] . '/jobs') . '/' . $jobId . '.json';
        if (!is_file($jobPath)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($jobPath), true);
        if (!is_array($decoded)) {
            return;
        }

        foreach ($fields as $key => $value) {
            $decoded[$key] = $value;
        }
        $decoded['updated_at'] = time();

        file_put_contents(
            $jobPath,
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Completează verdicturi lipsă după job Cursor (fallback OpenAI dacă există cheie).
     */
    public function fillMissingCursorAuditResults(PDO $pdo, string $jobId): int
    {
        $job = $this->readCursorAuditJob($jobId);
        if ($job === null) {
            return 0;
        }

        if (!empty($job['vision_fallback_done'])) {
            return 0;
        }

        $status = (string) ($job['status'] ?? '');
        if (!in_array($status, ['done', 'error'], true)) {
            return 0;
        }

        $ids = is_array($job['ids'] ?? null) ? $job['ids'] : [];
        if ($ids === []) {
            return 0;
        }

        $startedAt = (int) ($job['started_at'] ?? 0);
        $existing = $this->loadAuditResultsSince($ids, $startedAt);
        $have = [];
        foreach ($existing as $row) {
            if (is_array($row) && !empty($row['randomn_id'])) {
                $have[(string) $row['randomn_id']] = true;
            }
        }

        $missing = array_values(array_filter($ids, static fn ($id) => !isset($have[(string) $id])));
        if ($missing === []) {
            $this->patchCursorAuditJob($jobId, ['vision_fallback_done' => true]);

            return 0;
        }

        if (!self::openAiFallbackEnabled()) {
            return 0;
        }

        $apiKey = self::normalizeOpenAiKey((string) ($_ENV['OPENAI_KEY'] ?? getenv('OPENAI_KEY') ?: ''));
        if ($apiKey === '') {
            return 0;
        }

        $model = $this->openAiAuditModel();
        $products = $this->loadProductsByPublicIds($pdo, $missing);
        $saved = 0;

        foreach ($products as $product) {
            try {
                $result = $this->analyzeProductWithVision($product, $apiKey, $model);
                $result['engine'] = 'openai-fallback';
                $this->saveProductAuditResult($result);
                ++$saved;
            } catch (RuntimeException) {
                break;
            }
        }

        $this->patchCursorAuditJob($jobId, [
            'vision_fallback_done' => true,
            'vision_fallback_count' => $saved,
        ]);

        return $saved;
    }

    /**
     * Audit Cursor sincron (fără polling) — așteaptă Python + salvează verdicturi.
     *
     * @param array<int, array<string, mixed>> $products
     * @param array<int, string>               $ids
     * @return array{ok: bool, results: array<int, array<string, mixed>>, job_id: string, error?: string}
     */
    public function runCursorAuditSync(
        CursorImageAuditClient $client,
        array $prep,
        array $ids,
        array $products,
        PDO $pdo
    ): array {
        $job = $this->createCursorAuditJob($prep, $ids, $products);
        $jobId = (string) $job['job_id'];
        $timeout = max(180, min(900, count($products) * 90));
        $run = $client->runBatchAudit((string) ($prep['batch_path'] ?? ''), $timeout, (string) $job['job_path']);

        $this->fillMissingCursorAuditResults($pdo, $jobId);
        $results = $this->loadAuditResultsForIds($ids);

        if ($results === [] && !empty($run['error'])) {
            return [
                'ok' => false,
                'results' => [],
                'job_id' => $jobId,
                'error' => (string) $run['error'],
            ];
        }

        return [
            'ok' => $results !== [],
            'results' => $results,
            'job_id' => $jobId,
            'error' => $results === [] ? 'Agentul Cursor nu a returnat verdicturi.' : null,
        ];
    }

    /**
     * Pornește audit Cursor în fundal — răspuns HTTP imediat, progres prin polling job.
     *
     * @param array<int, array<string, mixed>> $products
     * @param array<int, string>               $ids
     * @return array{ok: bool, job_id: string, error?: string}
     */
    public function startCursorAuditAsync(
        CursorImageAuditClient $client,
        array $prep,
        array $ids,
        array $products
    ): array {
        $job = $this->createCursorAuditJob($prep, $ids, $products);
        $jobId = (string) $job['job_id'];
        $batchPath = (string) ($prep['batch_path'] ?? '');
        $jobPath = (string) ($job['job_path'] ?? '');

        $spawn = $client->spawnBatchAudit($batchPath, $jobPath);
        if (!$spawn['ok']) {
            $this->patchCursorAuditJob($jobId, [
                'status' => 'error',
                'error' => (string) ($spawn['error'] ?? 'Nu am putut porni agentul Cursor.'),
            ]);

            return [
                'ok' => false,
                'job_id' => $jobId,
                'error' => (string) ($spawn['error'] ?? 'Nu am putut porni agentul Cursor.'),
            ];
        }

        $this->patchCursorAuditJob($jobId, [
            'status' => 'running',
            'phase' => 'Agent Cursor pornit — analizez primul produs…',
        ]);

        return [
            'ok' => true,
            'job_id' => $jobId,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param array<int, string>               $ids
     * @return array{ok: bool, results: array<int, array<string, mixed>>, message: string}
     */
    public function runOpenAiAuditSync(array $products, array $ids, string $apiKey, string $model): array
    {
        $run = $this->auditProductsWithVision($products, $apiKey, $model, [
            'source' => 'admin_crud_sync',
            'ids' => $ids,
        ]);

        if (!empty($run['api_error'])) {
            return [
                'ok' => false,
                'results' => $run['results'] ?? [],
                'message' => (string) $run['api_error'],
            ];
        }

        $results = [];
        foreach ($run['results'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $this->saveProductAuditResult($row);
            $results[] = $row;
        }

        return [
            'ok' => $results !== [],
            'results' => $results,
            'message' => 'Audit finalizat: ' . count($results) . ' produse.',
        ];
    }

    /** @return array<string, mixed>|null */
    public function readCursorAuditJob(string $jobId): ?array
    {
        $jobId = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($jobId));
        if ($jobId === '') {
            return null;
        }

        $paths = $this->storagePaths();
        $jobPath = (string) ($paths['jobs'] ?? $paths['base'] . '/jobs') . '/' . $jobId . '.json';
        if (!is_file($jobPath)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($jobPath), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<int, string> $publicIds
     * @return array<int, array<string, mixed>>
     */
    public function loadAuditResultsSince(array $publicIds, int $sinceUnix): array
    {
        $paths = $this->storagePaths();
        $byProduct = (string) ($paths['by_product'] ?? $paths['base'] . '/by_product');
        $out = [];

        foreach ($publicIds as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }

            $path = $byProduct . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $id) . '.json';
            if (!is_file($path)) {
                continue;
            }
            if ($sinceUnix > 0 && (int) filemtime($path) < $sinceUnix) {
                continue;
            }

            $row = $this->loadProductAuditResult($id);
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function buildCursorJobProgress(string $jobId): ?array
    {
        $job = $this->readCursorAuditJob($jobId);
        if ($job === null) {
            return null;
        }

        $ids = is_array($job['ids'] ?? null) ? $job['ids'] : [];
        $startedAt = (int) ($job['started_at'] ?? 0);
        $total = max(1, (int) ($job['total'] ?? count($ids)));
        $status = (string) ($job['status'] ?? 'starting');
        $elapsed = max(0, time() - $startedAt);
        $results = $this->loadAuditResultsSince($ids, $startedAt);
        $done = count($results);
        $remaining = max(0, $total - $done);

        $doneIds = [];
        foreach ($results as $row) {
            if (is_array($row) && !empty($row['randomn_id'])) {
                $doneIds[(string) $row['randomn_id']] = true;
            }
        }

        $nextProduct = null;
        $products = is_array($job['products'] ?? null) ? $job['products'] : [];
        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }
            $rid = (string) ($product['randomn_id'] ?? '');
            if ($rid !== '' && !isset($doneIds[$rid])) {
                $nextProduct = $product;
                break;
            }
        }

        $phase = (string) ($job['phase'] ?? '');
        $currentIndex = max(0, (int) ($job['current_index'] ?? 0));
        $activeId = trim((string) ($job['current_product_id'] ?? ''));
        $activeTitle = trim((string) ($job['current_product_title'] ?? ''));
        if ($activeTitle === '' && $activeId !== '') {
            foreach ($products as $product) {
                if (is_array($product) && (string) ($product['randomn_id'] ?? '') === $activeId) {
                    $activeTitle = (string) ($product['title'] ?? '');
                    break;
                }
            }
        }

        if ($status === 'running' && $currentIndex > 0 && $activeTitle !== '') {
            $phase = 'Analizez ' . $currentIndex . '/' . $total . ': ' . $activeTitle;
        } elseif ($status === 'running' && $done > 0 && $nextProduct !== null) {
            $title = (string) ($nextProduct['title'] ?? 'produs');
            $phase = 'Finalizat ' . $done . '/' . $total . ' — urmează: ' . $title;
        } elseif ($status === 'running' && $phase !== '') {
            // Faza din runner Python
        } elseif ($status === 'running' && $done === 0 && $elapsed < 50) {
            $phase = 'Pornesc Cursor API — primul produs durează ~30–60 sec…';
        } elseif ($status === 'running' && $done === 0) {
            $firstTitle = is_array($products[0] ?? null) ? (string) ($products[0]['title'] ?? 'primul produs') : 'primul produs';
            $phase = 'Analizez vizual: ' . $firstTitle;
        } elseif ($status === 'starting') {
            $phase = 'Pregătesc lotul și pornesc agentul Cursor…';
        } elseif ($status === 'done') {
            if ($done < $total) {
                $phase = 'Doar ' . $done . '/' . $total . ' produse au verdict salvat.';
            } else {
                $phase = 'Audit finalizat: ' . $done . '/' . $total . ' produse.';
            }
        } elseif ($status === 'error') {
            $phase = (string) ($job['error'] ?? 'Eroare la audit Cursor.');
        }

        $resultsIncomplete = $status === 'done' && $done < $total;

        $activity = match (true) {
            $resultsIncomplete => 'Lipsesc verdicturi — agentul nu a salvat toate rezultatele. Reîncearcă auditul sau verifică logul cursor_audit_spawn.log.',
            $status === 'done' => 'Audit terminat. Urmează căutarea automată de imagini pentru produsele cu probleme (Pas 2).',
            $status === 'error' => 'A apărut o eroare la comunicarea cu Cursor API.',
            $currentIndex > 0 => 'Composer 2.5 citește poza și verifică: «' . ($activeTitle !== '' ? $activeTitle : 'produs') . '» — titlu, categorie, conținut imagine.',
            default => 'Agentul Cursor pornește pe server. Fiecare produs = o analiză vizuală (~20–40 sec).',
        };

        $secPerProduct = 28;
        $estimateTotal = max(90, $total * $secPerProduct);
        if ($done > 0) {
            $estimateTotal = (int) max($elapsed, ceil(($elapsed / $done) * $total));
        }
        $etaSec = max(0, $estimateTotal - $elapsed);

        if ($status === 'done') {
            $percent = $done >= $total ? 100 : min(99, (int) floor(($done / $total) * 100));
        } elseif ($status === 'error') {
            $percent = $done > 0 ? min(99, 10 + (int) floor(($done / $total) * 85)) : 0;
        } elseif ($status === 'starting') {
            $percent = 5;
        } elseif ($done > 0) {
            $percent = min(95, 10 + (int) floor(($done / $total) * 85));
        } elseif ($currentIndex > 0) {
            $percent = min(90, 8 + (int) floor(($currentIndex / $total) * 82));
        } else {
            $percent = min(35, 8 + (int) floor($elapsed / 5));
        }

        $currentProduct = $nextProduct;
        if ($activeId !== '') {
            foreach ($products as $product) {
                if (is_array($product) && (string) ($product['randomn_id'] ?? '') === $activeId) {
                    $currentProduct = $product;
                    break;
                }
            }
        }

        $displayDone = ($status === 'done' || $status === 'error') ? $done : max($done, $currentIndex);

        return [
            'success' => true,
            'job_id' => (string) ($job['job_id'] ?? $jobId),
            'status' => $status,
            'phase' => $phase,
            'activity' => $activity,
            'workflow_step' => 'audit',
            'total' => $total,
            'done' => $done,
            'display_done' => $displayDone,
            'results_incomplete' => $resultsIncomplete,
            'remaining' => $remaining,
            'percent' => $percent,
            'elapsed_sec' => $elapsed,
            'eta_sec' => $etaSec,
            'estimate_total_sec' => $estimateTotal,
            'current_index' => $currentIndex,
            'current_product_id' => $activeId !== '' ? $activeId : null,
            'error' => $job['error'] ?? null,
            'results' => $results,
            'current_product' => $currentProduct,
            'finished' => in_array($status, ['done', 'error'], true),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array<string, mixed>>
     */
    public function importAuditResults(array $results): array
    {
        $saved = [];
        foreach ($results as $row) {
            if (!is_array($row)) {
                continue;
            }
            $path = $this->saveProductAuditResult($row);
            if ($path !== '') {
                $saved[] = $row;
            }
        }

        return $saved;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array{results: array<int, array<string, mixed>>, report_path: string, api_error?: string}
     */
    public function auditProductsWithVision(
        array $products,
        string $apiKey,
        string $model = 'gpt-4o-mini',
        array $meta = []
    ): array {
        $apiKey = self::normalizeOpenAiKey($apiKey);
        $results = [];
        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }
            try {
                $result = $this->analyzeProductWithVision($product, $apiKey, $model);
            } catch (RuntimeException $e) {
                return [
                    'results' => $results,
                    'report_path' => '',
                    'api_error' => $e->getMessage(),
                ];
            }
            $results[] = $result;
        }

        $reportPath = $this->writeMarkdownReport($results, $meta);

        return ['results' => $results, 'report_path' => $reportPath];
    }

    public static function normalizeOpenAiKey(string $key): string
    {
        $key = trim($key);
        $key = trim($key, "\"'");

        return trim($key);
    }

    /** Verificare rapidă înainte de audit în masă. */
    public function verifyOpenAiKey(string $apiKey, string $model = 'gpt-4o-mini'): ?string
    {
        $apiKey = self::normalizeOpenAiKey($apiKey);
        if ($apiKey === '') {
            return 'OPENAI_KEY lipsește din admin/.env.';
        }
        if (!str_starts_with($apiKey, 'sk-')) {
            return 'OPENAI_KEY nu pare validă (trebuie să înceapă cu sk-).';
        }

        try {
            $client = $this->http ?? new Client(['timeout' => 25]);
            $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 5,
                    'messages' => [
                        ['role' => 'user', 'content' => 'ping'],
                    ],
                ],
            ]);

            return null;
        } catch (RequestException $e) {
            return self::formatOpenAiClientError($e);
        } catch (GuzzleException $e) {
            return 'Nu mă pot conecta la OpenAI: ' . $e->getMessage();
        }
    }

    private static function formatOpenAiClientError(RequestException $e): string
    {
        $status = $e->getResponse()?->getStatusCode() ?? 0;
        $body = (string) ($e->getResponse()?->getBody() ?? '');
        $decoded = json_decode($body, true);
        $openAiMsg = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';

        if ($status === 401 || str_contains($openAiMsg, 'Incorrect API key')) {
            return 'Cheie OpenAI respinsă (401). Deschide admin/.env → OPENAI_KEY= cheie nouă de la platform.openai.com/api-keys (nu e problemă la imagini).';
        }
        if ($status === 429) {
            return 'OpenAI: limită depășită sau fără credit (429). Verifică billing pe platform.openai.com.';
        }
        if ($openAiMsg !== '') {
            return 'OpenAI (' . $status . '): ' . $openAiMsg;
        }

        return 'OpenAI (' . $status . '): ' . $e->getMessage();
    }

    /** @param array<string, mixed> $result */
    public function saveProductAuditResult(array $result): string
    {
        $paths = $this->storagePaths();
        $byProduct = $paths['by_product'] ?? ($paths['base'] . '/by_product');
        if (!is_dir($byProduct)) {
            mkdir($byProduct, 0775, true);
        }

        $id = trim((string) ($result['randomn_id'] ?? ''));
        if ($id === '') {
            return '';
        }

        $path = $byProduct . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $id) . '.json';
        file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /** @return array<string, mixed>|null */
    public function loadProductAuditResult(string $publicId): ?array
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return null;
        }

        $paths = $this->storagePaths();
        $path = ($paths['by_product'] ?? ($paths['base'] . '/by_product'))
            . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $publicId) . '.json';

        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function loadProductsForAudit(PDO $pdo, array $options = []): array
    {
        $limit = max(1, min(200, (int) ($options['limit'] ?? 20)));
        $randomnId = trim((string) ($options['randomn_id'] ?? ''));
        $category = trim((string) ($options['category'] ?? ''));
        $vitrinaOnly = !empty($options['vitrina_only']);
        $missingImageOk = !empty($options['include_without_image']);

        $where = ["status <> '0'"];
        $params = [];

        if ($randomnId !== '') {
            $where[] = 'randomn_id = :rid';
            $params['rid'] = $randomnId;
        }
        if ($category !== '') {
            $where[] = 'pCategory = :cat';
            $params['cat'] = $category;
        }
        if ($vitrinaOnly) {
            $where[] = 'pVitrina = 1';
        }

        $sql = 'SELECT id, randomn_id, pName, pCode, pBrand, pCategory, pSubcategory, pNote, pImages, pImageSource, pVitrina
                FROM produse WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = $this->normalizeProductRow($row);
            if (!$missingImageOk && ($item['image_url'] ?? '') === '') {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<string, mixed>
     */
    public function buildManifest(array $products, array $meta = []): array
    {
        return [
            'schema' => 'product_image_audit_v1',
            'created_at' => date('c'),
            'project' => 'besoiupieseauto.ro',
            'agent' => 'image-audit',
            'composer_model' => $this->cursorAuditModel(),
            'meta' => $meta,
            'instructions' => 'Deschide fiecare local_image_path cu Read; compară vizual cu title și description_excerpt.',
            'products' => $products,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function saveManifest(array $manifest, ?string $filename = null): string
    {
        $paths = $this->storagePaths();
        $name = $filename ?: ('batch_' . date('Ymd_His') . '.json');
        $path = $paths['batches'] . '/' . $name;
        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function analyzeProductWithVision(array $product, string $apiKey, string $model = 'gpt-4o-mini'): array
    {
        $imagePayload = $this->buildVisionImagePayload($product);
        if ($imagePayload === null) {
            return $this->resultTemplate($product, [
                'verdict' => 'no_image',
                'match_score' => 0,
                'image_shows' => '',
                'issues' => ['Produs fără imagine validă'],
                'recommendation' => 'replace',
                'summary_ro' => 'Lipsește imaginea — nu se poate verifica.',
            ]);
        }

        $prompt = $this->buildVisionPrompt($product);
        $apiKey = self::normalizeOpenAiKey($apiKey);

        try {
            $client = $this->http ?? new Client(['timeout' => 90]);
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.2,
                    'max_tokens' => 800,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Ești auditor imagini pentru magazin piese auto din România. Răspunde DOAR JSON valid, fără markdown.',
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                $imagePayload,
                            ],
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);
            $content = (string) ($body['choices'][0]['message']['content'] ?? '{}');
            $parsed = json_decode($content, true);

            if (!is_array($parsed)) {
                throw new \RuntimeException('Răspuns AI invalid (non-JSON).');
            }

            return $this->resultTemplate($product, [
                'verdict' => (string) ($parsed['verdict'] ?? 'uncertain'),
                'match_score' => (int) ($parsed['match_score'] ?? 0),
                'image_shows' => (string) ($parsed['image_shows'] ?? ''),
                'issues' => is_array($parsed['issues'] ?? null) ? array_values($parsed['issues']) : [],
                'recommendation' => (string) ($parsed['recommendation'] ?? 'review'),
                'summary_ro' => (string) ($parsed['summary_ro'] ?? ''),
                'model' => $model,
            ]);
        } catch (RequestException $e) {
            $fatal = self::formatOpenAiClientError($e);
            if (($e->getResponse()?->getStatusCode() ?? 0) === 401
                || str_contains($fatal, '401')
                || str_contains($fatal, 'Cheie OpenAI respinsă')) {
                throw new RuntimeException($fatal);
            }

            return $this->resultTemplate($product, [
                'verdict' => 'error',
                'match_score' => 0,
                'image_shows' => '',
                'issues' => [$fatal],
                'recommendation' => 'review',
                'summary_ro' => $fatal,
            ]);
        } catch (GuzzleException $e) {
            return $this->resultTemplate($product, [
                'verdict' => 'error',
                'match_score' => 0,
                'image_shows' => '',
                'issues' => ['Conexiune OpenAI: ' . $e->getMessage()],
                'recommendation' => 'review',
                'summary_ro' => 'Eroare rețea la analiza automată.',
            ]);
        } catch (Throwable $e) {
            return $this->resultTemplate($product, [
                'verdict' => 'error',
                'match_score' => 0,
                'image_shows' => '',
                'issues' => [$e->getMessage()],
                'recommendation' => 'review',
                'summary_ro' => 'Eroare la analiza automată.',
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $results
     */
    public function writeMarkdownReport(array $results, array $meta = []): string
    {
        $paths = $this->storagePaths();
        $path = $paths['reports'] . '/report_' . date('Ymd_His') . '.md';

        $lines = [
            '# Raport audit imagini produse',
            '',
            '- **Generat:** ' . date('Y-m-d H:i:s'),
            '- **Agent:** image-audit',
            '- **Produse analizate:** ' . count($results),
            '',
        ];

        if ($meta !== []) {
            $lines[] = '## Parametri rulare';
            foreach ($meta as $k => $v) {
                $lines[] = '- **' . $k . ':** ' . (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
            }
            $lines[] = '';
        }

        $match = 0;
        $mismatch = 0;
        foreach ($results as $r) {
            $v = (string) ($r['verdict'] ?? '');
            if ($v === 'match') {
                ++$match;
            } elseif (in_array($v, ['mismatch', 'partial', 'no_image'], true)) {
                ++$mismatch;
            }
        }

        $lines[] = '## Rezumat';
        $lines[] = '- Potrivire: **' . $match . '**';
        $lines[] = '- Probleme / incert: **' . $mismatch . '**';
        $lines[] = '';

        foreach ($results as $i => $r) {
            $n = $i + 1;
            $score = (int) ($r['match_score'] ?? 0);
            $lines[] = '## ' . $n . '. ' . ($r['title'] ?? 'Produs');
            $lines[] = '';
            $lines[] = '| Câmp | Valoare |';
            $lines[] = '|------|---------|';
            $lines[] = '| ID | `' . ($r['randomn_id'] ?? '') . '` |';
            $lines[] = '| Cod | `' . ($r['code'] ?? '') . '` |';
            $lines[] = '| Categorie | ' . ($r['category'] ?? '') . ' |';
            $lines[] = '| Scor potrivire | **' . $score . '/100** |';
            $lines[] = '| Verdict | **' . ($r['verdict'] ?? '') . '** |';
            $lines[] = '| Recomandare | ' . ($r['recommendation'] ?? '') . ' |';
            $lines[] = '| Imagine | ' . ($r['image_url'] ?? '') . ' |';
            $lines[] = '';
            $lines[] = '**Ce arată imaginea:** ' . ($r['image_shows'] ?? '-');
            $lines[] = '';
            $lines[] = '**Rezumat:** ' . ($r['summary_ro'] ?? '-');
            $lines[] = '';
            $issues = $r['issues'] ?? [];
            if (is_array($issues) && $issues !== []) {
                $lines[] = '**Probleme:**';
                foreach ($issues as $issue) {
                    $lines[] = '- ' . (string) $issue;
                }
                $lines[] = '';
            }
        }

        file_put_contents($path, implode("\n", $lines));

        return $path;
    }

    /** @param array<string, mixed> $row */
    private function normalizeProductRow(array $row): array
    {
        $imageUrl = $this->extractFirstImageUrl((string) ($row['pImages'] ?? '[]'));
        $resolved = $this->resolveImagePaths($imageUrl);
        $note = (string) ($row['pNote'] ?? '');
        $plainNote = trim(strip_tags($note));
        if (mb_strlen($plainNote, 'UTF-8') > 400) {
            $plainNote = mb_substr($plainNote, 0, 400, 'UTF-8') . '…';
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'randomn_id' => (string) ($row['randomn_id'] ?? ''),
            'title' => (string) ($row['pName'] ?? ''),
            'code' => (string) ($row['pCode'] ?? ''),
            'brand' => (string) ($row['pBrand'] ?? ''),
            'category' => (string) ($row['pCategory'] ?? ''),
            'subcategory' => (string) ($row['pSubcategory'] ?? ''),
            'description_excerpt' => $plainNote,
            'image_url' => $imageUrl,
            'image_source' => (string) ($row['pImageSource'] ?? ''),
            'local_image_path' => $resolved['local_path'] ?? '',
            'image_readable_by_agent' => !empty($resolved['local_path']) && is_file((string) $resolved['local_path']),
            'vitrina' => (int) ($row['pVitrina'] ?? 0) === 1,
        ];
    }

    private function extractFirstImageUrl(string $json): string
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return '';
        }
        foreach ($decoded as $url) {
            $url = trim((string) $url);
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * @return array{local_path?:string,public_url?:string}
     */
    private function resolveImagePaths(string $imageUrl): array
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return [];
        }

        if (preg_match('#^https?://#i', $imageUrl)) {
            return ['public_url' => $imageUrl];
        }

        $relative = ltrim($imageUrl, '/');
        $local = $this->projectRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_file($local)) {
            return ['local_path' => $local, 'public_url' => '/' . $relative];
        }

        return ['public_url' => '/' . $relative];
    }

    /** @param array<string, mixed> $product */
    private function buildVisionPrompt(array $product): string
    {
        $rulesExtra = '';
        $pipelinePath = dirname(__DIR__, 3) . '/system/image_search_pipeline.php';
        if (is_file($pipelinePath)) {
            require_once $pipelinePath;
            if (\function_exists('besoiu_scraper_image_ai_rules')) {
                $aiCfg = \besoiu_scraper_image_ai_rules();
                $rulesExtra = trim((string) ($aiCfg['prompt_extra'] ?? ''));
            }
        }

        $sourceId = trim((string) ($product['image_source'] ?? $product['pImageSource'] ?? ''));
        if ($sourceId !== '') {
            $schemaPath = dirname(__DIR__, 3) . '/lib/Scraper/ScraperIntegrationSchema.php';
            $storePath = dirname(__DIR__, 3) . '/lib/Scraper/ScraperSourceStore.php';
            if (is_file($schemaPath) && is_file($storePath)) {
                require_once $schemaPath;
                require_once $storePath;
                try {
                    $srcCfg = \ScraperSourceStore::load($sourceId);
                    $srcAi = is_array($srcCfg['integration']['image_ai'] ?? null) ? $srcCfg['integration']['image_ai'] : [];
                    if (!empty($srcAi['enabled'])) {
                        $globalAi = \function_exists('besoiu_scraper_image_ai_rules') ? \besoiu_scraper_image_ai_rules() : [];
                        $rulesExtra .= "\n" . \ScraperIntegrationSchema::buildImageAiPromptExtra($globalAi, $srcAi, $sourceId);
                    }
                } catch (Throwable) {
                    // sursă necunoscută — reguli globale doar
                }
            }
        }

        return implode("\n", array_filter([
            'Analizează imaginea produsului auto și spune dacă corespunde titlului și descrierii.',
            '',
            'Date produs:',
            '- Titlu: ' . ($product['title'] ?? ''),
            '- Cod: ' . ($product['code'] ?? ''),
            '- Brand: ' . ($product['brand'] ?? ''),
            '- Categorie: ' . ($product['category'] ?? ''),
            '- Subcategorie: ' . ($product['subcategory'] ?? ''),
            '- Descriere (extras): ' . ($product['description_excerpt'] ?? ''),
            '',
            'Răspunde JSON cu cheile:',
            '{"verdict":"match|partial|mismatch|uncertain","match_score":0-100,"image_shows":"ce vezi în imagine","issues":["..."],"recommendation":"keep|replace|review","summary_ro":"explicație scurtă în română"}',
            '',
            'Reguli:',
            '- match = imaginea e clar piesa din titlu (ex: disc frână la «disc frână»)',
            '- partial = aceeași familie dar ambiguu (ex: filtru ulei vs filtru aer)',
            '- mismatch = altceva (bec la ulei, logo, ambalaj generic greșit, mașină întreagă)',
            '- Pentru ulei/lichide: verifică ambalaj, volum, brand vizibil',
            $rulesExtra !== '' ? "\nReguli scraper / operator:\n" . $rulesExtra : '',
        ]));
    }

    /**
     * @param array<string, mixed> $product
     * @return array{type:string,image_url?:array<string,string>}|null
     */
    private function buildVisionImagePayload(array $product): ?array
    {
        $local = trim((string) ($product['local_image_path'] ?? ''));
        if ($local !== '' && is_file($local)) {
            return $this->visionPayloadFromLocalFile($local);
        }

        $url = trim((string) ($product['image_url'] ?? ''));
        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $url,
                    'detail' => 'low',
                ],
            ];
        }

        $relative = ltrim($url, '/');
        $diskPath = $this->projectRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_file($diskPath)) {
            return $this->visionPayloadFromLocalFile($diskPath);
        }

        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: ''), '/');
        if ($appUrl !== '') {
            return [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $appUrl . '/' . $relative,
                    'detail' => 'low',
                ],
            ];
        }

        return null;
    }

    private function visionPayloadFromLocalFile(string $local): array
    {
        $mime = mime_content_type($local) ?: 'image/jpeg';
        $data = base64_encode((string) file_get_contents($local));

        return [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:' . $mime . ';base64,' . $data,
                'detail' => 'low',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    private function resultTemplate(array $product, array $analysis): array
    {
        return array_merge([
            'randomn_id' => $product['randomn_id'] ?? '',
            'title' => $product['title'] ?? '',
            'code' => $product['code'] ?? '',
            'category' => $product['category'] ?? '',
            'image_url' => $product['image_url'] ?? '',
            'analyzed_at' => date('c'),
        ], $analysis);
    }
}
