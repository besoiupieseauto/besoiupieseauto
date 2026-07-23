<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Services\AiSupervisor\Config\AiSupervisorConfig;

/**
 * Motor autonom — din semnale reale generează propuneri și acțiuni mărunte.
 */
final class AiAutonomyEngine
{
    private string $autonomyDir;
    private AiActionEventService $events;

    public function __construct(?string $contextDir = null, ?AiActionEventService $events = null)
    {
        $root = dirname(__DIR__, 3);
        $base = $contextDir ?? ($root . '/robot/data/ai_context');
        $this->autonomyDir = $base . '/autonomy';
        $this->events = $events ?? new AiActionEventService();
    }

    /** @return array<string, mixed> */
    public function tick(): array
    {
        $config = new AiSupervisorConfig();
        if (!$config->isAutonomyEnabled()) {
            return [
                'skipped' => true,
                'reason' => 'autonomy_disabled',
                'ran_at' => date('c'),
                'proposals_open' => 0,
                'actions' => [],
            ];
        }

        if (!is_dir($this->autonomyDir)) {
            @mkdir($this->autonomyDir, 0775, true);
        }

        $core = $this->events->getCore();
        $patterns = is_array($core['patterns'] ?? null) ? $core['patterns'] : [];
        $proposals = $this->loadProposals();
        $actions = [];

        $notFound = is_array($patterns['searches_not_found'] ?? null) ? $patterns['searches_not_found'] : [];
        foreach (array_slice($notFound, 0, 6) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = (string) ($row['query_value'] ?? '');
            if ($code === '') {
                continue;
            }
            $id = 'import_oem:' . md5($code);
            if (isset($proposals[$id])) {
                continue;
            }
            $proposals[$id] = [
                'id' => $id,
                'type' => 'import_oem',
                'priority' => 'high',
                'title' => 'Importă OEM/cod căutat fără rezultat',
                'detail' => 'Clienții caută «' . $code . '» — nu există în catalog.',
                'created_at' => date('c'),
                'status' => 'open',
                'meta' => $row,
            ];
            $actions[] = $this->logAction('proposal_created', $id, $proposals[$id]['title']);
        }

        $carts = (int) ($patterns['open_cart_abandonments'] ?? 0);
        if ($carts > 0) {
            $id = 'cart_abandon:' . date('Y-m-d');
            if (!isset($proposals[$id])) {
                $proposals[$id] = [
                    'id' => $id,
                    'type' => 'contact_cart',
                    'priority' => 'high',
                    'title' => 'Coșuri abandonate — contact clienți',
                    'detail' => $carts . ' coșuri deschise — follow-up WhatsApp/telefon.',
                    'created_at' => date('c'),
                    'status' => 'open',
                ];
                $actions[] = $this->logAction('proposal_created', $id, 'Coșuri abandonate');
            }
        }

        $robotRoot = dirname($this->autonomyDir, 2);
        if (!is_file($robotRoot . '/webhook.log')) {
            $id = 'fix_webhook';
            if (!isset($proposals[$id])) {
                $proposals[$id] = [
                    'id' => $id,
                    'type' => 'ops_webhook',
                    'priority' => 'medium',
                    'title' => 'Webhook WhatsApp lipsă',
                    'detail' => 'robot/data/webhook.log nu există — roboții nu văd conversații.',
                    'created_at' => date('c'),
                    'status' => 'open',
                ];
            }
        }

        $this->saveProposals($proposals);
        $cycle = [
            'ran_at' => date('c'),
            'proposals_open' => count(array_filter($proposals, static fn (array $p): bool => ($p['status'] ?? '') === 'open')),
            'core_events' => (int) ($core['event_count'] ?? 0),
            'actions' => $actions,
        ];
        file_put_contents(
            $this->autonomyDir . '/last_cycle.json',
            json_encode($cycle, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $cycle;
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $last = $this->readJson($this->autonomyDir . '/last_cycle.json');
        $proposals = array_values($this->loadProposals());
        usort($proposals, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return [
            'last_cycle' => $last,
            'proposals_open' => array_values(array_filter($proposals, static fn (array $p): bool => ($p['status'] ?? '') === 'open')),
            'proposals_total' => count($proposals),
            'actions_recent' => $this->readActionLog(20),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function loadProposals(): array
    {
        $path = $this->autonomyDir . '/proposals.json';
        $json = $this->readJson($path);

        return is_array($json) ? $json : [];
    }

    /** @param array<string, array<string, mixed>> $proposals */
    private function saveProposals(array $proposals): void
    {
        file_put_contents(
            $this->autonomyDir . '/proposals.json',
            json_encode($proposals, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /** @param array<string, mixed> $meta @return array<string, mixed> */
    private function logAction(string $action, string $subject, string $detail, array $meta = []): array
    {
        $row = [
            'at' => date('c'),
            'action' => $action,
            'subject' => $subject,
            'detail' => $detail,
            'meta' => $meta,
        ];
        @file_put_contents(
            $this->autonomyDir . '/actions.jsonl',
            json_encode($row, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );

        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function readActionLog(int $limit): array
    {
        $path = $this->autonomyDir . '/actions.jsonl';
        if (!is_file($path)) {
            return [];
        }
        $lines = array_slice(@file($path, FILE_IGNORE_NEW_LINES) ?: [], -max(1, min(100, $limit)));
        $out = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                $out[] = $row;
            }
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
}
