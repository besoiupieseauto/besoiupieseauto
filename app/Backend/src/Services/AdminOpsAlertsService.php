<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\AppCache;
use Besoiu\Core\AdminUrl;
use Config\Database;
use Throwable;

/**
 * Sursă unică alerte operaționale — topbar, AI Agent, pagina /admin/alerts.
 */
final class AdminOpsAlertsService
{
    /** @var list<string> */
    private const SCRAPER_CODES = ['scrape_do_token_missing', 'scrape_do_quota', 'scraper_no_plans', 'scraper_rapidapi_key'];

    /** @var list<string> */
    private const SCRAPER_CHANNELS = ['scrape_do', 'scraper', 'autodoc', 'epiesa', 'emag'];

    /** @var list<string> */
    private const TECDOC_CODES = ['tecdoc_dead', 'tecdoc_api', 'tecdoc_unified', 'tecdoc_ip_invalid'];

    /** @var list<string> */
    private const TECDOC_CHANNELS = ['rapidapi', 'tecdoc', 'tecdoc_api', 'catalog'];

    /** @return array<string, mixed> */
    public function feed(): array
    {
        $items = $this->collectRawItems();
        $items = $this->dedupeItems($items);
        $items = $this->enrichFixMeta($items);
        $groups = $this->groupItems($items);

        $critical = $groups['critical'];
        $warning = $groups['warning'];
        $info = $groups['info'];

        $status = 'ok';
        if ($critical !== []) {
            $status = 'critical';
        } elseif ($warning !== []) {
            $status = 'warning';
        }

        $aiHealth = $this->readAiHealth();
        $keyOk = $this->aiKeyConfigured();
        $errStats = $this->systemErrorStats();

        return [
            'status' => $status,
            'bell_count' => min(99, count($critical)),
            'critical_count' => count($critical),
            'warning_count' => count($warning),
            'info_count' => count($info),
            'total_count' => count($items),
            'unresolved_errors' => (int) ($errStats['unresolved'] ?? 0),
            'ai_key_ok' => $keyOk,
            'ai_health' => $aiHealth,
            'checked_at' => date('c'),
            'groups' => $groups,
            'items' => array_merge($critical, $warning, $info),
        ];
    }

    /** Compat popup clopoțel / banner AI Agent. @return array<string, mixed> */
    public function summary(): array
    {
        return AppCache::remember('ops_alerts_summary_v2', 30, function (): array {
            $feed = $this->feed();

            return [
                'status' => $feed['status'],
                'bell_count' => $feed['bell_count'],
                'critical_count' => $feed['critical_count'],
                'warning_count' => $feed['warning_count'],
                'unresolved_errors' => $feed['unresolved_errors'],
                'ai_key_ok' => $feed['ai_key_ok'],
                'ai_health' => $feed['ai_health'],
                'checked_at' => $feed['checked_at'],
                'items' => array_slice($feed['items'], 0, 12),
            ];
        });
    }

