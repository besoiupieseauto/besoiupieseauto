<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Config\Database;
use PDO;
use Throwable;

/**
 * Analytics vizitatori — sesiuni, timeline evenimente (tip Metrica).
 */
final class VisitorAnalyticsService
{
    private PDO $pdo;

    public function __construct(
        private readonly EventTrackingStore $store,
        ?PDO $pdo = null,
        ?string $projectRoot = null,
    ) {
        $root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $this->pdo = $pdo ?? $this->resolvePdo($root);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{sessions:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    public function listSessions(array $filters = []): array
    {
        $days = max(1, min(90, (int) ($filters['days'] ?? 7)));
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 30)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $country = trim((string) ($filters['country'] ?? ''));
        $ip = trim((string) ($filters['ip'] ?? ''));
        $q = trim((string) ($filters['q'] ?? ''));

        $since = (new \DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d 00:00:00');
        $where = ['(s.last_seen_at >= :since OR s.started_at >= :since2)'];
        $params = [':since' => $since, ':since2' => $since];

        if ($country !== '') {
            $where[] = 's.country_code = :country';
            $params[':country'] = mb_strtoupper($country);
        }
        if ($ip !== '') {
            $where[] = 's.ip_address LIKE :ip';
            $params[':ip'] = '%' . $ip . '%';
        }
        if ($q !== '') {
            $where[] = '(s.city LIKE :q OR s.country_name LIKE :q2 OR s.session_id LIKE :q3 OR s.ip_address LIKE :q4)';
            $params[':q'] = '%' . $q . '%';
            $params[':q2'] = '%' . $q . '%';
            $params[':q3'] = '%' . $q . '%';
            $params[':q4'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);
        $table = EventTrackingStore::TABLE_SESSIONS;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} s WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $countStmt->closeCursor();

        $sql = "SELECT s.*,
                       (SELECT e.event_type FROM " . EventTrackingStore::TABLE_EVENTS . " e
                        WHERE e.session_id = s.session_id ORDER BY e.ts DESC LIMIT 1) AS last_event_type,
                       (SELECT e.metadata FROM " . EventTrackingStore::TABLE_EVENTS . " e
                        WHERE e.session_id = s.session_id ORDER BY e.ts DESC LIMIT 1) AS last_event_meta
                FROM {$table} s
                WHERE {$whereSql}
                ORDER BY COALESCE(s.last_seen_at, s.started_at) DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $sessions = [];
        foreach ($rows as $row) {
            $sessions[] = $this->formatSessionRow($row);
        }

        return [
            'sessions' => $sessions,
            'total' => $total,
            'page' => $page,
            'pages' => (int) max(1, ceil($total / $limit)),
        ];
    }

    /** @return array<string, mixed>|null */
    public function sessionDetail(string $sessionId): ?array
    {
        $sessionId = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($sessionId)) ?? '';
        if ($sessionId === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . EventTrackingStore::TABLE_SESSIONS . ' WHERE session_id = :sid LIMIT 1'
        );
        $stmt->execute([':sid' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!$session) {
            return null;
        }

        $evStmt = $this->pdo->prepare(
            'SELECT event_id, event_type, entity_type, entity_id, metadata, ts
             FROM ' . EventTrackingStore::TABLE_EVENTS . '
             WHERE session_id = :sid
             ORDER BY ts ASC'
        );
        $evStmt->execute([':sid' => $sessionId]);
        $events = [];
        foreach ($evStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ev) {
            $meta = json_decode((string) ($ev['metadata'] ?? '{}'), true);
            if (!is_array($meta)) {
                $meta = [];
            }
            $events[] = [
                'event_id' => (string) ($ev['event_id'] ?? ''),
                'event_type' => (string) ($ev['event_type'] ?? ''),
                'entity_type' => (string) ($ev['entity_type'] ?? ''),
                'entity_id' => (string) ($ev['entity_id'] ?? ''),
                'ts' => (string) ($ev['ts'] ?? ''),
                'label' => $this->eventLabel((string) ($ev['event_type'] ?? ''), $meta),
                'icon' => $this->eventIcon((string) ($ev['event_type'] ?? '')),
                'metadata' => $meta,
            ];
        }

        $formatted = $this->formatSessionRow($session);
        $formatted['events'] = $events;

        return $formatted;
    }

    /** @return array{countries:list<array<string,mixed>>,devices:list<array<string,mixed>>,live_today:int} */
    public function visitorStats(int $days = 7): array
    {
        $days = max(1, min(30, $days));
        $since = (new \DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d 00:00:00');
        $table = EventTrackingStore::TABLE_SESSIONS;

        $countries = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT country_code, country_name, COUNT(*) AS sessions
                 FROM {$table}
                 WHERE started_at >= :since AND country_code IS NOT NULL AND country_code <> ''
                 GROUP BY country_code, country_name
                 ORDER BY sessions DESC
                 LIMIT 15"
            );
            $stmt->execute([':since' => $since]);
            $countries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $stmt->closeCursor();
        } catch (Throwable) {
            /* optional */
        }

        $devices = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT device_type, COUNT(*) AS sessions
                 FROM {$table}
                 WHERE started_at >= :since AND device_type IS NOT NULL
                 GROUP BY device_type
                 ORDER BY sessions DESC"
            );
            $stmt->execute([':since' => $since]);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $stmt->closeCursor();
        } catch (Throwable) {
            /* optional */
        }

