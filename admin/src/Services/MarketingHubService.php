<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Services\DashboardService;
use Besoiu\Core\Comenzi\ComenziModel;
use Config\Database;
use PDO;
use Throwable;

/**
 * Instrument marketing — CRUD BD, insights live, playbooks, alerte.
 */
final class MarketingHubService
{
    private const AUTO_KEYS = [
        'site_searches_today' => ['platform' => 'besoiupieseauto.ro', 'metric' => 'Căutări site azi', 'url' => 'https://besoiupieseauto.ro'],
        'site_searches_total' => ['platform' => 'besoiupieseauto.ro', 'metric' => 'Căutări totale (jurnal)', 'url' => 'https://besoiupieseauto.ro'],
        'site_orders_today' => ['platform' => 'besoiupieseauto.ro', 'metric' => 'Comenzi noi azi', 'url' => '/admin/orders'],
        'site_revenue_today' => ['platform' => 'besoiupieseauto.ro', 'metric' => 'Venit azi (RON)', 'url' => '/admin/orders'],
        'site_missing_oem' => ['platform' => 'besoiupieseauto.ro', 'metric' => 'Coduri OEM lipsă din stoc', 'url' => '/admin/searchlogs'],
        'site_success_rate' => ['platform' => 'besoiupieseauto.ro', 'metric' => 'Rată succes căutări (%)', 'url' => '/admin/searchlogs'],
    ];

