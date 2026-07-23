<?php

declare(strict_types=1);

namespace Besoiu\Core\Comunicare;

use Besoiu\Core\AdvancedCRUD;
use Config\Database;
use PDO;
use Throwable;

final class ShopChatKnowledgeModel
{
    private const TABLE = 'shop_chat_knowledge';

    private const ALLOWED = [
        'randomn_id', 'entry_type', 'channel', 'title',
        'question_examples', 'expected_meaning', 'expected_action',
        'expected_intent', 'expected_response', 'metadata_json',
        'tags_json', 'priority', 'status',
    ];

    public static function tableExists(): bool
    {
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query("SHOW TABLES LIKE '" . self::TABLE . "'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function findAll(array $filters = []): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $where = ['status = 1'];
        $params = [];

        if (!empty($filters['entry_type'])) {
            $where[] = 'entry_type = :entry_type';
            $params[':entry_type'] = (string) $filters['entry_type'];
        }
        if (!empty($filters['channel']) && $filters['channel'] !== 'all') {
            $where[] = '(channel = :channel OR channel = \'both\')';
            $params[':channel'] = (string) $filters['channel'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(title LIKE :q OR expected_meaning LIKE :q OR expected_response LIKE :q OR question_examples LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);

        return AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            $sqlWhere,
            'priority DESC, updated_at DESC, title ASC',
            null,
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function findByRandomId(string $randomnId): ?array
    {
        if (!self::tableExists()) {
            return null;
        }

        $rows = AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE randomn_id = :rid',
            '',
            null,
            [':rid' => $randomnId]
        );

        return $rows[0] ?? null;
    }

    /** @param array<string, mixed> $payload */
    public function insert(array $payload): bool
    {
        return AdvancedCRUD::create(self::TABLE, $this->filter($payload));
    }

    /** @param array<string, mixed> $payload */
    public function updateByRandomId(string $randomnId, array $payload): bool
    {
        $pdo = Database::getDB();
        $data = $this->filter($payload);
        if ($data === []) {
            return false;
        }

        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = '`' . $col . '` = :' . $col;
            $params[':' . $col] = $val;
        }
        $params[':rid'] = $randomnId;
        $sql = 'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $sets) . ' WHERE randomn_id = :rid';
        $stmt = $pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function softDelete(string $randomnId): bool
    {
        return $this->updateByRandomId($randomnId, ['status' => 0]);
    }

    /** @return array<string, mixed>|null */
    public function findBySeedKey(string $seedKey): ?array
    {
        if (!self::tableExists() || $seedKey === '') {
            return null;
        }

        $pdo = Database::getDB();
        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::TABLE . '
             WHERE status = 1
               AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json, \'$.seed_key\')) = :sk
             LIMIT 1'
        );
        $stmt->execute([':sk' => $seedKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        if (!self::tableExists()) {
            return ['total' => 0, 'qa' => 0, 'buttons' => 0, 'rules' => 0];
        }

        $pdo = Database::getDB();
        $row = $pdo->query(
            'SELECT COUNT(*) AS total,
                    SUM(entry_type = \'qa_pair\') AS qa,
                    SUM(entry_type = \'button_doc\') AS buttons,
                    SUM(entry_type IN (\'system_rule\', \'rag_chunk\')) AS rules
             FROM ' . self::TABLE . ' WHERE status = 1'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'qa' => (int) ($row['qa'] ?? 0),
            'buttons' => (int) ($row['buttons'] ?? 0),
            'rules' => (int) ($row['rules'] ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function findActiveForRag(string $channel = 'external', int $limit = 80): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $pdo = Database::getDB();
        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::TABLE . '
             WHERE status = 1 AND (channel = :ch OR channel = \'both\')
             ORDER BY priority DESC, updated_at DESC
             LIMIT ' . $limit
        );
        $stmt->execute([':ch' => $channel]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countActive(): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        $pdo = Database::getDB();
        $n = $pdo->query('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE status = 1')->fetchColumn();

        return (int) $n;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function filter(array $payload): array
    {
        $out = array_intersect_key($payload, array_flip(self::ALLOWED));
        foreach (['question_examples', 'metadata_json', 'tags_json'] as $jsonCol) {
            if (!array_key_exists($jsonCol, $out)) {
                continue;
            }
            if (is_array($out[$jsonCol])) {
                $out[$jsonCol] = json_encode($out[$jsonCol], JSON_UNESCAPED_UNICODE);
            }
        }

        return $out;
    }
}
