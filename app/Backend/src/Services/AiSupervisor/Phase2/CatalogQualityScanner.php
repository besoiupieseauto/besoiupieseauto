<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Phase2;

use Config\Database;
use Besoiu\Services\AiSupervisor\Support\AiSupervisorBootstrap;
use Besoiu\Services\AiSupervisor\Store\AiSupervisorStore;
use PDO;
use Throwable;

/**
 * Faza 2 — audit calitate catalog: imagini, text, categorii, vizibilitate site.
 */
final class CatalogQualityScanner
{
    public function __construct(
        private ?AiSupervisorStore $store = null,
        private int $sampleLimit = 500,
    ) {
        $this->store = $store ?? new AiSupervisorStore();
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        AiSupervisorBootstrap::ensureDatabase();
        $started = microtime(true);
        $issues = [];
        $counts = [
            'active_total' => 0,
            'no_image' => 0,
            'no_oem' => 0,
            'short_title' => 0,
            'no_category' => 0,
            'duplicate_oem' => 0,
            'not_on_vitrina' => 0,
            'broken_image_path' => 0,
            'pending_import' => 0,
        ];
        $samples = [];

        try {
            $pdo = Database::getDB();
            $row = $pdo->query(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status IS NULL OR status <> '0' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN (status IS NULL OR status <> '0')
                        AND (pImages IS NULL OR TRIM(pImages) = '' OR pImages IN ('[]', 'null')) THEN 1 ELSE 0 END) AS no_image,
                    SUM(CASE WHEN (status IS NULL OR status <> '0')
                        AND (pOem IS NULL OR TRIM(pOem) = '')
                        AND (pCode IS NULL OR TRIM(pCode) = '') THEN 1 ELSE 0 END) AS no_oem
                 FROM produse"
            )->fetch(PDO::FETCH_ASSOC) ?: [];

            $counts['active_total'] = (int) ($row['active'] ?? 0);
            $counts['no_image'] = (int) ($row['no_image'] ?? 0);
            $counts['no_oem'] = (int) ($row['no_oem'] ?? 0);

            $counts['short_title'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM produse
                 WHERE (status IS NULL OR status <> '0')
                   AND CHAR_LENGTH(COALESCE(pName, '')) < 8"
            )->fetchColumn();

            $counts['no_category'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM produse
                 WHERE (status IS NULL OR status <> '0')
                   AND (pCategory IS NULL OR TRIM(pCategory) = '' OR pCategory = '0')"
            )->fetchColumn();

            $counts['not_on_vitrina'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM produse
                 WHERE (status IS NULL OR status <> '0')
                   AND COALESCE(pVitrina, 0) = 0"
            )->fetchColumn();

            $dupRows = $pdo->query(
                "SELECT UPPER(TRIM(pCode)) AS code, COUNT(*) AS cnt
                 FROM produse
                 WHERE pCode IS NOT NULL AND TRIM(pCode) <> ''
                 GROUP BY UPPER(TRIM(pCode))
                 HAVING cnt > 1
                 ORDER BY cnt DESC
                 LIMIT 20"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $counts['duplicate_oem'] = count($dupRows);

            try {
                $counts['pending_import'] = (int) $pdo->query(
                    "SELECT COUNT(*) FROM import_produse WHERE status = 'pending'"
                )->fetchColumn();
            } catch (Throwable) {
                $counts['pending_import'] = 0;
            }

            $samples = $this->loadIssueSamples($pdo);
            $issues = $this->buildIssuesList($counts, $dupRows, $samples);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'http' => 500,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }

        $score = $this->computeScore($counts);
        $report = [
            'ok' => true,
            'http' => 200,
            'generated_at' => date('c'),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'score' => $score,
            'score_label' => $score >= 85 ? 'excelent' : ($score >= 65 ? 'ok' : 'slab'),
            'counts' => $counts,
            'issues' => $issues,
            'duplicate_oem_top' => $dupRows,
            'samples' => $samples,
            'recommendations' => $this->recommendations($counts),
        ];

        $this->store->write('catalog_audit', $report);
        $this->recordSupervisorEvent('catalog_audit', $score, count($issues));

        return $report;
    }

    /** @return array<string, mixed> */
    public function latest(): array
    {
        return $this->store->read('catalog_audit');
    }

    /** @param array<string, int> $counts @param list<array<string, mixed>> $dupRows @param list<array<string, mixed>> $samples @return list<array<string, mixed>> */
    private function buildIssuesList(array $counts, array $dupRows, array $samples): array
    {
        $issues = [];
        $map = [
            'no_image' => ['level' => 'critical', 'title' => 'Produse active fără imagine'],
            'no_oem' => ['level' => 'warning', 'title' => 'Produse fără cod OEM/pCode'],
            'short_title' => ['level' => 'warning', 'title' => 'Titluri prea scurte (< 8 car.)'],
            'no_category' => ['level' => 'warning', 'title' => 'Produse fără categorie'],
            'not_on_vitrina' => ['level' => 'info', 'title' => 'Active dar fără vitrină'],
            'duplicate_oem' => ['level' => 'critical', 'title' => 'Coduri OEM duplicate'],
            'pending_import' => ['level' => 'info', 'title' => 'Rânduri în coadă import pending'],
        ];
        foreach ($map as $key => $meta) {
            $n = (int) ($counts[$key] ?? 0);
            if ($n <= 0) {
                continue;
            }
            $issues[] = [
                'code' => 'catalog_' . $key,
                'level' => $meta['level'],
                'title' => $meta['title'],
                'count' => $n,
                'url' => '/admin/product',
            ];
        }
        if ($dupRows !== []) {
            $issues[] = [
                'code' => 'catalog_duplicate_detail',
                'level' => 'critical',
                'title' => 'Top duplicate OEM',
                'detail' => implode(', ', array_map(
                    static fn (array $r): string => ($r['code'] ?? '?') . '×' . ($r['cnt'] ?? 0),
                    array_slice($dupRows, 0, 5)
                )),
                'url' => '/admin/product',
            ];
        }
        if ($samples !== []) {
            $broken = count(array_filter($samples, static fn (array $s): bool => !empty($s['image_missing_file'])));
            if ($broken > 0) {
                $issues[] = [
                    'code' => 'catalog_broken_image_file',
                    'level' => 'warning',
                    'title' => 'Fișier imagine lipsă pe disc',
                    'count' => $broken,
                    'url' => '/admin/scraper',
                ];
            }
        }

        return $issues;
    }