    /** @return list<array<string, mixed>> */
    private function collectRawItems(): array
    {
        $items = [];

        // IMPORTANT: NU apela DashboardService::overview() aici.
        // overview() face COUNT grele pe produse + probe HTTP TecDoc (până la 30s)
        // și blochează TOATE paginile admin când clopoțelul / assistant fac poll.

        $keyOk = $this->aiKeyConfigured();
        $aiHealth = $this->readAiHealth();

        if (!$keyOk) {
            $ollamaOk = false;
            try {
                $metroFile = dirname(__DIR__, 2) . '/system/metro_llm_hub.php';
                if (is_file($metroFile)) {
                    require_once $metroFile;
                    require_once dirname(__DIR__, 2) . '/system/ollama_llm.php';
                    if (besoiu_ollama_enabled()) {
                        require_once dirname(__DIR__) . '/Services/OllamaLlmClient.php';
                        $ollamaOk = (new OllamaLlmClient(dirname(__DIR__, 3)))->readiness()['ready'] ?? false;
                    }
                }
            } catch (Throwable) {
                $ollamaOk = false;
            }
            if (!$ollamaOk) {
                $items[] = $this->makeItem([
                    'code' => 'ai_key_missing',
                    'level' => 'critical',
                    'title' => 'Cheie AI lipsă',
                    'detail' => 'Configurează GROQ_KEY / OPENAI_KEY sau activează Ollama local în Setări → Metro LLM.',
                    'problem' => 'Nici Cursor, nici Ollama local nu sunt disponibile.',
                    'action_label' => 'Deschide setări',
                    'url' => '/admin/settings?tab=tokens',
                ]);
            }
        } else {
            $lastErr = trim((string) ($aiHealth['last_error'] ?? ''));
            $lastErrAt = (string) ($aiHealth['last_error_at'] ?? '');
            if ($lastErr !== '' && $lastErrAt !== '' && (time() - (int) strtotime($lastErrAt)) < 86400) {
                $items[] = $this->makeItem([
                    'code' => 'ai_api_error',
                    'level' => 'critical',
                    'title' => 'Eroare API AI',
                    'detail' => $lastErr,
                    'problem' => 'Ultimul apel către furnizorul AI a eșuat.',
                    'action_label' => 'Vezi monitor AI',
                    'url' => '/admin/ai-rag?tab=system',
                    'at' => $lastErrAt,
                    'source' => (string) ($aiHealth['last_error_source'] ?? ''),
                ]);
            }
        }

        $covered = $this->inferCoveredChannels($items);
        foreach ($this->recentSystemErrors(8) as $row) {
            $channel = strtolower(trim((string) ($row['channel'] ?? 'general')));
            if ($channel !== '' && in_array($channel, $covered, true)) {
                continue;
            }
            $lvl = (string) ($row['level'] ?? 'error');
            if (!in_array($lvl, ['error', 'critical', 'warning'], true)) {
                continue;
            }
            $msg = trim((string) ($row['message'] ?? ''));
            if ($msg === '') {
                continue;
            }
            $items[] = $this->makeItem([
                'code' => 'system_error_' . $channel,
                'level' => in_array($lvl, ['error', 'critical'], true) ? 'critical' : 'warning',
                'title' => $this->channelTitle($channel),
                'detail' => $msg,
                'problem' => 'Eroare nerezolvată în jurnalul de sistem (' . $channel . ').',
                'action_label' => 'Jurnal erori',
                'url' => '/admin/system-errors',
                'at' => (string) ($row['created_at'] ?? ''),
                'error_id' => (int) ($row['id'] ?? 0),
                'channel' => $channel,
            ]);
        }

        try {
            $metroFile = dirname(__DIR__, 2) . '/system/metro_llm_hub.php';
            if (is_file($metroFile)) {
                require_once $metroFile;
                foreach (metro_llm_collect_ops_alerts(dirname(__DIR__, 3)) as $metroItem) {
                    $items[] = $this->makeItem($metroItem);
                }
            }
        } catch (Throwable) {
            // optional
        }

        return $items;
    }