    /** @return array<string, mixed> */
    public static function playbooks(): array
    {
        return [
            'seo_catalog' => [
                'key' => 'seo_catalog',
                'title' => 'SEO catalog — top OEM negăsite',
                'targetKpi' => 'Coduri OEM lipsă din stoc',
                'priority' => 'high',
                'impact' => 5,
                'effort' => 3,
                'notes' => 'Importă sau publică produse pentru top 5 coduri OEM căutate fără rezultat.',
            ],
            'fb_weekly' => [
                'key' => 'fb_weekly',
                'title' => '3 postări Facebook / săptămână',
                'targetKpi' => 'Reach postări / lună',
                'priority' => 'medium',
                'impact' => 4,
                'effort' => 2,
                'notes' => 'Promo piese, tips mecanici, CTA WhatsApp.',
            ],
            'pieseauto_refresh' => [
                'key' => 'pieseauto_refresh',
                'title' => 'Refresh anunțuri PieseAuto.ro',
                'targetKpi' => 'Listări active PieseAuto.ro',
                'priority' => 'medium',
                'impact' => 4,
                'effort' => 2,
                'notes' => 'Republică sau actualizează preț/stoc pe PieseAuto.ro.',
            ],
            'blog_oem' => [
                'key' => 'blog_oem',
                'title' => 'Articol blog OEM trending',
                'targetKpi' => 'Impresii căutare Google',
                'priority' => 'medium',
                'impact' => 4,
                'effort' => 4,
                'notes' => 'Articol ghid compatibilitate + link produse.',
            ],
            'whatsapp_followup' => [
                'key' => 'whatsapp_followup',
                'title' => 'Follow-up leads WhatsApp',
                'targetKpi' => 'Conversații / lună',
                'priority' => 'high',
                'impact' => 5,
                'effort' => 2,
                'notes' => 'Răspuns sub 30 min la mesaje noi.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function contentTemplates(): array
    {
        return [
            'fb_post' => [
                'key' => 'fb_post',
                'label' => 'Post Facebook',
                'platform' => 'Facebook',
                'format' => 'post',
                'body' => "🔧 [PROBLEMĂ CLIENT]\n\n✅ Soluție: [PIESĂ + OEM]\n💰 Preț de la [X] RON\n📦 Stoc: [disponibil]\n\n📲 Comandă pe WhatsApp: [link/nr]",
            ],
            'pieseauto_ad' => [
                'key' => 'pieseauto_ad',
                'label' => 'Anunț PieseAuto.ro',
                'platform' => 'PieseAuto.ro',
                'format' => 'ad',
                'body' => "Titlu: [BRAND] [OEM] — [Denumire scurtă]\n\nDescriere:\n- Piesă nouă/originală\n- Compatibil: [modele]\n- Livrare: [curier/ridicare]\n- Garanție\n\nPreț: [X] RON",
            ],
            'blog_guide' => [
                'key' => 'blog_guide',
                'label' => 'Articol blog ghid',
                'platform' => 'Blog',
                'format' => 'article',
                'body' => "# [Titlu OEM / simptom]\n\n## Simptome\n...\n\n## Piese recomandate\n- OEM: ...\n\n## CTA\nVezi stoc pe besoiupieseauto.ro",
            ],
        ];
    }

    public static function tablesExist(): bool
    {
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query("SHOW TABLES LIKE 'marketing_indicators'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /** Indicatori demo per platformă (site, Google, FB, PieseAuto, WhatsApp). */
    /** @return list<array<string, mixed>> */
    public static function buildDemoPlatformIndicators(): array
    {
        $now = date('c');
        $rows = [
            ['platform' => 'Site / besoiupieseauto.ro', 'category' => 'site', 'metric' => 'Vizitatori (lună)', 'value' => 120, 'target' => 5000, 'url' => 'https://besoiupieseauto.ro', 'notes' => 'Pagina Proiect în lucru + catalog /test/'],
            ['platform' => 'Site / besoiupieseauto.ro', 'category' => 'site', 'metric' => 'Sesiuni catalog /test/', 'value' => 45, 'target' => 800, 'url' => '/test/'],
            ['platform' => 'Google / SEO', 'category' => 'google', 'metric' => 'Trafic organic (estim.)', 'value' => 180, 'target' => 1200, 'url' => '/admin/searchlogs'],
            ['platform' => 'Google / SEO', 'category' => 'google', 'metric' => 'Pagini indexate', 'value' => 340, 'target' => 2000, 'url' => 'https://besoiupieseauto.ro/sitemap.xml'],
            ['platform' => 'Facebook', 'category' => 'facebook', 'metric' => 'Reach postări (lună)', 'value' => 820, 'target' => 5000, 'url' => 'https://facebook.com/'],
            ['platform' => 'Facebook', 'category' => 'facebook', 'metric' => 'Lead-uri WhatsApp din FB', 'value' => 3, 'target' => 50, 'url' => '/admin/comunicare'],
            ['platform' => 'PieseAuto.ro', 'category' => 'pieseauto', 'metric' => 'Listări active', 'value' => 8, 'target' => 30, 'url' => '/admin/marketplace/pieseauto'],
            ['platform' => 'PieseAuto.ro', 'category' => 'pieseauto', 'metric' => 'Vizualizări anunțuri (lună)', 'value' => 240, 'target' => 1500, 'url' => '/admin/marketplace/pieseauto'],
            ['platform' => 'PieseAuto.ro', 'category' => 'pieseauto', 'metric' => 'Mesaje primite (lună)', 'value' => 12, 'target' => 60, 'url' => '/admin/marketplace/pieseauto'],
            ['platform' => 'WhatsApp', 'category' => 'whatsapp', 'metric' => 'Lead-uri calificate', 'value' => 18, 'target' => 80, 'url' => '/admin/comunicare'],
            ['platform' => 'WhatsApp', 'category' => 'whatsapp', 'metric' => 'Timp mediu răspuns (min)', 'value' => 25, 'target' => 10, 'url' => '/admin/comunicare', 'notes' => 'Mai mic = mai bine'],
        ];
        $out = [];
        foreach ($rows as $row) {
            $out[] = array_merge($row, [
                'id' => self::newId('ind'),
                'source' => 'manual',
                'autoKey' => null,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
        }

        return $out;
    }

    public static function ensureHubColumns(): void
    {
        if (!self::tablesExist()) {
            self::ensureCoreTables();
        }
        if (!self::tablesExist()) {
            return;
        }
        try {
            $pdo = Database::getDB();
            if (!self::columnExists($pdo, 'marketing_indicators', 'category')) {
                $pdo->exec(
                    "ALTER TABLE marketing_indicators ADD COLUMN category VARCHAR(32) NOT NULL DEFAULT 'general'"
                );
            }
            if (MarketingCampaignService::tablesExist()) {
                if (!self::columnExists($pdo, 'marketing_actions', 'campaign_id')) {
                    $pdo->exec(
                        "ALTER TABLE marketing_actions ADD COLUMN campaign_id VARCHAR(32) DEFAULT NULL COMMENT 'Campanie marketing'"
                    );
                }
                if (!self::columnExists($pdo, 'marketing_actions', 'kanban_step')) {
                    $pdo->exec(
                        "ALTER TABLE marketing_actions ADD COLUMN kanban_step VARCHAR(24) DEFAULT NULL"
                    );
                }
                if (!self::columnExists($pdo, 'marketing_content', 'campaign_id')) {
                    $pdo->exec(
                        "ALTER TABLE marketing_content ADD COLUMN campaign_id VARCHAR(32) DEFAULT NULL COMMENT 'Campanie marketing'"
                    );
                }
            }
            if (!self::columnExists($pdo, 'marketing_content', 'metrics_json')) {
                $pdo->exec(
                    "ALTER TABLE marketing_content ADD COLUMN metrics_json JSON DEFAULT NULL COMMENT 'hashtags, media, spend ads'"
                );
            }
        } catch (Throwable $e) {
            error_log('[MarketingHubService] ensureHubColumns: ' . $e->getMessage());
        }
    }

    /** Creează tabelele marketing dacă lipsesc (067). */
    public static function ensureCoreTables(): void
    {
        try {
            $pdo = Database::getDB();
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS marketing_indicators (
                    id VARCHAR(32) NOT NULL PRIMARY KEY,
                    platform VARCHAR(120) NOT NULL,
                    url VARCHAR(500) NOT NULL DEFAULT '',
                    metric VARCHAR(160) NOT NULL,
                    value DECIMAL(14,2) NOT NULL DEFAULT 0,
                    target DECIMAL(14,2) NOT NULL DEFAULT 0,
                    source VARCHAR(20) NOT NULL DEFAULT 'manual',
                    auto_key VARCHAR(80) DEFAULT NULL,
                    notes TEXT,
                    linked_okr_id VARCHAR(32) DEFAULT NULL,
                    user_id INT UNSIGNED DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_mind_platform (platform),
                    KEY idx_mind_auto (auto_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS marketing_actions (
                    id VARCHAR(32) NOT NULL PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    target_kpi VARCHAR(255) NOT NULL DEFAULT '',
                    indicator_id VARCHAR(32) DEFAULT NULL,
                    deadline DATE DEFAULT NULL,
                    priority VARCHAR(10) NOT NULL DEFAULT 'medium',
                    status VARCHAR(20) NOT NULL DEFAULT 'todo',
                    effort TINYINT UNSIGNED NOT NULL DEFAULT 3,
                    impact TINYINT UNSIGNED NOT NULL DEFAULT 3,
                    score INT NOT NULL DEFAULT 0,
                    notes TEXT,
                    playbook_key VARCHAR(64) DEFAULT NULL,
                    user_id INT UNSIGNED DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_mact_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS marketing_content (
                    id VARCHAR(32) NOT NULL PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    platform VARCHAR(120) NOT NULL DEFAULT '',
                    format VARCHAR(20) NOT NULL DEFAULT 'post',
                    status VARCHAR(20) NOT NULL DEFAULT 'draft',
                    publish_date DATE DEFAULT NULL,
                    linked_action_id VARCHAR(32) DEFAULT NULL,
                    linked_indicator_id VARCHAR(32) DEFAULT NULL,
                    body MEDIUMTEXT,
                    template_key VARCHAR(64) DEFAULT NULL,
                    user_id INT UNSIGNED DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_mcnt_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS marketing_okrs (
                    id VARCHAR(32) NOT NULL PRIMARY KEY,
                    quarter_label VARCHAR(20) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    target_value DECIMAL(14,2) NOT NULL DEFAULT 0,
                    current_value DECIMAL(14,2) NOT NULL DEFAULT 0,
                    unit VARCHAR(40) NOT NULL DEFAULT '',
                    status VARCHAR(20) NOT NULL DEFAULT 'active',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            error_log('[MarketingHubService] ensureCoreTables: ' . $e->getMessage());
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function hasIndicatorCategoryColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        if (!self::tablesExist()) {
            $cached = false;

            return false;
        }
        try {
            $cached = self::columnExists(Database::getDB(), 'marketing_indicators', 'category');
        } catch (Throwable) {
            $cached = false;
        }

        return $cached;
    }

    public static function hasActionCampaignColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        if (!self::tablesExist()) {
            $cached = false;

            return false;
        }
        try {
            $cached = self::columnExists(Database::getDB(), 'marketing_actions', 'campaign_id');
        } catch (Throwable) {
            $cached = false;
        }

        return $cached;
    }

    public static function hasContentCampaignColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        if (!self::tablesExist()) {
            $cached = false;

            return false;
        }
        try {
            $cached = self::columnExists(Database::getDB(), 'marketing_content', 'campaign_id');
        } catch (Throwable) {
            $cached = false;
        }

        return $cached;
    }

    public static function ensurePlatformIndicators(?int $userId = null): void
    {
        if (!self::tablesExist()) {
            return;
        }
        try {
            $state = self::loadAll();
            if (($state['indicators'] ?? []) !== []) {
                return;
            }
            self::syncLiveIndicators($userId);
            $state = self::loadAll();
            if (($state['indicators'] ?? []) !== []) {
                return;
            }
            self::saveState(['indicators' => self::buildDemoPlatformIndicators()], $userId);
        } catch (Throwable $e) {
            error_log('[MarketingHubService] ensurePlatformIndicators: ' . $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    public static function loadAll(): array
    {
        if (!self::tablesExist()) {
            return self::emptyStorageState();
        }

        try {
            $pdo = Database::getDB();
            $rawActions = self::fetchActions($pdo);

            return [
                'indicators' => self::fetchIndicators($pdo),
                'actions' => self::dedupeActions($rawActions),
                'content' => self::fetchContent($pdo),
                'okrs' => self::fetchOkrs($pdo),
                'storage' => 'database',
            ];
        } catch (Throwable $e) {
            error_log('[MarketingHubService] loadAll: ' . $e->getMessage());

            return self::emptyStorageState('error');
        }
    }

    /** @return array<string, mixed> */
    public static function emptyStorageState(string $storage = 'none'): array
    {
        return [
            'indicators' => [],
            'actions' => [],
            'content' => [],
            'okrs' => [],
            'storage' => $storage,
        ];
    }

    /**
     * O singură acțiune deschisă per playbook / titlu+KPI (păstrează cea mai recentă).
     *
     * @param list<array<string, mixed>> $actions
     * @return list<array<string, mixed>>
     */
    public static function dedupeActions(array $actions): array
    {
        if ($actions === []) {
            return [];
        }

        $playbooks = self::playbooks();
        $normalized = [];
        foreach ($actions as $act) {
            if (!is_array($act)) {
                continue;
            }
            $row = $act;
            $key = (string) ($row['playbookKey'] ?? $row['playbook_key'] ?? '');
            if ($key === '') {
                foreach ($playbooks as $pb) {
                    if (($row['title'] ?? '') === ($pb['title'] ?? '')) {
                        $row['playbookKey'] = $pb['key'];
                        break;
                    }
                }
            }
            $normalized[] = $row;
        }

        usort($normalized, static function (array $a, array $b): int {
            return strcmp((string) ($b['updatedAt'] ?? $b['updated_at'] ?? ''), (string) ($a['updatedAt'] ?? $a['updated_at'] ?? ''));
        });

        $openFingerprints = [];
        $result = [];
        foreach ($normalized as $act) {
            if (($act['status'] ?? '') === 'done') {
                $result[] = $act;
                continue;
            }
            $fp = self::actionFingerprint($act);
            if (isset($openFingerprints[$fp])) {
                continue;
            }
            $openFingerprints[$fp] = true;
            $result[] = $act;
        }

        return $result;
    }

    /** @param array<string, mixed> $act */
    private static function actionFingerprint(array $act): string
    {
        $pb = (string) ($act['playbookKey'] ?? $act['playbook_key'] ?? '');
        if ($pb !== '') {
            return 'pb:' . $pb;
        }
        $title = mb_strtolower(trim((string) ($act['title'] ?? '')));
        $kpi = mb_strtolower(trim((string) ($act['targetKpi'] ?? $act['target_kpi'] ?? '')));

        return 'manual:' . $title . '|' . $kpi;
    }

    /** @param array<string, mixed> $payload */
    public static function saveState(array $payload, ?int $userId = null): bool
    {
        if (!self::tablesExist()) {
            return false;
        }

        $pdo = Database::getDB();
        $pdo->beginTransaction();
        try {
            if (isset($payload['indicators']) && is_array($payload['indicators']) && $payload['indicators'] !== []) {
                self::replaceIndicators($pdo, $payload['indicators'], $userId);
            }
            if (isset($payload['actions']) && is_array($payload['actions']) && $payload['actions'] !== []) {
                self::replaceActions($pdo, self::dedupeActions($payload['actions']), $userId);
            }
            if (isset($payload['content']) && is_array($payload['content']) && $payload['content'] !== []) {
                self::replaceContent($pdo, $payload['content'], $userId);
            }
            if (isset($payload['okrs']) && is_array($payload['okrs']) && $payload['okrs'] !== []) {
                self::replaceOkrs($pdo, $payload['okrs']);
            }
            if (isset($payload['campaigns']) && is_array($payload['campaigns']) && $payload['campaigns'] !== []) {
                MarketingCampaignService::saveCampaigns($payload, $userId);
            }
            $pdo->commit();

            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('[MarketingHubService] saveState: ' . $e->getMessage());

            return false;
        }
    }

    /** @return array<string, mixed> */
    public static function insights(): array
    {
        try {
            $dash = (new DashboardService())->overview(false);
        } catch (Throwable $e) {
            error_log('[MarketingHubService] insights dashboard: ' . $e->getMessage());

            return [
                'live' => [
                    'searches_today' => 0,
                    'searches_total' => 0,
                    'searches_not_found' => 0,
                    'missing_codes' => 0,
                    'success_rate' => 0,
                    'orders_today' => 0,
                    'revenue_today' => 0,
                ],
                'top_missing_oem' => [],
                'planner_hints' => [],
                'suggestions' => [],
                'generated_at' => date('Y-m-d H:i:s'),
                'dashboard_error' => $e->getMessage(),
            ];
        }

        $search = is_array($dash['search_logs'] ?? null) ? $dash['search_logs'] : [];
        $orders = is_array($dash['orders'] ?? null) ? $dash['orders'] : [];

        $total = (int) ($search['total'] ?? 0);
        $notFound = (int) ($search['not_found'] ?? 0);
        $successRate = $total > 0 ? round((($total - $notFound) / $total) * 100, 1) : 0;

        $live = [
            'searches_today' => (int) ($search['today'] ?? 0),
            'searches_total' => $total,
            'searches_not_found' => $notFound,
            'missing_codes' => (int) ($search['missing_codes_count'] ?? 0),
            'success_rate' => $successRate,
            'orders_today' => (int) ($orders['today_new'] ?? 0),
            'revenue_today' => round((float) ($orders['today_revenue'] ?? 0), 2),
        ];

        $topMissing = [];
        foreach ((array) ($search['top_missing'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $topMissing[] = [
                'code' => (string) ($row['code'] ?? $row['oem'] ?? ''),
                'count' => (int) ($row['count'] ?? $row['hits'] ?? 0),
            ];
        }

        return [
            'live' => $live,
            'top_missing_oem' => array_slice($topMissing, 0, 8),
            'planner_hints' => self::plannerHints(),
            'suggestions' => self::buildSuggestions($live, $topMissing),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public static function syncLiveIndicators(?int $userId = null): int
    {
        if (!self::tablesExist()) {
            return 0;
        }

        $insights = self::insights();
        $live = $insights['live'];
        $map = [
            'site_searches_today' => (float) $live['searches_today'],
            'site_searches_total' => (float) $live['searches_total'],
            'site_orders_today' => (float) $live['orders_today'],
            'site_revenue_today' => (float) $live['revenue_today'],
            'site_missing_oem' => (float) $live['missing_codes'],
            'site_success_rate' => (float) $live['success_rate'],
        ];

        $updated = 0;
        $pdo = Database::getDB();
        foreach ($map as $autoKey => $value) {
            $meta = self::AUTO_KEYS[$autoKey];
            $existing = self::findByAutoKey($pdo, $autoKey);
            if ($existing !== null) {
                $stmt = $pdo->prepare(
                    'UPDATE marketing_indicators SET value = ?, source = ?, updated_at = NOW() WHERE id = ?'
                );
                $stmt->execute([$value, 'auto', $existing['id']]);
                $updated++;
            } else {
                $id = self::newId('ind');
                $stmt = $pdo->prepare(
                    'INSERT INTO marketing_indicators (id, platform, url, metric, value, target, source, auto_key, user_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $defaultTarget = match ($autoKey) {
                    'site_success_rate' => 75.0,
                    'site_missing_oem' => 50.0,
                    default => max($value * 1.5, 10.0),
                };
                $stmt->execute([
                    $id,
                    $meta['platform'],
                    $meta['url'],
                    $meta['metric'],
                    $value,
                    $defaultTarget,
                    'auto',
                    $autoKey,
                    $userId,
                ]);
                $updated++;
            }
        }

        return $updated;
    }

    /** @return list<array<string, mixed>> */
    public static function alerts(): array
    {
        $state = self::loadAll();
        $alerts = [];
        $now = time();

        foreach ($state['indicators'] as $ind) {
            $target = (float) ($ind['target'] ?? 0);
            $value = (float) ($ind['value'] ?? 0);
            if ($target <= 0) {
                continue;
            }
            $p = min(999, (int) round(($value / $target) * 100));
            if ($p < 60) {
                $alerts[] = [
                    'code' => 'kpi_below_target',
                    'severity' => $p < 30 ? 'critical' : 'warning',
                    'title' => ($ind['platform'] ?? '') . ': sub țintă (' . $p . '%)',
                    'detail' => ($ind['metric'] ?? '') . ' — ' . $value . ' / ' . $target,
                    'url' => '/admin/marketing',
                    'indicator_id' => $ind['id'] ?? '',
                ];
            }
        }

        foreach ($state['actions'] as $act) {
            if (($act['status'] ?? '') === 'done') {
                continue;
            }
            $deadline = (string) ($act['deadline'] ?? '');
            if ($deadline !== '') {
                $ts = strtotime($deadline);
                if ($ts !== false && $ts < $now && ($act['status'] ?? '') !== 'done') {
                    $alerts[] = [
                        'code' => 'action_overdue',
                        'severity' => 'critical',
                        'title' => 'Acțiune depășită: ' . ($act['title'] ?? ''),
                        'detail' => 'Deadline: ' . $deadline,
                        'url' => '/admin/marketing-actions',
                        'action_id' => $act['id'] ?? '',
                    ];
                }
            }
            if (($act['priority'] ?? '') === 'high' && ($act['status'] ?? '') === 'todo') {
                $alerts[] = [
                    'code' => 'action_urgent_todo',
                    'severity' => 'warning',
                    'title' => 'Urgent neînceput: ' . ($act['title'] ?? ''),
                    'detail' => 'Prioritate maximă',
                    'url' => '/admin/marketing-actions',
                    'action_id' => $act['id'] ?? '',
                ];
            }
        }

        $scheduledNextWeek = 0;
        $weekEnd = strtotime('+7 days');
        foreach ($state['content'] as $c) {
            if (($c['status'] ?? '') === 'scheduled') {
                $scheduledNextWeek++;
            }
        }
        if ($scheduledNextWeek === 0 && count($state['content']) > 0) {
            $alerts[] = [
                'code' => 'no_scheduled_content',
                'severity' => 'warning',
                'title' => 'Niciun conținut programat',
                'detail' => 'Programează postări pentru săptămâna viitoare.',
                'url' => '/admin/marketing-content',
            ];
        }

        return array_slice(self::dedupeAlertList($alerts), 0, 12);
    }

    /** @param list<array<string, mixed>> $alerts
     * @return list<array<string, mixed>>
     */
    private static function dedupeAlertList(array $alerts): array
    {
        $seen = [];
        $unique = [];
        foreach ($alerts as $alert) {
            $key = ($alert['code'] ?? '') . '|' . ($alert['title'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $alert;
        }

        return $unique;
    }

    /** @return array<string, mixed> */
    public static function weeklyReview(): array
    {
        $state = self::loadAll();
        $insights = self::insights();

        $actionsDone = 0;
        $actionsOpen = 0;
        foreach ($state['actions'] as $a) {
            if (($a['status'] ?? '') === 'done') {
                $actionsDone++;
            } else {
                $actionsOpen++;
            }
        }

        $published = 0;
        $drafts = 0;
        foreach ($state['content'] as $c) {
            if (($c['status'] ?? '') === 'published') {
                $published++;
            }
            if (($c['status'] ?? '') === 'draft') {
                $drafts++;
            }
        }

        $belowTarget = 0;
        foreach ($state['indicators'] as $ind) {
            $t = (float) ($ind['target'] ?? 0);
            $v = (float) ($ind['value'] ?? 0);
            if ($t > 0 && ($v / $t) < 0.6) {
                $belowTarget++;
            }
        }

        return [
            'summary' => [
                'indicators_below_target' => $belowTarget,
                'indicators_total' => count($state['indicators']),
                'actions_done' => $actionsDone,
                'actions_open' => $actionsOpen,
                'content_published' => $published,
                'content_drafts' => $drafts,
            ],
            'live' => $insights['live'],
            'top_missing_oem' => $insights['top_missing_oem'],
            'alerts' => self::alerts(),
            'week_label' => date('d M', strtotime('monday this week')) . ' – ' . date('d M Y'),
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private static function replaceIndicators(PDO $pdo, array $rows, ?int $userId): void
    {
        $pdo->exec('DELETE FROM marketing_indicators');
        $withCategory = self::hasIndicatorCategoryColumn();
        if ($withCategory) {
            $stmt = $pdo->prepare(
                'INSERT INTO marketing_indicators (id, platform, url, metric, value, target, source, auto_key, notes, linked_okr_id, category, user_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO marketing_indicators (id, platform, url, metric, value, target, source, auto_key, notes, linked_okr_id, user_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? self::newId('ind'));
            $base = [
                $id,
                (string) ($row['platform'] ?? ''),
                (string) ($row['url'] ?? ''),
                (string) ($row['metric'] ?? ''),
                (float) ($row['value'] ?? 0),
                (float) ($row['target'] ?? 0),
                (string) ($row['source'] ?? 'manual'),
                $row['autoKey'] ?? $row['auto_key'] ?? null,
                (string) ($row['notes'] ?? ''),
                $row['linkedOkrId'] ?? $row['linked_okr_id'] ?? null,
            ];
            if ($withCategory) {
                $stmt->execute(array_merge($base, [
                    (string) ($row['category'] ?? 'general'),
                    $userId,
                    self::normalizeDatetime($row['createdAt'] ?? $row['created_at'] ?? null),
                    self::normalizeDatetime($row['updatedAt'] ?? $row['updated_at'] ?? null),
                ]));
            } else {
                $stmt->execute(array_merge($base, [
                    $userId,
                    self::normalizeDatetime($row['createdAt'] ?? $row['created_at'] ?? null),
                    self::normalizeDatetime($row['updatedAt'] ?? $row['updated_at'] ?? null),
                ]));
            }
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private static function replaceActions(PDO $pdo, array $rows, ?int $userId): void
    {
        $pdo->exec('DELETE FROM marketing_actions');
        $withCampaign = MarketingHubService::hasActionCampaignColumn();
        if ($withCampaign) {
            $stmt = $pdo->prepare(
                'INSERT INTO marketing_actions (id, title, target_kpi, indicator_id, campaign_id, kanban_step, deadline, priority, status, effort, impact, score, notes, playbook_key, user_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO marketing_actions (id, title, target_kpi, indicator_id, deadline, priority, status, effort, impact, score, notes, playbook_key, user_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $effort = max(1, min(5, (int) ($row['effort'] ?? 3)));
            $impact = max(1, min(5, (int) ($row['impact'] ?? 3)));
            $score = self::actionScore($impact, $effort, (string) ($row['priority'] ?? 'medium'), (string) ($row['status'] ?? 'todo'));
            $base = [
                (string) ($row['id'] ?? self::newId('act')),
                (string) ($row['title'] ?? ''),
                (string) ($row['targetKpi'] ?? $row['target_kpi'] ?? ''),
                $row['indicatorId'] ?? $row['indicator_id'] ?? null,
            ];
            if ($withCampaign) {
                $stmt->execute(array_merge($base, [
                    $row['campaignId'] ?? $row['campaign_id'] ?? null,
                    $row['kanbanStep'] ?? $row['kanban_step'] ?? null,
                    self::normalizeDate($row['deadline'] ?? null),
                    (string) ($row['priority'] ?? 'medium'),
                    (string) ($row['status'] ?? 'todo'),
                    $effort,
                    $impact,
                    $score,
                    (string) ($row['notes'] ?? ''),
                    $row['playbookKey'] ?? $row['playbook_key'] ?? null,
                    $userId,
                    self::normalizeDatetime($row['createdAt'] ?? $row['created_at'] ?? null),
                    self::normalizeDatetime($row['updatedAt'] ?? $row['updated_at'] ?? null),
                ]));
            } else {
                $stmt->execute(array_merge($base, [
                    self::normalizeDate($row['deadline'] ?? null),
                    (string) ($row['priority'] ?? 'medium'),
                    (string) ($row['status'] ?? 'todo'),
                    $effort,
                    $impact,
                    $score,
                    (string) ($row['notes'] ?? ''),
                    $row['playbookKey'] ?? $row['playbook_key'] ?? null,
                    $userId,
                    self::normalizeDatetime($row['createdAt'] ?? $row['created_at'] ?? null),
                    self::normalizeDatetime($row['updatedAt'] ?? $row['updated_at'] ?? null),
                ]));
            }
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private static function replaceContent(PDO $pdo, array $rows, ?int $userId): void
    {
        $pdo->exec('DELETE FROM marketing_content');
        $withCampaign = MarketingHubService::hasContentCampaignColumn();
        if ($withCampaign) {
            $stmt = $pdo->prepare(
                'INSERT INTO marketing_content (id, title, platform, format, status, publish_date, linked_action_id, linked_indicator_id, campaign_id, body, template_key, metrics_json, user_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO marketing_content (id, title, platform, format, status, publish_date, linked_action_id, linked_indicator_id, body, template_key, metrics_json, user_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $metrics = $row['metrics'] ?? $row['metrics_json'] ?? null;
            if (is_array($metrics)) {
                $metrics = json_encode($metrics, JSON_UNESCAPED_UNICODE);
            }
            $base = [
                (string) ($row['id'] ?? self::newId('cnt')),
                (string) ($row['title'] ?? ''),
                (string) ($row['platform'] ?? ''),
                (string) ($row['format'] ?? 'post'),
                (string) ($row['status'] ?? 'draft'),
                self::normalizeDate($row['publishDate'] ?? $row['publish_date'] ?? null),
                $row['linkedActionId'] ?? $row['linked_action_id'] ?? $row['linkedAction'] ?? null,
                $row['linkedIndicatorId'] ?? $row['linked_indicator_id'] ?? null,
            ];
            if ($withCampaign) {
                $stmt->execute(array_merge($base, [
                    $row['campaignId'] ?? $row['campaign_id'] ?? null,
                    (string) ($row['body'] ?? ''),
                    $row['templateKey'] ?? $row['template_key'] ?? null,
                    $metrics,
                    $userId,
                    self::normalizeDatetime($row['createdAt'] ?? $row['created_at'] ?? null),
                    self::normalizeDatetime($row['updatedAt'] ?? $row['updated_at'] ?? null),
                ]));
            } else {
                $stmt->execute(array_merge($base, [
                    (string) ($row['body'] ?? ''),
                    $row['templateKey'] ?? $row['template_key'] ?? null,
                    $metrics,
                    $userId,
                    self::normalizeDatetime($row['createdAt'] ?? $row['created_at'] ?? null),
                    self::normalizeDatetime($row['updatedAt'] ?? $row['updated_at'] ?? null),
                ]));
            }
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private static function replaceOkrs(PDO $pdo, array $rows): void
    {
        $pdo->exec('DELETE FROM marketing_okrs');
        $stmt = $pdo->prepare(
            'INSERT INTO marketing_okrs (id, quarter_label, title, target_value, current_value, unit, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $stmt->execute([
                (string) ($row['id'] ?? self::newId('okr')),
                (string) ($row['quarterLabel'] ?? $row['quarter_label'] ?? self::currentQuarterLabel()),
                (string) ($row['title'] ?? ''),
                (float) ($row['targetValue'] ?? $row['target_value'] ?? 0),
                (float) ($row['currentValue'] ?? $row['current_value'] ?? 0),
                (string) ($row['unit'] ?? ''),
                (string) ($row['status'] ?? 'active'),
                self::normalizeDatetime($row['createdAt'] ?? null),
                self::normalizeDatetime($row['updatedAt'] ?? null),
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private static function fetchIndicators(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT * FROM marketing_indicators ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r): array => self::mapIndicator($r), $rows);
    }

    /** @return list<array<string, mixed>> */
    private static function fetchActions(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT * FROM marketing_actions ORDER BY score DESC, updated_at DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r): array => self::mapAction($r), $rows);
    }

    /** @return list<array<string, mixed>> */
    private static function fetchContent(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT * FROM marketing_content ORDER BY publish_date ASC, updated_at DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r): array => self::mapContent($r), $rows);
    }

    /** @return list<array<string, mixed>> */
    private static function fetchOkrs(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT * FROM marketing_okrs ORDER BY quarter_label DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r): array => self::mapOkr($r), $rows);
    }

    /** @param array<string, mixed> $r */
    private static function mapIndicator(array $r): array
    {
        return [
            'id' => (string) ($r['id'] ?? ''),
            'platform' => (string) ($r['platform'] ?? ''),
            'url' => (string) ($r['url'] ?? ''),
            'metric' => (string) ($r['metric'] ?? ''),
            'value' => (float) ($r['value'] ?? 0),
            'target' => (float) ($r['target'] ?? 0),
            'source' => (string) ($r['source'] ?? 'manual'),
            'autoKey' => $r['auto_key'] ?? null,
            'category' => (string) ($r['category'] ?? 'general'),
            'notes' => (string) ($r['notes'] ?? ''),
            'linkedOkrId' => $r['linked_okr_id'] ?? null,
            'updatedAt' => (string) ($r['updated_at'] ?? ''),
            'createdAt' => (string) ($r['created_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $r */
    private static function mapAction(array $r): array
    {
        return [
            'id' => (string) ($r['id'] ?? ''),
            'title' => (string) ($r['title'] ?? ''),
            'targetKpi' => (string) ($r['target_kpi'] ?? ''),
            'indicatorId' => $r['indicator_id'] ?? null,
            'deadline' => $r['deadline'] ?? '',
            'priority' => (string) ($r['priority'] ?? 'medium'),
            'status' => (string) ($r['status'] ?? 'todo'),
            'effort' => (int) ($r['effort'] ?? 3),
            'impact' => (int) ($r['impact'] ?? 3),
            'score' => (int) ($r['score'] ?? 0),
            'notes' => (string) ($r['notes'] ?? ''),
            'playbookKey' => $r['playbook_key'] ?? null,
            'campaignId' => $r['campaign_id'] ?? null,
            'kanbanStep' => $r['kanban_step'] ?? null,
            'createdAt' => (string) ($r['created_at'] ?? ''),
            'updatedAt' => (string) ($r['updated_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $r */
    private static function mapContent(array $r): array
    {
        return [
            'id' => (string) ($r['id'] ?? ''),
            'title' => (string) ($r['title'] ?? ''),
            'platform' => (string) ($r['platform'] ?? ''),
            'format' => (string) ($r['format'] ?? 'post'),
            'status' => (string) ($r['status'] ?? 'draft'),
            'publishDate' => $r['publish_date'] ?? '',
            'linkedActionId' => $r['linked_action_id'] ?? null,
            'linkedIndicatorId' => $r['linked_indicator_id'] ?? null,
            'linkedAction' => $r['linked_action_id'] ?? '',
            'body' => (string) ($r['body'] ?? ''),
            'templateKey' => $r['template_key'] ?? null,
            'campaignId' => $r['campaign_id'] ?? null,
            'metrics' => self::decodeJsonField($r['metrics_json'] ?? null),
            'createdAt' => (string) ($r['created_at'] ?? ''),
            'updatedAt' => (string) ($r['updated_at'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private static function decodeJsonField(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $r */
    private static function mapOkr(array $r): array
    {
        return [
            'id' => (string) ($r['id'] ?? ''),
            'quarterLabel' => (string) ($r['quarter_label'] ?? ''),
            'title' => (string) ($r['title'] ?? ''),
            'targetValue' => (float) ($r['target_value'] ?? 0),
            'currentValue' => (float) ($r['current_value'] ?? 0),
            'unit' => (string) ($r['unit'] ?? ''),
            'status' => (string) ($r['status'] ?? 'active'),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function findByAutoKey(PDO $pdo, string $autoKey): ?array
    {
        $stmt = $pdo->prepare('SELECT id FROM marketing_indicators WHERE auto_key = ? LIMIT 1');
        $stmt->execute([$autoKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @param array<string, float|int> $live @param list<array<string, mixed>> $topMissing */
    /** @return list<array<string, string>> */
    private static function buildSuggestions(array $live, array $topMissing): array
    {
        $out = [];
        if (($live['missing_codes'] ?? 0) > 10) {
            $out[] = [
                'type' => 'indicator',
                'title' => 'Adaugă indicator OEM lipsă',
                'detail' => (string) $live['missing_codes'] . ' coduri unice negăsite — oportunitate stoc.',
                'action' => 'create_missing_oem_indicator',
            ];
        }
        if (($live['success_rate'] ?? 100) < 70) {
            $out[] = [
                'type' => 'action',
                'title' => 'Playbook SEO catalog',
                'detail' => 'Rată succes sub 70% — extinde stocul pentru căutări frecvente.',
                'action' => 'playbook_seo_catalog',
            ];
        }
        if ($topMissing !== []) {
            $first = $topMissing[0];
            $out[] = [
                'type' => 'content',
                'title' => 'Post despre OEM ' . ($first['code'] ?? ''),
                'detail' => 'Căutat de ' . ($first['count'] ?? 0) . ' ori — conținut + CTA stoc.',
                'action' => 'content_oem_post',
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function plannerHints(): array
    {
        return [
            'note' => 'Completează Planner financiar — utilizatori × conversie × preț mediu.',
            'target_users' => null,
            'conversion_rate' => null,
            'avg_price' => null,
            'estimated_orders_month' => null,
            'estimated_visits_hint' => 'Regulă practică: vizite ≈ utilizatori țintă × 3 (3 pagini/sesiune).',
        ];
    }

    public static function actionScore(int $impact, int $effort, string $priority, string $status): int
    {
        if ($status === 'done') {
            return 0;
        }
        $pBonus = match ($priority) {
            'high' => 30,
            'low' => -10,
            default => 0,
        };

        return max(0, ($impact * 20) + $pBonus - ($effort * 5));
    }

    public static function newId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(8));
    }

    public static function currentQuarterLabel(): string
    {
        $q = (int) ceil((int) date('n') / 3);

        return 'Q' . $q . ' ' . date('Y');
    }

    private static function normalizeDate(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = substr((string) $v, 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }

    private static function normalizeDatetime(mixed $v): string
    {
        if ($v === null || $v === '') {
            return date('Y-m-d H:i:s');
        }
        $ts = strtotime((string) $v);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
    }
}
