<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor;

use Config\Database;
use Besoiu\Services\AiBotAgentLinkService;
use Besoiu\Services\AiSupervisor\Support\AiSupervisorBootstrap;
use Besoiu\Services\AiSupervisor\Support\ApiKeysStatus;
use PDO;
use Throwable;

/**
 * Raport narativ profund pentru tab Supervizor — explică ce, cum, de ce.
 */
final class SupervisorDeepReportBuilder
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 4);
    }

    /** @param array<string, mixed> $status @return array<string, mixed> */
    public function build(array $status): array
    {
        return [
            'generated_at' => date('c'),
            'cycle' => $this->cycleSection($status),
            'catalog' => $this->catalogSection($status),
            'pipeline' => $this->pipelineSection($status),
            'tokens' => $this->tokensSection($status),
            'suppliers' => $this->suppliersSection($status),
            'conversations' => $this->conversationsSection($status),
        ];
    }

    /** @param array<string, mixed> $status @return array<string, mixed> */
    private function cycleSection(array $status): array
    {
        $cycle = is_array($status['last_cycle'] ?? null) ? $status['last_cycle'] : [];
        $jobs = is_array($cycle['jobs'] ?? null) ? $cycle['jobs'] : [];
        $jobLines = [];
        $ran = 0;
        $skipped = 0;

        foreach ($jobs as $key => $job) {
            if (!is_array($job)) {
                continue;
            }
            $label = $this->jobLabel((string) $key);
            if (!empty($job['ran'])) {
                ++$ran;
                $http = (int) (($job['result']['http'] ?? 0));
                $jobLines[] = [
                    'key' => $key,
                    'label' => $label,
                    'status' => $http >= 200 && $http < 300 || $http === 207 ? 'ok' : 'warn',
                    'detail' => 'Executat · HTTP ' . $http,
                ];
            } else {
                ++$skipped;
                $jobLines[] = [
                    'key' => $key,
                    'label' => $label,
                    'status' => 'skip',
                    'detail' => 'Sărit — interval cron (' . (string) ($job['reason'] ?? 'interval') . ')',
                ];
            }
        }

        $durationSec = round(((int) ($cycle['duration_ms'] ?? 0)) / 1000, 1);
        $summary = [];
        if (!empty($cycle['skipped'])) {
            $summary[] = 'Supervizorul este dezactivat în config — activează din formularul de mai jos.';
        } elseif ($cycle === []) {
            $summary[] = 'Niciun ciclu înregistrat. Cron `ai_agent_cycle.php` la 2–3 minute.';
        } else {
            $summary[] = sprintf(
                'Ultimul ciclu: %s sec (%d ms), %d joburi rulate, %d sărite (interval).',
                (string) $durationSec,
                (int) ($cycle['duration_ms'] ?? 0),
                $ran,
                $skipped
            );
            if ($durationSec > 30) {
                $summary[] = 'Durată mare — scan catalog/pipeline; normal ocazional.';
            }
        }

        return [
            'headline' => !empty($cycle['ok']) ? 'Ciclu finalizat cu succes' : (!empty($cycle['skipped']) ? 'Supervizor oprit' : 'Așteaptă primul ciclu'),
            'summary' => $summary,
            'started_at' => $this->fmtTime((string) ($cycle['started_at'] ?? '')),
            'duration_ms' => (int) ($cycle['duration_ms'] ?? 0),
            'jobs' => $jobLines,
            'what_is' => 'Ciclul = rundă cron: tokeni → catalog → pipeline → furnizori → conversații → diagnostic. Fără browser.',
        ];
    }

    /** @param array<string, mixed> $status @return array<string, mixed> */
    private function catalogSection(array $status): array
    {
        $cat = is_array($status['catalog_audit'] ?? null) ? $status['catalog_audit'] : [];
        $counts = is_array($cat['counts'] ?? null) ? $cat['counts'] : [];
        $issues = is_array($cat['issues'] ?? null) ? $cat['issues'] : [];
        $score = (int) ($cat['score'] ?? -1);

        $lines = [];
        if ($score < 0) {
            $lines[] = 'Audit nerulat — apasă „Scan catalog” sau așteaptă cron.';
        } else {
            $active = (int) ($counts['active_total'] ?? 0);
            $lines[] = sprintf('Scor %d/100 (%s) · %s produse active.', $score, (string) ($cat['score_label'] ?? '—'), number_format($active, 0, ',', '.'));
            $metrics = [
                ['key' => 'no_image', 'label' => 'Fără imagine', 'impact' => 'SEO, conversie'],
                ['key' => 'no_oem', 'label' => 'Fără OEM', 'impact' => 'Căutare TecDoc'],
                ['key' => 'short_title', 'label' => 'Titlu scurt', 'impact' => 'Listări'],
                ['key' => 'no_category', 'label' => 'Fără categorie', 'impact' => 'Navigare'],
                ['key' => 'not_on_vitrina', 'label' => 'Fără vitrină', 'impact' => 'Homepage'],
                ['key' => 'duplicate_oem', 'label' => 'OEM duplicate', 'impact' => 'Export/stoc'],
                ['key' => 'pending_import', 'label' => 'Import pending', 'impact' => 'Nepublicate'],
            ];
            foreach ($metrics as $m) {
                $n = (int) ($counts[$m['key']] ?? 0);
                $lines[] = sprintf('%s: %s — %s', $m['label'], number_format($n, 0, ',', '.'), $m['impact']);
            }
        }

        return [
            'headline' => $score >= 0 ? 'Catalog ' . $score . '/100' : 'Catalog nerulat',
            'summary' => $lines,
            'issues' => array_slice($issues, 0, 12),
            'recommendations' => is_array($cat['recommendations'] ?? null) ? $cat['recommendations'] : [],
            'generated_at' => $this->fmtTime((string) ($cat['generated_at'] ?? '')),
            'what_is' => 'Faza 2 scanează produse: imagini, OEM, categorii, vitrină. Nu modifică date.',
        ];
    }

    /** @param array<string, mixed> $status @return array<string, mixed> */
    private function pipelineSection(array $status): array
    {
        $pipe = is_array($status['pipeline_health'] ?? null) ? $status['pipeline_health'] : [];
        $failed = is_array($pipe['failed_tests'] ?? null) ? $pipe['failed_tests'] : [];
        $passed = (int) ($pipe['passed'] ?? 0);
        $total = (int) ($pipe['total'] ?? 0);

        $lines = [];
        if ($total === 0) {
            $lines[] = 'Pipeline nerulat — 50 teste (Scrape.do, RapidAPI, foldere).';
        } else {
            $lines[] = sprintf('%d/%d teste OK.', $passed, $total);
            foreach (array_slice($failed, 0, 8) as $t) {
                if (!is_array($t)) {
                    continue;
                }
                $lines[] = '✗ #' . (int) ($t['id'] ?? 0) . ' ' . (string) ($t['name'] ?? '?') . ': ' . (string) ($t['message'] ?? '');
            }
        }

        return [
            'headline' => $total ? $passed . '/' . $total . ' teste' : 'Pipeline nerulat',
            'summary' => $lines,
            'failed_tests' => array_slice($failed, 0, 12),
            'recommendations' => is_array($pipe['recommendations'] ?? null) ? $pipe['recommendations'] : [],
            'generated_at' => $this->fmtTime((string) ($pipe['generated_at'] ?? '')),
            'what_is' => 'Faza 3a = health check pipeline imagini/API. Nu consumă tokeni LLM.',
        ];
    }

    /** @param array<string, mixed> $status @return array<string, mixed> */
    private function tokensSection(array $status): array
    {
        $tok = is_array($status['token_budget'] ?? null) ? $status['token_budget'] : [];
        $cfg = is_array($status['config'] ?? null) ? $status['config'] : [];
        $dailyLimit = (int) ($tok['daily_limit'] ?? $cfg['token_daily_limit'] ?? 500000);
        $today = (int) ($tok['tokens_today'] ?? 0);
        $usedPct = (float) ($tok['used_pct'] ?? 0);

        $bySource = [];
        $byModel = [];
        $recent = [];
        $providers = [];
        $monthTotal = 0;
        $allTimeRows = 0;

        try {
            AiSupervisorBootstrap::ensureDatabase();
            $helper = $this->projectRoot . '/admin/system/ai_token_usage.php';
            if (is_file($helper)) {
                require_once $helper;
                $pdo = Database::getDB();
                if (function_exists('ai_token_stats')) {
                    $stats = ai_token_stats($pdo);
                    $monthTotal = (int) ($stats['month'] ?? 0);
                    $allTimeRows = (int) ($stats['rows'] ?? 0);
                    $providers = is_array($stats['by_provider'] ?? null) ? $stats['by_provider'] : [];
                }
                if (function_exists('ai_token_query')) {
                    $recent = ai_token_query($pdo, ['limit' => 12])['items'] ?? [];
                }
            }
            $pdo = Database::getDB();
            $srcRows = $pdo->query(
                "SELECT COALESCE(NULLIF(TRIM(source), ''), 'necunoscut') AS src,
                        SUM(total_tokens) AS tokens, COUNT(*) AS calls
                 FROM ai_token_usage WHERE DATE(created_at) = CURDATE()
                 GROUP BY src ORDER BY tokens DESC LIMIT 10"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($srcRows as $row) {
                $bySource[] = [
                    'source' => (string) ($row['src'] ?? ''),
                    'tokens' => (int) ($row['tokens'] ?? 0),
                    'calls' => (int) ($row['calls'] ?? 0),
                    'label' => $this->sourceLabel((string) ($row['src'] ?? '')),
                ];
            }
            $modelRows = $pdo->query(
                "SELECT provider, model, SUM(total_tokens) AS tokens, COUNT(*) AS calls
                 FROM ai_token_usage WHERE DATE(created_at) = CURDATE()
                 GROUP BY provider, model ORDER BY tokens DESC LIMIT 8"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($modelRows as $row) {
                $byModel[] = [
                    'provider' => strtoupper((string) ($row['provider'] ?? '')),
                    'model' => (string) ($row['model'] ?? '—'),
                    'tokens' => (int) ($row['tokens'] ?? 0),
                    'calls' => (int) ($row['calls'] ?? 0),
                ];
            }
        } catch (Throwable) {
            // optional
        }

        $lines = [];
        if ($today === 0 && $allTimeRows === 0) {
            $lines[] = '0 tokeni azi — jurnal gol. Apelurile LLM se loghează la: agent run, diagnostic supervizor, chat roboți, test /admin/ai-tokens.';
        } elseif ($today === 0) {
            $lines[] = '0 tokeni azi — fără LLM astăzi. Istoric: ' . number_format($allTimeRows, 0, ',', '.') . ' înregistrări.';
        } else {
            $lines[] = sprintf(
                'Azi %s / %s tokeni (%s%%), %d apeluri, medie %d/apel.',
                number_format($today, 0, ',', '.'),
                number_format($dailyLimit, 0, ',', '.'),
                (string) $usedPct,
                (int) ($tok['calls_today'] ?? 0),
                (int) ($tok['avg_tokens_per_call'] ?? 0)
            );
            $lines[] = 'Luna: ' . number_format($monthTotal, 0, ',', '.') . ' tokeni.';
            $lines[] = 'Rămân ~' . (int) ($tok['estimated_llm_tasks_left'] ?? 0) . ' apeluri LLM estimate.';
            $lines[] = !empty($tok['sufficient_for_supervisor'])
                ? 'Buget OK — diagnostic LLM activ.'
                : 'Buget redus — diagnostic fără LLM.';
        }

        foreach ($providers as $prov => $pdata) {
            if (!is_array($pdata) || (int) ($pdata['today'] ?? 0) === 0) {
                continue;
            }
            $lines[] = strtoupper((string) $prov) . ': ' . number_format((int) ($pdata['today'] ?? 0), 0, ',', '.') . ' tok azi, '
                . (int) ($pdata['requests_today'] ?? 0) . ' cereri.';
        }

        $lines = array_values(array_filter($lines, static fn (string $s): bool => $s !== ''));

        $keysStatus = is_array($tok['keys_status'] ?? null) ? $tok['keys_status'] : ApiKeysStatus::snapshot();
        $keysConfigured = (int) ($keysStatus['configured_count'] ?? 0);
        $llmKeys = (int) ($keysStatus['llm_count'] ?? 0);

        if ($today === 0 && $keysConfigured > 0) {
            array_unshift(
                $lines,
                sprintf(
                    'Chei API: %d configurate (%d LLM, %d pipeline) — setările sunt OK; 0 consum înseamnă că nu s-a apelat LLM azi.',
                    $keysConfigured,
                    $llmKeys,
                    (int) ($keysStatus['pipeline_count'] ?? 0)
                )
            );
        } elseif ($today === 0 && $keysConfigured === 0) {
            array_unshift($lines, 'Nicio cheie API în Setări — completează GROQ_KEY sau OPENAI_KEY.');
        }

        $headline = $today > 0
            ? number_format($today, 0, ',', '.') . ' tokeni consum (' . $usedPct . '%)'
            : ($keysConfigured > 0
                ? '0 consum azi · ' . $keysConfigured . ' chei active'
                : 'Nicio cheie configurată');

        $apiHub = is_array($tok['api_hub'] ?? null) ? $tok['api_hub'] : [];
        $apiProviders = [];
        foreach (['scrape_do', 'rapidapi_tecdoc'] as $pk) {
            $row = is_array($apiHub['providers'][$pk] ?? null) ? $apiHub['providers'][$pk] : null;
            if ($row === null) {
                continue;
            }
            $usage = is_array($row['usage'] ?? null) ? $row['usage'] : [];
            $apiProviders[] = [
                'provider_key' => $pk,
                'label' => (string) ($row['label'] ?? $pk),
                'monthly_quota' => (int) ($usage['monthly_quota'] ?? $row['monthly_quota'] ?? 0),
                'tokens_per_request' => (int) ($usage['tokens_per_request'] ?? $row['tokens_per_request'] ?? 1),
                'month_requests' => (int) ($usage['month_requests'] ?? 0),
                'max_requests' => (int) ($usage['max_requests'] ?? 0),
                'requests_left' => (int) ($usage['requests_left'] ?? 0),
                'used_tokens' => (int) ($usage['used_tokens'] ?? 0),
                'remaining_tokens' => (int) ($usage['remaining_tokens'] ?? 0),
                'month_cost' => (float) ($row['month_cost'] ?? 0),
            ];
            $lines[] = sprintf(
                '%s (Setări): %d/%d cereri, ~%d rămase, %d credite/cerere.',
                (string) ($row['label'] ?? $pk),
                (int) ($usage['month_requests'] ?? 0),
                (int) ($usage['max_requests'] ?? 0),
                (int) ($usage['requests_left'] ?? 0),
                (int) ($usage['tokens_per_request'] ?? 1)
            );
        }

        return [
            'headline' => $headline,
            'summary' => $lines,
            'by_source' => $bySource,
            'by_model' => $byModel,
            'api_providers' => $apiProviders,
            'api_hub_synced_at' => (string) ($apiHub['generated_at'] ?? $tok['api_hub_synced_at'] ?? ''),
            'recent_calls' => array_map(fn (array $row): array => [
                'at' => $this->fmtTime((string) ($row['created_at'] ?? '')),
                'provider' => strtoupper((string) ($row['provider'] ?? '')),
                'model' => (string) ($row['model'] ?? ''),
                'source' => $this->sourceLabel((string) ($row['source'] ?? '')),
                'total' => (int) ($row['total_tokens'] ?? 0),
            ], $recent),
            'consumers' => [
                ['label' => 'Rulare agent / context', 'desc' => 'Generează runtime.md — consum LLM la synthesize.'],
                ['label' => 'Diagnostic supervizor', 'desc' => 'Pași LLM pe alerte (dacă buget + config).'],
                ['label' => 'Roboți WhatsApp/Facebook', 'desc' => 'Răspunsuri chat via agent legat.'],
                ['label' => 'Test admin', 'desc' => '/admin/ai-tokens, Editor ▶ Generează.'],
            ],
            'keys_status' => $keysStatus,
            'tokens_today' => $today,
            'calls_today' => (int) ($tok['calls_today'] ?? 0),
            'alerts' => is_array($tok['recommendations'] ?? null) ? $tok['recommendations'] : [],
            'daily_limit' => $dailyLimit,
            'month_total' => $monthTotal,
            'generated_at' => $this->fmtTime((string) ($tok['generated_at'] ?? '')),
            'what_is' => 'Consum LLM = apeluri logate în jurnal (Groq, OpenAI, Cursor…). Sub „Buget API” = aceleași cifre ca Setări → Tokeni API (Scrape.do, RapidAPI).',
            'link' => '/admin/settings?tab=tokens',
        ];
    }

    /** @param array<string, mixed> $status @return array<string, mixed> */
    private function suppliersSection(array $status): array
    {
        $sup = is_array($status['supplier_watch'] ?? null) ? $status['supplier_watch'] : [];
        $filesNew = is_array($sup['files_new'] ?? null) ? $sup['files_new'] : [];
        $needs = is_array($sup['needs_attention'] ?? null) ? $sup['needs_attention'] : [];

        $lines = [];
        if ($sup === []) {
            $lines[] = 'Scan nerulat.';
        } else {
            $lines[] = (int) ($sup['suppliers_total'] ?? 0) . ' furnizori · scan ' . $this->fmtTime((string) ($sup['last_scan_at'] ?? ''));
            if ($filesNew === []) {
                $lines[] = '0 fișiere noi de la furnizori.';
            }
            foreach ($filesNew as $f) {
                if (is_array($f)) {
                    $lines[] = (string) ($f['supplier'] ?? '?') . ': ' . (int) ($f['new_files'] ?? 0) . ' fișiere noi';
                }
            }
            foreach ($needs as $n) {
                if (is_array($n)) {
                    $lines[] = '⚠ ' . (string) ($n['supplier'] ?? '?') . ' — ' . (string) ($n['detail'] ?? '');
                }
            }
        }

        return [
            'headline' => count($filesNew) . ' fișiere noi · ' . count($needs) . ' atenție',
            'summary' => $lines,
            'metrics' => [
                ['val' => (string) ((int) ($sup['suppliers_total'] ?? 0)), 'lbl' => 'Furnizori monitorizați'],
                ['val' => (string) count($filesNew), 'lbl' => 'Fișiere noi'],
                ['val' => (string) count($needs), 'lbl' => 'Necesită atenție'],
                ['val' => $this->fmtTime((string) ($sup['last_scan_at'] ?? '')) ?: '—', 'lbl' => 'Ultimul scan'],
            ],
            'files_new' => $filesNew,
            'needs_attention' => $needs,
            'recommendations' => is_array($sup['recommendations'] ?? null) ? $sup['recommendations'] : [],
            'generated_at' => $this->fmtTime((string) ($sup['generated_at'] ?? '')),
            'what_is' => 'Faza 3c = fișiere furnizori + erori import. Fără tokeni AI.',
            'link' => '/admin/furnizori',
        ];
    }

    /** @param array<string, mixed> $status @return array<string, mixed> */
    private function conversationsSection(array $status): array
    {
        $conv = is_array($status['conversations'] ?? null) ? $status['conversations'] : [];
        $robotRoot = $this->projectRoot . '/robot';
        $webhookPath = $robotRoot . '/data/webhook.log';
        if (!is_file($webhookPath)) {
            $webhookPath = $robotRoot . '/webhook.log';
        }

        $webhookMeta = [
            'exists' => is_file($webhookPath),
            'size_kb' => is_file($webhookPath) ? round(filesize($webhookPath) / 1024, 1) : 0,
            'modified' => is_file($webhookPath) ? $this->fmtTime(date('c', (int) filemtime($webhookPath))) : '—',
            'lines_analyzed' => (int) ($conv['webhook_lines_analyzed'] ?? 0),
        ];

        $bots = [];
        try {
            foreach ((new AiBotAgentLinkService())->listBotsWithAgents() as $bot) {
                if (!is_array($bot)) {
                    continue;
                }
                $bots[] = [
                    'name' => (string) ($bot['name'] ?? 'Bot'),
                    'channel' => (string) ($bot['channel'] ?? '—'),
                    'agent' => (string) ($bot['ai_agent_name'] ?? $bot['ai_agent_slug'] ?? 'Context Master'),
                ];
            }
        } catch (Throwable) {
            // optional
        }

        $sessions = is_array($conv['sessions_summary'] ?? null) ? $conv['sessions_summary'] : [];
        $issuesCount = (int) ($conv['issues_detected'] ?? 0);
        $posCount = (int) ($conv['positive_signals'] ?? 0);

        $lines = [];
        if (!$webhookMeta['exists'] && $sessions === []) {
            $lines[] = 'Fără webhook.log și sesiuni — configurează webhook WhatsApp/Messenger.';
        } else {
            $lines[] = $webhookMeta['lines_analyzed'] . ' linii webhook · ' . count($sessions) . ' sesiuni · '
                . (int) ($conv['leads_count'] ?? 0) . ' lead-uri.';
            if ($webhookMeta['exists']) {
                $lines[] = 'Log: ' . $webhookMeta['size_kb'] . ' KB, upd ' . $webhookMeta['modified'];
            }
        }
        if ($issuesCount === 0 && $posCount === 0) {
            $lines[] = '0 probleme/0 pozitive — mesaje neutre sau fără cuvinte cheie (reclamație/mulțumire).';
            $lines[] = 'Caută: «eroare», «retur», «scump» vs «mulțum», «perfect».';
        }
        foreach ($bots as $b) {
            $lines[] = 'Bot ' . $b['name'] . ' (' . $b['channel'] . ') → ' . $b['agent'];
        }

        return [
            'headline' => $issuesCount . ' probleme · ' . $posCount . ' pozitive',
            'summary' => $lines,
            'webhook' => $webhookMeta,
            'bots' => $bots,
            'sessions' => array_slice($sessions, 0, 10),
            'recent_issues' => is_array($conv['recent_issues'] ?? null) ? $conv['recent_issues'] : [],
            'recent_positive' => is_array($conv['recent_positive'] ?? null) ? $conv['recent_positive'] : [],
            'recommendations' => is_array($conv['recommendations'] ?? null) ? $conv['recommendations'] : [],
            'generated_at' => $this->fmtTime((string) ($conv['generated_at'] ?? '')),
            'what_is' => 'Faza 4b = analiză heuristică webhook + sesiuni. Opțional LLM separat.',
            'links' => [
                ['label' => 'Leagă roboți', 'url' => '/admin/ai-agent#roboti'],
                ['label' => 'Bots', 'url' => '/admin/bots'],
            ],
        ];
    }

    private function jobLabel(string $key): string
    {
        return match ($key) {
            'token_budget' => 'Buget tokeni',
            'catalog_audit' => 'Audit catalog',
            'pipeline_health' => 'Health pipeline',
            'supplier_watch' => 'Scan furnizori',
            'conversations' => 'Conversații',
            'diagnostics' => 'Diagnostic',
            'composer_repair' => 'Composer Repair',
            'daily_report' => 'Raport zilnic',
            default => $key,
        };
    }

    private function sourceLabel(string $source): string
    {
        $map = [
            'agent_run' => 'Rulare agent',
            'context_agent' => 'Context agent',
            'supervisor' => 'Supervizor',
            'chat' => 'Chat robot',
            'whatsapp' => 'WhatsApp',
            'necunoscut' => 'Nespecificat',
        ];

        return $map[strtolower(trim($source))] ?? ($source !== '' ? $source : 'Nespecificat');
    }

    private function fmtTime(string $iso): string
    {
        if ($iso === '') {
            return '—';
        }
        try {
            return (new \DateTimeImmutable($iso))
                ->setTimezone(new \DateTimeZone('Europe/Bucharest'))
                ->format('d.m.Y H:i:s');
        } catch (Throwable) {
            return $iso;
        }
    }
}