        $liveToday = 0;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE DATE(COALESCE(last_seen_at, started_at)) = CURDATE()"
            );
            $stmt->execute();
            $liveToday = (int) $stmt->fetchColumn();
            $stmt->closeCursor();
        } catch (Throwable) {
            /* optional */
        }

        return [
            'countries' => $countries,
            'devices' => $devices,
            'live_today' => $liveToday,
        ];
    }

    /** @param array<string, mixed> $row */
    private function formatSessionRow(array $row): array
    {
        $lastMeta = [];
        if (!empty($row['last_event_meta'])) {
            $decoded = json_decode((string) $row['last_event_meta'], true);
            if (is_array($decoded)) {
                $lastMeta = $decoded;
            }
        }

        $cc = (string) ($row['country_code'] ?? '');

        return [
            'session_id' => (string) ($row['session_id'] ?? ''),
            'user_id' => (string) ($row['user_id'] ?? ''),
            'started_at' => (string) ($row['started_at'] ?? ''),
            'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
            'source' => (string) ($row['source'] ?? 'web'),
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'country_code' => $cc,
            'country_name' => (string) ($row['country_name'] ?? ''),
            'country_flag' => $this->countryFlag($cc),
            'city' => (string) ($row['city'] ?? ''),
            'region' => (string) ($row['region'] ?? ''),
            'device_type' => (string) ($row['device_type'] ?? ''),
            'browser' => (string) ($row['browser'] ?? ''),
            'os' => (string) ($row['os'] ?? ''),
            'user_agent' => (string) ($row['user_agent'] ?? ''),
            'referrer' => (string) ($row['referrer'] ?? ''),
            'landing_page' => (string) ($row['landing_page'] ?? ''),
            'event_count' => (int) ($row['event_count'] ?? 0),
            'last_event_type' => (string) ($row['last_event_type'] ?? ''),
            'last_event_label' => $this->eventLabel((string) ($row['last_event_type'] ?? ''), $lastMeta),
        ];
    }

    /** @param array<string, mixed> $meta */
    private function eventLabel(string $type, array $meta): string
    {
        return match ($type) {
            'page_view' => 'Pagină: ' . ($meta['path'] ?? $meta['title'] ?? '—'),
            'search_query' => 'Căutare: «' . ($meta['query'] ?? $meta['subject'] ?? '—') . '»',
            'search_result_click' => 'Click produs (search): ' . ($meta['subject'] ?? $meta['entity_id'] ?? '—'),
            'product_view' => 'Produs vizualizat: ' . ($meta['subject'] ?? $meta['entity_id'] ?? '—'),
            'add_to_cart' => 'Adăugat în coș: ' . ($meta['subject'] ?? '—'),
            'purchase' => 'Comandă #' . ($meta['order_id'] ?? '—'),
            'recommendation_click' => 'Click recomandare: ' . ($meta['subject'] ?? '—'),
            'filter_applied' => 'Filtru: ' . ($meta['filter_name'] ?? '') . '=' . ($meta['filter_value'] ?? ''),
            'ui_click' => 'Click: ' . ($meta['element_text'] ?? $meta['tag'] ?? $meta['href'] ?? 'element'),
            default => $type !== '' ? $type : 'Eveniment',
        };
    }

    private function eventIcon(string $type): string
    {
        return match ($type) {
            'page_view' => 'fa-solid fa-file-lines',
            'search_query' => 'fa-solid fa-magnifying-glass',
            'search_result_click', 'recommendation_click' => 'fa-solid fa-hand-pointer',
            'product_view' => 'fa-solid fa-box',
            'add_to_cart' => 'fa-solid fa-cart-shopping',
            'purchase' => 'fa-solid fa-circle-check',
            'filter_applied' => 'fa-solid fa-sliders',
            'ui_click' => 'fa-solid fa-computer-mouse',
            default => 'fa-solid fa-circle-dot',
        };
    }

    private function countryFlag(string $code): string
    {
        $code = strtoupper(trim($code));
        if ($code === 'LAN' || $code === 'LOC') {
            return '🏠';
        }
        if ($code === 'UNK') {
            return '❓';
        }
        if (strlen($code) !== 2) {
            return '🌍';
        }

        $a = 127397;

        return mb_chr($a + ord($code[0])) . mb_chr($a + ord($code[1]));
    }

    private function resolvePdo(string $root): PDO
    {
        if (class_exists(Database::class) && Database::hasConnection()) {
            return Database::getDB();
        }
        $config = require $root . '/admin/config/config.php';
        Database::getInstance(
            (string) ($config['db_host'] ?? '127.0.0.1'),
            (string) ($config['db_name'] ?? ''),
            (string) ($config['db_user'] ?? ''),
            (string) ($config['db_pass'] ?? '')
        );

        return Database::getDB();
    }
}
