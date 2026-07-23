<?php
declare(strict_types=1);

/**
 * Jurnal centralizat erori — DB + fișier fallback pentru procesare fundal.
 */

/** @return list<string> */
function system_errors_valid_levels(): array
{
    return ['debug', 'info', 'warning', 'error', 'critical'];
}

/** @return list<string> */
function system_errors_valid_channels(): array
{
    return ['general', 'cron', 'queue', 'tecdoc', 'rapidapi', 'cache', 'site-content', 'product', 'import', 'ai'];
}

function system_errors_normalize_level(string $level): string
{
    $key = strtolower(trim($level));
    return in_array($key, system_errors_valid_levels(), true) ? $key : 'error';
}

function system_errors_normalize_channel(string $channel): string
{
    $key = strtolower(trim($channel));
    if ($key === '' || !in_array($key, system_errors_valid_channels(), true)) {
        return 'general';
    }

    return $key;
}

function system_errors_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(['system_errors']);
    $exists = ((int) $stmt->fetchColumn()) > 0;

    return $exists;
}

function system_errors_ensure_archive_columns(PDO $pdo): void
{
    static $done = false;
    if ($done || !system_errors_table_exists($pdo)) {
        return;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM system_errors LIKE 'is_archived'");
        if ($stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) === false) {
            $pdo->exec(
                'ALTER TABLE system_errors
                 ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_resolved,
                 ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER is_archived,
                 ADD KEY idx_system_errors_archived (is_archived, created_at)'
            );
        }
    } catch (Throwable $e) {
        error_log('[system_errors] archive columns: ' . $e->getMessage());
    }

    $done = true;
}

