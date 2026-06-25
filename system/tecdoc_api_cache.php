<?php
declare(strict_types=1);

/**
 * Cache TecDoc în MySQL — încărcat din tecdoc_stock.php după funcțiile de bază.
 */

function tecdoc_cache_log_error(string $message, array $context = []): void
{
    error_log('[TecDoc DB cache] ' . $message);
    if (is_file(__DIR__ . '/system_errors.php')) {
        require_once __DIR__ . '/system_errors.php';
        besoiu_system_error_log('warning', 'cache', $message, $context);
    }
}

function tecdoc_db_cache_key(string $url): string
{
    return hash('sha256', $url);
}

function tecdoc_db_cache_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    try {
        $pdo = tecdoc_db();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute(['tecdoc_api_cache']);
        $ready = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        tecdoc_cache_log_error('table check failed: ' . $e->getMessage());
        $ready = false;
    }

    return $ready;
}

function tecdoc_db_cache_get(string $url, int $ttl = 86400): ?string
{
    if (!tecdoc_db_cache_table_ready()) {
        return null;
    }

    try {
        $pdo = tecdoc_db();
        $stmt = $pdo->prepare(
            'SELECT body FROM tecdoc_api_cache
             WHERE cache_key = ? AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([tecdoc_db_cache_key($url)]);
        $body = $stmt->fetchColumn();
        if (!is_string($body) || $body === '') {
            return null;
        }
        if (tecdoc_cache_body_is_error($body)) {
            return null;
        }

        return $body;
    } catch (Throwable $e) {
        tecdoc_cache_log_error('get failed: ' . $e->getMessage(), ['url' => mb_substr($url, 0, 120)]);
        return null;
    }
}

function tecdoc_db_cache_set(string $url, string $body, int $ttl = 86400): bool
{
    if (!tecdoc_db_cache_table_ready()) {
        return false;
    }
    if ($body === '' || tecdoc_cache_body_is_error($body)) {
        return false;
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || !tecdoc_response_is_valid($decoded)) {
        return false;
    }

    $ttl = max(60, $ttl);

    try {
        $pdo = tecdoc_db();
        $stmt = $pdo->prepare(
            'INSERT INTO tecdoc_api_cache (cache_key, url, body, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
                url = VALUES(url),
                body = VALUES(body),
                expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            tecdoc_db_cache_key($url),
            mb_substr($url, 0, 768),
            $body,
            $ttl,
            $ttl,
        ]);

        return true;
    } catch (Throwable $e) {
        tecdoc_cache_log_error('set failed: ' . $e->getMessage(), ['url' => mb_substr($url, 0, 120)]);
        return false;
    }
}

/** @return array{table_ready:bool,total_rows:int,active_rows:int,expired_rows:int} */
function tecdoc_db_cache_stats(): array
{
    if (!tecdoc_db_cache_table_ready()) {
        return [
            'table_ready' => false,
            'total_rows' => 0,
            'active_rows' => 0,
            'expired_rows' => 0,
        ];
    }

    try {
        $pdo = tecdoc_db();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM tecdoc_api_cache')->fetchColumn();
        $active = (int) $pdo->query(
            'SELECT COUNT(*) FROM tecdoc_api_cache WHERE expires_at > NOW()'
        )->fetchColumn();

        return [
            'table_ready' => true,
            'total_rows' => $total,
            'active_rows' => $active,
            'expired_rows' => max(0, $total - $active),
        ];
    } catch (Throwable $e) {
        tecdoc_cache_log_error('stats failed: ' . $e->getMessage());
        return [
            'table_ready' => true,
            'total_rows' => 0,
            'active_rows' => 0,
            'expired_rows' => 0,
        ];
    }
}
