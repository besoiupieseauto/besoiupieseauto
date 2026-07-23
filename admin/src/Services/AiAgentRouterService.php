<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Alege automat agentul potrivit după secțiune site + evenimente client.
 * Context Master rămâne strat global; agenții specializați primesc context propriu.
 */
final class AiAgentRouterService
{
    /** @var array<string, string> */
    private const SECTION_LABELS = [
        'context-master' => 'Global — tot proiectul',
        'agent-imagini' => 'Imagini produse',
        'agent-produse' => 'Produse & catalog',
        'agent-clienti' => 'Clienți & comenzi',
        'agent-statistici' => 'Statistici & KPI',
        'catalog-stoc' => 'Catalog & căutări',
        'leaduri-cos' => 'Coș & lead-uri',
        'comenzi-livrare' => 'Comenzi & livrare',
        'cautari-fara-rezultat' => 'Căutări fără rezultat',
        'import-furnizori' => 'Import & furnizori',
        'import-quality' => 'Quality Import — match date',
        'ops-composer-repair' => 'Sistem — alerte & reparări',
    ];

    /**
     * Ghid organizare agenți — secțiune, declanșare, volum (pentru UI admin).
     *
     * @return array<string, array{label: string, zone: string, trigger: string, live: bool, system?: bool}>
     */
    public static function agentOrganizationCatalog(): array
    {
        return [
            'context-master' => [
                'label' => 'Global — strat comun',
                'zone' => 'Tot site-ul + admin',
                'trigger' => 'Mereu activ în fundal (Live ~25s)',
                'live' => true,
            ],
            'agent-imagini' => [
                'label' => 'Imagini produse',
                'zone' => 'admin Produse, Import Review, audit imagini',
                'trigger' => 'Audit imagine, import fără poză, placeholder',
                'live' => true,
            ],
            'agent-produse' => [
                'label' => 'Produse & catalog',
                'zone' => '/catalog, /produs, admin Produse, TecDoc',
                'trigger' => 'Căutare OEM/VIN, stoc, preț',
                'live' => true,
            ],
            'agent-clienti' => [
                'label' => 'Clienți & comenzi',
                'zone' => '/cont, comenzi, admin Comenzi, WhatsApp',
                'trigger' => 'Status comandă, AWB, client',
                'live' => true,
            ],
            'agent-statistici' => [
                'label' => 'Statistici & KPI',
                'zone' => 'admin Dashboard, rapoarte, search logs',
                'trigger' => 'Întrebări „câte”, „cât”, rezumat zilnic',
                'live' => true,
            ],
            'catalog-stoc' => [
                'label' => 'Produse & catalog',
                'zone' => '/catalog, /produs, căutări client, admin Produse',
                'trigger' => 'Când clientul caută / vede piese',
                'live' => true,
            ],
            'leaduri-cos' => [
                'label' => 'Coș & lead-uri',
                'zone' => '/cos, coș abandonat, contact',
                'trigger' => 'Add to cart, coș neterminat',
                'live' => true,
            ],
            'comenzi-livrare' => [
                'label' => 'Comenzi & livrare',
                'zone' => '/cont, comenzi, admin Comenzi',
                'trigger' => 'Status comandă, AWB',
                'live' => true,
            ],
            'cautari-fara-rezultat' => [
                'label' => 'Căutări fără rezultat',
                'zone' => 'Search logs, zero results',
                'trigger' => 'Căutări client fără potrivire',
                'live' => true,
            ],
            'import-furnizori' => [
                'label' => 'Import & furnizori',
                'zone' => 'admin Import, furnizori, CSV',
                'trigger' => 'Acțiuni import în admin',
                'live' => true,
            ],
            'import-quality' => [
                'label' => 'Quality Import — match date',
                'zone' => 'admin Importreview, coadă staging',
                'trigger' => 'După scan / buton Quality Match',
                'live' => true,
            ],
            'ops-composer-repair' => [
                'label' => 'Sistem — alerte',
                'zone' => 'Supervizor, popup Corectează',
                'trigger' => 'Cron ~20 min + buton manual',
                'live' => false,
                'system' => true,
            ],
        ];
    }

    /** @var list<string> */
    private const SYNC_ALWAYS = ['context-master'];

    public function __construct(
        private ?AiAgentRegistryService $registry = null,
        private ?AiActionEventService $events = null,
    ) {
        $this->registry = $registry ?? new AiAgentRegistryService();
        $this->events = $events ?? new AiActionEventService();
    }

