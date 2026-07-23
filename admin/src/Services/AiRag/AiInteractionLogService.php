<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Config\Database;
use PDO;
use Throwable;

/**
 * Jurnal audit complet — prompt, input, output, model, latență, acțiune umană.
 */
final class AiInteractionLogService
{
    private PDO $pdo;
    private string $jsonlPath;

    public function __construct(?PDO $pdo = null, ?string $projectRoot = null)
    {
        $root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $dir = $root . '/admin/storage/ai_rag';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->jsonlPath = $dir . '/interactions.jsonl';
        $this->pdo = $pdo ?? $this->resolvePdo($root);
        $this->ensureTable();
    }

    private function resolvePdo(string $root): PDO
    {
        try {
            if (\Config\Database::hasConnection()) {
                return \Config\Database::getDB();
            }
        } catch (Throwable) {
            // init below
        }
        $configPath = $root . '/admin/config/config.php';
        if (is_file($configPath)) {
            $config = require $configPath;
            \Config\Database::getInstance(
                (string) ($config['db_host'] ?? '127.0.0.1'),
                (string) ($config['db_name'] ?? ''),
                (string) ($config['db_user'] ?? ''),
                (string) ($config['db_pass'] ?? '')
            );

            return \Config\Database::getDB();
        }

        throw new \RuntimeException('Config BD lipsă pentru ai_interaction_logs.');
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS ai_interaction_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                module_id VARCHAR(64) NOT NULL,
                action VARCHAR(64) NOT NULL DEFAULT 'propose',
                input_summary VARCHAR(500) NOT NULL DEFAULT '',
                input_json LONGTEXT NULL,
                prompt_text LONGTEXT NULL,
                output_raw LONGTEXT NULL,
                output_json LONGTEXT NULL,
                model VARCHAR(120) NOT NULL DEFAULT '',
                confidence DECIMAL(5,4) NULL,
                latency_ms INT NOT NULL DEFAULT 0,
                human_action VARCHAR(32) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'proposed',
                user_id INT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ai_log_module (module_id, created_at),
                KEY idx_ai_log_status (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @param array<string, mixed> $row */
    public function append(array $row): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_interaction_logs
            (module_id, action, input_summary, input_json, prompt_text, output_raw, output_json, model, confidence, latency_ms, human_action, status, user_id)
            VALUES (:module_id, :action, :input_summary, :input_json, :prompt_text, :output_raw, :output_json, :model, :confidence, :latency_ms, :human_action, :status, :user_id)'
        );
        $stmt->execute([
            ':module_id' => (string) ($row['module_id'] ?? ''),
            ':action' => (string) ($row['action'] ?? 'propose'),
            ':input_summary' => mb_substr((string) ($row['input_summary'] ?? ''), 0, 500),
            ':input_json' => isset($row['input_json']) ? json_encode($row['input_json'], JSON_UNESCAPED_UNICODE) : null,
            ':prompt_text' => (string) ($row['prompt_text'] ?? ''),
            ':output_raw' => (string) ($row['output_raw'] ?? ''),
            ':output_json' => isset($row['output_json']) ? json_encode($row['output_json'], JSON_UNESCAPED_UNICODE) : null,
            ':model' => (string) ($row['model'] ?? ''),
            ':confidence' => isset($row['confidence']) ? (float) $row['confidence'] : null,
            ':latency_ms' => (int) ($row['latency_ms'] ?? 0),
            ':human_action' => $row['human_action'] ?? null,
            ':status' => (string) ($row['status'] ?? 'proposed'),
            ':user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        $line = json_encode(array_merge($row, ['id' => $id, 'ts' => date('c')]), JSON_UNESCAPED_UNICODE);
        if ($line !== false) {
            @file_put_contents($this->jsonlPath, $line . "\n", FILE_APPEND | LOCK_EX);
        }

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 50, ?string $moduleId = null): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM ai_interaction_logs';
        $params = [];
        if ($moduleId !== null && $moduleId !== '') {
            $sql .= ' WHERE module_id = :mid';
            $params[':mid'] = $moduleId;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'mapRow'], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function recentSince(int $cutoffUnixTs, int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));
        $cutoff = date('Y-m-d H:i:s', max(0, $cutoffUnixTs));
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ai_interaction_logs WHERE created_at >= :cutoff ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute([':cutoff' => $cutoff]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'mapRow'], $rows);
    }

    public function recordHumanAction(int $logId, string $action, ?int $userId = null): bool
    {
        if ($logId <= 0 || $action === '') {
            return false;
        }
        $status = $action === 'confirmed' ? 'accepted' : ($action === 'rejected' ? 'rejected' : 'reviewed');
        $stmt = $this->pdo->prepare(
            'UPDATE ai_interaction_logs SET human_action = :ha, status = :st, user_id = COALESCE(:uid, user_id) WHERE id = :id'
        );

        return $stmt->execute([
            ':ha' => $action,
            ':st' => $status,
            ':uid' => $userId,
            ':id' => $logId,
        ]);
    }

    /** @param array<string, mixed> $fields */
    public function patchEntry(int $logId, array $fields): bool
    {
        if ($logId <= 0 || $fields === []) {
            return false;
        }

        $allowed = ['status', 'output_raw', 'output_json', 'human_action', 'latency_ms'];
        $sets = [];
        $params = [':id' => $logId];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }
            if ($key === 'output_json' && is_array($fields[$key])) {
                $params[':output_json'] = json_encode($fields[$key], JSON_UNESCAPED_UNICODE);
            } else {
                $params[':' . $key] = $fields[$key];
            }
            $sets[] = $key . ' = :' . $key;
        }

        if ($sets === []) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE ai_interaction_logs SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );

        return $stmt->execute($params);
    }

    /** @return array<string, mixed> */
    public function todayStats(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'accepted' OR human_action = 'confirmed' THEN 1 ELSE 0 END) AS accepted,
                SUM(CASE WHEN status = 'rejected' OR human_action = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                AVG(latency_ms) AS avg_latency_ms
             FROM ai_interaction_logs
             WHERE created_at >= CURDATE()"
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'accepted' => (int) ($row['accepted'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
            'avg_latency_ms' => (int) round((float) ($row['avg_latency_ms'] ?? 0)),
        ];
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): array
    {
        foreach (['input_json', 'output_json'] as $key) {
            if (!empty($row[$key]) && is_string($row[$key])) {
                $decoded = json_decode($row[$key], true);
                if (is_array($decoded)) {
                    $row[$key] = $decoded;
                }
            }
        }

        return $row;
    }
}