/** @param array<string, mixed> $filters */
function system_errors_apply_list_filters(PDO $pdo, array $filters): array
{
    system_errors_ensure_archive_columns($pdo);

    $where = ['1=1'];
    $params = [];

    if (empty($filters['include_archived'])) {
        $where[] = 'is_archived = 0';
    } elseif (!empty($filters['archived_only'])) {
        $where[] = 'is_archived = 1';
    }

    $level = trim((string) ($filters['level'] ?? ''));
    if ($level !== '' && in_array($level, system_errors_valid_levels(), true)) {
        $where[] = 'level = :level';
        $params[':level'] = $level;
    }

    $channel = trim((string) ($filters['channel'] ?? ''));
    if ($channel !== '') {
        $where[] = 'channel = :channel';
        $params[':channel'] = system_errors_normalize_channel($channel);
    }

    if (!empty($filters['unresolved_only'])) {
        $where[] = 'is_resolved = 0';
    }

    if (!empty($filters['resolved_only'])) {
        $where[] = 'is_resolved = 1';
    }

    $preset = trim((string) ($filters['date_preset'] ?? ''));
    if ($preset !== '') {
        system_errors_apply_date_preset($where, $preset);
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(message LIKE :q OR source_file LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $where[] = 'created_at >= :date_from';
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if ($dateTo !== '') {
        $where[] = 'created_at <= :date_to';
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    return [$where, $params];
}

/** @param list<string> $where */
function system_errors_apply_date_preset(array &$where, string $preset): void
{
    $preset = strtolower(trim($preset));
    match ($preset) {
        'today', 'azi' => $where[] = 'DATE(created_at) = CURDATE()',
        'yesterday', 'ieri' => $where[] = 'DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)',
        'week', '7days', 'saptamana' => $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
        default => null,
    };
}

function system_errors_log_dir(): string
{
    return BESOIU_BACKEND . '/storage/logs';
}

function system_errors_append_file(string $line): void
{
    $logDir = system_errors_log_dir();
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    file_put_contents(
        $logDir . '/system_errors.log',
        date('[Y-m-d H:i:s] ') . $line . PHP_EOL,
        FILE_APPEND
    );
}

/**
 * @param array<string, mixed> $context
 */
function besoiu_system_error_log(
    string $level,
    string $channel,
    string $message,
    array $context = [],
    ?string $sourceFile = null
): void {
    $level = system_errors_normalize_level($level);
    $channel = system_errors_normalize_channel($channel);
    $message = mb_substr(trim($message), 0, 1000);
    if ($message === '') {
        return;
    }

    if ($sourceFile === null || $sourceFile === '') {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? $trace[0] ?? null;
        if (is_array($caller) && !empty($caller['file'])) {
            $sourceFile = (string) $caller['file'];
            if (!empty($caller['line'])) {
                $sourceFile .= ':' . (int) $caller['line'];
            }
        }
    }

    $prefix = '[' . strtoupper($channel) . '] ';
    error_log($prefix . $message);

    $contextJson = $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    system_errors_append_file(
        strtoupper($level) . ' ' . $prefix . $message
        . ($contextJson !== '' && $contextJson !== false ? ' ' . $contextJson : '')
        . ($sourceFile ? ' @ ' . $sourceFile : '')
    );

    try {
        require_once __DIR__ . '/tecdoc_stock.php';
        $pdo = tecdoc_db();
        if (!system_errors_table_exists($pdo)) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO system_errors (level, channel, message, context_json, source_file)
             VALUES (:level, :channel, :message, :context, :source)'
        );
        $stmt->execute([
            ':level' => $level,
            ':channel' => $channel,
            ':message' => $message,
            ':context' => $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            ':source' => $sourceFile !== null && $sourceFile !== '' ? mb_substr($sourceFile, 0, 255) : null,
        ]);
    } catch (Throwable $e) {
        error_log('[system_errors] DB insert failed: ' . $e->getMessage());
    }
}

/** @return array<string, int> */
function system_errors_stats(PDO $pdo): array
{
    if (!system_errors_table_exists($pdo)) {
        return [
            'total' => 0,
            'active' => 0,
            'unresolved' => 0,
            'today' => 0,
            'critical_today' => 0,
            'archived_total' => 0,
            'by_channel' => [],
        ];
    }

    system_errors_ensure_archive_columns($pdo);

    $grand = $pdo->query(
        "SELECT
            SUM(CASE WHEN is_archived = 0 THEN 1 ELSE 0 END) AS active_total,
            SUM(CASE WHEN is_resolved = 0 AND is_archived = 0 THEN 1 ELSE 0 END) AS unresolved,
            SUM(CASE WHEN DATE(created_at) = CURDATE() AND is_archived = 0 THEN 1 ELSE 0 END) AS today_all,
            SUM(CASE WHEN DATE(created_at) = CURDATE() AND level IN ('error','critical') AND is_archived = 0 THEN 1 ELSE 0 END) AS critical_today,
            SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END) AS archived_total
         FROM system_errors"
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    $byChannel = [];
    $stmt = $pdo->query(
        "SELECT channel, COUNT(*) AS cnt
         FROM system_errors
         WHERE is_resolved = 0 AND is_archived = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY channel
         ORDER BY cnt DESC
         LIMIT 12"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $byChannel[(string) ($row['channel'] ?? 'general')] = (int) ($row['cnt'] ?? 0);
    }

    return [
        'total' => (int) ($grand['active_total'] ?? 0),
        'active' => (int) ($grand['active_total'] ?? 0),
        'unresolved' => (int) ($grand['unresolved'] ?? 0),
        'today' => (int) ($grand['today_all'] ?? 0),
        'critical_today' => (int) ($grand['critical_today'] ?? 0),
        'archived_total' => (int) ($grand['archived_total'] ?? 0),
        'by_channel' => $byChannel,
    ];
}

/** @param array<string, mixed> $filters @return list<array<string, mixed>> */
function system_errors_query(PDO $pdo, array $filters): array
{
    if (!system_errors_table_exists($pdo)) {
        return [];
    }

    [$where, $params] = system_errors_apply_list_filters($pdo, $filters);

    $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
    $offset = max(0, (int) ($filters['offset'] ?? 0));

    $sql = 'SELECT id, level, channel, message, context_json, source_file, is_resolved, is_archived, archived_at, created_at
            FROM system_errors
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY id DESC
            LIMIT ' . $limit . ' OFFSET ' . $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $ctx = $row['context_json'] ?? null;
        if (is_string($ctx) && $ctx !== '') {
            $decoded = json_decode($ctx, true);
            $row['context'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['context'] = [];
        }
        unset($row['context_json']);
        $row['is_resolved'] = (int) ($row['is_resolved'] ?? 0) === 1;
        $row['is_archived'] = (int) ($row['is_archived'] ?? 0) === 1;
    }
    unset($row);

    return $rows;
}

/** @param array<string, mixed> $filters */
function system_errors_count(PDO $pdo, array $filters): int
{
    if (!system_errors_table_exists($pdo)) {
        return 0;
    }

    [$where, $params] = system_errors_apply_list_filters($pdo, $filters);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM system_errors WHERE ' . implode(' AND ', $where));
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function system_errors_mark_resolved(PDO $pdo, int $id, bool $resolved = true): bool
{
    if (!system_errors_table_exists($pdo) || $id <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE system_errors SET is_resolved = ? WHERE id = ?');
    $stmt->execute([$resolved ? 1 : 0, $id]);

    return $stmt->rowCount() > 0;
}

function system_errors_resolve_channel(PDO $pdo, string $channel): int
{
    if (!system_errors_table_exists($pdo)) {
        return 0;
    }

    $channel = trim($channel);
    if ($channel === '') {
        return 0;
    }

    $stmt = $pdo->prepare('UPDATE system_errors SET is_resolved = 1 WHERE is_resolved = 0 AND channel = ?');
    $stmt->execute([$channel]);

    return $stmt->rowCount();
}

/** @param list<int> $ids */
function system_errors_mark_resolved_bulk(PDO $pdo, array $ids, bool $resolved = true): int
{
    if (!system_errors_table_exists($pdo) || $ids === []) {
        return 0;
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare('UPDATE system_errors SET is_resolved = ? WHERE id IN (' . $placeholders . ')');
    $stmt->execute(array_merge([$resolved ? 1 : 0], $ids));

    return $stmt->rowCount();
}

/** @param array<string, mixed> $filters */
function system_errors_mark_all_resolved(PDO $pdo, array $filters = []): int
{
    if (!system_errors_table_exists($pdo)) {
        return 0;
    }

    system_errors_ensure_archive_columns($pdo);

    $where = ['is_resolved = 0', 'is_archived = 0'];
    $params = [];

    $level = trim((string) ($filters['level'] ?? ''));
    if ($level !== '' && in_array($level, system_errors_valid_levels(), true)) {
        $where[] = 'level = :level';
        $params[':level'] = $level;
    }

    $channel = trim((string) ($filters['channel'] ?? ''));
    if ($channel !== '') {
        $where[] = 'channel = :channel';
        $params[':channel'] = system_errors_normalize_channel($channel);
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(message LIKE :q OR source_file LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }

    $stmt = $pdo->prepare('UPDATE system_errors SET is_resolved = 1 WHERE ' . implode(' AND ', $where));
    $stmt->execute($params);

    return $stmt->rowCount();
}

/** @return list<array<string, mixed>> */
function system_errors_recent_by_channel(PDO $pdo, int $limit = 8): array
{
    if (!system_errors_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT id, channel, level, message, created_at
         FROM system_errors
         WHERE is_resolved = 0 AND is_archived = 0
         ORDER BY id DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, max(1, min(50, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<string> */
function system_errors_valid_periods(): array
{
    return ['today', 'yesterday', 'week', 'all'];
}

function system_errors_normalize_period(string $period): string
{
    $period = strtolower(trim($period));
    $map = [
        'azi' => 'today',
        'ieri' => 'yesterday',
        'saptamana' => 'week',
        '7days' => 'week',
        'tot' => 'all',
        'tot_timpul' => 'all',
    ];

    return $map[$period] ?? $period;
}

/** @param array<string, mixed> $filters */
function system_errors_archive_where(PDO $pdo, string $period, array $filters = []): array
{
    system_errors_ensure_archive_columns($pdo);

    $where = ['is_archived = 0'];
    $params = [];

    $level = trim((string) ($filters['level'] ?? ''));
    if ($level !== '' && in_array($level, system_errors_valid_levels(), true)) {
        $where[] = 'level = :level';
        $params[':level'] = $level;
    }

    $channel = trim((string) ($filters['channel'] ?? ''));
    if ($channel !== '') {
        $where[] = 'channel = :channel';
        $params[':channel'] = system_errors_normalize_channel($channel);
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(message LIKE :q OR source_file LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }

    $period = system_errors_normalize_period($period);
    if ($period !== '' && $period !== 'all' && in_array($period, system_errors_valid_periods(), true)) {
        system_errors_apply_date_preset($where, $period);
    }

    return [$where, $params];
}

/** @param array<string, mixed> $filters */
function system_errors_count_for_archive(PDO $pdo, string $period, array $filters = []): int
{
    if (!system_errors_table_exists($pdo)) {
        return 0;
    }

    [$where, $params] = system_errors_archive_where($pdo, $period, $filters);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM system_errors WHERE ' . implode(' AND ', $where));
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/** @param array<string, mixed> $filters @return list<array<string, mixed>> */
function system_errors_fetch_for_archive(PDO $pdo, string $period, array $filters = [], int $limit = 5000): array
{
    if (!system_errors_table_exists($pdo)) {
        return [];
    }

    [$where, $params] = system_errors_archive_where($pdo, $period, $filters);
    $limit = max(1, min(5000, $limit));

    $sql = 'SELECT id, level, channel, message, context_json, source_file, is_resolved, created_at
            FROM system_errors
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY id ASC
            LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $ctx = $row['context_json'] ?? null;
        if (is_string($ctx) && $ctx !== '') {
            $decoded = json_decode($ctx, true);
            $row['context'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['context'] = [];
        }
        unset($row['context_json']);
        $row['is_resolved'] = (int) ($row['is_resolved'] ?? 0) === 1;
    }
    unset($row);

    return $rows;
}

/** @param list<int> $ids */
function system_errors_mark_archived(PDO $pdo, array $ids): int
{
    if (!system_errors_table_exists($pdo) || $ids === []) {
        return 0;
    }

    system_errors_ensure_archive_columns($pdo);

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        'UPDATE system_errors
         SET is_archived = 1, archived_at = NOW(), is_resolved = 1
         WHERE is_archived = 0 AND id IN (' . $placeholders . ')'
    );
    $stmt->execute($ids);

    return $stmt->rowCount();
}
