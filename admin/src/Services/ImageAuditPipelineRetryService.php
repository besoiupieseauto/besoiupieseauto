<?php

declare(strict_types=1);

namespace Evasystem\Services;

use PDO;
use Throwable;

/**
 * Pipeline imagini Plan 1→2→3 — aceeași logică ca Scraper → Test pipeline.
 */
final class ImageAuditPipelineRetryService
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ProductImageAuditService $auditService,
        private readonly ImageAuditImportBridge $auditBridge,
    ) {
    }

    /**
     * @param list<string>              $publicIds randomn_id
     * @param array<string, mixed>      $options force, dry_run (fără UPDATE BD)
     * @return array<string, mixed>
     */
    public function retryForProductIds(PDO $pdo, array $publicIds, array $options = []): array
    {
        $publicIds = array_values(array_unique(array_filter(array_map('strval', $publicIds))));
        if ($publicIds === []) {
            return ['ok' => false, 'message' => 'Niciun produs selectat.', 'results' => [], 'plans' => []];
        }

        $this->bootstrapPipeline();

        $force = !empty($options['force']);
        $dryRun = !empty($options['dry_run']);

        $aiRules = $this->loadAiRules();

        if (!$force && empty($aiRules['auto_retry_on_mismatch'])) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Auto-retry dezactivat — folosește «Caută imagine Plan 1→3» sau activează în Scraper → Pipeline.',
                'results' => [],
                'plans' => $this->loadImagePlans(),
            ];
        }

        $minKeep = max(0, min(100, (int) ($aiRules['min_score_keep'] ?? 70)));
        $plans = $this->loadImagePlans();
        if ($plans === []) {
            return [
                'ok' => false,
                'message' => 'Niciun plan activ — configurează Scraper → Pipeline imagini (Autodoc / TecDoc).',
                'results' => [],
                'plans' => [],
            ];
        }

        $out = [];
        $replaced = 0;
        $missed = 0;
        $skipped = 0;
        $lastTried = [];
        $lastHit = null;

        foreach ($publicIds as $rid) {
            $audit = $this->auditBridge->loadAudit($rid);
            if (!$force && !$this->shouldRetry($audit, $aiRules, $minKeep)) {
                ++$skipped;
                $out[] = [
                    'randomn_id' => $rid,
                    'status' => 'skipped',
                    'message' => $audit === null ? 'Fără verdict audit.' : 'Scor OK sau nu necesită înlocuire.',
                    'audit_verdict' => (string) ($audit['verdict'] ?? ''),
                    'audit_score' => (int) ($audit['match_score'] ?? 0),
                ];
                continue;
            }

            $row = $this->loadProductDbRow($pdo, $rid);
            if ($row === null) {
                $out[] = ['randomn_id' => $rid, 'status' => 'error', 'message' => 'Produs negăsit în BD.'];
                continue;
            }

            $resolved = $this->resolveProductImage($row, $force);
            $hit = is_array($resolved['hit'] ?? null) ? $resolved['hit'] : null;
            $product = is_array($resolved['product'] ?? null) ? $resolved['product'] : $row;
            $tried = is_array($resolved['tried'] ?? null) ? $resolved['tried'] : [];
            $lastTried = $tried;
            $imageUrl = trim((string) ($hit['url'] ?? ''));

            if ($imageUrl === '') {
                ++$missed;
                $tryHints = [];
                foreach ($tried as $step) {
                    if (!is_array($step)) {
                        continue;
                    }
                    $src = trim((string) ($step['source_id'] ?? ''));
                    $msg = trim((string) ($step['message'] ?? ''));
                    if ($src !== '' && $msg !== '') {
                        $tryHints[] = $src . ': ' . $msg;
                    }
                }
                $out[] = [
                    'randomn_id' => $rid,
                    'status' => 'miss',
                    'message' => $tryHints !== []
                        ? ('Niciun plan nu a salvat imagine. ' . implode(' · ', array_slice($tryHints, 0, 3)))
                        : 'Niciun plan nu a găsit imagine (verifică Scrape.do / planuri active).',
                    'tried' => $tried,
                    'hit' => null,
                    'audit_verdict' => (string) ($audit['verdict'] ?? ''),
                ];
                continue;
            }

            $lastHit = $hit;

            $savedUrl = '';
            if (!$dryRun) {
                $savedUrl = $this->persistProductImage($pdo, $rid, $row, $hit);
                if ($savedUrl === '') {
                    $out[] = [
                        'randomn_id' => $rid,
                        'status' => 'error',
                        'message' => 'Imagine găsită dar descărcarea/salvarea a eșuat.',
                        'new_image' => $imageUrl,
                        'tried' => $tried,
                        'hit' => $hit,
                    ];
                    continue;
                }
                $this->annotateAuditWithPipeline($rid, $hit, $tried, $audit, $savedUrl);
            } else {
                $savedUrl = $imageUrl;
            }

            ++$replaced;
            $hitOut = is_array($hit) ? $hit : [];
            $hitOut['url'] = $savedUrl;
            $hitOut['url_remote'] = $imageUrl;
            $lastHit = $hitOut;

            $out[] = [
                'randomn_id' => $rid,
                'status' => $dryRun ? 'found' : 'replaced',
                'message' => $dryRun ? 'Imagine găsită (previzualizare).' : 'Imagine salvată în produs.',
                'new_image' => $savedUrl,
                'new_image_remote' => $imageUrl,
                'source' => (string) ($hit['source'] ?? ''),
                'title' => (string) ($hit['title'] ?? ($product['pName'] ?? '')),
                'url_product' => (string) ($hit['url_product'] ?? ''),
                'tried' => $tried,
                'hit' => $hitOut,
                'audit_verdict' => (string) ($audit['verdict'] ?? ''),
                'min_score_keep' => $minKeep,
            ];
        }

        $elapsedHint = count($publicIds) === 1 && $lastHit !== null
            ? 'Imagine găsită — sursă: ' . (string) ($lastHit['source'] ?? '')
            : '';

        $msg = $replaced > 0
            ? ($dryRun
                ? "Gata — imagine găsită pentru {$replaced} produs(e)."
                : "Gata — {$replaced} imagini înlocuite (Plan 1→2→3).")
            : ($missed > 0
                ? 'Gata — niciun plan nu a găsit imagine.'
                : ($skipped > 0
                    ? 'Nimic de înlocuit după audit (produse sărite).'
                    : 'Pipeline finalizat.'));

        if ($elapsedHint !== '' && $replaced > 0) {
            $msg = $elapsedHint;
        }

        return [
            'ok' => true,
            'message' => $msg,
            'replaced' => $dryRun ? 0 : $replaced,
            'found' => $replaced,
            'missed' => $missed,
            'skipped' => $skipped,
            'min_score_keep' => $minKeep,
            'plans' => $plans,
            'tried' => $lastTried,
            'hit' => $lastHit,
            'results' => $out,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function loadImagePlans(): array
    {
        $this->bootstrapPipeline();
        $storePath = $this->projectRoot . '/lib/Scraper/ScraperIntegrationStore.php';
        if (!is_file($storePath)) {
            return [];
        }
        require_once $storePath;

        $cfg = \ScraperIntegrationStore::load();
        $plans = is_array($cfg['image_plans'] ?? null) ? $cfg['image_plans'] : [];

        return array_values(array_filter($plans, static fn ($p) => is_array($p) && !empty($p['enabled'])));
    }

    /**
     * @param array<string, mixed> $row
     * @return array{product: array<string, mixed>, hit: array<string, mixed>|null, tried: list<array<string, mixed>>}
     */
    public function resolveProductImage(array $row, bool $force = true): array
    {
        $this->bootstrapPipeline();

        $cats = [];
        foreach (['pCategory', 'pSubcategory'] as $col) {
            $v = strtolower(trim((string) ($row[$col] ?? '')));
            if ($v !== '') {
                $cats[] = $v;
            }
        }

        // Același motor ca Scraper → Test pipeline (test_mode = imagini din listă Autodoc, fără pagină detaliu).
        return \ImageSearchService::resolve($row, [
            'categories' => $cats,
            'force' => $force,
            'test_mode' => true,
            'skip_vision' => true,
            'listing_fallback' => !$this->productHasRealImage($row),
            'pipeline_test_query' => trim((string) ($row['pName'] ?? '')),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function productHasRealImage(array $row): bool
    {
        $decoded = json_decode((string) ($row['pImages'] ?? '[]'), true);
        if (!is_array($decoded)) {
            return trim((string) ($row['pImages'] ?? '')) !== '';
        }
        foreach ($decoded as $url) {
            if (trim((string) $url) !== '') {
                return true;
            }
        }

        return false;
    }

    private function bootstrapPipeline(): void
    {
        $pipeline = $this->projectRoot . '/system/image_search_pipeline.php';
        if (is_file($pipeline)) {
            require_once $pipeline;
        }

        $service = $this->projectRoot . '/lib/Scraper/ImageSearchService.php';
        if (is_file($service)) {
            require_once $service;
        }
    }

    /** @return array<string, mixed> */
    private function loadAiRules(): array
    {
        if (\function_exists('besoiu_scraper_image_ai_rules')) {
            return \besoiu_scraper_image_ai_rules();
        }
        if (\function_exists('besoiu_image_audit_config')) {
            return \besoiu_image_audit_config();
        }

        return [
            'enabled' => true,
            'auto_retry_on_mismatch' => true,
            'min_score_keep' => 70,
            'verdicts_retry' => ['mismatch', 'error', 'no_image'],
        ];
    }

    /** @param array<string, mixed>|null $audit @param array<string, mixed> $aiRules */
    private function shouldRetry(?array $audit, array $aiRules, int $minKeep): bool
    {
        if ($audit === null) {
            return false;
        }

        $verdict = strtolower((string) ($audit['verdict'] ?? ''));
        $score = (int) ($audit['match_score'] ?? 0);
        $reco = strtolower((string) ($audit['recommendation'] ?? ''));
        $retryVerdicts = is_array($aiRules['verdicts_retry'] ?? null)
            ? array_map('strval', $aiRules['verdicts_retry'])
            : ['mismatch', 'error', 'no_image'];

        if ($reco === 'replace') {
            return true;
        }
        if (in_array($verdict, $retryVerdicts, true)) {
            return true;
        }
        if ($verdict === 'partial' && $score < $minKeep) {
            return true;
        }
        if ($score > 0 && $score < $minKeep) {
            return true;
        }

        return $this->auditBridge->needsImageReplace($audit);
    }

    /** @return array<string, mixed>|null */
    private function loadProductDbRow(PDO $pdo, string $publicId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT id, randomn_id, pName, pCode, pBrand, pOem, pCategory, pSubcategory, pNote, pImages, pImageSource
             FROM produse WHERE status <> '0' AND randomn_id = ? LIMIT 1"
        );
        $stmt->execute([$publicId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Descarcă imaginea pe server și actualizează produsul. Returnează calea publică salvată.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $hit
     */
    private function persistProductImage(PDO $pdo, string $publicId, array $row, array $hit): string
    {
        $remoteUrl = trim((string) ($hit['url'] ?? ''));
        if ($remoteUrl === '') {
            return '';
        }

        $tecPath = $this->projectRoot . '/system/tecdoc_stock.php';
        if (is_file($tecPath)) {
            require_once $tecPath;
        }

        $code = trim((string) ($row['pCode'] ?? ''));
        if ($code === '') {
            $code = preg_replace('/[^A-Za-z0-9_-]/', '_', $publicId);
        }

        $storeUrl = $remoteUrl;
        if (function_exists('tecdoc_download_image')) {
            $local = tecdoc_download_image($remoteUrl, $code);
            if ($local !== '') {
                $storeUrl = $local;
            }
        }

        $product = $row;
        $hitLocal = array_merge($hit, [
            'url' => $storeUrl,
            'remote_url' => $remoteUrl,
            'source' => (string) ($hit['source'] ?? 'pipeline'),
        ]);

        if (function_exists('import_apply_image_lookup_result')) {
            $product = import_apply_image_lookup_result($product, $hitLocal);
            $decoded = json_decode((string) ($product['pImages'] ?? '[]'), true);
            if (is_array($decoded) && trim((string) ($decoded[0] ?? '')) !== '') {
                $storeUrl = (string) $decoded[0];
            }
        } else {
            $product['pImages'] = json_encode([$storeUrl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $product['pImageSource'] = (string) ($hit['source'] ?? 'pipeline');
        }

        $pImages = (string) ($product['pImages'] ?? json_encode([$storeUrl], JSON_UNESCAPED_UNICODE));
        $pSource = (string) ($product['pImageSource'] ?? (string) ($hit['source'] ?? 'pipeline'));

        $stmt = $pdo->prepare(
            'UPDATE produse SET pImages = ?, pImageSource = ? WHERE randomn_id = ? AND status <> \'0\''
        );
        if (!$stmt->execute([$pImages, $pSource, $publicId])) {
            return '';
        }

        return $storeUrl;
    }

    /**
     * @param array<string, mixed> $hit
     * @param list<array<string, mixed>> $tried
     * @param array<string, mixed>|null $audit
     */
    private function annotateAuditWithPipeline(string $publicId, array $hit, array $tried, ?array $audit, string $savedUrl): void
    {
        if ($audit === null) {
            return;
        }

        $source = (string) ($hit['source'] ?? 'pipeline');
        $audit['verdict'] = 'match';
        $audit['match_score'] = max((int) ($audit['match_score'] ?? 0), 90);
        $audit['recommendation'] = 'keep';
        $audit['summary_ro'] = 'Imagine înlocuită și salvată local din ' . $source . '.';
        $audit['image_url'] = $savedUrl;
        $audit['pipeline_retry'] = [
            'at' => date('c'),
            'new_image' => $savedUrl,
            'remote_url' => (string) ($hit['url_remote'] ?? $hit['url'] ?? ''),
            'source' => $source,
            'plans_tried' => $tried,
            'note_ro' => 'Imagine salvată în produs (Plan 1→2→3).',
        ];
        $audit['pipeline_replaced'] = true;

        try {
            $this->auditService->saveProductAuditResult($audit);
        } catch (Throwable) {
            // non-blocking
        }
    }
}