    /**
     * @param array<string, mixed> $hints path, admin_section, events
     * @return array{slug: string, name: string, reason: string, section: string, agents_to_sync: list<string>, scores: array<string, int>}
     */
    public function resolve(array $hints = []): array
    {
        $path = strtolower(trim((string) ($hints['path'] ?? '')));
        $adminSection = strtolower(trim((string) ($hints['admin_section'] ?? '')));
        $eventList = is_array($hints['events'] ?? null)
            ? $hints['events']
            : $this->events->readEvents(20);

        $scores = $this->baseScores();

        foreach (array_reverse($eventList) as $i => $event) {
            if (!is_array($event)) {
                continue;
            }
            $weight = max(1, 12 - (int) $i);
            $this->scoreEvent($scores, $event, $weight);
        }

        $this->scorePath($scores, $path);
        $this->scoreAdminSection($scores, $adminSection);
        $this->scoreCorePatterns($scores);
        $this->scoreActiveSessions($scores);

        $installed = [];
        foreach ($this->registry->listAgents() as $agent) {
            $s = (string) ($agent['slug'] ?? '');
            if ($s !== '') {
                $installed[$s] = true;
            }
        }

        foreach (array_keys($scores) as $slug) {
            if (!isset($installed[$slug])) {
                unset($scores[$slug]);
            }
        }

        if ($scores === []) {
            $scores = ['context-master' => 1];
        }

        arsort($scores);
        $slug = (string) array_key_first($scores);
        if ($slug === '' || $this->registry->getAgent($slug) === null) {
            $slug = 'context-master';
        }

        $agent = $this->registry->getAgent($slug);
        $agentsToSync = self::SYNC_ALWAYS;
        if ($slug !== 'context-master' && isset($installed[$slug])) {
            $agentsToSync[] = $slug;
        }

        foreach (array_keys($scores) as $candidate) {
            if ($candidate !== 'context-master' && ($scores[$candidate] ?? 0) >= 8 && isset($installed[$candidate])) {
                $agentsToSync[] = $candidate;
            }
        }

        return [
            'slug' => $slug,
            'name' => (string) ($agent['name'] ?? $slug),
            'reason' => $this->buildReason($slug, $scores, $path, $eventList),
            'section' => self::SECTION_LABELS[$slug] ?? $slug,
            'agents_to_sync' => array_values(array_unique($agentsToSync)),
            'scores' => $scores,
        ];
    }

    /**
     * Orchestră Live — toți agenții specializați, nu doar câștigătorul rutării.
     *
     * @param array<string, mixed> $route
     * @return list<array<string, mixed>>
     */
    public function buildOrchestra(array $route): array
    {
        $scores = is_array($route['scores'] ?? null) ? $route['scores'] : [];
        $primary = (string) ($route['slug'] ?? 'context-master');
        $syncSlugs = is_array($route['agents_to_sync'] ?? null) ? $route['agents_to_sync'] : [];
        $org = self::agentOrganizationCatalog();

        $installed = [];
        foreach ($this->registry->listAgents() as $agent) {
            $slug = (string) ($agent['slug'] ?? '');
            if ($slug !== '') {
                $installed[$slug] = $agent;
            }
        }

        $rows = [];
        foreach ($org as $slug => $meta) {
            if (!empty($meta['system'])) {
                continue;
            }
            if (!isset($installed[$slug])) {
                $rows[] = [
                    'slug' => $slug,
                    'name' => self::SECTION_LABELS[$slug] ?? $slug,
                    'label' => (string) ($meta['label'] ?? $slug),
                    'status' => 'missing',
                    'installed' => false,
                    'live' => !empty($meta['live']),
                    'score' => (int) ($scores[$slug] ?? 0),
                    'temperature' => null,
                    'zone' => (string) ($meta['zone'] ?? ''),
                    'trigger' => (string) ($meta['trigger'] ?? ''),
                    'files_path' => 'robot/data/ai_agents/' . $slug . '/',
                    'last_run_at' => null,
                    'runtime_chars' => 0,
                ];
                continue;
            }

            $agent = $installed[$slug];
            $score = (int) ($scores[$slug] ?? 0);
            $status = 'idle';
            if ($slug === $primary) {
                $status = 'primary';
            } elseif (in_array($slug, $syncSlugs, true)) {
                $status = 'sync';
            } elseif ($score >= 6) {
                $status = 'warm';
            }

            $runtimeMd = $this->registry->loadRuntimeMarkdown($slug);
            $rows[] = [
                'slug' => $slug,
                'name' => (string) ($agent['name'] ?? $slug),
                'label' => (string) ($meta['label'] ?? $slug),
                'status' => $status,
                'installed' => true,
                'live' => !empty($meta['live']),
                'score' => $score,
                'temperature' => (float) ($agent['temperature'] ?? 0.35),
                'zone' => (string) ($meta['zone'] ?? ''),
                'trigger' => (string) ($meta['trigger'] ?? ''),
                'files_path' => 'robot/data/ai_agents/' . $slug . '/',
                'last_run_at' => is_array($agent['state'] ?? null) ? ($agent['state']['last_run_at'] ?? null) : null,
                'runtime_chars' => strlen($runtimeMd),
            ];
        }

        foreach ($installed as $slug => $agent) {
            if (isset($org[$slug])) {
                continue;
            }
            if ($slug === 'ops-composer-repair') {
                continue;
            }
            $runtimeMd = $this->registry->loadRuntimeMarkdown($slug);
            $rows[] = [
                'slug' => $slug,
                'name' => (string) ($agent['name'] ?? $slug),
                'label' => (string) ($agent['description'] ?? 'Agent custom'),
                'status' => $slug === $primary ? 'primary' : (in_array($slug, $syncSlugs, true) ? 'sync' : 'idle'),
                'installed' => true,
                'live' => false,
                'score' => (int) ($scores[$slug] ?? 0),
                'temperature' => (float) ($agent['temperature'] ?? 0.35),
                'zone' => 'Custom',
                'trigger' => 'Manual / Editor',
                'files_path' => 'robot/data/ai_agents/' . $slug . '/',
                'last_run_at' => is_array($agent['state'] ?? null) ? ($agent['state']['last_run_at'] ?? null) : null,
                'runtime_chars' => strlen($runtimeMd),
            ];
        }

        $rank = ['primary' => 0, 'sync' => 1, 'warm' => 2, 'idle' => 3, 'missing' => 4];
        usort($rows, static function (array $a, array $b) use ($rank): int {
            $sa = $rank[(string) ($a['status'] ?? 'idle')] ?? 9;
            $sb = $rank[(string) ($b['status'] ?? 'idle')] ?? 9;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            return ((int) ($b['score'] ?? 0)) <=> ((int) ($a['score'] ?? 0));
        });

        return $rows;
    }

