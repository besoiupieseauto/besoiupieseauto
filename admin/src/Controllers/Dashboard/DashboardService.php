<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Dashboard;

use Config\Database;
use Evasystem\Core\Bots\BotsModel;
use Evasystem\Core\Comenzi\ComenziModel;
use Evasystem\Core\Messages\MessagesModel;
use PDO;

/**
 * Agregă date reale pentru dashboard admin.
 */
final class DashboardService
{
    public function __construct(
        private readonly ComenziModel $comenziModel = new ComenziModel(),
        private readonly MessagesModel $messagesModel = new MessagesModel(),
        private readonly BotsModel $botsModel = new BotsModel(),
    ) {
    }

    /** @return array<string, mixed> */
    public function overview(bool $forceRefresh = false): array
    {
        $pdo = Database::getDB();
        $products = $this->productStats($pdo);
        $search = $this->searchLogStats();
        $import = $this->importStats($pdo);
        $health = $this->systemHealth($forceRefresh);

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'orders' => $this->comenziModel->getDashboardStats(),
            'products' => $products,
            'search_logs' => $search,
            'import' => $import,
            'health' => $health,
            'bots' => $this->botSummaries(),
            'activity' => $this->recentActivity(),
            'red_flags' => $this->buildRedFlags($products, $search, $import, $health),
        ];
    }

    /** @return array<string, int> */
    private function productStats(PDO $pdo): array
    {
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

        $queuePending = (int) $pdo->query(
            "SELECT COUNT(*) FROM import_produse WHERE status = 'pending'"
        )->fetchColumn();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'no_image' => (int) ($row['no_image'] ?? 0),
            'no_oem' => (int) ($row['no_oem'] ?? 0),
            'queue_pending' => $queuePending,
            'oem_index' => $this->productOemIndexStats($pdo),
        ];
    }

    /** @return array{products:int,codes:int} */
    private function productOemIndexStats(PDO $pdo): array
    {
        require_once dirname(__DIR__, 4) . '/system/products_oem.php';
        if (!products_oem_table_exists($pdo)) {
            return ['products' => 0, 'codes' => 0];
        }

        return products_oem_stats($pdo);
    }

    /** @return array<string, mixed> */
    private function searchLogStats(): array
    {
        require_once dirname(__DIR__, 4) . '/system/tecdoc_stock.php';

        if (!search_logs_table_exists(tecdoc_db())) {
            return [
                'available' => false,
                'total' => 0,
                'not_found' => 0,
                'found' => 0,
                'today' => 0,
                'today_not_found' => 0,
                'top_missing' => [],
                'missing_codes_count' => 0,
                'top_oem' => [],
                'daily_trend' => [],
            ];
        }

        $pdo = tecdoc_db();
        $stats = search_logs_stats($pdo);

        return [
            'available' => true,
            'total' => (int) ($stats['total'] ?? 0),
            'not_found' => (int) ($stats['not_found'] ?? 0),
            'found' => (int) ($stats['found'] ?? 0),
            'today' => (int) ($stats['today'] ?? 0),
            'today_not_found' => (int) ($stats['today_not_found'] ?? 0),
            'today_found' => (int) ($stats['today_found'] ?? 0),
            'vin_not_found' => (int) ($stats['vin_not_found'] ?? 0),
            'oem_not_found' => (int) ($stats['oem_not_found'] ?? 0),
            'missing_codes_count' => search_logs_missing_codes_count($pdo),
            'top_missing' => search_logs_top_missing($pdo, 5),
            'top_oem' => search_logs_top_oem($pdo, 10),
            'daily_trend' => search_logs_daily_trend($pdo, 14),
        ];
    }

    /** @return array<string, mixed> */
    private function importStats(PDO $pdo): array
    {
        $cacheFile = dirname(__DIR__, 3) . '/storage/cache/dashboard_import_stats.json';
        if (is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && (int) ($cached['expires'] ?? 0) > time()) {
                return $cached['data'] ?? [];
            }
        }

        $running = 0;
        $failed = 0;
        $blocked = 0;
        $blockedJobId = '';
        $lastJob = null;
        $jobsDir = dirname(__DIR__, 3) . '/storage/imports/jobs';
        $blockedThreshold = time() - 1800;

        foreach (glob($jobsDir . '/*.json') ?: [] as $path) {
            if (str_ends_with($path, '.state.json')) {
                continue;
            }

            $meta = json_decode((string) file_get_contents($path), true);
            if (!is_array($meta)) {
                continue;
            }

            $status = (string) ($meta['status'] ?? '');
            if ($status === 'running') {
                $running++;
                $updatedAt = strtotime((string) ($meta['updated_at'] ?? ''));
                if ($updatedAt > 0 && $updatedAt < $blockedThreshold) {
                    $blocked++;
                    if ($blockedJobId === '') {
                        $blockedJobId = (string) ($meta['job_id'] ?? '');
                    }
                }
            }
            if ($status === 'error') {
                $failed++;
            }

            if ($lastJob === null || strcmp((string) ($meta['updated_at'] ?? ''), (string) ($lastJob['updated_at'] ?? '')) > 0) {
                $lastJob = [
                    'job_id' => (string) ($meta['job_id'] ?? ''),
                    'type' => (string) ($meta['type'] ?? ''),
                    'status' => $status,
                    'message' => (string) ($meta['message'] ?? ''),
                    'error' => $meta['error'] ?? null,
                    'updated_at' => (string) ($meta['updated_at'] ?? ''),
                ];
            }
        }

        $data = [
            'running_jobs' => $running,
            'failed_jobs' => $failed,
            'blocked_jobs' => $blocked,
            'blocked_job_id' => $blockedJobId,
            'last_job' => $lastJob,
            'last_import_at' => $this->lastImportTimestamp($pdo),
        ];

        if (!is_dir(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0755, true);
        }
        file_put_contents($cacheFile, json_encode([
            'expires' => time() + 60,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE));

        return $data;
    }

    private function lastImportTimestamp(PDO $pdo): string
    {
        foreach (['updated_at', 'pMarkupAppliedAt', 'created_at'] as $column) {
            try {
                $stmt = $pdo->query('SELECT MAX(`' . $column . '`) AS last_sync FROM import_produse');
                $value = $stmt ? (string) (($stmt->fetch(PDO::FETCH_ASSOC) ?: [])['last_sync'] ?? '') : '';
                if ($value !== '' && $value !== '0000-00-00 00:00:00') {
                    return $value;
                }
            } catch (\PDOException) {
                continue;
            }
        }

        return '';
    }

    /** @return array<string, mixed> */
    private function systemHealth(bool $forceRefresh = false): array
    {
        require_once dirname(__DIR__, 4) . '/system/tecdoc_stock.php';

        $quotaExceeded = tecdoc_api_is_unavailable();
        $logPath = dirname(__DIR__, 2) . '/storage/logs/rapidapi.log';
        $logExists = is_file($logPath);
        $logMtime = $logExists ? date('Y-m-d H:i:s', (int) filemtime($logPath)) : null;

        $backupDir = dirname(__DIR__, 2) . '/storage/backups';
        $latestBackup = null;
        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/*') ?: [];
            usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
            if ($files !== []) {
                $latestBackup = date('Y-m-d H:i:s', (int) filemtime($files[0]));
            }
        }

        $tecdocIp = tecdoc_probe_ip_status($forceRefresh);

        return [
            'tecdoc_quota_exceeded' => $quotaExceeded,
            'tecdoc_online' => !$quotaExceeded,
            'tecdoc_ip' => $tecdocIp,
            'rapidapi_log_at' => $logMtime,
            'latest_backup_at' => $latestBackup,
            'database_ok' => true,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function botSummaries(): array
    {
        $items = [];
        foreach ($this->botsModel->findAll() as $row) {
            $items[] = [
                'name' => (string) ($row['name'] ?? 'Bot'),
                'channel' => (string) ($row['channel'] ?? ''),
                'token_status' => (string) ($row['token_status'] ?? 'unknown'),
                'last_test_status' => (string) ($row['last_test_status'] ?? ''),
                'last_test_at' => (string) ($row['last_test_at'] ?? ''),
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function recentActivity(): array
    {
        $orders = array_map(static function (array $row): array {
            return [
                'type' => 'order',
                'time' => (string) ($row['created_at'] ?? ''),
                'title' => (string) ($row['client_name'] ?? $row['name'] ?? 'Comandă'),
                'subtitle' => (string) ($row['product_name'] ?? $row['name'] ?? ''),
                'channel' => (string) ($row['channel'] ?? 'website'),
                'status' => (string) ($row['order_status'] ?? ''),
                'amount' => (float) ($row['total_amount'] ?? 0),
                'url' => '/admin/orders',
            ];
        }, $this->comenziModel->findRecent(4));

        $messages = array_map(static function (array $row): array {
            $body = (string) ($row['message_body'] ?? '');
            if (function_exists('mb_strlen') && mb_strlen($body) > 80) {
                $body = mb_substr($body, 0, 80) . '…';
            } elseif (strlen($body) > 80) {
                $body = substr($body, 0, 80) . '…';
            }

            return [
                'type' => 'message',
                'time' => (string) ($row['created_at'] ?? ''),
                'title' => (string) ($row['name'] ?? 'Mesaj'),
                'subtitle' => $body,
                'channel' => (string) ($row['channel'] ?? 'manual'),
                'status' => (string) ($row['bot_status'] ?? $row['message_status'] ?? ''),
                'url' => '/admin/messages',
            ];
        }, array_slice($this->messagesModel->findRecent(4), 0, 4));

        $combined = array_merge($orders, $messages);
        usort($combined, static function (array $a, array $b): int {
            return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
        });

        return [
            'items' => array_slice($combined, 0, 3),
            'orders_count' => count($orders),
            'messages_count' => count($messages),
        ];
    }

    /** @return array<int, array{type:string,id:string,name:string,url:string}> */
    private function brokenIntegrationLinks(PDO $pdo): array
    {
        $items = [];

        try {
            $rows = $pdo->query(
                "SELECT randomn_id, name FROM furnizori
                 WHERE status = 'active' AND LOWER(COALESCE(last_test_status, '')) = 'failed'
                 ORDER BY last_test_at DESC LIMIT 5"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                $id = (string) ($row['randomn_id'] ?? '');
                $items[] = [
                    'type' => 'furnizor',
                    'id' => $id,
                    'name' => (string) ($row['name'] ?? 'Furnizor'),
                    'url' => $id !== '' ? '/admin/profilefurnizori?randomn_id=' . rawurlencode($id) : '/admin/furnizori',
                ];
            }
        } catch (\PDOException) {
        }

        try {
            $rows = $pdo->query(
                "SELECT randomn_id, name, channel FROM bots
                 WHERE LOWER(COALESCE(last_test_status, '')) = 'failed'
                 ORDER BY last_test_at DESC LIMIT 5"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                $id = (string) ($row['randomn_id'] ?? '');
                $items[] = [
                    'type' => 'bot',
                    'id' => $id,
                    'name' => (string) ($row['name'] ?? $row['channel'] ?? 'Bot'),
                    'url' => '/admin/bots',
                ];
            }
        } catch (\PDOException) {
        }

        return $items;
    }

    /** @param array<string, mixed> $products @param array<string, mixed> $search @param array<string, mixed> $import @param array<string, mixed> $health */
    private function buildRedFlags(array $products, array $search, array $import, array $health): array
    {
        $flags = [];
        $pdo = Database::getDB();
        $lastJob = is_array($import['last_job'] ?? null) ? $import['last_job'] : null;

        if (($import['failed_jobs'] ?? 0) > 0) {
            $failedDetail = (int) $import['failed_jobs'] . ' job(uri) import cu eroare.';
            if ($lastJob !== null && ($lastJob['status'] ?? '') === 'error' && ($lastJob['message'] ?? '') !== '') {
                $failedDetail .= ' Ultimul: ' . (string) $lastJob['message'];
            }

            $flags[] = [
                'code' => 'import_failed',
                'level' => 'danger',
                'critical' => true,
                'title' => 'Import eșuat',
                'detail' => $failedDetail,
                'url' => '/admin/import',
                'retry_url' => '/admin/import',
                'job_id' => $lastJob !== null && ($lastJob['status'] ?? '') === 'error'
                    ? (string) ($lastJob['job_id'] ?? '')
                    : '',
            ];
        }

        $tecdocIp = is_array($health['tecdoc_ip'] ?? null) ? $health['tecdoc_ip'] : [];
        if (($tecdocIp['ip_valid'] ?? true) === false) {
            $flags[] = [
                'code' => 'tecdoc_ip_invalid',
                'level' => 'danger',
                'critical' => true,
                'title' => 'IP TecDoc invalid',
                'detail' => (string) ($tecdocIp['operator_message'] ?? 'Schimbă IP-ul pentru ca sistemul să funcționeze')
                    . ' IP curent: ' . (string) ($tecdocIp['server_ip'] ?? 'necunoscut') . '.',
                'url' => '/admin/searchlogs',
                'retry_action' => 'refresh_tecdoc',
            ];
        }

        if (empty($health['tecdoc_online'])) {
            $flags[] = [
                'code' => 'tecdoc_dead',
                'level' => 'danger',
                'critical' => true,
                'title' => 'API TecDoc mort',
                'detail' => 'Catalogul RapidAPI nu răspunde sau a atins limita. Căutarea pe site folosește doar stocul local.',
                'url' => '/admin/searchlogs',
                'retry_action' => 'refresh_tecdoc',
            ];
        }

        if (($import['blocked_jobs'] ?? 0) > 0) {
            $flags[] = [
                'code' => 'job_blocked',
                'level' => 'danger',
                'critical' => true,
                'title' => 'Job blocat',
                'detail' => (int) $import['blocked_jobs'] . ' job(uri) import rulează fără progres (>30 min). Oprește și relansează din Import.',
                'url' => '/admin/import',
                'retry_action' => 'cancel_blocked_job',
                'job_id' => (string) ($import['blocked_job_id'] ?? ''),
            ];
        }

        $brokenLinks = $this->brokenIntegrationLinks($pdo);
        if ($brokenLinks !== []) {
            $first = $brokenLinks[0];
            $flags[] = [
                'code' => 'link_broken',
                'level' => 'danger',
                'critical' => true,
                'title' => 'Link rupt',
                'detail' => count($brokenLinks) . ' integrare(i) cu test conexiune eșuat. Exemplu: ' . (string) ($first['name'] ?? 'N/A') . '.',
                'url' => (string) ($first['url'] ?? '/admin/furnizori'),
                'retry_action' => 'test_integration',
                'entity_type' => (string) ($first['type'] ?? ''),
                'entity_id' => (string) ($first['id'] ?? ''),
            ];
        }

        if (($products['no_image'] ?? 0) > 0) {
            $flags[] = [
                'code' => 'products_no_image',
                'level' => 'warning',
                'title' => $products['no_image'] . ' produse fără imagine',
                'detail' => 'Completează imaginile înainte de publicare pe site.',
                'url' => '/admin/product',
            ];
        }

        if (($products['no_oem'] ?? 0) > 0) {
            $flags[] = [
                'code' => 'products_no_oem',
                'level' => 'warning',
                'title' => $products['no_oem'] . ' produse fără cod OEM',
                'detail' => 'Codurile OEM ajută la căutare TecDoc și SEO.',
                'url' => '/admin/product',
            ];
        }

        if (($products['queue_pending'] ?? 0) > 0) {
            $flags[] = [
                'code' => 'import_queue_pending',
                'level' => 'warning',
                'title' => $products['queue_pending'] . ' produse în coada import',
                'detail' => 'Există rânduri pending de publicat din staging.',
                'url' => '/admin/importreview',
            ];
        }

        if (!empty($search['available']) && ($search['today_not_found'] ?? 0) >= 3) {
            $flags[] = [
                'code' => 'search_not_found',
                'level' => 'warning',
                'title' => $search['today_not_found'] . ' căutări negăsite azi',
                'detail' => 'Clienții caută piese care lipsesc din stoc.',
                'url' => '/admin/searchlogs',
            ];
        }

        if ($health['latest_backup_at'] === null) {
            $flags[] = [
                'code' => 'backup_missing',
                'level' => 'warning',
                'title' => 'Backup automat neconfigurat',
                'detail' => 'Nu există backup recent în admin/storage/backups.',
                'url' => '/admin/backup',
            ];
        }

        return $flags;
    }
}
