<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * Agent context Besoiu (agent context Besoiu) — colectează semnale din admin/robot/site,
 * sintetizează context creativ (temperature 1) pentru roboți.
 * Core-ul AI se formează din acțiunile reale (admin + client).
 */
final class AiContextAgentService
{
    private const TEMPERATURE = 1.0;

    private string $contextDir;
    private AiActionEventService $events;

    public function __construct(?string $contextDir = null, ?AiActionEventService $events = null)
    {
        $root = dirname(__DIR__, 3);
        $this->contextDir = $contextDir ?? ($root . '/robot/data/ai_context');
        $this->events = $events ?? new AiActionEventService($this->contextDir);
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $latest = $this->readJson($this->contextDir . '/latest.json');

        return [
            'context_dir' => $this->contextDir,
            'latest_at' => $latest['generated_at'] ?? null,
            'core_at' => ($this->events->getCore()['updated_at'] ?? null),
            'event_count' => count($this->events->readEvents(9999)),
            'signal_count' => (int) ($latest['signal_count'] ?? 0),
            'markdown_chars' => strlen((string) ($latest['markdown'] ?? '')),
            'sources_ok' => (int) ($latest['sources_ok'] ?? 0),
            'sources_total' => (int) ($latest['sources_total'] ?? 0),
            'llm_used' => (bool) ($latest['llm_used'] ?? false),
            'temperature' => self::TEMPERATURE,
            'paths' => [
                'json' => $this->contextDir . '/latest.json',
                'markdown' => $this->contextDir . '/latest.md',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function runFullCycle(bool $useLlm = true): array
    {
        $core = $this->events->rebuildCore();
        $signals = $this->collectSignals($core);
        $bundle = $this->synthesize($signals, $useLlm);
        $this->persist($bundle);

        return $bundle;
    }

    /** @param array<string, mixed>|null $core */
    public function collectSignals(?array $core = null): array
    {
        $robotRoot = dirname($this->contextDir);
        $adminStorage = dirname(__DIR__, 2) . '/storage';

        $sources = [];

        $sources['robot_leads'] = $this->sourceMeta(
            'Lead-uri robot',
            $this->readJson($robotRoot . '/leads.json'),
            static fn (array $data): string => count($data) . ' lead-uri înregistrate'
        );

        $webhookPath = $robotRoot . '/webhook.log';
        if (is_file($webhookPath)) {
            $lines = @file($webhookPath, FILE_IGNORE_NEW_LINES) ?: [];
            $sources['robot_webhook'] = [
                'label' => 'Webhook WhatsApp',
                'ok' => true,
                'summary' => count($lines) . ' linii log · ultima: ' . $this->tailLine($lines),
            ];
        } else {
            $sources['robot_webhook'] = ['label' => 'Webhook WhatsApp', 'ok' => false, 'summary' => 'Lipsă webhook.log'];
        }

        $sessionsDir = $robotRoot . '/sessions';
        $sessionCount = is_dir($sessionsDir) ? count(glob($sessionsDir . '/*.json') ?: []) : 0;
        $sources['robot_sessions'] = [
            'label' => 'Sesiuni chat robot',
            'ok' => $sessionCount > 0,
            'summary' => $sessionCount . ' sesiuni active în arhivă',
        ];

        $runtime = $this->readJson($robotRoot . '/runtime_besoiu.json');
        $sources['robot_runtime'] = [
            'label' => 'Runtime robot',
            'ok' => $runtime !== [],
            'summary' => $runtime !== []
                ? 'Ultimul status: ' . (string) ($runtime['status'] ?? $runtime['phase'] ?? 'ok')
                : 'Fără runtime_besoiu.json',
        ];

        $scanPath = $adminStorage . '/supplier_scan_last_run.json';
        $scan = $this->readJson($scanPath);
        $sources['supplier_scan'] = [
            'label' => 'Scan furnizori',
            'ok' => $scan !== [],
            'summary' => $scan !== []
                ? 'Ultimul scan: ' . (string) ($scan['finished_at'] ?? $scan['started_at'] ?? '—')
                : 'Fără raport scan recent',
        ];

        $sources['database'] = [
            'label' => 'Bază de date',
            'ok' => false,
            'summary' => 'Indisponibil',
        ];
        try {
            $pdo = Database::getDB();
            $sources['database'] = [
                'label' => 'Bază de date',
                'ok' => true,
                'summary' => sprintf(
                    '%d produse · %d rânduri import · %d comenzi (7 zile)',
                    (int) ($this->scalar($pdo, 'SELECT COUNT(*) FROM produse') ?? 0),
                    (int) ($this->scalar($pdo, 'SELECT COUNT(*) FROM import_produse') ?? 0),
                    (int) ($this->scalar($pdo, 'SELECT COUNT(*) FROM comenzi WHERE data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)') ?? 0)
                ),
            ];
        } catch (Throwable) {
            // fallback fără comenzi
            try {
                $pdo = Database::getDB();
                $sources['database'] = [
                    'label' => 'Bază de date',
                    'ok' => true,
                    'summary' => sprintf(
                        '%d produse · %d rânduri import',
                        $this->scalar($pdo, 'SELECT COUNT(*) FROM produse'),
                        $this->scalar($pdo, 'SELECT COUNT(*) FROM import_produse')
                    ),
                ];
            } catch (Throwable) {
                // păstrează indisponibil
            }
        }

        $importByStatus = $this->importQueueSummary();
        $sources['import_queue'] = [
            'label' => 'Coadă import',
            'ok' => $importByStatus !== [],
            'summary' => $importByStatus !== []
                ? implode(' · ', array_map(
                    static fn (string $k, int $v): string => $k . ': ' . $v,
                    array_keys($importByStatus),
                    array_values($importByStatus)
                ))
                : 'Fără date coadă',
        ];

        $sources['ai_tokens'] = [
            'label' => 'Consum tokeni AI (azi)',
            'ok' => false,
            'summary' => 'Fără date',
        ];
        $sources['api_token_budget'] = [
            'label' => 'Buget API (Scrape.do / RapidAPI)',
            'ok' => false,
            'summary' => 'Nesincronizat',
        ];
        try {
            $pdo = Database::getDB();
            $budgetFile = dirname(__DIR__, 2) . '/system/api_token_budget.php';
            if (is_file($budgetFile)) {
                require_once $budgetFile;
                if (function_exists('api_token_budget_hub_snapshot')) {
                    $hub = api_token_budget_hub_snapshot($pdo);
                    $sources['api_token_budget'] = [
                        'label' => 'Buget API (Scrape.do / RapidAPI)',
                        'ok' => ($hub['llm_context'] ?? '') !== '',
                        'summary' => (string) ($hub['llm_context'] !== '' ? $hub['llm_context'] : 'Completează tokenii în Setări'),
                        'hub' => $hub,
                    ];
                }
            }
            $tokensToday = $this->scalar(
                $pdo,
                'SELECT COALESCE(SUM(total_tokens), 0) FROM ai_token_usage WHERE usage_date = CURDATE()'
            );
            if ($tokensToday !== null) {
                $sources['ai_tokens'] = [
                    'label' => 'Consum tokeni AI (azi)',
                    'ok' => true,
                    'summary' => number_format((int) $tokensToday, 0, ',', '.') . ' tokeni azi',
                ];
            }
        } catch (Throwable) {
            // tabel opțional
        }

        $sysErrLatest = $this->readJson($this->contextDir . '/system_errors_latest.json');
        $learning = $this->readJson($this->contextDir . '/learning_state.json');
        $sysArchives = is_array($learning['system_errors_archives'] ?? null) ? $learning['system_errors_archives'] : [];
        $lastArchive = $sysArchives !== [] ? $sysArchives[array_key_last($sysArchives)] : null;
        $sources['system_errors_archive'] = [
            'label' => 'Jurnal erori (arhivă AI)',
            'ok' => $sysErrLatest !== [] || $lastArchive !== null,
            'summary' => $sysErrLatest !== []
                ? sprintf(
                    'Ultima arhivare: %s · %d erori · %s',
                    (string) ($sysErrLatest['period_label'] ?? $sysErrLatest['period'] ?? '—'),
                    (int) ($sysErrLatest['count'] ?? 0),
                    isset($sysErrLatest['updated_at']) ? date('d.m H:i', strtotime((string) $sysErrLatest['updated_at'])) : '—'
                )
                : ($lastArchive !== null
                    ? 'Arhivă în learning_state: ' . (int) ($lastArchive['count'] ?? 0) . ' erori'
                    : 'Fără arhivă erori — jurnal activ curat'),
        ];

        $supervisorDir = dirname($this->contextDir) . '/ai_supervisor';
        $catalogAudit = $this->readJson($supervisorDir . '/catalog_audit.json');
        $sources['supervisor_catalog'] = [
            'label' => 'Supervizor — audit catalog',
            'ok' => ($catalogAudit['score'] ?? 0) >= 65,
            'summary' => $catalogAudit !== []
                ? sprintf('Scor %d/100 · %d probleme · %s', (int) ($catalogAudit['score'] ?? 0), count($catalogAudit['issues'] ?? []), (string) ($catalogAudit['generated_at'] ?? '—'))
                : 'Nerulat — așteaptă cron supervizor',
        ];
        $pipeHealth = $this->readJson($supervisorDir . '/pipeline_health.json');
        $sources['supervisor_pipeline'] = [
            'label' => 'Supervizor — pipeline imagini',
            'ok' => !empty($pipeHealth['ok']),
            'summary' => $pipeHealth !== []
                ? sprintf('%d/%d teste OK · %d eșuate', (int) ($pipeHealth['passed'] ?? 0), (int) ($pipeHealth['total'] ?? 0), (int) ($pipeHealth['failed'] ?? 0))
                : 'Nerulat',
        ];
        $dailyReport = $this->readJson($supervisorDir . '/daily_report.json');
        $sources['supervisor_daily'] = [
            'label' => 'Raport zilnic supervizor',
            'ok' => $dailyReport !== [] && (($dailyReport['day'] ?? '') === date('Y-m-d')),
            'summary' => $dailyReport !== []
                ? 'Zi ' . (string) ($dailyReport['day'] ?? '—') . ' · ' . count($dailyReport['highlights'] ?? []) . ' highlights'
                : 'Lipsește raportul zilnic',
        ];

        $leads = $this->readJson($robotRoot . '/leads.json');
        $recentLeads = [];
        if (is_array($leads)) {
            foreach (array_slice(array_reverse($leads), 0, 5) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $recentLeads[] = [
                    'name' => (string) ($row['name'] ?? $row['nume'] ?? '—'),
                    'phone' => (string) ($row['phone'] ?? $row['telefon'] ?? ''),
                    'source' => (string) ($row['source'] ?? $row['canal'] ?? ''),
                    'at' => (string) ($row['created_at'] ?? $row['data'] ?? ''),
                ];
            }
        }

        $core = $core ?? $this->events->rebuildCore();
        $recentEvents = $this->events->readEvents(30);

        $sources['ai_core'] = [
            'label' => 'Core AI (acțiuni proiect)',
            'ok' => ($core['event_count'] ?? 0) > 0 || ($core['highlights'] ?? []) !== [],
            'summary' => sprintf(
                '%d evenimente · %d admin · %d client · actualizat %s',
                (int) ($core['event_count'] ?? 0),
                (int) ($core['actors']['admin'] ?? 0),
                (int) ($core['actors']['client'] ?? 0),
                isset($core['updated_at']) ? date('d.m H:i', strtotime((string) $core['updated_at'])) : '—'
            ),
        ];

        $sources['client_searches'] = [
            'label' => 'Căutări clienți (7 zile, negăsite)',
            'ok' => is_array($core['patterns']['searches_not_found'] ?? null)
                && ($core['patterns']['searches_not_found'] ?? []) !== [],
            'summary' => $this->summarizeNotFoundSearches($core),
        ];

        return [
            'collected_at' => date('c'),
            'sources' => $sources,
            'facts' => [
                'import_queue' => $importByStatus,
                'recent_leads' => $recentLeads,
                'webhook_tail' => is_file($webhookPath)
                    ? array_slice(@file($webhookPath, FILE_IGNORE_NEW_LINES) ?: [], -8)
                    : [],
                'ai_core' => $core,
                'recent_events' => $recentEvents,
            ],
        ];
    }

    /** @param array<string, mixed> $signals */
    public function synthesize(array $signals, bool $useLlm = true): array
    {
        $sources = is_array($signals['sources'] ?? null) ? $signals['sources'] : [];
        $facts = is_array($signals['facts'] ?? null) ? $signals['facts'] : [];
        $core = is_array($facts['ai_core'] ?? null) ? $facts['ai_core'] : [];

        $lines = [];
        $lines[] = '# Context Besoiu — briefing agent (creativitate maximă · temperature ' . self::TEMPERATURE . ')';
        $lines[] = 'Generat: ' . date('Y-m-d H:i:s');
        $lines[] = '';
        $lines[] = '## Ce se întâmplă acum (pe scurt)';
        $lines[] = $this->buildSituationNarrative($sources, $facts, $core);
        $lines[] = '';
        $lines[] = '## Semnale operaționale';
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $mark = !empty($source['ok']) ? '✓' : '○';
            $lines[] = '- ' . $mark . ' **' . (string) ($source['label'] ?? 'Sursă') . '**: '
                . (string) ($source['summary'] ?? '—');
        }

        $lines[] = '';
        $lines[] = '## Lead-uri recente';
        $recentLeads = $facts['recent_leads'] ?? [];
        if ($recentLeads === []) {
            $lines[] = '- Niciun lead recent în robot/data/leads.json';
        } else {
            foreach ($recentLeads as $lead) {
                if (!is_array($lead)) {
                    continue;
                }
                $lines[] = '- ' . trim((string) ($lead['name'] ?? '—'))
                    . ' · ' . trim((string) ($lead['phone'] ?? ''))
                    . ' · ' . trim((string) ($lead['source'] ?? ''));
            }
        }

        $lines[] = '';
        $lines[] = '## Coadă import (status)';
        $queue = $facts['import_queue'] ?? [];
        if ($queue === []) {
            $lines[] = '- Fără statistici import';
        } else {
            foreach ($queue as $status => $count) {
                $lines[] = '- ' . $status . ': ' . $count;
            }
        }

        $lines[] = '';
        $lines[] = '## Core AI — învățat din acțiuni (admin + client)';
        foreach ($core['robot_directives'] ?? [] as $directive) {
            if (is_string($directive) && $directive !== '') {
                $lines[] = '- ' . $directive;
            }
        }

        $lines[] = '';
        $lines[] = '### Timeline acțiuni recente';
        $recentEvents = is_array($facts['recent_events'] ?? null) ? $facts['recent_events'] : [];
        if ($recentEvents === []) {
            $lines[] = '- Niciun eveniment înregistrat încă (acțiunile vor popula automat core-ul).';
        } else {
            foreach (array_slice(array_reverse($recentEvents), 0, 15) as $event) {
                if (!is_array($event)) {
                    continue;
                }
                $lines[] = '- [' . ($event['actor_type'] ?? '?') . '] '
                    . ($event['action'] ?? '')
                    . (($event['subject'] ?? '') !== '' ? ' — ' . $event['subject'] : '');
            }
        }

        $notFound = $core['patterns']['searches_not_found'] ?? [];
        if (is_array($notFound) && $notFound !== []) {
            $lines[] = '';
            $lines[] = '### Cereri clienți fără rezultat (prioritate stoc)';
            foreach (array_slice($notFound, 0, 8) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $lines[] = '- ' . (string) ($row['query_type'] ?? 'search') . ': '
                    . (string) ($row['query_value'] ?? '')
                    . ((string) ($row['vehicle_label'] ?? '') !== '' ? ' · ' . $row['vehicle_label'] : '');
            }
        }

        $orders = $core['patterns']['recent_orders'] ?? [];
        if (is_array($orders) && $orders !== []) {
            $lines[] = '';
            $lines[] = '### Comenzi recente (7 zile)';
            foreach ($orders as $order) {
                if (!is_array($order)) {
                    continue;
                }
                $lines[] = '- #' . (string) ($order['id'] ?? '?') . ' · '
                    . (string) ($order['status'] ?? '') . ' · '
                    . (string) ($order['total'] ?? '') . ' RON';
            }
        }

        $lines[] = '';
        $lines[] = '## Instrucțiuni pentru roboți (mod creativ)';
        $lines[] = '- Vorbește natural, cald, convingător — temperature 1, dar **nu inventa** stoc, preț sau disponibilitate.';
        $lines[] = '- Core-ul se actualizează automat din acțiunile admin și clienților — folosește-le ca adevăr.';
        $lines[] = '- Propune alternative creative la căutări negăsite; transformă lead-urile și coșurile abandonate în oportunități.';
        $lines[] = '- Dacă webhook/log arată erori, spune clar clientului că verifici cu operatorul.';
        $lines[] = '- Răspuns în română, stil consultativ auto — scurt când e urgent, detaliat când ajută la vânzare.';

        $markdown = implode("\n", $lines);
        $llmUsed = false;
        $llmBrief = '';

        if ($useLlm) {
            $llmBrief = $this->optionalLlmBrief($signals, $markdown);
            if ($llmBrief !== '') {
                $llmUsed = true;
                $markdown .= "\n\n## Brief LLM (creativ · temperature " . self::TEMPERATURE . ")\n" . $llmBrief;
            }
        }

        $sourcesOk = 0;
        foreach ($sources as $source) {
            if (is_array($source) && !empty($source['ok'])) {
                $sourcesOk++;
            }
        }

        return [
            'generated_at' => date('c'),
            'temperature' => self::TEMPERATURE,
            'signal_count' => count($sources),
            'sources_ok' => $sourcesOk,
            'sources_total' => count($sources),
            'llm_used' => $llmUsed,
            'markdown' => $markdown,
            'signals' => $signals,
            'robot_brief' => $llmBrief !== '' ? $llmBrief : $this->compactBrief($sources, $facts),
        ];
    }

    /** @param array<string, mixed> $bundle */
    private function persist(array $bundle): void
    {
        if (!is_dir($this->contextDir)) {
            @mkdir($this->contextDir, 0775, true);
        }
        $historyDir = $this->contextDir . '/history';
        if (!is_dir($historyDir)) {
            @mkdir($historyDir, 0775, true);
        }

        $jsonPath = $this->contextDir . '/latest.json';
        $mdPath = $this->contextDir . '/latest.md';
        $stamp = date('Y-m-d_His');

        file_put_contents($jsonPath, json_encode($bundle, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($mdPath, (string) ($bundle['markdown'] ?? ''));
        $corePath = $this->contextDir . '/core.json';
        if (is_file($corePath)) {
            @copy($corePath, $this->contextDir . '/core.snapshot.json');
        }
        file_put_contents(
            $historyDir . '/' . $stamp . '.json',
            json_encode($bundle, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $this->pruneHistory($historyDir, 30);
    }

    /** @return list<array<string, mixed>> */
    public function history(int $limit = 10): array
    {
        $dir = $this->contextDir . '/history';
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.json') ?: [];
        rsort($files);
        $out = [];
        foreach (array_slice($files, 0, max(1, min(50, $limit))) as $file) {
            $data = $this->readJson($file);
            $out[] = [
                'file' => basename($file),
                'generated_at' => $data['generated_at'] ?? null,
                'signal_count' => (int) ($data['signal_count'] ?? 0),
                'llm_used' => (bool) ($data['llm_used'] ?? false),
            ];
        }

        return $out;
    }

    /** @return array<string, int> */
    private function importQueueSummary(): array
    {
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                'SELECT COALESCE(NULLIF(TRIM(status), \'\'), \'unknown\') AS st, COUNT(*) AS cnt
                 FROM import_produse GROUP BY st ORDER BY cnt DESC LIMIT 8'
            );
            if ($stmt === false) {
                return [];
            }
            $out = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }
                $out[(string) ($row['st'] ?? 'unknown')] = (int) ($row['cnt'] ?? 0);
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    private function scalar(PDO $pdo, string $sql): ?int
    {
        try {
            $val = $pdo->query($sql)?->fetchColumn();

            return $val !== false ? (int) $val : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $core */
    private function summarizeNotFoundSearches(array $core): string
    {
        $rows = $core['patterns']['searches_not_found'] ?? [];
        if (!is_array($rows) || $rows === []) {
            return 'Nicio căutare negăsită recent';
        }
        $parts = [];
        foreach (array_slice($rows, 0, 4) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $parts[] = (string) ($row['query_value'] ?? '');
        }

        return count($rows) . ' cereri · ex: ' . implode(', ', array_filter($parts));
    }

    /** @param array<string, mixed> $sources @param array<string, mixed> $facts @param array<string, mixed> $core */
    private function buildSituationNarrative(array $sources, array $facts, array $core): string
    {
        $bits = [];
        $okCount = 0;
        $total = 0;
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $total++;
            if (!empty($source['ok'])) {
                $okCount++;
            }
        }
        $bits[] = sprintf(
            'Agentul monitorizează **%d/%d surse** active (BD, import, robot, webhook, core AI).',
            $okCount,
            max(1, $total)
        );

        $events = (int) ($core['event_count'] ?? 0);
        $admin = (int) ($core['actors']['admin'] ?? 0);
        $client = (int) ($core['actors']['client'] ?? 0);
        if ($events > 0) {
            $bits[] = sprintf(
                'În ultimele acțiuni: **%d evenimente** (%d admin · %d clienți).',
                $events,
                $admin,
                $client
            );
        } else {
            $bits[] = 'Încă nu sunt evenimente în jurnal — orice acțiune în admin sau căutare pe site le va popula.';
        }

        $notFound = $core['patterns']['searches_not_found'] ?? [];
        if (is_array($notFound) && $notFound !== []) {
            $top = array_slice($notFound, 0, 2);
            $codes = array_map(
                static fn (array $r): string => (string) ($r['query_value'] ?? ''),
                array_filter($top, 'is_array')
            );
            $codes = array_values(array_filter($codes));
            $bits[] = '**Atenție stoc:** clienții caută fără rezultat '
                . ( $codes !== [] ? '«' . implode('», «', $codes) . '»' : count($notFound) . ' coduri' )
                . ' — propune alternative sau verificare operator.';
        }

        if ((int) ($core['patterns']['open_cart_abandonments'] ?? 0) > 0) {
            $bits[] = '**Coșuri abandonate** deschise — ocazie de follow-up prietenos.';
        }

        $orders = $core['patterns']['recent_orders'] ?? [];
        if (is_array($orders) && $orders !== []) {
            $bits[] = count($orders) . ' comenzi recente în sistem — menționează disponibilitatea livrării dacă e cazul.';
        }

        $leadCount = is_array($facts['recent_leads'] ?? null) ? count($facts['recent_leads']) : 0;
        if ($leadCount > 0) {
            $bits[] = $leadCount . ' lead-uri recente din robot — prioritizează contactul rapid.';
        }

        return '- ' . implode("\n- ", $bits);
    }

    /** @param array<string, mixed> $sources @param array<string, mixed> $facts */
    private function compactBrief(array $sources, array $facts): string
    {
        $parts = [];
        foreach ($sources as $source) {
            if (!is_array($source) || empty($source['ok'])) {
                continue;
            }
            $parts[] = (string) ($source['label'] ?? '') . ': ' . (string) ($source['summary'] ?? '');
        }
        $leadCount = is_array($facts['recent_leads'] ?? null) ? count($facts['recent_leads']) : 0;
        $parts[] = 'Lead-uri recente listate: ' . $leadCount;

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $signals */
    private function optionalLlmBrief(array $signals, string $deterministicMarkdown): string
    {
        require_once dirname(__DIR__, 3) . '/system/ai_api_errors.php';

        $systemPrompt = 'Ești agentul de context Besoiu (agent context Besoiu). Sintetizezi semnale reale din proiect '
            . 'într-un briefing viu, clar, orientat spre vânzări — fără invenții despre stoc/preț. '
            . 'Creativitate maximă (temperature 1): formulări naturale, propuneri alternative, ton consultativ auto. '
            . 'Output: bullet points scurte în română pentru roboți WhatsApp/chat.';

        $userPrompt = "Semnale JSON:\n" . json_encode($signals, JSON_UNESCAPED_UNICODE)
            . "\n\nDraft determinist:\n" . $deterministicMarkdown;

        $llm = LlmRouterService::create(dirname(__DIR__, 3));
        if ($llm->isConfigured()) {
            $result = $llm->complete($systemPrompt, $userPrompt, self::TEMPERATURE, 120, 'context_brief');
            if (!empty($result['ok']) && trim((string) ($result['content'] ?? '')) !== '') {
                return trim((string) $result['content']);
            }
            if (!empty($result['error'])) {
                ai_api_report_error('context-master', (string) $result['error'], ['provider' => (string) ($result['routed_via'] ?? 'llm')], 0);
            }
        }

        $key = $this->resolveLlmKey();
        if ($key === '') {
            ai_api_report_error(
                'context-master',
                'Lipsă GROQ_KEY / OPENAI_KEY sau Ollama — briefing LLM dezactivat',
                [],
                401
            );

            return '';
        }

        $guardPath = dirname(__DIR__, 3) . '/system/api_automation_guard.php';
        if (is_file($guardPath)) {
            require_once $guardPath;
            if (!besoiu_api_live_call_allowed('llm')) {
                return '';
            }
        }

        $payload = [
            'model' => $this->resolveLlmModel(),
            'temperature' => self::TEMPERATURE,
            'max_tokens' => 900,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        $endpoint = str_contains($key, 'gsk_')
            ? 'https://api.groq.com/openai/v1/chat/completions'
            : 'https://api.openai.com/v1/chat/completions';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if (!is_string($raw) || $raw === '' || $httpCode !== 200) {
            $parsed = ai_api_parse_response_error(is_string($raw) ? $raw : ($curlErr ?: ''), $httpCode);
            ai_api_report_error('context-master', $parsed['message'], ['curl' => $curlErr], $httpCode);

            return '';
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            ai_api_report_error('context-master', 'Răspuns AI invalid (JSON)', [], $httpCode);

            return '';
        }

        $content = trim((string) ($json['choices'][0]['message']['content'] ?? ''));
        if ($content === '' && isset($json['error'])) {
            $parsed = ai_api_parse_response_error($raw, $httpCode);
            ai_api_report_error('context-master', $parsed['message'], [], $httpCode);
        }

        return $content;
    }

    private function resolveLlmKey(): string
    {
        if (function_exists('env')) {
            $groq = trim((string) env('GROQ_KEY', ''));
            if ($groq !== '') {
                return $groq;
            }
            $openai = trim((string) env('OPENAI_KEY', ''));
            if ($openai !== '') {
                return $openai;
            }
        }

        return trim((string) (getenv('GROQ_KEY') ?: getenv('OPENAI_KEY') ?: ''));
    }

    private function resolveLlmModel(): string
    {
        if (function_exists('env')) {
            $groqModel = trim((string) env('GROQ_MODEL', ''));
            if ($groqModel !== '') {
                return $groqModel;
            }
        }

        return 'llama-3.3-70b-versatile';
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }

    /** @param array<int, string> $lines */
    private function tailLine(array $lines): string
    {
        if ($lines === []) {
            return '—';
        }
        $line = trim((string) end($lines));

        return $line !== '' ? mb_substr($line, 0, 120) : '—';
    }

    /** @param mixed $probe */
    private function sourceMeta(string $label, mixed $probe, callable $summaryFn): array
    {
        $ok = is_array($probe) ? $probe !== [] : (bool) $probe;

        return [
            'label' => $label,
            'ok' => $ok,
            'summary' => $ok ? $summaryFn(is_array($probe) ? $probe : []) : 'Indisponibil',
        ];
    }

    private function pruneHistory(string $dir, int $keep): void
    {
        $files = glob($dir . '/*.json') ?: [];
        rsort($files);
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }
}