    /** @return list<array<string, mixed>> */
    private function loadIssueSamples(PDO $pdo): array
    {
        $limit = max(10, min(200, $this->sampleLimit));
        $sql = "SELECT randomn_id, pCode, pName, pImages, pImageSource, pCategory, pVitrina, status
                FROM produse
                WHERE (status IS NULL OR status <> '0')
                  AND (
                    pImages IS NULL OR TRIM(pImages) = '' OR pImages IN ('[]', 'null')
                    OR ((pOem IS NULL OR TRIM(pOem) = '') AND (pCode IS NULL OR TRIM(pCode) = ''))
                    OR CHAR_LENGTH(COALESCE(pName, '')) < 8
                    OR pCategory IS NULL OR TRIM(pCategory) = '' OR pCategory = '0'
                  )
                ORDER BY id DESC
                LIMIT {$limit}";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $root = dirname(__DIR__, 5);
        $out = [];
        foreach ($rows as $row) {
            $imgs = trim((string) ($row['pImages'] ?? ''));
            $missingFile = $imgs === '' || $imgs === '[]';
            if (!$missingFile && $imgs !== '') {
                $decoded = json_decode($imgs, true);
                if (is_array($decoded) && $decoded !== []) {
                    $first = (string) ($decoded[0] ?? '');
                    if ($first !== '' && !str_starts_with($first, 'http')) {
                        $candidates = [
                            $root . '/public/images/produse/' . ltrim($first, '/'),
                            $root . '/images/produse/' . ltrim($first, '/'),
                        ];
                        $missingFile = true;
                        foreach ($candidates as $path) {
                            if (is_file($path)) {
                                $missingFile = false;
                                break;
                            }
                        }
                    } else {
                        $missingFile = false;
                    }
                }
            }
            $out[] = [
                'randomn_id' => (int) ($row['randomn_id'] ?? 0),
                'pCode' => (string) ($row['pCode'] ?? ''),
                'pName' => mb_substr((string) ($row['pName'] ?? ''), 0, 80),
                'pCategory' => (string) ($row['pCategory'] ?? ''),
                'pVitrina' => (int) ($row['pVitrina'] ?? 0),
                'pImageSource' => (string) ($row['pImageSource'] ?? ''),
                'image_missing_file' => $missingFile,
            ];
        }

        return $out;
    }

    /** @param array<string, int> $counts */
    private function computeScore(array $counts): int
    {
        $active = max(1, (int) ($counts['active_total'] ?? 1));
        $penalty = 0;
        $penalty += min(40, ((int) ($counts['no_image'] ?? 0) / $active) * 100);
        $penalty += min(20, ((int) ($counts['no_oem'] ?? 0) / $active) * 50);
        $penalty += min(15, ((int) ($counts['no_category'] ?? 0) / $active) * 40);
        $penalty += min(10, (int) ($counts['duplicate_oem'] ?? 0) * 2);

        return max(0, min(100, (int) round(100 - $penalty)));
    }

    /** @param array<string, int> $counts @return list<string> */
    private function recommendations(array $counts): array
    {
        $rec = [];
        if (($counts['no_image'] ?? 0) > 0) {
            $rec[] = 'Rulează pipeline imagini (Scraper / cron image_pipeline_retry) pentru produse fără imagine.';
        }
        if (($counts['no_oem'] ?? 0) > 0) {
            $rec[] = 'Completează pCode/OEM — afectează căutarea și TecDoc.';
        }
        if (($counts['duplicate_oem'] ?? 0) > 0) {
            $rec[] = 'Unifică duplicatele OEM înainte de export marketplace.';
        }
        if (($counts['pending_import'] ?? 0) > 0) {
            $rec[] = 'Publică sau curăță rândurile pending din Import Review.';
        }
        if (($counts['not_on_vitrina'] ?? 0) > 0 && ($counts['active_total'] ?? 0) > 0) {
            $rec[] = 'Verifică produse active care nu sunt pe vitrină — poate fi intenționat (doar catalog).';
        }

        return $rec;
    }

    private function recordSupervisorEvent(string $job, int $score, int $issueCount): void
    {
        $helper = dirname(__DIR__, 5) . '/system/ai_action_events.php';
        if (!is_file($helper)) {
            return;
        }
        require_once $helper;
        if (function_exists('ai_action_event_record')) {
            ai_action_event_record('cron', 'supervisor_' . $job, 'score=' . $score, [
                'score' => $score,
                'issues' => $issueCount,
                'phase' => 2,
            ]);
        }
    }
}