    public function buildCompositeRuntimeMarkdown(string $primarySlug): string
    {
        $primarySlug = $this->registry->normalizeSlug($primarySlug);
        if ($primarySlug === '' || $this->registry->getAgent($primarySlug) === null) {
            $primarySlug = 'context-master';
        }

        $master = $this->registry->loadRuntimeMarkdown('context-master');
        if ($primarySlug === 'context-master') {
            return $master;
        }

        $specialist = $this->registry->loadRuntimeMarkdown($primarySlug);
        $agent = $this->registry->getAgent($primarySlug);
        $name = (string) ($agent['name'] ?? $primarySlug);

        $parts = [];
        $parts[] = '# Agent activ (automat): ' . $name;
        $parts[] = 'Slug: `' . $primarySlug . '` — context specializat pentru secțiunea curentă.';
        $parts[] = $specialist !== '' ? $specialist : '(Runtime specialist gol — se generează automat.)';
        $parts[] = '---';
        $parts[] = '# Context global — Context Master';
        $parts[] = $master !== '' ? $master : '(Context Master se actualizează automat.)';

        return implode("\n\n", $parts);
    }

    /** Runtime compozit scurt — fără duplicare masivă (widget + Composer LLM). */
    public function buildBriefCompositeRuntimeMarkdown(string $primarySlug, int $maxChars = 7000): string
    {
        $primarySlug = $this->registry->normalizeSlug($primarySlug);
        if ($primarySlug === '' || $this->registry->getAgent($primarySlug) === null) {
            $primarySlug = 'context-master';
        }

        $parts = [];
        if ($primarySlug !== 'context-master') {
            $agent = $this->registry->getAgent($primarySlug);
            $name = (string) ($agent['name'] ?? $primarySlug);
            $parts[] = '# Agent activ: ' . $name . ' (`' . $primarySlug . '`)';
            $parts[] = $this->registry->getRuntimeBrief($primarySlug, (int) floor($maxChars * 0.55));
        }

        $parts[] = '# Context Master (global)';
        $parts[] = $this->registry->getRuntimeBrief('context-master', (int) floor($maxChars * 0.45));

        $text = implode("\n\n", array_filter($parts, static fn ($p) => trim($p) !== ''));
        if (mb_strlen($text, 'UTF-8') > $maxChars) {
            return mb_substr($text, 0, $maxChars, 'UTF-8') . "\n\n[... context trunchiat — foloseste date live Composer pentru inventar exact]";
        }

        return $text;
    }

