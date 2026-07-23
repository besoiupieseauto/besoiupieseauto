<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use Besoiu\Core\Auth\AdminPermissionCatalog;
use Besoiu\Core\Auth\AdminWorkspace;
use Besoiu\Core\Auth\AdminWorkspaceCatalog;
use Besoiu\Core\AdminUrl;
use Besoiu\Core\Module\ModuleGate;
use Besoiu\Core\Bots\BotsModel;
use Besoiu\Core\Comenzi\ComenziModel;
use Besoiu\Core\Messages\MessagesModel;
use Besoiu\Core\Users\UsersModel;
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
        $workspace = AdminWorkspace::getCurrent() ?? 'company';
        $isCompany = $workspace === 'company';
        $currentUser = $this->currentUserContext();
        $products = $this->productStats($pdo);
        $search = $this->searchLogStats();
        $import = $this->importStats($pdo);
        $health = $this->systemHealth($forceRefresh);
        $wsMeta = AdminWorkspaceCatalog::get($workspace) ?? [];

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'workspace' => $workspace,
            'scope' => $isCompany ? 'company_all' : 'workspace_user',
            'scope_label' => $isCompany
                ? 'Date consolidate — întreaga organizație (toți utilizatorii admin + activitate magazin)'
                : 'Date pentru contul tău în zona «' . (string) ($wsMeta['label'] ?? $workspace) . '»',
            'current_user' => $currentUser,
            'team' => $isCompany ? $this->teamOverview($pdo) : null,
            'user_metrics' => !$isCompany ? $this->userMetrics($pdo, $currentUser) : null,
            'orders' => array_merge($this->comenziModel->getDashboardStats(), [
                'recent' => array_map(static function (array $row): array {
                    return [
                        'time' => (string) ($row['created_at'] ?? ''),
                        'client' => (string) ($row['client_name'] ?? $row['name'] ?? '—'),
                        'product' => (string) ($row['product_name'] ?? $row['name'] ?? ''),
                        'channel' => (string) ($row['channel'] ?? 'website'),
                        'status' => (string) ($row['order_status'] ?? ''),
                        'amount' => round((float) ($row['total_amount'] ?? 0), 2),
                        'url' => $this->moduleNavUrl('orders'),
                    ];
                }, $this->comenziModel->findRecent(12)),
            ]),
            'products' => $products,
            'search_logs' => $search,
            'import' => $import,
            'health' => $health,
            'bots' => $this->botSummaries(),
            'activity' => $this->recentActivity(),
            'messages_hub' => $this->messagesHubStats(),
            'website' => $this->websiteStats(),
            'furnizori' => \Besoiu\Core\Module\OptionalModuleBridge::furnizoriAvailable()
                ? $this->furnizoriStats($pdo)
                : [
                    'total' => 0,
                    'active' => 0,
                    'blocked' => 0,
                    'failed_tests' => 0,
                    'failed_scans' => 0,
                    'products_total' => 0,
                    'recent' => [],
                ],
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
            'pricing' => $this->catalogPriceStats($pdo),
        ];
    }

    /** @return array<string, mixed> */
    private function furnizoriStats(PDO $pdo): array
    {
        try {
            $summary = $pdo->query(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked,
                    SUM(CASE WHEN status = 'active' AND LOWER(COALESCE(last_test_status, '')) = 'failed' THEN 1 ELSE 0 END) AS failed_tests,
                    SUM(CASE WHEN status = 'active' AND LOWER(COALESCE(last_scan_status, '')) = 'failed' THEN 1 ELSE 0 END) AS failed_scans,
                    SUM(COALESCE(products_count, 0)) AS products_total
                 FROM furnizori"
            )->fetch(PDO::FETCH_ASSOC) ?: [];

            $rows = $pdo->query(
                "SELECT randomn_id, name, code, status, connection_type,
                        last_scan_at, last_scan_status, last_test_status, products_count
                 FROM furnizori
                 ORDER BY status = 'active' DESC, name ASC
                 LIMIT 12"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $recent = array_map(static function (array $row): array {
                $id = (string) ($row['randomn_id'] ?? '');

                return [
                    'id' => $id,
                    'name' => (string) ($row['name'] ?? '—'),
                    'code' => (string) ($row['code'] ?? '—'),
                    'status' => (string) ($row['status'] ?? '—'),
                    'connection' => (string) ($row['connection_type'] ?? '—'),
                    'products' => (int) ($row['products_count'] ?? 0),
                    'last_scan' => (string) ($row['last_scan_at'] ?? ''),
                    'scan_status' => (string) ($row['last_scan_status'] ?? ''),
                    'test_status' => (string) ($row['last_test_status'] ?? ''),
                    'url' => $id !== ''
                        ? $this->moduleNavUrl('profilefurnizori', '?randomn_id=' . rawurlencode($id))
                        : $this->moduleNavUrl('furnizori'),
                ];
            }, $rows);

            return [
                'total' => (int) ($summary['total'] ?? 0),
                'active' => (int) ($summary['active'] ?? 0),
                'blocked' => (int) ($summary['blocked'] ?? 0),
                'failed_tests' => (int) ($summary['failed_tests'] ?? 0),
                'failed_scans' => (int) ($summary['failed_scans'] ?? 0),
                'products_total' => (int) ($summary['products_total'] ?? 0),
                'recent' => $recent,
            ];
        } catch (\PDOException) {
            return [
                'total' => 0,
                'active' => 0,
                'blocked' => 0,
                'failed_tests' => 0,
                'failed_scans' => 0,
                'products_total' => 0,
                'recent' => [],
            ];
        }
    }

    /** @return array{avg:float,min:float,max:float,priced_count:int} */
    private function catalogPriceStats(PDO $pdo): array
    {
        try {
            // Normalizează prețul indiferent de separator:
            //  - dacă are virgulă => virgula e zecimală (RO): elimină punctele (mii), apoi ',' -> '.'
            //  - altfel punctul e zecimal (ex: 141.23) => păstrează
            // Acoperă: "141,23", "141.23", "1.234,56", "1 234,56".
            $priceRow = $pdo->query(
                "SELECT
                    MIN(p) AS min_p,
                    MAX(p) AS max_p,
                    AVG(p) AS avg_p,
                    COUNT(*) AS priced_count
                 FROM (
                    SELECT CAST(
                        REPLACE(
                            CASE WHEN INSTR(pPrice, ',') > 0
                                 THEN REPLACE(REPLACE(pPrice, '.', ''), ',', '.')
                                 ELSE pPrice END,
                            ' ', ''
                        ) AS DECIMAL(14,2)) AS p
                    FROM produse
                    WHERE (status IS NULL OR status <> '0')
                      AND pPrice IS NOT NULL
                      AND TRIM(pPrice) <> ''
                 ) t
                 WHERE p > 0"
            )->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'avg' => round((float) ($priceRow['avg_p'] ?? 0), 2),
                'min' => round((float) ($priceRow['min_p'] ?? 0), 2),
                'max' => round((float) ($priceRow['max_p'] ?? 0), 2),
                'priced_count' => (int) ($priceRow['priced_count'] ?? 0),
            ];
        } catch (\Throwable $e) {
            error_log('[DashboardService] catalogPriceStats: ' . $e->getMessage());

            return ['avg' => 0.0, 'min' => 0.0, 'max' => 0.0, 'priced_count' => 0];
        }
    }

    /** @return array{products:int,codes:int} */
    private function productOemIndexStats(PDO $pdo): array
    {
        require_once dirname(__DIR__, 3) . '/Legacy/products_oem.php';
        if (!products_oem_table_exists($pdo)) {
            return ['products' => 0, 'codes' => 0];
        }

        return products_oem_stats($pdo);
    }

    /** @return array<string, mixed> */
    private function searchLogStats(): array
    {
        require_once dirname(__DIR__, 3) . '/Legacy/tecdoc_stock.php';

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
        $cacheFile = dirname(__DIR__, 2) . '/storage/cache/dashboard_import_stats.json';
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
        $jobsDir = dirname(__DIR__, 2) . '/storage/imports/jobs';
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
        require_once dirname(__DIR__, 3) . '/Legacy/tecdoc_stock.php';

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
                'url' => $this->moduleNavUrl('orders'),
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
                'url' => $this->moduleNavUrl('messages'),
            ];
        }, array_slice($this->messagesModel->findRecent(4), 0, 4));

        $combined = array_merge($orders, $messages);
        usort($combined, static function (array $a, array $b): int {
            return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
        });

        return [
            'items' => array_slice($combined, 0, 10),
            'orders_count' => count($orders),
            'messages_count' => count($messages),
        ];
    }

    /** @return array<string, mixed> */
    private function messagesHubStats(): array
    {
        $pdo = Database::getDB();
        $byChannel = [];
        try {
            $rows = $pdo->query(
                "SELECT COALESCE(channel, 'manual') AS ch, COUNT(*) AS cnt FROM messages GROUP BY ch ORDER BY cnt DESC"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $byChannel[(string) ($row['ch'] ?? 'manual')] = (int) ($row['cnt'] ?? 0);
            }
        } catch (\PDOException) {
        }

        $recent = array_map(static function (array $row): array {
            $body = (string) ($row['message_body'] ?? '');
            if (function_exists('mb_substr') && mb_strlen($body) > 60) {
                $preview = mb_substr($body, 0, 60) . '…';
            } elseif (strlen($body) > 60) {
                $preview = substr($body, 0, 60) . '…';
            } else {
                $preview = $body;
            }

            return [
                'time' => (string) ($row['created_at'] ?? ''),
                'name' => (string) ($row['name'] ?? 'Contact'),
                'channel' => (string) ($row['channel'] ?? 'manual'),
                'status' => (string) ($row['bot_status'] ?? $row['message_status'] ?? ''),
                'preview' => $preview,
            ];
        }, $this->messagesModel->findRecent(10));

        return [
            'total' => array_sum($byChannel),
            'by_channel' => $byChannel,
            'recent' => $recent,
        ];
    }

    /** @return array<string, mixed> */
    private function websiteStats(): array
    {
        $pages = 0;
        try {
            $rows = (new \Besoiu\Services\WebsiteService())->getAll();
            $pages = count($rows);
        } catch (\Throwable) {
        }

        $pdo = Database::getDB();
        $vitrina = 0;
        $blog = 0;
        try {
            $vitrina = (int) $pdo->query("SELECT COUNT(*) FROM produse_selective WHERE status = 1")->fetchColumn();
        } catch (\PDOException) {
        }
        if (\Besoiu\Core\Module\ModuleGate::enabled('blog')) {
            try {
                $blog = (int) $pdo->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn();
            } catch (\PDOException) {
                try {
                    $blog = (int) $pdo->query('SELECT COUNT(*) FROM blog')->fetchColumn();
                } catch (\PDOException) {
                }
            }
        }

        return [
            'pages' => $pages,
            'vitrina' => $vitrina,
            'blog_posts' => $blog,
            'products_active' => (int) ($this->productStats($pdo)['active'] ?? 0),
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
                    'url' => $id !== ''
                        ? $this->moduleNavUrl('profilefurnizori', '?randomn_id=' . rawurlencode($id))
                        : $this->moduleNavUrl('furnizori'),
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

            if (ModuleGate::slugAllowed('bots')) {
                foreach ($rows as $row) {
                    $id = (string) ($row['randomn_id'] ?? '');
                    $items[] = [
                        'type' => 'bot',
                        'id' => $id,
                        'name' => (string) ($row['name'] ?? $row['channel'] ?? 'Bot'),
                        'url' => $this->moduleNavUrl('bots'),
                    ];
                }
            }
        } catch (\PDOException) {
        }

        return $items;
    }

    private function moduleNavUrl(string $slug, string $suffix = ''): string
    {
        if (ModuleGate::slugAllowed($slug)) {
            return AdminUrl::navPath($slug) . $suffix;
        }

        return AdminUrl::navPath('settings');
    }

    /** @param array<string, mixed> $products @param array<string, mixed> $search @param array<string, mixed> $import @param array<string, mixed> $health */
    private function buildRedFlags(array $products, array $search, array $import, array $health): array
    {
        $flags = [];

        try {
            foreach (\Besoiu\Core\Scraper\ScraperHooks::operationalAlerts() as $alert) {
                if (is_array($alert)) {
                    $flags[] = $alert;
                }
            }
        } catch (\Throwable) {
            // optional — scraper module
        }

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
                'url' => $this->moduleNavUrl('import', '#job-progress-wrap'),
                'retry_url' => $this->moduleNavUrl('import'),
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
                'url' => $this->moduleNavUrl('searchlogs'),
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
                'url' => $this->moduleNavUrl('searchlogs'),
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
                'url' => $this->moduleNavUrl('import', '#job-progress-wrap'),
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
                'url' => (string) ($first['url'] ?? $this->moduleNavUrl('furnizori')),
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
                'url' => $this->moduleNavUrl('product'),
            ];
        }

        if (($products['no_oem'] ?? 0) > 0) {
            $flags[] = [
                'code' => 'products_no_oem',
                'level' => 'warning',
                'title' => $products['no_oem'] . ' produse fără cod OEM',
                'detail' => 'Codurile OEM ajută la căutare TecDoc și SEO.',
                'url' => $this->moduleNavUrl('product'),
            ];
        }

        if (($products['queue_pending'] ?? 0) > 0) {
            $flags[] = [
                'code' => 'import_queue_pending',
                'level' => 'warning',
                'title' => $products['queue_pending'] . ' produse în coada import',
                'detail' => 'Există rânduri pending de publicat din staging.',
                'url' => $this->moduleNavUrl('importreview'),
            ];
        }

        if (!empty($search['available']) && ($search['today_not_found'] ?? 0) >= 3) {
            $flags[] = [
                'code' => 'search_not_found',
                'level' => 'warning',
                'title' => $search['today_not_found'] . ' căutări negăsite azi',
                'detail' => 'Clienții caută piese care lipsesc din stoc.',
                'url' => $this->moduleNavUrl('searchlogs'),
            ];
        }

        if ($health['latest_backup_at'] === null) {
            $flags[] = [
                'code' => 'backup_missing',
                'level' => 'warning',
                'title' => 'Backup automat neconfigurat',
                'detail' => 'Nu există backup recent în admin/storage/backups.',
                'url' => $this->moduleNavUrl('backup'),
            ];
        }

        return $flags;
    }

    /** @return array<string, mixed> */
    private function currentUserContext(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role = (string) ($_SESSION['role'] ?? 'guest');
        $perms = AdminPermissionCatalog::normalizePermissions(
            $_SESSION['admin_permissions'] ?? null,
            $role
        );
        $name = (string) ($_SESSION['fullname'] ?? $_SESSION['nikname'] ?? $_SESSION['login'] ?? 'Operator');
        $workspaces = [];
        foreach (AdminWorkspaceCatalog::ids() as $wsId) {
            if (AdminWorkspace::userCanAccessWorkspace($wsId, $role, $perms)) {
                $meta = AdminWorkspaceCatalog::get($wsId) ?? [];
                $workspaces[] = [
                    'id' => $wsId,
                    'label' => (string) ($meta['label'] ?? $wsId),
                ];
            }
        }

        return [
            'id' => $userId,
            'name' => $name,
            'login' => (string) ($_SESSION['login'] ?? ''),
            'role' => $role,
            'workspaces' => $workspaces,
        ];
    }

    /** @return array<string, mixed> */
    private function userMetrics(PDO $pdo, array $currentUser): array
    {
        $userId = (int) ($currentUser['id'] ?? 0);
        $owned = $this->productStatsForUser($pdo, $userId);
        $global = $this->productStats($pdo);

        return [
            'products_owned' => $owned,
            'products_org_total' => (int) ($global['total'] ?? 0),
            'share_pct' => ($global['total'] ?? 0) > 0
                ? (int) round(($owned['total'] / (int) $global['total']) * 100)
                : 0,
        ];
    }

    /** @return array<string, int> */
    private function productStatsForUser(PDO $pdo, int $userId): array
    {
        if ($userId <= 0) {
            return ['total' => 0, 'active' => 0];
        }

        $uid = (string) $userId;
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status IS NULL OR status <> '0' THEN 1 ELSE 0 END) AS active
             FROM produse
             WHERE id_users = :u OR connect_id = :u2"
        );
        $stmt->execute([':u' => $uid, ':u2' => $uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function teamOverview(PDO $pdo): array
    {
        $productByUser = [];
        try {
            $rows = $pdo->query(
                "SELECT COALESCE(NULLIF(TRIM(id_users), ''), NULLIF(TRIM(connect_id), ''), '0') AS uid,
                        COUNT(*) AS cnt,
                        SUM(CASE WHEN status IS NULL OR status <> '0' THEN 1 ELSE 0 END) AS active
                 FROM produse
                 GROUP BY uid"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $key = (string) ($row['uid'] ?? '0');
                $productByUser[$key] = [
                    'total' => (int) ($row['cnt'] ?? 0),
                    'active' => (int) ($row['active'] ?? 0),
                ];
            }
        } catch (\PDOException) {
        }

        $members = [];
        $activeUsers = 0;
        foreach (UsersModel::getUserssAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rid = (int) ($row['randomn_id'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            if ($status === '1' || strtolower($status) === 'active') {
                $activeUsers++;
            }
            $role = (string) ($row['role'] ?? 'operator');
            $perms = AdminPermissionCatalog::normalizePermissions($row['permissions_json'] ?? null, $role);
            $workspaces = [];
            foreach (AdminWorkspaceCatalog::ids() as $wsId) {
                if (AdminWorkspace::userCanAccessWorkspace($wsId, $role, $perms)) {
                    $meta = AdminWorkspaceCatalog::get($wsId) ?? [];
                    $workspaces[] = (string) ($meta['label'] ?? $wsId);
                }
            }
            $stats = $productByUser[(string) $rid] ?? ['total' => 0, 'active' => 0];
            $members[] = [
                'id' => $rid,
                'name' => (string) ($row['fullname'] ?? $row['nikname'] ?? $row['login'] ?? 'Utilizator'),
                'login' => (string) ($row['login'] ?? ''),
                'role' => $role,
                'status' => $status,
                'products_total' => $stats['total'],
                'products_active' => $stats['active'],
                'workspaces' => $workspaces,
            ];
        }

        usort($members, static fn (array $a, array $b): int => ($b['products_total'] ?? 0) <=> ($a['products_total'] ?? 0));

        return [
            'total_users' => count($members),
            'active_users' => $activeUsers,
            'members' => $members,
            'unassigned_products' => $productByUser['0']['total'] ?? 0,
        ];
    }
}
