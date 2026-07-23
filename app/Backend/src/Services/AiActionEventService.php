<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * Jurnal acțiuni proiect → formează core-ul AI din ce se întâmplă (admin + client).
 */
final class AiActionEventService
{
    private const MAX_EVENTS = 800;
    private const CORE_REBUILD_EVERY = 5;

    private string $contextDir;

    public function __construct(?string $contextDir = null)
    {
        $root = dirname(__DIR__, 3);
        $this->contextDir = $contextDir ?? ($root . '/robot/data/ai_context');
    }

    /** @param array<string, mixed> $meta */
    public function record(
        string $actorType,
        string $action,
        string $subject = '',
        array $meta = [],
        ?string $actorId = null
    ): void {
        $helper = dirname(__DIR__, 3) . '/system/ai_action_events.php';
        if (is_file($helper)) {
            require_once $helper;
            if (function_exists('ai_action_event_record')) {
                ai_action_event_record($actorType, $action, $subject, $meta, $actorId);
            }
        }

        $count = count($this->readEvents(9999));
        if ($count === 0 || $count % self::CORE_REBUILD_EVERY === 0) {
            $this->rebuildCore();
        }
    }

    /** @return list<array<string, mixed>> */
    public function readEvents(int $limit = 50): array
    {
        $path = $this->contextDir . '/events.jsonl';
        if (!is_file($path)) {
            return [];
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $lines = array_slice($lines, -max(1, min(self::MAX_EVENTS, $limit)));
        $out = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** Evenimente utile în UI — fără zgomot sync repetitiv. @return list<array<string, mixed>> */
    public function readEventsForDisplay(int $limit = 50): array
    {
        $skip = ['agent_full_cycle', 'system_sync'];
        $all = $this->readEvents(max(1, min(self::MAX_EVENTS, $limit * 4)));
        $out = [];
        for ($i = count($all) - 1; $i >= 0; $i--) {
            $event = $all[$i];
            if (!is_array($event)) {
                continue;
            }
            $actor = (string) ($event['actor_type'] ?? '');
            $action = (string) ($event['action'] ?? '');
            if ($actor === 'system' && in_array($action, $skip, true)) {
                continue;
            }
            $out[] = $event;
            if (count($out) >= $limit) {
                break;
            }
        }

        return array_reverse($out);
    }

    /** @return array<string, mixed> */
    public function getCore(): array
    {
        $path = $this->contextDir . '/core.json';
        if (!is_file($path)) {
            return $this->rebuildCore();
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : $this->rebuildCore();
    }

    /** @return array<string, mixed> */
    public function rebuildCore(): array
    {
        $events = $this->readEvents(200);
        $dbFacts = $this->collectDbActionFacts();

        $byActor = ['admin' => 0, 'client' => 0, 'robot' => 0, 'cron' => 0, 'system' => 0];
        $highlights = [];
        $adminActions = [];
        $clientActions = [];

        foreach (array_reverse($events) as $event) {
            $type = (string) ($event['actor_type'] ?? 'system');
            if (isset($byActor[$type])) {
                $byActor[$type]++;
            }
            $text = $this->eventToHighlight($event);
            if ($text !== '' && count($highlights) < 25) {
                $highlights[] = [
                    'at' => $event['at'] ?? null,
                    'actor_type' => $type,
                    'text' => $text,
                ];
            }
            if ($type === 'admin' && count($adminActions) < 12) {
                $adminActions[] = $text;
            }
            if ($type === 'client' && count($clientActions) < 12) {
                $clientActions[] = $text;
            }
        }

        $patterns = [
            'searches_not_found' => $dbFacts['searches_not_found'] ?? [],
            'searches_resolved' => $this->loadResolvedSearches(),
            'admin_activity' => $this->loadAdminActivityCounts(),
            'recent_orders' => $dbFacts['recent_orders'] ?? [],
            'open_cart_abandonments' => (int) ($dbFacts['open_cart_abandonments'] ?? 0),
            'recent_import_activity' => $dbFacts['recent_import_activity'] ?? [],
        ];

        $directives = $this->buildRobotDirectives($patterns, $highlights, $byActor);

        $core = [
            'updated_at' => date('c'),
            'event_count' => count($events),
            'actors' => $byActor,
            'highlights' => $highlights,
            'admin_recent' => array_values(array_filter($adminActions)),
            'client_recent' => array_values(array_filter($clientActions)),
            'patterns' => $patterns,
            'robot_directives' => $directives,
        ];

        if (!is_dir($this->contextDir)) {
            @mkdir($this->contextDir, 0775, true);
        }
        file_put_contents(
            $this->contextDir . '/core.json',
            json_encode($core, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $core;
    }

    /** @return array<string, mixed> */
    private function collectDbActionFacts(): array
    {
        $out = [
            'searches_not_found' => [],
            'recent_orders' => [],
            'open_cart_abandonments' => 0,
            'recent_import_activity' => [],
        ];

        try {
            $pdo = Database::getDB();
            $out['searches_not_found'] = $this->filterResolvedSearches($this->queryRows(
                $pdo,
                'SELECT query_type, query_value, vehicle_label, created_at
                 FROM search_logs
                 WHERE found = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY created_at DESC LIMIT 10'
            ));
            $out['recent_orders'] = $this->queryRows(
                $pdo,
                'SELECT idcomanda AS id, stare AS status, total, data AS created_at
                 FROM comenzi
                 ORDER BY idcmd DESC LIMIT 8'
            );
            $out['open_cart_abandonments'] = (int) ($this->scalar(
                $pdo,
                "SELECT COUNT(*) FROM cart_abandonments WHERE status = 'open'"
            ) ?? $this->scalar($pdo, 'SELECT COUNT(*) FROM cart_abandonments') ?? 0);
            $out['recent_import_activity'] = $this->queryRows(
                $pdo,
                'SELECT status, COUNT(*) AS cnt, MAX(updated_at) AS last_at
                 FROM import_produse
                 WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                 GROUP BY status
                 ORDER BY cnt DESC LIMIT 6'
            );
        } catch (Throwable) {
            // BD opțională parțial
        }

        return $out;
    }

    /** @param array<string, mixed> $event */
    private function eventToHighlight(array $event): string
    {
        $actor = (string) ($event['actor_type'] ?? 'system');
        $action = (string) ($event['action'] ?? '');
        $subject = (string) ($event['subject'] ?? '');
        $meta = is_array($event['meta'] ?? null) ? $event['meta'] : [];

        if ($actor === 'client' && $action === 'search') {
            $found = !empty($meta['found']) ? 'găsit' : 'NEGĂSIT';
            $q = (string) ($meta['query'] ?? $subject);

            return 'Client caută ' . ($meta['query_type'] ?? 'piesă') . ' «' . $q . '» — ' . $found;
        }
        if ($actor === 'client' && $action === 'page_view') {
            return 'Client deschide pagina ' . ($subject !== '' ? $subject : '—');
        }
        if ($actor === 'client' && $action === 'scroll') {
            return 'Client scroll pe site: ' . $subject;
        }
        if ($actor === 'client' && $action === 'product_view') {
            return 'Client vede produs: «' . $subject . '»';
        }
        if ($actor === 'client' && $action === 'product_click') {
            return 'Client dă click produs: «' . $subject . '»';
        }
        if ($actor === 'client' && $action === 'add_to_cart') {
            $oem = (string) ($meta['oem'] ?? '');
            return 'Client adaugă în coș: «' . $subject . '»'
                . ($oem !== '' ? ' · ' . $oem : '')
                . (isset($meta['quantity']) ? ' ×' . $meta['quantity'] : '');
        }
        if ($actor === 'client' && $action === 'cart_view') {
            return 'Client pe pagina coș: ' . $subject;
        }
        if ($actor === 'client' && $action === 'click') {
            return 'Client click: ' . $subject;
        }
        if ($actor === 'client' && $action === 'time_on_page') {
            return 'Client timp pe pagină: ' . $subject;
        }
        if ($actor === 'client' && $action === 'checkout_step') {
            return 'Client checkout: ' . $subject;
        }
        if ($actor === 'client' && $action === 'idle_return') {
            return 'Client revine pe tab: ' . $subject;
        }
        if ($actor === 'client' && $action === 'cart_abandon') {
            return 'Client a abandonat coșul' . ($subject !== '' ? ' (' . $subject . ')' : '');
        }
        if ($actor === 'admin') {
            $who = (string) ($event['actor_id'] ?? 'admin');
            $module = (string) ($meta['module'] ?? '');

            return 'Admin ' . $who . ': ' . $action . ($module !== '' ? ' [' . $module . ']' : '')
                . ($subject !== '' ? ' — ' . $subject : '');
        }
        if ($actor === 'system' && $action === 'search_resolved') {
            return 'Automatizare: OEM/căutare rezolvată «' . $subject . '» (găsit '
                . (int) ($meta['found_count'] ?? 2) . '×, scos din agent)';
        }

        return ucfirst($actor) . ': ' . $action . ($subject !== '' ? ' — ' . $subject : '');
    }

    /**
     * @param array<string, mixed> $patterns
     * @param list<array<string, mixed>> $highlights
     * @param array<string, int> $byActor
     * @return list<string>
     */
    private function buildRobotDirectives(array $patterns, array $highlights, array $byActor): array
    {
        $directives = [
            '🎼 Orchestră Live: router alege agent specialist (catalog, coș, comenzi…) — fiecare cu prompt și fișiere proprii în robot/data/ai_agents/.',
            '📡 Citește core.json + evenimente live; nu presupune stoc/preț fără confirmare.',
        ];

        $notFound = $patterns['searches_not_found'] ?? [];
        if (is_array($notFound) && $notFound !== []) {
            $top = array_slice($notFound, 0, 3);
            $queries = array_map(
                static fn (array $r): string => (string) ($r['query_value'] ?? ''),
                array_filter($top, 'is_array')
            );
            $queries = array_values(array_filter($queries));
            if ($queries !== []) {
                $directives[] = '🔍 Clienții caută fără rezultat: «' . implode('», «', $queries) . '»'
                    . ' — propune echivalente, filtre alternative sau escaladare operator.';
            }
        }

        if ((int) ($patterns['open_cart_abandonments'] ?? 0) > 0) {
            $directives[] = '🛒 Coșuri abandonate active — mesaj prietenos de finalizare comandă sau ofertă personalizată.';
        }

        if (($byActor['client'] ?? 0) > ($byActor['admin'] ?? 0)) {
            $directives[] = '👥 Trafic clienți ridicat — prioritizează căutări, produse văzute, coș și timp pe pagină.';
        } elseif (($byActor['client'] ?? 0) > 0) {
            $directives[] = '👁️ Clienți activi pe site — urmărește page_view, scroll, product_view, add_to_cart din jurnal.';
        }

        if ($highlights !== []) {
            $last = $highlights[0]['text'] ?? '';
            if (is_string($last) && $last !== '') {
                $directives[] = '⚡ Ultimul semnal: ' . mb_substr($last, 0, 160);
            }
        }

        $adminCounts = $patterns['admin_activity'] ?? [];
        if (is_array($adminCounts) && $adminCounts !== []) {
            arsort($adminCounts);
            $top = array_slice($adminCounts, 0, 3, true);
            $parts = [];
            foreach ($top as $k => $n) {
                $parts[] = $k . '×' . $n;
            }
            if ($parts !== []) {
                $directives[] = '🛠️ Admin activ recent: ' . implode(', ', $parts);
            }
        }

        $resolved = $patterns['searches_resolved'] ?? [];
        if (is_array($resolved) && $resolved !== []) {
            $directives[] = '✅ ' . count($resolved) . ' căutări OEM/de cod rezolvate automat — nu mai alerta pentru ele.';
        }

        return $directives;
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function filterResolvedSearches(array $rows): array
    {
        $path = dirname(__DIR__, 3) . '/system/ai_learning.php';
        if (!is_file($path)) {
            return $rows;
        }
        require_once $path;
        if (!function_exists('ai_learning_filter_not_found_rows')) {
            return $rows;
        }

        return ai_learning_filter_not_found_rows($rows);
    }

    /** @return list<array<string, mixed>> */
    private function loadResolvedSearches(): array
    {
        $path = dirname(__DIR__, 3) . '/system/ai_learning.php';
        if (!is_file($path)) {
            return [];
        }
        require_once $path;

        return function_exists('ai_learning_recent_resolved')
            ? ai_learning_recent_resolved(8)
            : [];
    }

    /** @return array<string, int> */
    private function loadAdminActivityCounts(): array
    {
        $path = dirname(__DIR__, 3) . '/system/ai_learning.php';
        if (!is_file($path)) {
            return [];
        }
        require_once $path;

        return function_exists('ai_learning_admin_counts') ? ai_learning_admin_counts() : [];
    }

    /** @return list<array<string, mixed>> */
    private function queryRows(PDO $pdo, string $sql): array
    {
        try {
            $stmt = $pdo->query($sql);
            if ($stmt === false) {
                return [];
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
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
}
