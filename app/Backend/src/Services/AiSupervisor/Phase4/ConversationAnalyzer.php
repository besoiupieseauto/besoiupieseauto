<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Phase4;

use Besoiu\Services\AiSupervisor\Config\AiSupervisorConfig;
use Besoiu\Services\AiSupervisor\Store\AiSupervisorStore;
use Besoiu\Services\SectionAssistantQueryLogService;

/**
 * Faza 4b — analiză conversații clienți (WhatsApp/webhook/sesiuni).
 */
final class ConversationAnalyzer
{
    private string $robotRoot;

    public function __construct(
        private ?AiSupervisorConfig $config = null,
        private ?AiSupervisorStore $store = null,
        ?string $projectRoot = null,
    ) {
        $root = $projectRoot ?? dirname(__DIR__, 5);
        $this->config = $config ?? new AiSupervisorConfig($root);
        $this->store = $store ?? new AiSupervisorStore($root);
        $this->robotRoot = $root . '/robot';
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $cfg = $this->config->get();
        $tailLines = max(20, min(500, (int) ($cfg['conversation_tail_lines'] ?? 120)));

        $webhookLines = $this->tailFile($this->robotRoot . '/data/webhook.log', $tailLines);
        $sessions = array_merge(
            $this->loadRecentSessions($this->robotRoot . '/data/sessions', 8),
            $this->loadRecentSessions($this->robotRoot . '/data/widget_sessions', 4)
        );
        $leads = $this->readJson($this->robotRoot . '/data/leads.json');

        $parsed = $this->parseWebhookLines($webhookLines);
        $issues = [];
        $positive = [];

        foreach ($parsed as $entry) {
            $text = mb_strtolower((string) ($entry['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            if (preg_match('/\b(nu merge|eroare|problema|reclam|retur|garan|scump|fara stoc|lipsa)\b/u', $text)) {
                $issues[] = $entry;
            } elseif (preg_match('/\b(multum|mulțum|super|ok|perfect|comand)\b/u', $text)) {
                $positive[] = $entry;
            }
        }

        $report = [
            'ok' => true,
            'http' => 200,
            'generated_at' => date('c'),
            'webhook_lines_analyzed' => count($webhookLines),
            'sessions_count' => count($sessions),
            'leads_count' => is_array($leads) ? count($leads) : 0,
            'issues_detected' => count($issues),
            'positive_signals' => count($positive),
            'recent_issues' => array_slice($issues, -8),
            'recent_positive' => array_slice($positive, -5),
            'sessions_summary' => $sessions,
            'recommendations' => $this->recommendations($issues, $sessions),
            'section_assistant_training' => (new SectionAssistantQueryLogService(dirname(__DIR__, 5)))->summary(7),
        ];

        $this->store->write('conversations', $report);
        if ($issues !== []) {
            $this->recordEvent(count($issues));
        }

        return $report;
    }

    /** @return array<string, mixed> */
    public function latest(): array
    {
        return $this->store->read('conversations');
    }

    /** @return list<string> */
    private function tailFile(string $path, int $limit): array
    {
        if (!is_file($path)) {
            return [];
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES) ?: [];

        return array_slice($lines, -$limit);
    }

    /** @return list<array<string, mixed>> */
    private function loadRecentSessions(string $dir, int $limit): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.json') ?: [];
        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $out = [];
        foreach (array_slice($files, 0, $limit) as $file) {
            $json = json_decode((string) file_get_contents($file), true);
            if (!is_array($json)) {
                continue;
            }
            $out[] = [
                'file' => basename($file),
                'dir' => basename(dirname($file)),
                'updated' => date('c', (int) filemtime($file)),
                'messages' => is_array($json['messages'] ?? null) ? count($json['messages']) : 0,
                'phone' => (string) ($json['phone'] ?? $json['from'] ?? ''),
            ];
        }

        return $out;
    }

    /** @param list<string> $lines @return list<array<string, mixed>> */
    private function parseWebhookLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $json = json_decode($line, true);
            if (is_array($json)) {
                $text = (string) ($json['text'] ?? $json['body'] ?? $json['message'] ?? '');
                $out[] = [
                    'at' => (string) ($json['at'] ?? $json['timestamp'] ?? ''),
                    'from' => (string) ($json['from'] ?? $json['phone'] ?? ''),
                    'text' => mb_substr($text, 0, 200),
                ];
                continue;
            }
            $out[] = ['at' => '', 'from' => '', 'text' => mb_substr($line, 0, 200)];
        }

        return $out;
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

    /** @param list<array<string, mixed>> $issues @param list<array<string, mixed>> $sessions @return list<string> */
    private function recommendations(array $issues, array $sessions): array
    {
        $rec = [];
        if ($issues !== []) {
            $rec[] = count($issues) . ' mesaje cu ton negativ/reclamație — verifică răspunsurile botului.';
        }
        if ($sessions === []) {
            $rec[] = 'Fără sesiuni chat recente — verifică webhook WhatsApp.';
        } else {
            $rec[] = count($sessions) . ' sesiuni recente — leagă agent AI potrivit în /admin/bots.';
        }
        if ($rec === []) {
            $rec[] = 'Conversații — fără probleme majore detectate.';
        }

        return $rec;
    }

    private function recordEvent(int $issueCount): void
    {
        $helper = dirname(__DIR__, 5) . '/system/ai_action_events.php';
        if (!is_file($helper)) {
            return;
        }
        require_once $helper;
        if (function_exists('ai_action_event_record')) {
            ai_action_event_record('cron', 'supervisor_conversations', 'issues=' . $issueCount, [
                'phase' => 4,
                'issues' => $issueCount,
            ]);
        }
    }
}