    /** @return array<string, int> */
    private function baseScores(): array
    {
        return [
            'context-master' => 2,
            'agent-imagini' => 0,
            'agent-produse' => 0,
            'agent-clienti' => 0,
            'agent-statistici' => 0,
            'catalog-stoc' => 0,
            'leaduri-cos' => 0,
            'comenzi-livrare' => 0,
            'cautari-fara-rezultat' => 0,
            'import-furnizori' => 0,
        ];
    }

    /** @param array<string, int> $scores @param array<string, mixed> $event */
    private function scoreEvent(array &$scores, array $event, int $weight): void
    {
        $actor = (string) ($event['actor_type'] ?? '');
        $action = (string) ($event['action'] ?? '');
        $subject = strtolower((string) ($event['subject'] ?? ''));

        if ($actor === 'admin') {
            if (str_contains($action, 'import')) {
                $scores['import-furnizori'] = ($scores['import-furnizori'] ?? 0) + 4 * $weight;
            }
            if (in_array($action, ['delete', 'delete_bulk', 'update', 'add'], true)) {
                $scores['catalog-stoc'] = ($scores['catalog-stoc'] ?? 0) + $weight;
            }

            return;
        }

        if ($actor !== 'client') {
            return;
        }

        if (in_array($action, ['add_to_cart', 'cart_view', 'checkout_step', 'cart_abandon'], true)) {
            $scores['leaduri-cos'] = ($scores['leaduri-cos'] ?? 0) + 4 * $weight;
        }
        if (in_array($action, ['product_view', 'product_click', 'search', 'click'], true)) {
            $scores['catalog-stoc'] = ($scores['catalog-stoc'] ?? 0) + 3 * $weight;
        }
        if ($action === 'search') {
            $scores['cautari-fara-rezultat'] = ($scores['cautari-fara-rezultat'] ?? 0) + 2 * $weight;
        }
        if ($action === 'page_view') {
            if (str_contains($subject, 'cart') || str_contains($subject, 'cos')) {
                $scores['leaduri-cos'] = ($scores['leaduri-cos'] ?? 0) + 3 * $weight;
            } elseif (str_contains($subject, 'cont') || str_contains($subject, 'comenz')) {
                $scores['comenzi-livrare'] = ($scores['comenzi-livrare'] ?? 0) + 3 * $weight;
            } elseif (str_contains($subject, 'catalog') || str_contains($subject, 'product')) {
                $scores['catalog-stoc'] = ($scores['catalog-stoc'] ?? 0) + 3 * $weight;
            }
        }
    }

    /** @param array<string, int> $scores */
    private function scorePath(array &$scores, string $path): void
    {
        if ($path === '') {
            return;
        }
        if (preg_match('#/(cart|cos)(/|$|\?)#', $path)) {
            $scores['leaduri-cos'] = ($scores['leaduri-cos'] ?? 0) + 20;
        } elseif (preg_match('#/(cont|comenz|order)(/|$|\?)#', $path)) {
            $scores['agent-clienti'] = ($scores['agent-clienti'] ?? 0) + 22;
            $scores['comenzi-livrare'] = ($scores['comenzi-livrare'] ?? 0) + 18;
        } elseif (preg_match('#/(catalog|product|piese|categorie)(/|$|\?)#', $path)) {
            $scores['agent-produse'] = ($scores['agent-produse'] ?? 0) + 22;
            $scores['catalog-stoc'] = ($scores['catalog-stoc'] ?? 0) + 18;
        } elseif (preg_match('#/(contact|blog)(/|$|\?)#', $path)) {
            $scores['leaduri-cos'] = ($scores['leaduri-cos'] ?? 0) + 5;
        }
    }