    /** @param array<string, mixed> $flag @return array<string, mixed> */
    private function normalizeItem(array $flag): array
    {
        $code = (string) ($flag['code'] ?? 'ops_alert');
        $level = $this->normalizeLevel($flag);
        $title = (string) ($flag['title'] ?? 'Alertă');
        $detail = (string) ($flag['detail'] ?? '');

        return $this->makeItem([
            'code' => $code,
            'level' => $level,
            'title' => $title,
            'detail' => $detail,
            'problem' => $this->problemForCode($code, $title, $detail),
            'action_label' => $this->actionLabelForCode($code),
            'url' => (string) ($flag['url'] ?? '/admin/alerts'),
            'at' => (string) ($flag['at'] ?? date('c')),
            'retry_action' => (string) ($flag['retry_action'] ?? ''),
            'job_id' => (string) ($flag['job_id'] ?? ''),
            'entity_type' => (string) ($flag['entity_type'] ?? ''),
            'entity_id' => (string) ($flag['entity_id'] ?? ''),
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function makeItem(array $data): array
    {
        return [
            'code' => (string) ($data['code'] ?? 'ops_alert'),
            'level' => (string) ($data['level'] ?? 'warning'),
            'title' => (string) ($data['title'] ?? 'Alertă'),
            'detail' => (string) ($data['detail'] ?? ''),
            'problem' => (string) ($data['problem'] ?? ''),
            'action_label' => (string) ($data['action_label'] ?? 'Deschide'),
            'url' => (string) ($data['url'] ?? '/admin/alerts'),
            'at' => (string) ($data['at'] ?? date('c')),
            'source' => (string) ($data['source'] ?? ''),
            'retry_action' => (string) ($data['retry_action'] ?? ''),
            'job_id' => (string) ($data['job_id'] ?? ''),
            'entity_type' => (string) ($data['entity_type'] ?? ''),
            'entity_id' => (string) ($data['entity_id'] ?? ''),
            'error_id' => (int) ($data['error_id'] ?? 0),
            'channel' => (string) ($data['channel'] ?? ''),
            'fix_action' => (string) ($data['fix_action'] ?? ''),
            'fixable' => (bool) ($data['fixable'] ?? false),
            'fix_label' => (string) ($data['fix_label'] ?? ''),
            'fix_guide' => is_array($data['fix_guide'] ?? null) ? $data['fix_guide'] : [],
        ];
    }

    /** @param list<array<string, mixed>> $items @return list<array<string, mixed>> */
    private function enrichFixMeta(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $code = (string) ($item['code'] ?? '');
            $retry = (string) ($item['retry_action'] ?? '');

            $fixAction = match (true) {
                $retry !== '' => $retry,
                in_array($code, ['tecdoc_unified', 'tecdoc_dead', 'tecdoc_api', 'tecdoc_ip_invalid'], true) => 'refresh_tecdoc',
                $code === 'ai_api_error' => 'clear_ai_error',
                $code === 'import_failed' => 'dismiss_import_error',
                $code === 'job_blocked' => 'cancel_blocked_job',
                $code === 'link_broken' => 'test_integration',
                str_starts_with($code, 'system_error_') => 'resolve_system_error',
                default => '',
            };

            $nonAuto = in_array($code, [
                'ai_key_missing',
                'backup_missing',
                'products_no_image',
                'products_no_oem',
                'search_not_found',
                'import_queue_pending',
            ], true);

            $item['fix_action'] = $fixAction;
            $item['fixable'] = !$nonAuto && $fixAction !== '';
            $item['fix_label'] = $item['fixable'] ? 'Corectează' : '';

            if (str_starts_with($code, 'system_error_') && ($item['channel'] ?? '') === '') {
                $item['channel'] = substr($code, strlen('system_error_'));
            }

            $item['fix_guide'] = $this->fixGuideForItem($item);
            $item['url'] = $this->normalizeAlertUrl($code, (string) ($item['url'] ?? ''));

            $out[] = $item;
        }

        return $out;
    }

    /**
     * Ghid remediere pentru popup „Corectează”.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function fixGuideForItem(array $item): array
    {
        $code = (string) ($item['code'] ?? '');
        $detail = trim((string) ($item['detail'] ?? ''));
        $title = (string) ($item['title'] ?? 'Alertă');
        $problem = (string) ($item['problem'] ?? '');
        $jobId = trim((string) ($item['job_id'] ?? ''));
        $entityType = (string) ($item['entity_type'] ?? '');
        $channel = (string) ($item['channel'] ?? '');

        $u = [
            'import' => $this->navLink('import'),
            'importJobs' => $this->navLink('import') . '#job-progress-wrap',
            'importreview' => $this->navLink('importreview'),
            'cron' => $this->navLink('cron'),
            'suppliers' => $this->navLink('suppliers'),
            'settings' => $this->navLink('settings'),
            'settingsRapid' => $this->navLink('settings') . '#rapidapi',
            'searchlogs' => $this->navLink('searchlogs'),
            'dashboard' => $this->navLink('dashboard'),
            'systemErrors' => $this->navLink('system-errors'),
            'bots' => $this->navLink('bots'),
            'scraper' => $this->navLink('scraper'),
            'aiAgent' => $this->navLink('ai-rag'),
            'aiTokens' => $this->navLink('ai-tokens'),
            'alerts' => $this->navLink('alerts'),
        ];

        $base = [
            'situation' => $problem !== '' ? $problem : $title,
            'detail' => $detail,
            'steps' => [],
            'links' => [],
            'auto_label' => 'Reparare automată',
            'auto_hint' => '',
        ];

        $guide = match ($code) {
            'import_failed' => [
                'situation' => 'Un job de import s-a oprit cu eroare. Dashboard-ul și supervizorul raportează problema până e curățată sau relansată.',
                'steps' => [
                    'Deschide Import și identifică job-ul eșuat — mesajul de eroare explică cauza (CSV invalid, coloană lipsă, timeout).',
                    'Verifică fișierul furnizor (Autonet/Elit) și CSV TecDoc dacă folosești mod TecDoc master.',
                    'Corectează datele sau reduce batch-ul, apoi relansează importul.',
                    'Dacă eroarea e veche și job-ul nu mai e util, folosește repararea automată pentru a elimina job-ul din coadă.',
                ],
                'links' => [
                    ['label' => 'Deschide Import', 'url' => $u['importJobs']],
                    ['label' => 'Revizuire staging', 'url' => $u['importreview']],
                    ['label' => 'Jurnal erori', 'url' => $u['systemErrors']],
                ],
                'auto_label' => 'Curăță job eșuat',
                'auto_hint' => 'Elimină job-ul eșuat din coadă. Nu repară CSV-ul — trebuie corectat manual înainte de relansare.',
            ],
            'job_blocked' => [
                'situation' => 'Un job de import rulează fără progres de peste 30 minute — de obicei blocat la scan TecDoc sau la un fișier mare.',
                'steps' => [
                    'Deschide Import și verifică progresul job-ului (procentul nu se mișcă).',
                    'Notează ID job' . ($jobId !== '' ? ' (' . $jobId . ')' : '') . ' și ultimul mesaj din log.',
                    'Oprește job-ul blocat (reparare automată) sau din interfața Import dacă e disponibil.',
                    'Relansează cu batch mai mic sau fără scan CSV TecDoc (mod rapid) dacă fișierul e foarte mare.',
                ],
                'links' => [
                    ['label' => 'Deschide Import', 'url' => $u['importJobs']],
                    ['label' => 'Cron Sync', 'url' => $u['cron']],
                ],
                'auto_label' => 'Oprește job blocat',
                'auto_hint' => 'Anulează job-ul care nu avansează. După oprire, relansează importul manual.',
            ],
            'link_broken' => [
                'situation' => 'O integrare (furnizor sau bot) nu trece testul de conexiune — API, token sau URL incorect.',
                'steps' => [
                    'Deschide pagina integrării (furnizor/bot) indicată în detaliu.',
                    'Verifică URL API, credențiale și dacă furnizorul permite IP-ul serverului.',
                    'Rulează „Test conexiune” din formularul furnizorului.',
                    'Dacă testul trece manual, folosește repararea automată pentru a revalida și șterge alerta.',
                ],
                'links' => array_values(array_filter([
                    ['label' => 'Verifică integrări', 'url' => $this->normalizeAlertUrl('link_broken', (string) ($item['url'] ?? ''))],
                    $entityType === 'bot'
                        ? ['label' => 'Roboți AI', 'url' => $u['bots']]
                        : ['label' => 'Furnizori', 'url' => $u['suppliers']],
                    ['label' => 'Setări tokeni', 'url' => $u['settings']],
                ])),
                'auto_label' => 'Retest conexiune',
                'auto_hint' => 'Rulează test API/bot din server. Dacă eșuează, trebuie corectate credențialele manual.',
            ],
            'tecdoc_ip_invalid' => [
                'situation' => 'IP-ul serverului Besoiu nu e autorizat la RapidAPI TecDoc — catalogul extern refuză cererile.',
                'steps' => [
                    'Intră în contul RapidAPI → API auto-parts-catalog → whitelist IP.',
                    'Adaugă IP-ul serverului afișat în detaliu (sau IP-ul public al hostingului).',
                    'Salvează și așteaptă 1–2 minute propagare.',
                    'Apasă reparare automată pentru re-probe IP sau verifică din Dashboard.',
                ],
                'links' => [
                    ['label' => 'Setări RapidAPI', 'url' => $u['settingsRapid']],
                    ['label' => 'Jurnal căutări', 'url' => $u['searchlogs']],
                    ['label' => 'Dashboard', 'url' => $u['dashboard']],
                ],
                'auto_label' => 'Re-verifică TecDoc',
                'auto_hint' => 'Șterge flag-uri locale și re-probează API-ul. Funcționează doar după ce IP-ul e autorizat la RapidAPI.',
            ],
            'tecdoc_unified', 'tecdoc_dead', 'tecdoc_api' => [
                'situation' => 'Catalogul TecDoc / RapidAPI nu răspunde sau a atins limita. Clienții văd doar stocul local până revine API-ul.',
                'steps' => [
                    'Verifică cheia RAPIDAPI_AUTOPARTS_KEY în Setări → Tokeni (sau admin/.env).',
                    'Controlează cota lunară RapidAPI — plan Basic are limită; upgrade sau așteaptă reset.',
                    'Dacă mesajul e „IP invalid”, autorizează IP server în panoul RapidAPI.',
                    'Testează o căutare din /admin/searchlogs sau Dashboard.',
                    'Reparare automată: curăță erori cache locale și re-probează — nu reîncarcă cota RapidAPI.',
                ],
                'links' => [
                    ['label' => 'Setări RapidAPI', 'url' => $u['settingsRapid']],
                    ['label' => 'Jurnal căutări', 'url' => $u['searchlogs']],
                    ['label' => 'Scraper pipeline', 'url' => $u['scraper']],
                ],
                'auto_label' => 'Re-probe TecDoc / RapidAPI',
                'auto_hint' => 'Curăță flag-uri locale și testează din nou. Dacă cheia lipsește sau cota e epuizată, trebuie schimbat planul/cheia manual.',
            ],
            'ai_api_error' => [
                'situation' => 'Ultimul apel către furnizorul AI (Cursor/Groq/OpenAI) a eșuat.',
                'steps' => [
                    'Verifică cheile în Setări: GROQ_KEY, OPENAI_KEY sau Ollama local.',
                    'Deschide /admin/ai-tokens — vezi ultima eroare și consum.',
                    'Dacă e rate limit, așteaptă sau schimbă modelul/furnizorul.',
                ],
                'links' => [
                    ['label' => 'Setări AI', 'url' => $u['settings']],
                    ['label' => 'Monitor tokeni', 'url' => $u['aiTokens']],
                    ['label' => 'Centru AI Agent', 'url' => $u['aiAgent']],
                ],
                'auto_label' => 'Reset eroare AI',
                'auto_hint' => 'Șterge ultima eroare din cache. Dacă cheia e invalidă, alerta revine la următorul apel.',
            ],
            'scrape_do_token_missing', 'scraper_rapidapi_key' => [
                'situation' => 'Lipsește un token necesar pipeline-ului de imagini (Scrape.do sau RapidAPI TecDoc).',
                'steps' => [
                    'Deschide Setări → completează SCRAPE_DO_TOKEN și/sau RAPIDAPI_AUTOPARTS_KEY.',
                    'Salvează .env și reîncarcă pagina Scraper.',
                    'Rulează test pipeline din /admin/scraper.',
                ],
                'links' => [
                    ['label' => 'Setări tokeni', 'url' => $u['settings']],
                    ['label' => 'Scraper', 'url' => $u['scraper']],
                ],
                'auto_label' => '',
                'auto_hint' => 'Adaugă tokenul manual — nu există reparare automată.',
            ],
            'scrape_do_quota' => [
                'situation' => 'Cota Scrape.do e epuizată — Autodoc/ePiesa/eMAG nu pot căuta imagini.',
                'steps' => [
                    'Verifică planul și consumul pe scrape.do.',
                    'Reîncarcă token sau upgrade plan.',
                    'Alternativ: folosește Plan 2 TecDoc sau imagini manuale temporar.',
                ],
                'links' => [
                    ['label' => 'Setări Scrape.do', 'url' => $u['settings']],
                    ['label' => 'Scraper', 'url' => $u['scraper']],
                ],
                'auto_label' => '',
                'auto_hint' => '',
            ],
            default => str_starts_with($code, 'system_error_') ? [
                'situation' => 'Eroare nerezolvată în jurnalul de sistem' . ($channel !== '' ? ' (canal: ' . $channel . ').' : '.'),
                'steps' => [
                    'Deschide Jurnal erori și citește mesajul complet.',
                    'Identifică sursa: import, scraper, catalog, AI.',
                    'Corectează cauza (token, fișier, API) apoi marchează rezolvată sau folosește reparare automată.',
                ],
                'links' => [
                    ['label' => 'Jurnal erori', 'url' => $u['systemErrors']],
                    ['label' => 'Toate alertele', 'url' => $u['alerts']],
                ],
                'auto_label' => 'Marchează rezolvată',
                'auto_hint' => 'Marchează eroarea în jurnal ca rezolvată. Reapare dacă problema persistă.',
            ] : [],
        };

        if ($guide === []) {
            return $base;
        }

        $guide['detail'] = $detail !== '' ? $detail : ($guide['detail'] ?? '');
        if ($problem !== '' && ($guide['situation'] ?? '') === $problem) {
            // păstrează ghidul extins
        } elseif ($problem !== '' && !str_contains((string) ($guide['situation'] ?? ''), $problem)) {
            $guide['situation'] = $problem;
        }

        return array_merge($base, $guide);
    }

    /** @param array<string, mixed> $flag */
    private function normalizeLevel(array $flag): string
    {
        if (!empty($flag['critical']) || ($flag['level'] ?? '') === 'danger') {
            return 'critical';
        }
        if (($flag['level'] ?? '') === 'warning') {
            return 'warning';
        }

        return 'info';
    }

    /** @param list<array<string, mixed>> $items @return list<string> */
    private function inferCoveredChannels(array $items): array
    {
        $covered = [];
        foreach ($items as $item) {
            $code = (string) ($item['code'] ?? '');
            if (in_array($code, self::TECDOC_CODES, true) || str_starts_with($code, 'system_error_rapidapi')) {
                foreach (self::TECDOC_CHANNELS as $ch) {
                    $covered[] = $ch;
                }
            }
            if (in_array($code, self::SCRAPER_CODES, true) || str_starts_with($code, 'system_error_scrape')) {
                foreach (self::SCRAPER_CHANNELS as $ch) {
                    $covered[] = $ch;
                }
            }
            if ($code === 'ai_key_missing' || $code === 'ai_api_error' || str_starts_with($code, 'system_error_ai')) {
                $covered[] = 'ai';
                $covered[] = 'groq';
                $covered[] = 'openai';
            }
        }

        return array_values(array_unique($covered));
    }

    /** @param list<array<string, mixed>> $items @return list<array<string, mixed>> */
    private function dedupeItems(array $items): array
    {
        $byCode = [];
        foreach ($items as $item) {
            $code = (string) ($item['code'] ?? '');
            if ($code === '') {
                continue;
            }
            if (!isset($byCode[$code]) || mb_strlen((string) ($item['detail'] ?? '')) > mb_strlen((string) ($byCode[$code]['detail'] ?? ''))) {
                $byCode[$code] = $item;
            }
        }

        $list = array_values($byCode);

        $hasTecdoc = false;
        $tecdocBest = null;
        $tecdocScore = -1;
        $filtered = [];

        foreach ($list as $item) {
            $code = (string) ($item['code'] ?? '');
            if (in_array($code, ['tecdoc_dead', 'tecdoc_api'], true) || $code === 'system_error_rapidapi') {
                $hasTecdoc = true;
                $score = mb_strlen((string) ($item['detail'] ?? ''));
                if ($score > $tecdocScore) {
                    $tecdocScore = $score;
                    $tecdocBest = $item;
                }
                continue;
            }
            $filtered[] = $item;
        }

        if ($hasTecdoc && $tecdocBest !== null) {
            $filtered[] = $this->makeItem([
                'code' => 'tecdoc_unified',
                'level' => 'critical',
                'title' => 'Catalog TecDoc / RapidAPI indisponibil',
                'detail' => (string) ($tecdocBest['detail'] ?? 'API-ul nu răspunde sau a atins limita. Căutarea pe site folosește doar stocul local.'),
                'problem' => 'Catalogul extern de piese nu funcționează — clienții văd doar stoc local.',
                'action_label' => 'Vezi jurnal căutări',
                'url' => '/admin/searchlogs',
                'at' => (string) ($tecdocBest['at'] ?? date('c')),
            ]);
        }

        $out = [];
        $hasAiKey = false;
        foreach ($filtered as $item) {
            if ((string) ($item['code'] ?? '') === 'ai_key_missing') {
                $hasAiKey = true;
            }
        }
        foreach ($filtered as $item) {
            $code = (string) ($item['code'] ?? '');
            if ($hasAiKey && $code === 'ai_api_error') {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $items @return array{critical: list<array<string, mixed>>, warning: list<array<string, mixed>>, info: list<array<string, mixed>>} */
    private function groupItems(array $items): array
    {
        $groups = ['critical' => [], 'warning' => [], 'info' => []];
        foreach ($items as $item) {
            $lvl = (string) ($item['level'] ?? 'warning');
            if ($lvl === 'critical') {
                $groups['critical'][] = $item;
            } elseif ($lvl === 'info') {
                $groups['info'][] = $item;
            } else {
                $groups['warning'][] = $item;
            }
        }

        return $groups;
    }

    private function problemForCode(string $code, string $title, string $detail): string
    {
        return match ($code) {
            'import_failed' => 'Un job de import s-a oprit cu eroare.',
            'tecdoc_dead', 'tecdoc_api', 'tecdoc_unified' => 'Catalogul extern TecDoc/RapidAPI nu răspunde.',
            'tecdoc_ip_invalid' => 'IP-ul serverului nu e autorizat la RapidAPI.',
            'link_broken' => 'O integrare cu furnizor nu trece testul de conexiune.',
            'job_blocked' => 'Import blocat — rulează fără progres.',
            'backup_missing' => 'Nu există backup recent al datelor.',
            'import_queue_pending' => 'Produse așteaptă publicare din staging.',
            'products_no_image' => 'Produse active fără imagine.',
            'products_no_oem' => 'Produse fără cod OEM — căutare și SEO afectate.',
            'search_not_found' => 'Clienții caută piese care lipsesc din stoc.',
            'scrape_do_token_missing' => 'Lipsește tokenul Scrape.do — sursele HTML din pipeline imagini nu pot rula.',
            'scrape_do_quota' => 'Cota Scrape.do este epuizată — Autodoc/ePiesa/eMAG nu pot căuta imagini.',
            'scraper_no_plans' => 'Pipeline imagini fără plan activ — importul nu găsește imagini.',
            'scraper_rapidapi_key' => 'Plan TecDoc activ dar cheia RapidAPI lipsește.',
            default => $title !== '' ? $title : mb_substr($detail, 0, 120),
        };
    }

    private function actionLabelForCode(string $code): string
    {
        return match ($code) {
            'import_failed', 'job_blocked' => 'Deschide Import',
            'tecdoc_dead', 'tecdoc_api', 'tecdoc_unified', 'tecdoc_ip_invalid', 'search_not_found' => 'Jurnal căutări',
            'link_broken' => 'Verifică integrări',
            'backup_missing' => 'Configurează backup',
            'import_queue_pending' => 'Revizuire import',
            'products_no_image', 'products_no_oem' => 'Deschide produse',
            'ai_key_missing' => 'Setări .env',
            'ai_api_error' => 'Monitor AI',
            'scrape_do_token_missing', 'scrape_do_quota', 'scraper_rapidapi_key' => 'Setări tokeni',
            'scraper_no_plans' => 'Deschide Scraper',
            default => 'Deschide',
        };
    }

    private function channelTitle(string $channel): string
    {
        return match ($channel) {
            'rapidapi', 'tecdoc', 'tecdoc_api', 'catalog' => 'Catalog TecDoc / RapidAPI',
            'scrape_do', 'scraper', 'autodoc', 'epiesa', 'emag' => 'Scraper / pipeline imagini',
            'ai', 'groq', 'openai' => 'Eroare API AI',
            'import' => 'Eroare import',
            default => 'Eroare ' . strtoupper($channel),
        };
    }

    /** @return array<string, mixed> */
    private function readAiHealth(): array
    {
        $path = dirname(__DIR__, 3) . '/robot/data/ai_context/ai_api_health.json';
        if (!is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }

    private function aiKeyConfigured(): bool
    {
        $helper = dirname(__DIR__, 3) . '/system/ai_api_errors.php';
        if (!is_file($helper)) {
            return true;
        }
        require_once $helper;

        return ai_api_key_configured();
    }

    /** @return array<string, mixed> */
    private function systemErrorStats(): array
    {
        try {
            require_once dirname(__DIR__, 3) . '/system/system_errors.php';
            $pdo = Database::getDB();

            return \system_errors_stats($pdo);
        } catch (Throwable) {
            return ['unresolved' => 0];
        }
    }

    /** @return list<array<string, mixed>> */
    private function recentSystemErrors(int $limit): array
    {
        try {
            require_once dirname(__DIR__, 3) . '/system/system_errors.php';
            $pdo = Database::getDB();

            return \system_errors_recent_by_channel($pdo, $limit);
        } catch (Throwable) {
            return [];
        }
    }

    private function navLink(string $slug, string $hash = ''): string
    {
        $path = AdminUrl::navPath($slug);
        if ($hash === '') {
            return $path;
        }

        return $path . (str_starts_with($hash, '#') ? $hash : '#' . $hash);
    }

    private function normalizeAlertUrl(string $code, string $url): string
    {
        $url = trim($url);
        $legacy = [
            '/admin/cron-sync' => 'cron',
            '/admin/homepages' => 'dashboard',
            '/admin/furnizori' => 'suppliers',
            '/admin/public/import' => 'import',
            '/admin/public/cron' => 'cron',
        ];
        if ($url !== '' && isset($legacy[$url])) {
            return $this->navLink($legacy[$url]);
        }

        if ($url !== '' && !str_contains($url, 'cron-sync') && !str_contains($url, 'homepages')) {
            return $url;
        }

        return match ($code) {
            'import_failed', 'job_blocked' => $this->navLink('import') . '#job-progress-wrap',
            'import_queue_pending' => $this->navLink('importreview'),
            'link_broken' => $this->navLink('suppliers'),
            'tecdoc_unified', 'tecdoc_dead', 'tecdoc_api', 'tecdoc_ip_invalid', 'search_not_found' => $this->navLink('searchlogs'),
            'scrape_do_token_missing', 'scrape_do_quota', 'scraper_no_plans', 'scraper_rapidapi_key' => $this->navLink('scraper'),
            'ai_api_error', 'ai_key_missing' => $this->navLink('ai-rag'),
            'backup_missing' => $this->navLink('backup'),
            'products_no_image', 'products_no_oem' => $this->navLink('product'),
            default => $url !== '' ? $url : $this->navLink('alerts'),
        };
    }
}
