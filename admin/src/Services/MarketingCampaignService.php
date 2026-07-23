<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * Marketing Hub v2 - campanii, probleme detectate, rezultat, dovezi.
 */
final class MarketingCampaignService
{
    /** @var array<string, string> */
    public const KPI_METRICS = [
        'visitors' => 'Vizitatori',
        'traffic' => 'Trafic / sesiuni',
        'leads' => 'Lead-uri',
        'conversions' => 'Conversii',
        'listings' => 'Listări active',
        'searches' => 'Căutări găsite',
    ];

    public static function tablesExist(): bool
    {
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query("SHOW TABLES LIKE 'marketing_campaigns'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /** Creeaza tabela campanii + coloana kpi_metric daca lipsesc (067 trebuie rulat inainte). */
    public static function ensureSchema(): bool
    {
        if (!MarketingHubService::tablesExist()) {
            return false;
        }

        $pdo = Database::getDB();
        if (!self::tablesExist()) {
            try {
                $pdo->exec(
                    "CREATE TABLE IF NOT EXISTS marketing_campaigns (
                    id VARCHAR(32) NOT NULL PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    problem_type VARCHAR(32) NOT NULL DEFAULT 'stock',
                    channel VARCHAR(32) NOT NULL DEFAULT 'site',
                    status VARCHAR(24) NOT NULL DEFAULT 'identified',
                    problem_summary TEXT,
                    focus_oem VARCHAR(120) DEFAULT NULL,
                    baseline_value DECIMAL(14,2) NOT NULL DEFAULT 0,
                    target_value DECIMAL(14,2) NOT NULL DEFAULT 0,
                    current_value DECIMAL(14,2) NOT NULL DEFAULT 0,
                    impact_est_ron DECIMAL(14,2) NOT NULL DEFAULT 0,
                    playbook_key VARCHAR(64) DEFAULT NULL,
                    checklist_json JSON DEFAULT NULL,
                    evidence_before_json JSON DEFAULT NULL,
                    evidence_after_json JSON DEFAULT NULL,
                    priority_score INT NOT NULL DEFAULT 0,
                    is_focus TINYINT(1) NOT NULL DEFAULT 0,
                    notes TEXT,
                    user_id INT UNSIGNED DEFAULT NULL,
                    started_at DATETIME DEFAULT NULL,
                    measured_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_mcamp_status (status),
                    KEY idx_mcamp_channel (channel),
                    KEY idx_mcamp_focus (is_focus),
                    KEY idx_mcamp_score (priority_score)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            } catch (Throwable $e) {
                error_log('[MarketingCampaignService] ensureSchema create: ' . $e->getMessage());
            }
        }

        self::ensureKpiMetricColumn($pdo);
        MarketingHubService::ensureHubColumns();

        return self::tablesExist();
    }

    private static ?bool $hasKpiMetricCol = null;

    private static function hasKpiMetricColumn(): bool
    {
        if (self::$hasKpiMetricCol !== null) {
            return self::$hasKpiMetricCol;
        }
        if (!self::tablesExist()) {
            self::$hasKpiMetricCol = false;

            return false;
        }
        try {
            $stmt = Database::getDB()->query("SHOW COLUMNS FROM marketing_campaigns LIKE 'kpi_metric'");
            self::$hasKpiMetricCol = $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            self::$hasKpiMetricCol = false;
        }

        return self::$hasKpiMetricCol;
    }

    private static function ensureKpiMetricColumn(PDO $pdo): void
    {
        if (!self::tablesExist() || self::hasKpiMetricColumn()) {
            return;
        }
        try {
            $pdo->exec(
                "ALTER TABLE marketing_campaigns ADD COLUMN kpi_metric VARCHAR(32) NOT NULL DEFAULT 'leads'
                    COMMENT 'visitors|traffic|leads|conversions|listings|searches' AFTER current_value"
            );
            self::$hasKpiMetricCol = true;
        } catch (Throwable $e) {
            error_log('[MarketingCampaignService] ensureKpiMetricColumn: ' . $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    public static function hubBundle(): array
    {
        try {
            return self::hubBundleInternal();
        } catch (Throwable $e) {
            error_log('[MarketingCampaignService] hubBundle: ' . $e->getMessage());

            return self::hubBundleFallback($e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private static function hubBundleInternal(): array
    {
        self::ensureSchema();
        MarketingHubService::ensureHubColumns();

        try {
            MarketingHubService::ensurePlatformIndicators(null);
        } catch (Throwable $e) {
            error_log('[MarketingCampaignService] hubBundle indicators: ' . $e->getMessage());
        }

        $state = MarketingHubService::loadAll();
        try {
            $insights = MarketingHubService::insights();
        } catch (Throwable $e) {
            error_log('[MarketingCampaignService] hubBundle insights: ' . $e->getMessage());
            $insights = [
                'live' => [
                    'searches_today' => 0,
                    'searches_total' => 0,
                    'missing_codes' => 0,
                    'success_rate' => 0,
                    'orders_today' => 0,
                    'revenue_today' => 0,
                ],
                'top_missing_oem' => [],
            ];
        }
        $live = is_array($insights['live'] ?? null) ? $insights['live'] : [];

        $campaigns = self::fetchCampaigns();
        $usedDemoFallback = false;

        if ($campaigns === []) {
            $demo = self::buildDemoPayload();
            $campaigns = $demo['campaigns'];
            if (($state['actions'] ?? []) === []) {
                $state['actions'] = $demo['actions'];
            }
            if (($state['content'] ?? []) === []) {
                $state['content'] = $demo['content'];
            }
            if (($state['indicators'] ?? []) === []) {
                $state['indicators'] = MarketingHubService::buildDemoPlatformIndicators();
            }
            $usedDemoFallback = true;
            if (self::tablesExist() && MarketingHubService::tablesExist()) {
                try {
                    MarketingHubService::saveState([
                        'campaigns' => $campaigns,
                        'actions' => $state['actions'],
                        'content' => $state['content'],
                        'indicators' => $state['indicators'],
                    ], null);
                    $persisted = self::fetchCampaigns();
                    if ($persisted !== []) {
                        $campaigns = $persisted;
                        $state = MarketingHubService::loadAll();
                    }
                } catch (Throwable $e) {
                    error_log('[MarketingCampaignService] hubBundle demo persist: ' . $e->getMessage());
                }
            }
        }

        $problems = self::detectProblems($insights);
        $plannerSnapshot = MarketingPlannerBridgeService::loadSnapshot();
        if ($plannerSnapshot !== null) {
            $plannerProblems = MarketingPlannerBridgeService::plannerProblems($plannerSnapshot, $live, $state['indicators'] ?? []);
            $problems = array_merge($plannerProblems, $problems);
            usort($problems, static fn (array $a, array $b): int => ($b['impactScore'] ?? 0) <=> ($a['impactScore'] ?? 0));
            $problems = array_slice($problems, 0, 10);
        }
        $result = self::resultSummary($live, $state, $campaigns);
        $result = MarketingPlannerBridgeService::enrichResult(
            $result,
            $plannerSnapshot,
            $live,
            $state['indicators'] ?? []
        );

        try {
            $weekly = MarketingHubService::weeklyReview();
        } catch (Throwable) {
            $weekly = [];
        }

        try {
            $alerts = MarketingHubService::alerts();
        } catch (Throwable) {
            $alerts = [];
        }

        return array_merge($state, [
            'insights' => $insights,
            'alerts' => $alerts,
            'playbooks' => array_values(MarketingHubService::playbooks()),
            'content_templates' => array_values(MarketingHubService::contentTemplates()),
            'campaigns' => $campaigns,
            'problems' => $problems,
            'result' => $result,
            'weekly' => $weekly,
            'focus_limit' => 3,
            'demo_fallback' => $usedDemoFallback,
            'planner_goal' => MarketingPlannerBridgeService::loadSnapshot(),
        ]);
    }

    /** @return array<string, mixed> */
    public static function hubBundleFallback(string $errorMessage = ''): array
    {
        $demo = self::buildDemoPayload();

        return array_merge(MarketingHubService::emptyStorageState('fallback'), [
            'indicators' => MarketingHubService::buildDemoPlatformIndicators(),
            'actions' => $demo['actions'],
            'content' => $demo['content'],
            'campaigns' => $demo['campaigns'],
            'problems' => [],
            'result' => [
                'headline' => 'Marketing — mod degradat',
                'orders_today' => 0,
                'revenue_today' => 0,
                'searches_today' => 0,
                'missing_oem' => 0,
                'success_rate' => 0,
                'actions_done' => 0,
                'actions_open' => 0,
                'content_published' => 0,
                'campaigns_total' => count($demo['campaigns']),
                'campaigns_in_progress' => 0,
                'campaigns_measured' => 0,
            ],
            'weekly' => [],
            'insights' => ['live' => [], 'top_missing_oem' => []],
            'alerts' => [],
            'playbooks' => array_values(MarketingHubService::playbooks()),
            'content_templates' => array_values(MarketingHubService::contentTemplates()),
            'focus_limit' => 3,
            'demo_fallback' => true,
            'hub_error' => $errorMessage,
        ]);
    }

    /** @param list<array<string, mixed>> $campaigns @param list<array<string, mixed>> $actions @param list<array<string, mixed>> $content */
    private static function countLinkedExecItems(array $campaigns, array $actions, array $content): int
    {
        $ids = [];
        foreach ($campaigns as $c) {
            $id = (string) ($c['id'] ?? '');
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return 0;
        }

        $linked = 0;
        foreach ($actions as $a) {
            if (isset($ids[(string) ($a['campaignId'] ?? '')])) {
                $linked++;
            }
        }
        foreach ($content as $cnt) {
            if (isset($ids[(string) ($cnt['campaignId'] ?? '')])) {
                $linked++;
            }
        }

        return $linked;
    }

    /** @return list<array<string, mixed>> */
    public static function fetchCampaigns(): array
    {
        if (!self::tablesExist()) {
            return [];
        }

        try {
            $pdo = Database::getDB();
            $rows = $pdo->query(
                'SELECT * FROM marketing_campaigns ORDER BY is_focus DESC, priority_score DESC, updated_at DESC'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(static fn (array $r): array => self::mapCampaign($r), $rows);
        } catch (Throwable $e) {
            error_log('[MarketingCampaignService] fetchCampaigns: ' . $e->getMessage());

            return [];
        }
    }

    /** @param array<string, mixed> $payload */
    public static function saveCampaigns(array $payload, ?int $userId = null): bool
    {
        if (!self::tablesExist() || !isset($payload['campaigns']) || !is_array($payload['campaigns'])) {
            return false;
        }

        $rows = array_values(array_filter($payload['campaigns'], static fn ($row): bool => is_array($row)));
        if ($rows === []) {
            return true;
        }
        $payload['campaigns'] = $rows;

        self::ensureKpiMetricColumn(Database::getDB());
        $withKpi = self::hasKpiMetricColumn();

        $pdo = Database::getDB();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        try {
            $pdo->exec('DELETE FROM marketing_campaigns');
            if ($withKpi) {
                $stmt = $pdo->prepare(
                    'INSERT INTO marketing_campaigns (
                    id, title, problem_type, channel, status, problem_summary, focus_oem,
                    baseline_value, target_value, current_value, kpi_metric, impact_est_ron, playbook_key,
                    checklist_json, evidence_before_json, evidence_after_json, priority_score,
                    is_focus, notes, user_id, started_at, measured_at, created_at, updated_at
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO marketing_campaigns (
                    id, title, problem_type, channel, status, problem_summary, focus_oem,
                    baseline_value, target_value, current_value, impact_est_ron, playbook_key,
                    checklist_json, evidence_before_json, evidence_after_json, priority_score,
                    is_focus, notes, user_id, started_at, measured_at, created_at, updated_at
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
            }

            foreach ($payload['campaigns'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $current = (float) ($row['currentValue'] ?? $row['current_value'] ?? 0);
                $target = (float) ($row['targetValue'] ?? $row['target_value'] ?? 0);
                $channel = (string) ($row['channel'] ?? 'site');
                $kpiMetric = (string) ($row['kpiMetric'] ?? $row['kpi_metric'] ?? self::defaultKpiForChannel($channel));
                if (!isset(self::KPI_METRICS[$kpiMetric])) {
                    $kpiMetric = self::defaultKpiForChannel($channel);
                }
                $base = [
                    (string) ($row['id'] ?? MarketingHubService::newId('cmp')),
                    (string) ($row['title'] ?? ''),
                    (string) ($row['problemType'] ?? $row['problem_type'] ?? 'stock'),
                    $channel,
                    (string) ($row['status'] ?? 'identified'),
                    (string) ($row['problemSummary'] ?? $row['problem_summary'] ?? ''),
                    $row['focusOem'] ?? $row['focus_oem'] ?? null,
                    (float) ($row['baselineValue'] ?? $row['baseline_value'] ?? $current),
                    $target,
                    $current,
                ];
                $tail = [
                    0,
                    $row['playbookKey'] ?? $row['playbook_key'] ?? null,
                    self::encodeJson($row['checklist'] ?? $row['checklist_json'] ?? []),
                    self::encodeJson($row['evidenceBefore'] ?? $row['evidence_before'] ?? []),
                    self::encodeJson($row['evidenceAfter'] ?? $row['evidence_after'] ?? []),
                    (int) ($row['priorityScore'] ?? $row['priority_score'] ?? 0),
                    !empty($row['isFocus'] ?? $row['is_focus']) ? 1 : 0,
                    (string) ($row['notes'] ?? ''),
                    $userId,
                    self::normalizeDatetime($row['startedAt'] ?? $row['started_at'] ?? null),
                    self::normalizeDatetime($row['measuredAt'] ?? $row['measured_at'] ?? null),
                    self::normalizeDatetime($row['createdAt'] ?? null),
                    self::normalizeDatetime($row['updatedAt'] ?? null),
                ];
                if ($withKpi) {
                    $stmt->execute([...$base, $kpiMetric, ...$tail]);
                } else {
                    $stmt->execute([...$base, ...$tail]);
                }
            }

            if ($ownTx) {
                $pdo->commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[MarketingCampaignService] saveCampaigns: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @param array<string, mixed> $insights
     * @return list<array<string, mixed>>
     */
    public static function detectProblems(array $insights): array
    {
        $live = is_array($insights['live'] ?? null) ? $insights['live'] : [];
        $topMissing = is_array($insights['top_missing_oem'] ?? null) ? $insights['top_missing_oem'] : [];
        $problems = [];

        $missing = (int) ($live['missing_codes'] ?? 0);
        if ($missing > 0) {
            $problems[] = [
                'id' => 'prob_missing_oem',
                'type' => 'stock',
                'channel' => 'site',
                'title' => 'Coduri OEM lipsa din stoc',
                'summary' => $missing . ' coduri diferite cautate fara rezultat in catalog.',
                'metric' => 'coduri OEM',
                'current' => $missing,
                'target' => max(10, (int) round($missing * 0.5)),
                'unit' => 'coduri',
                'kpiMetric' => 'searches',
                'impactScore' => $missing,
                'playbookKey' => 'seo_catalog',
                'severity' => $missing > 50 ? 'critical' : 'warning',
            ];
        }

        $successRate = (float) ($live['success_rate'] ?? 100);
        if ($successRate < 75) {
            $problems[] = [
                'id' => 'prob_low_success',
                'type' => 'stock',
                'channel' => 'site',
                'title' => 'Rata succes cautari scazuta',
                'summary' => 'Doar ' . $successRate . '% din cautari gasesc piesa - extinde stocul.',
                'metric' => 'rata succes',
                'current' => $successRate,
                'target' => 75,
                'unit' => '%',
                'kpiMetric' => 'searches',
                'impactScore' => (int) max(0, 75 - $successRate),
                'playbookKey' => 'seo_catalog',
                'severity' => $successRate < 50 ? 'critical' : 'warning',
            ];
        }

        foreach (array_slice($topMissing, 0, 5) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = (string) ($row['code'] ?? '');
            $count = (int) ($row['count'] ?? 0);
            if ($code === '' || $count < 2) {
                continue;
            }
            $problems[] = [
                'id' => 'prob_oem_' . md5($code),
                'type' => 'stock',
                'channel' => 'site',
                'title' => 'OEM ' . $code . ' cautat des',
                'summary' => 'Cautat de ' . $count . ' ori - import sau publica produs.',
                'metric' => 'cautari OEM',
                'current' => $count,
                'target' => 0,
                'unit' => 'cautari',
                'focusOem' => $code,
                'kpiMetric' => 'searches',
                'impactScore' => $count,
                'playbookKey' => 'seo_catalog',
                'severity' => $count >= 10 ? 'critical' : 'warning',
            ];
        }

        if ((int) ($live['orders_today'] ?? 0) === 0 && (int) ($live['searches_today'] ?? 0) > 5) {
            $problems[] = [
                'id' => 'prob_no_orders',
                'type' => 'traffic',
                'channel' => 'site',
                'title' => 'Trafic fara comenzi azi',
                'summary' => (int) $live['searches_today'] . ' cautari azi, 0 comenzi - verifica oferta si CTA.',
                'metric' => 'comenzi azi',
                'current' => 0,
                'target' => 1,
                'unit' => 'comenzi',
                'kpiMetric' => 'conversions',
                'impactScore' => max(1, (int) ($live['searches_today'] ?? 0)),
                'playbookKey' => 'fb_weekly',
                'severity' => 'warning',
            ];
        }

        $problems[] = [
            'id' => 'prob_marketplace',
            'type' => 'marketplace',
            'channel' => 'pieseauto',
            'title' => 'PieseAuto.ro — listări',
            'summary' => 'Republică anunțuri și sincronizează stoc pe PieseAuto.ro.',
            'metric' => 'anunturi active',
            'current' => 0,
            'target' => 50,
            'unit' => 'anunturi',
            'kpiMetric' => 'listings',
            'impactScore' => 50,
            'playbookKey' => 'pieseauto_refresh',
            'severity' => 'info',
        ];

        usort($problems, static fn (array $a, array $b): int => ($b['impactScore'] <=> $a['impactScore']));

        return array_slice($problems, 0, 8);
    }

    /**
     * @param array<string, mixed> $live
     * @param array<string, mixed> $state
     * @param list<array<string, mixed>> $campaigns
     * @return array<string, mixed>
     */
    public static function resultSummary(array $live, array $state, array $campaigns): array
    {
        $actionsDone = 0;
        $actionsOpen = 0;
        foreach ($state['actions'] ?? [] as $a) {
            if (!is_array($a)) {
                continue;
            }
            if (($a['status'] ?? '') === 'done') {
                $actionsDone++;
            } else {
                $actionsOpen++;
            }
        }

        $published = 0;
        foreach ($state['content'] ?? [] as $c) {
            if (is_array($c) && ($c['status'] ?? '') === 'published') {
                $published++;
            }
        }

        $measured = 0;
        $inProgress = 0;
        foreach ($campaigns as $cmp) {
            if (($cmp['status'] ?? '') === 'measured' || ($cmp['status'] ?? '') === 'done') {
                $measured++;
            }
            if (($cmp['status'] ?? '') === 'in_progress') {
                $inProgress++;
            }
        }

        return [
            'orders_today' => (int) ($live['orders_today'] ?? 0),
            'revenue_today' => (float) ($live['revenue_today'] ?? 0),
            'searches_today' => (int) ($live['searches_today'] ?? 0),
            'missing_oem' => (int) ($live['missing_codes'] ?? 0),
            'success_rate' => (float) ($live['success_rate'] ?? 0),
            'actions_done' => $actionsDone,
            'actions_open' => $actionsOpen,
            'content_published' => $published,
            'campaigns_total' => count($campaigns),
            'campaigns_in_progress' => $inProgress,
            'campaigns_measured' => $measured,
            'headline' => self::resultHeadline($live, $actionsDone, $measured),
        ];
    }

    /** @param array<string, mixed> $problem
     * @return array<string, mixed>
     */
    public static function createCampaignFromProblem(array $problem, ?int $userId = null): array
    {
        unset($userId);
        $playbooks = MarketingHubService::playbooks();
        $pbKey = (string) ($problem['playbookKey'] ?? 'seo_catalog');
        $pb = $playbooks[$pbKey] ?? null;

        $campaign = [
            'id' => MarketingHubService::newId('cmp'),
            'title' => (string) ($problem['title'] ?? 'Campanie noua'),
            'problemType' => (string) ($problem['type'] ?? 'stock'),
            'channel' => (string) ($problem['channel'] ?? 'site'),
            'status' => 'identified',
            'problemSummary' => (string) ($problem['summary'] ?? ''),
            'focusOem' => $problem['focusOem'] ?? null,
            'baselineValue' => (float) ($problem['current'] ?? 0),
            'targetValue' => (float) ($problem['target'] ?? 0),
            'currentValue' => (float) ($problem['current'] ?? 0),
            'kpiMetric' => (string) ($problem['kpiMetric'] ?? self::inferKpiFromProblem($problem)),
            'playbookKey' => $pbKey,
            'checklist' => self::defaultChecklist($pbKey),
            'evidenceBefore' => [
                'captured_at' => date('c'),
                'note' => 'Snapshot la crearea campaniei',
            ],
            'evidenceAfter' => [],
            'priorityScore' => self::priorityScore(
                (float) ($problem['current'] ?? 0),
                (float) ($problem['target'] ?? 0),
                (int) ($pb['effort'] ?? 3)
            ),
            'isFocus' => true,
            'notes' => $pb['notes'] ?? '',
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
        ];

        return [
            'campaign' => $campaign,
            'actions' => self::spawnActionsForCampaign($campaign, $pb),
            'content' => self::spawnContentForCampaign($campaign, $problem),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function defaultChecklistPublic(string $playbookKey): array
    {
        return self::defaultChecklist($playbookKey);
    }

    /** @return list<array<string, mixed>> */
    private static function defaultChecklist(string $playbookKey): array
    {
        return match ($playbookKey) {
            'seo_catalog' => [
                ['id' => 'c1', 'label' => 'Identifica top 5 OEM negasite', 'done' => false],
                ['id' => 'c2', 'label' => 'Import / publica produse in catalog', 'done' => false],
                ['id' => 'c3', 'label' => 'Verifica in Search Logs', 'done' => false],
            ],
            'fb_weekly' => [
                ['id' => 'c1', 'label' => 'Scrie post cu CTA WhatsApp', 'done' => false],
                ['id' => 'c2', 'label' => 'Publica pe Facebook', 'done' => false],
                ['id' => 'c3', 'label' => 'Noteaza reach / click-uri', 'done' => false],
            ],
            'pieseauto_refresh', 'olx_refresh' => [
                ['id' => 'c1', 'label' => 'Selecteaza anunturi de republicat', 'done' => false],
                ['id' => 'c2', 'label' => 'Actualizeaza pret + stoc', 'done' => false],
                ['id' => 'c3', 'label' => 'Marcheaza publicat', 'done' => false],
            ],
            default => [
                ['id' => 'c1', 'label' => 'Defineste actiunea', 'done' => false],
                ['id' => 'c2', 'label' => 'Executa si publica', 'done' => false],
                ['id' => 'c3', 'label' => 'Masoara dupa 7 zile', 'done' => false],
            ],
        };
    }

    /** @param array<string, mixed> $campaign
     * @param array<string, mixed>|null $pb
     * @return list<array<string, mixed>>
     */
    private static function spawnActionsForCampaign(array $campaign, ?array $pb): array
    {
        $now = date('c');
        $act = [
            'id' => MarketingHubService::newId('act'),
            'title' => $pb['title'] ?? ('Actiune: ' . $campaign['title']),
            'targetKpi' => $pb['targetKpi'] ?? '',
            'campaignId' => $campaign['id'],
            'kanbanStep' => 'identified',
            'deadline' => '',
            'priority' => $pb['priority'] ?? 'high',
            'status' => 'todo',
            'effort' => (int) ($pb['effort'] ?? 3),
            'impact' => (int) ($pb['impact'] ?? 4),
            'channel' => (string) ($campaign['channel'] ?? 'site'),
            'notes' => (string) ($campaign['problemSummary'] ?? ''),
            'playbookKey' => $campaign['playbookKey'] ?? null,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
        $act['score'] = MarketingHubService::actionScore($act['impact'], $act['effort'], $act['priority'], $act['status']);

        return [$act];
    }

    /** @param array<string, mixed> $campaign
     * @param array<string, mixed> $problem
     * @return list<array<string, mixed>>
     */
    private static function spawnContentForCampaign(array $campaign, array $problem): array
    {
        $templates = MarketingHubService::contentTemplates();
        $key = ($campaign['channel'] ?? '') === 'pieseauto' ? 'pieseauto_ad' : 'fb_post';
        $tpl = $templates[$key] ?? $templates['fb_post'];
        $oem = (string) ($problem['focusOem'] ?? '[OEM]');
        $body = str_replace('[OEM]', $oem, (string) ($tpl['body'] ?? ''));

        return [[
            'id' => MarketingHubService::newId('cnt'),
            'title' => 'Continut: ' . $campaign['title'],
            'platform' => (string) ($tpl['platform'] ?? ''),
            'format' => (string) ($tpl['format'] ?? 'post'),
            'status' => 'draft',
            'channel' => (string) ($campaign['channel'] ?? 'site'),
            'publishDate' => '',
            'campaignId' => $campaign['id'],
            'body' => $body,
            'templateKey' => $key,
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
        ]];
    }

    private static function priorityScore(float $current, float $target, int $effort): int
    {
        return (int) max(0, round(self::kpiGap($current, $target) / max(1, $effort)));
    }

    private static function kpiGap(float $current, float $target): float
    {
        return abs($target - $current);
    }

    public static function defaultKpiForChannel(string $channel): string
    {
        return match ($channel) {
            'facebook', 'whatsapp' => 'leads',
            'google' => 'traffic',
            'pieseauto' => 'listings',
            default => 'visitors',
        };
    }

    public static function kpiMetricLabel(string $metric): string
    {
        return self::KPI_METRICS[$metric] ?? $metric;
    }

    /** @param array<string, mixed> $problem */
    private static function inferKpiFromProblem(array $problem): string
    {
        if (!empty($problem['kpiMetric']) && isset(self::KPI_METRICS[(string) $problem['kpiMetric']])) {
            return (string) $problem['kpiMetric'];
        }
        $unit = strtolower((string) ($problem['unit'] ?? ''));
        if (str_contains($unit, 'lead')) {
            return 'leads';
        }
        if (str_contains($unit, 'anun')) {
            return 'listings';
        }
        if (str_contains($unit, 'comenzi')) {
            return 'conversions';
        }
        if (str_contains($unit, '%') || str_contains($unit, 'caut') || str_contains($unit, 'cod')) {
            return 'searches';
        }

        return self::defaultKpiForChannel((string) ($problem['channel'] ?? 'site'));
    }

    /** @param array<string, mixed> $live */
    private static function resultHeadline(array $live, int $actionsDone, int $measured): string
    {
        $orders = (int) ($live['orders_today'] ?? 0);
        $rev = (float) ($live['revenue_today'] ?? 0);
        if ($orders > 0) {
            return $orders . ' comenzi azi · ' . number_format($rev, 0, ',', '.') . ' RON';
        }
        if ($actionsDone > 0 || $measured > 0) {
            return $actionsDone . ' actiuni inchise · ' . $measured . ' campanii masurate';
        }

        return 'Nicio comanda azi - deschide Probleme si creeaza o campanie';
    }

    /** @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private static function mapCampaign(array $r): array
    {
        return [
            'id' => (string) ($r['id'] ?? ''),
            'title' => (string) ($r['title'] ?? ''),
            'problemType' => (string) ($r['problem_type'] ?? 'stock'),
            'channel' => (string) ($r['channel'] ?? 'site'),
            'status' => (string) ($r['status'] ?? 'identified'),
            'problemSummary' => (string) ($r['problem_summary'] ?? ''),
            'focusOem' => $r['focus_oem'] ?? null,
            'baselineValue' => (float) ($r['baseline_value'] ?? 0),
            'targetValue' => (float) ($r['target_value'] ?? 0),
            'currentValue' => (float) ($r['current_value'] ?? 0),
            'kpiMetric' => (string) ($r['kpi_metric'] ?? self::defaultKpiForChannel((string) ($r['channel'] ?? 'site'))),
            'playbookKey' => $r['playbook_key'] ?? null,
            'checklist' => self::decodeJson($r['checklist_json'] ?? null),
            'evidenceBefore' => self::decodeJson($r['evidence_before_json'] ?? null),
            'evidenceAfter' => self::decodeJson($r['evidence_after_json'] ?? null),
            'priorityScore' => (int) ($r['priority_score'] ?? 0),
            'isFocus' => !empty($r['is_focus']),
            'notes' => (string) ($r['notes'] ?? ''),
            'startedAt' => $r['started_at'] ?? null,
            'measuredAt' => $r['measured_at'] ?? null,
            'createdAt' => (string) ($r['created_at'] ?? ''),
            'updatedAt' => (string) ($r['updated_at'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private static function decodeJson(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $d = json_decode($raw, true);

        return is_array($d) ? $d : [];
    }

    private static function encodeJson(mixed $raw): ?string
    {
        if (!is_array($raw) || $raw === []) {
            return null;
        }
        $j = json_encode($raw, JSON_UNESCAPED_UNICODE);

        return $j !== false ? $j : null;
    }

    private static function normalizeDatetime(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $ts = strtotime((string) $v);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }

    /**
     * Set demo marketing - campanii, actiuni, continut (site in lucru + canale paralele).
     *
     * @return array{campaigns: list<array<string, mixed>>, actions: list<array<string, mixed>>, content: list<array<string, mixed>>}
     */
    public static function buildDemoPayload(): array
    {
        $now = date('c');
        $campaigns = [
            [
                'id' => MarketingHubService::newId('cmp'),
                'title' => 'Lansare magazin - site în lucru',
                'problemType' => 'traffic',
                'channel' => 'site',
                'status' => 'in_progress',
                'problemSummary' => 'Site public redirecționat la pagina Proiect în lucru. Dezvoltare în /test/ - pregătim lansarea.',
                'focusOem' => null,
                'baselineValue' => 0,
                'targetValue' => 5000,
                'currentValue' => 120,
                'kpiMetric' => 'visitors',
                'playbookKey' => 'seo_catalog',
                'checklist' => [
                    ['id' => 'c1', 'label' => 'Verifica redirect productie -> pagina in lucru', 'done' => true],
                    ['id' => 'c2', 'label' => 'Testeaza flux complet in /test/ (catalog, cos, checkout)', 'done' => false],
                    ['id' => 'c3', 'label' => 'Deschide site-ul public la lansare', 'done' => false],
                ],
                'evidenceBefore' => ['captured_at' => $now, 'note' => 'Site in mod mentenanta', 'visitors' => 120],
                'evidenceAfter' => [],
                'priorityScore' => 800,
                'isFocus' => true,
                'notes' => 'Scop: trafic clar + WhatsApp pentru lead-uri cat site-ul e in lucru.',
                'startedAt' => $now,
                'createdAt' => $now,
                'updatedAt' => $now,
            ],
            [
                'id' => MarketingHubService::newId('cmp'),
                'title' => 'PieseAuto.ro - vitrină stoc',
                'problemType' => 'marketplace',
                'channel' => 'pieseauto',
                'status' => 'in_progress',
                'problemSummary' => 'Listăm piese cu stoc real pe PieseAuto.ro - canal paralel cât site-ul e în lucru.',
                'focusOem' => '34116761244',
                'baselineValue' => 8,
                'targetValue' => 30,
                'currentValue' => 8,
                'kpiMetric' => 'listings',
                'playbookKey' => 'pieseauto_refresh',
                'checklist' => [
                    ['id' => 'c1', 'label' => 'Selecteaza 20 produse vitrina din admin', 'done' => true],
                    ['id' => 'c2', 'label' => 'Publica / actualizeaza anunturi PieseAuto', 'done' => false],
                    ['id' => 'c3', 'label' => 'Noteaza vizualizari saptamana 1', 'done' => false],
                ],
                'evidenceBefore' => ['captured_at' => $now, 'listings' => 8],
                'evidenceAfter' => [],
                'priorityScore' => 600,
                'isFocus' => true,
                'notes' => 'Canal marketplace - listari fara asteptarea lansarii site.',
                'startedAt' => $now,
                'createdAt' => $now,
                'updatedAt' => $now,
            ],
            [
                'id' => MarketingHubService::newId('cmp'),
                'title' => 'Facebook - OEM trending',
                'problemType' => 'traffic',
                'channel' => 'facebook',
                'status' => 'identified',
                'problemSummary' => 'Postari cu coduri OEM cautate des - CTA WhatsApp (informativ).',
                'focusOem' => '34116761244',
                'baselineValue' => 0,
                'targetValue' => 50,
                'currentValue' => 3,
                'kpiMetric' => 'leads',
                'playbookKey' => 'fb_weekly',
                'checklist' => self::defaultChecklist('fb_weekly'),
                'evidenceBefore' => ['captured_at' => $now, 'leads' => 3],
                'evidenceAfter' => [],
                'priorityScore' => 400,
                'isFocus' => true,
                'notes' => 'Reach -> lead-uri WhatsApp -> operator confirma stoc.',
                'createdAt' => $now,
                'updatedAt' => $now,
            ],
            [
                'id' => MarketingHubService::newId('cmp'),
                'title' => 'Google SEO - OEM negasite',
                'problemType' => 'stock',
                'channel' => 'google',
                'status' => 'identified',
                'problemSummary' => 'Top coduri OEM din Search Logs fara stoc - import + pagini produs pentru SEO.',
                'focusOem' => null,
                'baselineValue' => 91,
                'targetValue' => 45,
                'currentValue' => 91,
                'kpiMetric' => 'searches',
                'playbookKey' => 'seo_catalog',
                'checklist' => self::defaultChecklist('seo_catalog'),
                'evidenceBefore' => ['captured_at' => $now, 'missing_oem' => 91],
                'evidenceAfter' => [],
                'priorityScore' => 500,
                'isFocus' => false,
                'notes' => 'Scop: trafic organic inainte de lansarea homepage.',
                'createdAt' => $now,
                'updatedAt' => $now,
            ],
            [
                'id' => MarketingHubService::newId('cmp'),
                'title' => 'WhatsApp - lead-uri calificate',
                'problemType' => 'traffic',
                'channel' => 'whatsapp',
                'status' => 'published',
                'problemSummary' => 'Raspuns rapid + template 3 variante pret (informativ din DB).',
                'focusOem' => null,
                'baselineValue' => 12,
                'targetValue' => 150,
                'currentValue' => 28,
                'kpiMetric' => 'leads',
                'playbookKey' => 'whatsapp_followup',
                'checklist' => self::defaultChecklist('whatsapp_followup'),
                'evidenceBefore' => ['captured_at' => $now, 'conversations' => 12],
                'evidenceAfter' => ['captured_at' => $now, 'conversations' => 28],
                'priorityScore' => 450,
                'isFocus' => false,
                'notes' => 'Canal principal conversie cat site-ul e in lucru.',
                'createdAt' => $now,
                'updatedAt' => $now,
            ],
        ];

        $actions = [];
        $content = [];
        foreach ($campaigns as $campaign) {
            $pb = MarketingHubService::playbooks()[(string) ($campaign['playbookKey'] ?? 'seo_catalog')] ?? null;
            foreach (self::spawnActionsForCampaign($campaign, $pb) as $act) {
                $actions[] = $act;
            }
            $problem = [
                'focusOem' => $campaign['focusOem'] ?? null,
                'summary' => $campaign['problemSummary'] ?? '',
            ];
            foreach (self::spawnContentForCampaign($campaign, $problem) as $cnt) {
                $cnt['channel'] = $campaign['channel'];
                $content[] = $cnt;
            }
        }

        $cmpPa = $campaigns[1];
        $content[] = [
            'id' => MarketingHubService::newId('cnt'),
            'title' => 'Anunt PieseAuto - filtru ulei BMW',
            'platform' => 'PieseAuto.ro',
            'format' => 'ad',
            'status' => 'draft',
            'channel' => 'pieseauto',
            'publishDate' => '',
            'campaignId' => $cmpPa['id'],
            'body' => "Titlu: FILTRU ULEI MANN W712/75 - BMW\n\nPret: 45 RON\nStoc: disponibil\nLivrare FanCourier / ridicare Utvin\n\nOEM: 11427566327",
            'templateKey' => 'pieseauto_ad',
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $cmpFb = $campaigns[2];
        $content[] = [
            'id' => MarketingHubService::newId('cnt'),
            'title' => 'Post OEM trending: 34116761244',
            'platform' => 'Facebook',
            'format' => 'post',
            'status' => 'draft',
            'channel' => 'facebook',
            'publishDate' => '',
            'campaignId' => $cmpFb['id'],
            'body' => "Cautare frecventa: 34116761244 (disc frana BMW)\n\nVerifica stoc pe besoiupieseauto.ro/test/\nPret de la 217 RON\nWhatsApp: 0726 498 573",
            'templateKey' => 'fb_post',
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $cmpSite = $campaigns[0];
        $content[] = [
            'id' => MarketingHubService::newId('cnt'),
            'title' => 'Pagina Proiect in lucru - mesaj + CTA WhatsApp',
            'platform' => 'Site',
            'format' => 'page',
            'status' => 'published',
            'channel' => 'site',
            'publishDate' => '',
            'campaignId' => $cmpSite['id'],
            'body' => "Magazinul online se pregateste.\n\nIntre timp: catalog test /test/ · comenzi WhatsApp · PieseAuto.ro vitrina.",
            'templateKey' => 'blog_guide',
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $cmpWa = $campaigns[4];
        $actions[] = [
            'id' => MarketingHubService::newId('act'),
            'title' => 'Template raspuns 3 preturi (Economic/Mediu/Premium)',
            'targetKpi' => 'Lead-uri / luna',
            'campaignId' => $cmpWa['id'],
            'kanbanStep' => 'published',
            'deadline' => '',
            'priority' => 'high',
            'status' => 'done',
            'effort' => 2,
            'impact' => 5,
            'channel' => 'whatsapp',
            'notes' => 'Doar informativ - operator valideaza stocul inainte de trimitere.',
            'playbookKey' => 'whatsapp_followup',
            'score' => 90,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        return [
            'campaigns' => $campaigns,
            'actions' => $actions,
            'content' => $content,
        ];
    }

    /**
     * Continut demo - flux Rezultat -> Probleme -> Plan -> Executie -> Dovezi.
     *
     * @return array{campaigns: int, actions: int, content: int}
     */
    public static function seedDemoData(?int $userId = null, bool $force = false): array
    {
        if (!MarketingHubService::tablesExist()) {
            return ['campaigns' => 0, 'actions' => 0, 'content' => 0];
        }

        self::ensureSchema();

        if ($force) {
            $pdo = Database::getDB();
            if (self::tablesExist()) {
                $pdo->exec('DELETE FROM marketing_campaigns');
            }
            MarketingHubService::saveState([
                'actions' => [],
                'content' => [],
                'indicators' => MarketingHubService::loadAll()['indicators'] ?? [],
            ], $userId);
        }

        $existing = self::fetchCampaigns();
        if ($existing !== [] && !$force) {
            return ['campaigns' => count($existing), 'actions' => 0, 'content' => 0];
        }

        $state = self::buildDemoPayload();
        MarketingHubService::saveState($state, $userId);
        MarketingHubService::ensurePlatformIndicators($userId);

        return [
            'campaigns' => count($state['campaigns']),
            'actions' => count($state['actions']),
            'content' => count($state['content']),
        ];
    }
}