    /** @param array<string, int> $scores */
    private function scoreAdminSection(array &$scores, string $section): void
    {
        if ($section === '') {
            return;
        }
        if (str_contains($section, 'comenz') || str_contains($section, 'order') || str_contains($section, 'client')) {
            $scores['agent-clienti'] = ($scores['agent-clienti'] ?? 0) + 28;
            $scores['comenzi-livrare'] = ($scores['comenzi-livrare'] ?? 0) + 20;
        } elseif (str_contains($section, 'importreview') || str_contains($section, 'quality')) {
            $scores['agent-imagini'] = ($scores['agent-imagini'] ?? 0) + 20;
            $scores['import-quality'] = ($scores['import-quality'] ?? 0) + 30;
        } elseif (str_contains($section, 'import') || str_contains($section, 'furnizor')) {
            $scores['import-furnizori'] = ($scores['import-furnizori'] ?? 0) + 25;
        } elseif (str_contains($section, 'produs') || str_contains($section, 'catalog')) {
            $scores['agent-produse'] = ($scores['agent-produse'] ?? 0) + 28;
            $scores['catalog-stoc'] = ($scores['catalog-stoc'] ?? 0) + 20;
        } elseif (str_contains($section, 'dashboard') || str_contains($section, 'stat') || str_contains($section, 'raport')) {
            $scores['agent-statistici'] = ($scores['agent-statistici'] ?? 0) + 30;
        } elseif (str_contains($section, 'search') || str_contains($section, 'cautar')) {
            $scores['cautari-fara-rezultat'] = ($scores['cautari-fara-rezultat'] ?? 0) + 25;
            $scores['agent-statistici'] = ($scores['agent-statistici'] ?? 0) + 10;
        } elseif (str_contains($section, 'imag') || str_contains($section, 'audit')) {
            $scores['agent-imagini'] = ($scores['agent-imagini'] ?? 0) + 30;
        }
    }

    /** @param array<string, int> $scores */
    private function scoreCorePatterns(array &$scores): void
    {
        $core = $this->events->getCore();
        $patterns = is_array($core['patterns'] ?? null) ? $core['patterns'] : [];

        if ((int) ($patterns['open_cart_abandonments'] ?? 0) > 0) {
            $scores['leaduri-cos'] = ($scores['leaduri-cos'] ?? 0) + 8;
        }
        $notFound = $patterns['searches_not_found'] ?? [];
        if (is_array($notFound) && $notFound !== []) {
            $scores['cautari-fara-rezultat'] = ($scores['cautari-fara-rezultat'] ?? 0) + 6;
            $scores['catalog-stoc'] = ($scores['catalog-stoc'] ?? 0) + 4;
        }
        $orders = $patterns['recent_orders'] ?? [];
        if (is_array($orders) && $orders !== []) {
            $scores['comenzi-livrare'] = ($scores['comenzi-livrare'] ?? 0) + 5;
        }
    }

    /** @param array<string, int> $scores */
    private function scoreActiveSessions(array &$scores): void
    {
        $sessionPath = dirname(__DIR__, 3) . '/system/client_session.php';
        if (!is_file($sessionPath)) {
            return;
        }
        require_once $sessionPath;
        if (!function_exists('client_session_list_recent')) {
            return;
        }

        foreach (client_session_list_recent(8) as $session) {
            if (!is_array($session)) {
                continue;
            }
            $intent = (string) ($session['intent'] ?? '');
            $weight = min(12, (int) ($session['event_count'] ?? 1));

            if ($intent === 'buy' || (int) ($session['cart_adds'] ?? 0) > 0) {
                $scores['leaduri-cos'] = ($scores['leaduri-cos'] ?? 0) + 3 * $weight;
            }
            if (!empty($session['searches']) || !empty($session['products_viewed'])) {
                $scores['catalog-stoc'] = ($scores['catalog-stoc'] ?? 0) + 2 * $weight;
            }
            $lastPage = strtolower((string) ($session['last_page'] ?? ''));
            if ($lastPage !== '') {
                $this->scorePath($scores, $lastPage);
            }
        }
    }

    /**
     * @param array<string, int> $scores
     * @param list<array<string, mixed>> $events
     */
    private function buildReason(string $slug, array $scores, string $path, array $events): string
    {
        $parts = [];
        if ($path !== '') {
            $parts[] = 'secțiune site `' . $path . '`';
        }

        $clientActs = [];
        foreach (array_reverse($events) as $event) {
            if (!is_array($event) || ($event['actor_type'] ?? '') !== 'client') {
                continue;
            }
            $act = (string) ($event['action'] ?? '');
            if ($act !== '' && !in_array($act, $clientActs, true)) {
                $clientActs[] = $act;
            }
            if (count($clientActs) >= 3) {
                break;
            }
        }
        if ($clientActs !== []) {
            $parts[] = 'acțiuni recente: ' . implode(', ', $clientActs);
        }

        $top = array_key_first($scores);
        if ($top === $slug && ($scores[$slug] ?? 0) > ($scores['context-master'] ?? 0)) {
            $parts[] = 'scor agent ' . ($scores[$slug] ?? 0);
        }

        if ($parts === []) {
            return 'Activitate generală — Context Master + agent specialist când apar semnale.';
        }

        return 'Automat: ' . implode(' · ', $parts);
    }
}
