<?php

declare(strict_types=1);

/**
 * Învățare automată AI — streak-uri căutări, OEM rezolvat, curățare context agent.
 */

const AI_LEARNING_RESOLVE_FOUND_THRESHOLD = 2;

function ai_learning_dir(): string
{
    $dir = dirname(__DIR__) . '/robot/data/ai_context';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

function ai_learning_state_path(): string
{
    return ai_learning_dir() . '/learning_state.json';
}

/** @return array<string, mixed> */
function ai_learning_load(): array
{
    $path = ai_learning_state_path();
    if (!is_file($path)) {
        return [
            'search_streaks' => [],
            'resolved' => [],
            'admin_counts' => [],
            'updated_at' => null,
        ];
    }
    $json = json_decode((string) file_get_contents($path), true);

    return is_array($json) ? $json : [
        'search_streaks' => [],
        'resolved' => [],
        'admin_counts' => [],
        'updated_at' => null,
    ];
}

/** @param array<string, mixed> $state */
function ai_learning_save(array $state): void
{
    $state['updated_at'] = date('c');
    file_put_contents(
        ai_learning_state_path(),
        json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

function ai_learning_search_key(string $queryType, string $queryValue): string
{
    $type = strtolower(trim($queryType));
    $value = strtoupper(preg_replace('/\s+/', '', trim($queryValue)) ?? '');

    return $type . ':' . $value;
}

/** @param array<string, mixed> $meta */
function ai_learning_record_search(string $queryType, string $queryValue, bool $found, array $meta = []): void
{
    $queryValue = trim($queryValue);
    if ($queryValue === '') {
        return;
    }

    $state = ai_learning_load();
    $key = ai_learning_search_key($queryType, $queryValue);
    $streaks = is_array($state['search_streaks'] ?? null) ? $state['search_streaks'] : [];
    $resolved = is_array($state['resolved'] ?? null) ? $state['resolved'] : [];

    $prev = is_array($streaks[$key] ?? null) ? $streaks[$key] : ['found' => 0, 'not_found' => 0];
    $source = (string) ($meta['source'] ?? $meta['scan_source'] ?? '');

    if ($found) {
        $prev['found'] = (int) ($prev['found'] ?? 0) + 1;
        $prev['last_found_at'] = date('c');
        $prev['last_source'] = $source !== '' ? $source : (string) ($prev['last_source'] ?? '');
        $streaks[$key] = $prev;

        if ($prev['found'] >= AI_LEARNING_RESOLVE_FOUND_THRESHOLD && !isset($resolved[$key])) {
            $resolved[$key] = [
                'query_type' => $queryType,
                'query_value' => $queryValue,
                'found_count' => $prev['found'],
                'resolved_at' => date('c'),
                'source' => $prev['last_source'],
                'reason' => 'Găsit de ' . $prev['found'] . ' ori — scos din lista agent.',
            ];
            ai_learning_on_resolved($queryType, $queryValue, $resolved[$key]);
        }
    } else {
        if (isset($resolved[$key])) {
            $state['search_streaks'] = $streaks;
            $state['resolved'] = $resolved;
            ai_learning_save($state);

            return;
        }
        $prev['not_found'] = (int) ($prev['not_found'] ?? 0) + 1;
        $prev['last_not_found_at'] = date('c');
        $streaks[$key] = $prev;
    }

    $state['search_streaks'] = $streaks;
    $state['resolved'] = $resolved;
    ai_learning_save($state);
}

/** @param array<string, mixed> $info */
function ai_learning_on_resolved(string $queryType, string $queryValue, array $info): void
{
    $helper = __DIR__ . '/ai_action_events.php';
    if (is_file($helper)) {
        require_once $helper;
    }
    if (function_exists('ai_action_event_record')) {
        ai_action_event_record('system', 'search_resolved', $queryValue, [
            'query_type' => $queryType,
            'query' => $queryValue,
            'found_count' => (int) ($info['found_count'] ?? AI_LEARNING_RESOLVE_FOUND_THRESHOLD),
            'source' => (string) ($info['source'] ?? ''),
            'auto' => true,
        ]);
    }

    ai_learning_prune_all_agents($queryType, $queryValue);
}

function ai_learning_is_resolved(string $queryType, string $queryValue): bool
{
    $state = ai_learning_load();
    $key = ai_learning_search_key($queryType, $queryValue);
    $resolved = is_array($state['resolved'] ?? null) ? $state['resolved'] : [];

    return isset($resolved[$key]);
}

/** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
function ai_learning_filter_not_found_rows(array $rows): array
{
    return array_values(array_filter($rows, static function (array $row): bool {
        $type = (string) ($row['query_type'] ?? 'name');
        $value = (string) ($row['query_value'] ?? '');

        return $value !== '' && !ai_learning_is_resolved($type, $value);
    }));
}

function ai_learning_prune_learned_text(string $body, ?string $queryType = null, ?string $queryValue = null): string
{
    if (trim($body) === '') {
        return $body;
    }

    $needles = [];
    if ($queryType !== null && $queryValue !== null && $queryValue !== '') {
        $needles[] = strtoupper(trim($queryValue));
    } else {
        $state = ai_learning_load();
        $resolved = is_array($state['resolved'] ?? null) ? $state['resolved'] : [];
        foreach ($resolved as $item) {
            if (!is_array($item)) {
                continue;
            }
            $v = (string) ($item['query_value'] ?? '');
            if ($v !== '') {
                $needles[] = strtoupper($v);
            }
        }
    }

    if ($needles === []) {
        return $body;
    }

    $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $drop = false;
        $lineLower = mb_strtolower($line, 'UTF-8');
        if (preg_match('/\b(time_on_page|scroll|mousemove|heartbeat|visibility|page_view)\b/u', $lineLower)) {
            $drop = true;
        }
        if (!$drop && preg_match('/\bpe\s+\/(catalog|product|home)\b/u', $lineLower)
            && !preg_match('/\b(factur|comenz|awb|furnizor|produs|oem)\b/u', $lineLower)) {
            $drop = true;
        }
        foreach ($needles as $needle) {
            if ($needle === '') {
                continue;
            }
            if (stripos($line, $needle) !== false && (
                stripos($line, 'negăsit') !== false
                || stripos($line, 'negasit') !== false
                || stripos($line, 'Căutare negăsită') !== false
            )) {
                $drop = true;
                break;
            }
        }
        if (!$drop) {
            $out[] = $line;
        }
    }

    return implode("\n", $out);
}

function ai_learning_prune_all_agents(string $queryType, string $queryValue): void
{
    $agentsDir = dirname(__DIR__) . '/robot/data/ai_agents';
    if (!is_dir($agentsDir)) {
        return;
    }

    $helper = BESOIU_BACKEND . '/src/Services/AiMdcParser.php';
    if (!is_file($helper)) {
        return;
    }

    require_once $helper;

    foreach (glob($agentsDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $learnedPath = $dir . '/context.learned.mdc';
        if (!is_file($learnedPath)) {
            continue;
        }
        $parsed = \Besoiu\Services\AiMdcParser::parse((string) file_get_contents($learnedPath));
        $body = ai_learning_prune_learned_text((string) ($parsed['body'] ?? ''), $queryType, $queryValue);
        if ($body === (string) ($parsed['body'] ?? '')) {
            continue;
        }
        $meta = is_array($parsed['meta'] ?? null) ? $parsed['meta'] : [];
        $meta['lastPruneAt'] = date('c');
        file_put_contents(
            $learnedPath,
            \Besoiu\Services\AiMdcParser::compose($meta, $body)
        );
    }
}

/** @param array<string, mixed> $meta */
function ai_admin_notify_success(string $module, array $payload, int $status = 200, string $actionHint = '', array $meta = []): void
{
    ai_admin_notify($module, $payload, $status, $actionHint, $meta, false);
}

/** @param array<string, mixed> $meta */
function ai_admin_notify_failure(string $module, array $payload, int $status = 500, string $actionHint = 'admin_error', array $meta = []): void
{
    ai_admin_notify($module, $payload, $status, $actionHint, $meta, true);
}

/** @param array<string, mixed> $meta */
function ai_admin_notify(string $module, array $payload, int $status = 200, string $actionHint = '', array $meta = [], bool $forceError = false): void
{
    $isError = $forceError || $status >= 400 || empty($payload['success']);
    if (!$isError && ($status < 200 || $status >= 300 || empty($payload['success']))) {
        return;
    }

    $bridge = ai_admin_event_bridge();
    if ($bridge !== null) {
        if ($isError) {
            $bridge->recordFailure($module, $payload, $status, $actionHint !== '' ? $actionHint : 'admin_error', $meta);
        } else {
            $bridge->recordSuccess($module, $payload, $status, $actionHint, $meta);
        }

        return;
    }

    $helper = __DIR__ . '/ai_action_events.php';
    if (is_file($helper)) {
        require_once $helper;
    }
    if (!function_exists('ai_action_event_record')) {
        return;
    }

    $action = $isError
        ? ($actionHint !== '' ? $actionHint : 'admin_error')
        : ($actionHint !== '' ? $actionHint : ai_admin_infer_action($module, $payload));
    $subject = ai_admin_infer_subject($payload);
    $actorId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;

    ai_action_event_record('admin', $action, $subject, array_merge([
        'module' => $module,
        'message' => (string) ($payload['message'] ?? ''),
        'count' => $payload['count'] ?? $payload['imported'] ?? null,
        'id' => $payload['id'] ?? null,
        'error' => $isError,
        'http_status' => $status,
    ], $meta), $actorId);

    if (!$isError) {
        $state = ai_learning_load();
        $counts = is_array($state['admin_counts'] ?? null) ? $state['admin_counts'] : [];
        $countKey = $module . ':' . $action;
        $counts[$countKey] = (int) ($counts[$countKey] ?? 0) + 1;
        $state['admin_counts'] = $counts;
        ai_learning_save($state);
    }

    ai_learning_touch_admin($module, $action, $isError);
}

function ai_learning_touch_admin(string $module, string $action, bool $isError): void
{
    $state = ai_learning_load();
    $log = is_array($state['admin_recent'] ?? null) ? $state['admin_recent'] : [];
    array_unshift($log, [
        'at' => date('c'),
        'module' => $module,
        'action' => $action,
        'error' => $isError,
    ]);
    $state['admin_recent'] = array_slice($log, 0, 40);
    if ($isError) {
        $errors = is_array($state['admin_errors'] ?? null) ? $state['admin_errors'] : [];
        array_unshift($errors, [
            'at' => date('c'),
            'module' => $module,
            'action' => $action,
        ]);
        $state['admin_errors'] = array_slice($errors, 0, 25);
    }
    ai_learning_save($state);
}

/** @return object|null */
function ai_admin_event_bridge(): ?object
{
    static $bridge = null;
    static $tried = false;
    if ($tried) {
        return $bridge;
    }
    $tried = true;
    $class = '\\Besoiu\\Services\\AiSupervisor\\Phase1\\AdminEventBridge';
    if (!class_exists($class)) {
        $autoload = BESOIU_BACKEND . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }
    if (class_exists($class)) {
        $bridge = new $class();
    }

    return $bridge;
}

/** @param array<string, mixed> $payload */
function ai_admin_infer_action(string $module, array $payload): string
{
    $msg = mb_strtolower((string) ($payload['message'] ?? ''));
    if (str_contains($msg, 'sters') || str_contains($msg, 'șters')) {
        return str_contains($msg, 'produse') ? 'delete_bulk' : 'delete';
    }
    if (str_contains($msg, 'adaugat') || str_contains($msg, 'adăugat')) {
        return 'add';
    }
    if (str_contains($msg, 'salvat') || str_contains($msg, 'actualizat')) {
        return 'update';
    }
    if (str_contains($msg, 'import')) {
        return 'import';
    }
    if (isset($payload['mode'])) {
        return (string) $payload['mode'];
    }

    return $module . '_action';
}

/** @param array<string, mixed> $payload */
function ai_admin_infer_subject(array $payload): string
{
    if (isset($payload['id']) && (string) $payload['id'] !== '') {
        return 'id=' . (string) $payload['id'];
    }
    if (isset($payload['count'])) {
        return 'count=' . (string) $payload['count'];
    }
    $msg = trim((string) ($payload['message'] ?? ''));

    return $msg !== '' ? mb_substr($msg, 0, 160) : '';
}

/** @return list<array<string, mixed>> */
function ai_learning_recent_resolved(int $limit = 10): array
{
    $state = ai_learning_load();
    $resolved = is_array($state['resolved'] ?? null) ? $state['resolved'] : [];
    $rows = array_values($resolved);
    usort($rows, static function (array $a, array $b): int {
        return strcmp((string) ($b['resolved_at'] ?? ''), (string) ($a['resolved_at'] ?? ''));
    });

    return array_slice($rows, 0, max(1, $limit));
}

/** @return array<string, int> */
function ai_learning_admin_counts(): array
{
    $state = ai_learning_load();
    $counts = is_array($state['admin_counts'] ?? null) ? $state['admin_counts'] : [];

    return $counts;
}

/** @param array<string, mixed> $manifest */
function ai_learning_record_system_errors_archive(array $manifest): void
{
    $state = ai_learning_load();
    $archives = is_array($state['system_errors_archives'] ?? null) ? $state['system_errors_archives'] : [];

    $archives[] = [
        'archived_at' => (string) ($manifest['archived_at'] ?? date('c')),
        'period' => (string) ($manifest['period'] ?? ''),
        'period_label' => (string) ($manifest['period_label'] ?? ''),
        'count' => (int) ($manifest['count'] ?? 0),
        'path' => (string) ($manifest['path'] ?? ''),
        'by_channel' => $manifest['stats']['by_channel'] ?? [],
        'by_level' => $manifest['stats']['by_level'] ?? [],
        'samples' => $manifest['stats']['samples'] ?? [],
    ];

    if (count($archives) > 40) {
        $archives = array_slice($archives, -40);
    }

    $state['system_errors_archives'] = $archives;
    ai_learning_save($state);
}
