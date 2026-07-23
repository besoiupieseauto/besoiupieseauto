<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Legătură Planner financiar → Marketing Hub (North Star: utilizatori țintă).
 */
final class MarketingPlannerBridgeService
{
    private const SNAPSHOT_FILE = 'marketing_planner_snapshot.json';

    /** @param array<string, mixed> $input */
    public static function computeFunnel(array $input): array
    {
        $users = max(1, (int) round((float) ($input['target_users'] ?? $input['targetUsers'] ?? 1000)));
        $conv = min(100.0, max(0.1, (float) ($input['conversion_rate'] ?? $input['conversionRate'] ?? 5)));
        $price = max(0.0, (float) ($input['avg_price'] ?? $input['avgPrice'] ?? 141.23));
        $orders = (int) max(1, round($users * ($conv / 100)));
        $revenue = round($orders * $price, 2);
        $visits = $users * 3;

        return [
            'target_users' => $users,
            'conversion_rate' => $conv,
            'avg_price' => $price,
            'orders_month' => $orders,
            'revenue_month' => $revenue,
            'visits_month' => $visits,
            'applied_at' => date('c'),
            'source' => (string) ($input['source'] ?? 'planner'),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function loadSnapshot(): ?array
    {
        $path = self::snapshotPath();
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $funnel */
    public static function saveSnapshot(array $funnel): void
    {
        $dir = dirname(self::snapshotPath());
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            self::snapshotPath(),
            json_encode($funnel, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /**
     * @param array<string, mixed> $funnel
     * @return list<array<string, mixed>>
     */
    public static function buildIndicatorTargets(array $funnel): array
    {
        $users = (int) $funnel['target_users'];
        $orders = (int) $funnel['orders_month'];
        $visits = (int) $funnel['visits_month'];
        $now = date('c');

        $rows = [
            [
                'autoKey' => 'planner:visits',
                'platform' => 'Site / besoiupieseauto.ro',
                'category' => 'site',
                'metric' => 'Utilizatori / vizite (lună)',
                'target' => $users,
                'url' => 'https://besoiupieseauto.ro',
                'notes' => 'North Star din Planner — ținta ta principală.',
            ],
            [
                'autoKey' => 'planner:google',
                'platform' => 'Google / SEO',
                'category' => 'google',
                'metric' => 'Trafic organic (estim.)',
                'target' => (int) max(100, round($users * 0.25)),
                'url' => '/admin/searchlogs',
                'notes' => '≈25% din utilizatori țintă — SEO + OEM.',
            ],
            [
                'autoKey' => 'planner:facebook',
                'platform' => 'Facebook',
                'category' => 'facebook',
                'metric' => 'Reach postări (lună)',
                'target' => (int) max(200, round($users * 0.5)),
                'url' => 'https://facebook.com/',
                'notes' => '≈50% reach vs utilizatori țintă.',
            ],
            [
                'autoKey' => 'planner:pieseauto',
                'platform' => 'PieseAuto.ro',
                'category' => 'pieseauto',
                'metric' => 'Listări active',
                'target' => (int) max(30, round($orders * 0.6)),
                'url' => '/admin/marketplace/pieseauto',
                'notes' => 'Marketplace parallel — susține volum comenzi.',
            ],
            [
                'autoKey' => 'planner:whatsapp',
                'platform' => 'WhatsApp',
                'category' => 'whatsapp',
                'metric' => 'Lead-uri calificate (lună)',
                'target' => (int) max(10, round($orders * 1.2)),
                'url' => '/admin/comunicare',
                'notes' => 'Conversie din trafic — țintă lead-uri ≈ comenzi × 1,2.',
            ],
            [
                'autoKey' => 'planner:orders',
                'platform' => 'Magazin (conversie)',
                'category' => 'site',
                'metric' => 'Comenzi / lună',
                'target' => $orders,
                'url' => '/admin/orders',
                'notes' => sprintf(
                    'Din Planner: %s util. × %s%% conversie.',
                    number_format($users, 0, ',', '.'),
                    rtrim(rtrim(number_format((float) $funnel['conversion_rate'], 2, ',', '.'), '0'), ',')
                ),
            ],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[] = array_merge($row, [
                'id' => MarketingHubService::newId('ind'),
                'value' => 0,
                'source' => 'planner',
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $existing
     * @param list<array<string, mixed>> $plannerTargets
     * @return list<array<string, mixed>>
     */
    public static function mergePlannerIndicators(array $existing, array $plannerTargets): array
    {
        $byKey = [];
        foreach ($existing as $ind) {
            if (!is_array($ind)) {
                continue;
            }
            $key = (string) ($ind['autoKey'] ?? $ind['auto_key'] ?? '');
            if ($key !== '' && str_starts_with($key, 'planner:')) {
                continue;
            }
            $byKey[(string) ($ind['id'] ?? uniqid('ind_', true))] = $ind;
        }
        foreach ($plannerTargets as $target) {
            $ak = (string) ($target['autoKey'] ?? '');
            $merged = false;
            foreach ($byKey as $id => $ind) {
                if ((string) ($ind['autoKey'] ?? $ind['auto_key'] ?? '') === $ak) {
                    $byKey[$id] = array_merge($ind, $target, ['id' => $ind['id'], 'value' => $ind['value'] ?? 0]);
                    $merged = true;
                    break;
                }
            }
            if (!$merged) {
                $byKey[(string) $target['id']] = $target;
            }
        }

        return array_values($byKey);
    }

    /**
     * @param array<string, mixed> $funnel
     * @param array<string, mixed> $live
     * @return list<array<string, mixed>>
     */
    public static function plannerProblems(array $funnel, array $live, array $indicators = []): array
    {
        $users = (int) $funnel['target_users'];
        $ordersTarget = (int) $funnel['orders_month'];
        $currentVisits = self::estimateCurrentVisitors($live, $indicators);
        $currentOrders = max((int) ($live['orders_today'] ?? 0), 0);

        $problems = [];

        if ($currentVisits < $users) {
            $gap = $users - $currentVisits;
            $problems[] = [
                'id' => 'prob_planner_users',
                'type' => 'traffic',
                'channel' => 'site',
                'title' => 'Gap North Star — utilizatori țintă',
                'summary' => sprintf(
                    'Planner: %s utilizatori/lună. Estimat acum: ~%s. Lipsesc ~%s — acțiuni pe site, SEO, social.',
                    number_format($users, 0, ',', '.'),
                    number_format($currentVisits, 0, ',', '.'),
                    number_format($gap, 0, ',', '.')
                ),
                'metric' => 'utilizatori',
                'current' => $currentVisits,
                'target' => $users,
                'unit' => 'util.',
                'kpiMetric' => 'visitors',
                'impactScore' => min(999, $gap),
                'playbookKey' => 'seo_catalog',
                'severity' => $gap > ($users * 0.5) ? 'critical' : 'warning',
                'plannerLinked' => true,
            ];
        }

        if ($ordersTarget > 0 && $currentOrders < $ordersTarget) {
            $problems[] = [
                'id' => 'prob_planner_orders',
                'type' => 'traffic',
                'channel' => 'whatsapp',
                'title' => 'Conversie spre comenzi (Planner)',
                'summary' => sprintf(
                    'Țintă %s comenzi/lună (%s%% conversie). Crește lead-uri WhatsApp + CTA pe canale.',
                    number_format($ordersTarget, 0, ',', '.'),
                    rtrim(rtrim(number_format((float) $funnel['conversion_rate'], 2, ',', '.'), '0'), ',')
                ),
                'metric' => 'comenzi/lună',
                'current' => $currentOrders,
                'target' => $ordersTarget,
                'unit' => 'comenzi',
                'kpiMetric' => 'conversions',
                'impactScore' => (int) min(500, $ordersTarget),
                'playbookKey' => 'whatsapp_followup',
                'severity' => 'warning',
                'plannerLinked' => true,
            ];
        }

        return $problems;
    }

    /** @param array<string, mixed> $live @param list<array<string, mixed>> $indicators */
    public static function estimateCurrentVisitors(array $live, array $indicators = []): int
    {
        foreach ($indicators as $ind) {
            if (!is_array($ind)) {
                continue;
            }
            $ak = (string) ($ind['autoKey'] ?? $ind['auto_key'] ?? '');
            if ($ak === 'planner:visits' || ($ind['category'] ?? '') === 'site') {
                $val = (int) ($ind['value'] ?? 0);
                if ($val > 0) {
                    return $val;
                }
            }
        }

        $searches = (int) ($live['searches_today'] ?? 0);
        $fromSearches = $searches > 0 ? $searches * 25 : 0;

        return max($fromSearches, 120);
    }

    /**
     * @param list<array<string, mixed>> $campaigns
     * @param array<string, mixed> $funnel
     * @param array<string, mixed> $live
     * @return list<array<string, mixed>>
     */
    public static function upsertPlannerCampaigns(array $campaigns, array $funnel, array $live, array $indicators = []): array
    {
        $users = (int) $funnel['target_users'];
        $orders = (int) $funnel['orders_month'];
        $current = self::estimateCurrentVisitors($live, $indicators);
        $now = date('c');

        $specs = [
            [
                'id' => 'cmp_planner_north_star',
                'title' => 'North Star — ' . number_format($users, 0, ',', '.') . ' utilizatori/lună',
                'channel' => 'site',
                'playbookKey' => 'seo_catalog',
                'target' => (float) $users,
                'kpiMetric' => 'visitors',
                'summary' => sprintf(
                    'Ținta din Planner: %s utilizatori × %s%% = %s comenzi/lună (~%s RON).',
                    number_format($users, 0, ',', '.'),
                    rtrim(rtrim(number_format((float) $funnel['conversion_rate'], 2, ',', '.'), '0'), ','),
                    number_format($orders, 0, ',', '.'),
                    number_format((float) $funnel['revenue_month'], 0, ',', '.')
                ),
                'isFocus' => true,
                'status' => 'in_progress',
            ],
            [
                'id' => 'cmp_planner_google',
                'title' => 'SEO Google — trafic spre țintă',
                'channel' => 'google',
                'playbookKey' => 'seo_catalog',
                'target' => (float) max(100, round($users * 0.25)),
                'kpiMetric' => 'traffic',
                'summary' => 'OEM negăsite + pagini produs — alimentează ~25% din utilizatori țintă.',
                'isFocus' => true,
                'status' => 'identified',
            ],
            [
                'id' => 'cmp_planner_facebook',
                'title' => 'Facebook — reach & lead-uri',
                'channel' => 'facebook',
                'playbookKey' => 'fb_weekly',
                'target' => (float) max(200, round($users * 0.5)),
                'kpiMetric' => 'leads',
                'summary' => 'Postări săptămânale + CTA WhatsApp — susține North Star.',
                'isFocus' => true,
                'status' => 'identified',
            ],
            [
                'id' => 'cmp_planner_pieseauto',
                'title' => 'PieseAuto.ro — listări',
                'channel' => 'pieseauto',
                'playbookKey' => 'pieseauto_refresh',
                'target' => (float) max(30, round($orders * 0.6)),
                'kpiMetric' => 'listings',
                'summary' => 'Canal marketplace parallel până la lansarea completă site.',
                'isFocus' => false,
                'status' => 'identified',
            ],
            [
                'id' => 'cmp_planner_whatsapp',
                'title' => 'WhatsApp — conversie lead-uri',
                'channel' => 'whatsapp',
                'playbookKey' => 'whatsapp_followup',
                'target' => (float) max(10, round($orders * 1.2)),
                'kpiMetric' => 'leads',
                'summary' => 'Răspuns rapid + template 3 prețuri — închide gap-ul spre comenzi.',
                'isFocus' => false,
                'status' => 'identified',
            ],
        ];

        $byId = [];
        foreach ($campaigns as $c) {
            if (!is_array($c)) {
                continue;
            }
            $id = (string) ($c['id'] ?? '');
            if ($id === '' || str_starts_with($id, 'cmp_planner_')) {
                continue;
            }
            $byId[$id] = $c;
        }

        foreach ($specs as $spec) {
            $existing = null;
            foreach ($campaigns as $c) {
                if ((string) ($c['id'] ?? '') === $spec['id']) {
                    $existing = $c;
                    break;
                }
            }
            $byId[$spec['id']] = array_merge([
                'id' => $spec['id'],
                'problemType' => 'traffic',
                'status' => $spec['status'],
                'problemSummary' => $spec['summary'],
                'focusOem' => null,
                'baselineValue' => (float) $current,
                'targetValue' => $spec['target'],
                'currentValue' => (float) $current,
                'kpiMetric' => $spec['kpiMetric'],
                'playbookKey' => $spec['playbookKey'],
                'checklist' => MarketingCampaignService::defaultChecklistPublic($spec['playbookKey']),
                'evidenceBefore' => ['captured_at' => $now, 'planner_applied' => true],
                'evidenceAfter' => [],
                'priorityScore' => (int) min(999, $spec['target']),
                'isFocus' => $spec['isFocus'],
                'notes' => 'Generat automat din Planner.',
                'startedAt' => $now,
                'createdAt' => $now,
                'updatedAt' => $now,
            ], $existing ?? [], [
                'title' => $spec['title'],
                'channel' => $spec['channel'],
                'targetValue' => $spec['target'],
                'problemSummary' => $spec['summary'],
                'playbookKey' => $spec['playbookKey'],
                'kpiMetric' => $spec['kpiMetric'],
                'isFocus' => $spec['isFocus'],
                'updatedAt' => $now,
            ]);
        }

        usort($byId, static function (array $a, array $b): int {
            $fa = !empty($a['isFocus']) ? 1 : 0;
            $fb = !empty($b['isFocus']) ? 1 : 0;
            if ($fa !== $fb) {
                return $fb <=> $fa;
            }

            return ((int) ($b['priorityScore'] ?? 0)) <=> ((int) ($a['priorityScore'] ?? 0));
        });

        return array_values($byId);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $insights
     */
    public static function apply(array $input, array $insights, ?int $userId): array
    {
        $funnel = self::computeFunnel($input);
        self::saveSnapshot($funnel);

        $live = is_array($insights['live'] ?? null) ? $insights['live'] : [];
        $state = MarketingHubService::loadAll();

        $plannerTargets = self::buildIndicatorTargets($funnel);
        $state['indicators'] = self::mergePlannerIndicators($state['indicators'] ?? [], $plannerTargets);

        $campaigns = MarketingCampaignService::fetchCampaigns();
        $campaigns = self::upsertPlannerCampaigns($campaigns, $funnel, $live, $state['indicators']);

        MarketingHubService::saveState(['indicators' => $state['indicators']], $userId);
        MarketingCampaignService::saveCampaigns(['campaigns' => $campaigns], $userId);

        return [
            'funnel' => $funnel,
            'indicators_updated' => count($plannerTargets),
            'campaigns_updated' => count(array_filter(
                $campaigns,
                static fn (array $c): bool => str_starts_with((string) ($c['id'] ?? ''), 'cmp_planner_')
            )),
        ];
    }

    /** @param array<string, mixed>|null $funnel @param list<array<string, mixed>> $indicators */
    public static function enrichResult(array $result, ?array $funnel, array $live, array $indicators = []): array
    {
        if ($funnel === null) {
            $result['planner_linked'] = false;

            return $result;
        }

        $users = (int) $funnel['target_users'];
        $current = self::estimateCurrentVisitors($live, $indicators);
        $pct = $users > 0 ? min(100, round(($current / $users) * 100, 1)) : 0;

        $result['planner_linked'] = true;
        $result['planner_target_users'] = $users;
        $result['planner_orders_month'] = (int) $funnel['orders_month'];
        $result['planner_revenue_month'] = (float) $funnel['revenue_month'];
        $result['planner_conversion_rate'] = (float) $funnel['conversion_rate'];
        $result['planner_progress_pct'] = $pct;
        $result['planner_current_visitors'] = $current;
        $result['headline'] = sprintf(
            'North Star: %s / %s utilizatori (%.0f%%) · țintă %s comenzi/lună',
            number_format($current, 0, ',', '.'),
            number_format($users, 0, ',', '.'),
            $pct,
            number_format((int) $funnel['orders_month'], 0, ',', '.')
        );

        return $result;
    }

    private static function snapshotPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/' . self::SNAPSHOT_FILE;
    }
}
