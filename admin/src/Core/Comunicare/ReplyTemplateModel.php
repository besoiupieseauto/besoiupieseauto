<?php

declare(strict_types=1);

namespace Evasystem\Core\Comunicare;

use Config\Database;
use Evasystem\Core\AdvancedCRUD;
use PDO;
use Throwable;

final class ReplyTemplateModel
{
    private const TABLE = 'reply_templates';

    private const ALLOWED = [
        'randomn_id', 'title', 'slug', 'category', 'channel',
        'body_text', 'body_html', 'is_quick', 'use_count', 'status',
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

    /** @return array<int, array<string, mixed>> */
    public function findAll(array $filters = []): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $where = ['status = 1'];
        $params = [];

        if (isset($filters['is_quick'])) {
            $where[] = 'is_quick = :is_quick';
            $params[':is_quick'] = (int) $filters['is_quick'];
        }
        if (!empty($filters['channel']) && $filters['channel'] !== 'all') {
            $where[] = '(channel = :channel OR channel = \'all\')';
            $params[':channel'] = (string) $filters['channel'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'category = :category';
            $params[':category'] = (string) $filters['category'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(title LIKE :q OR body_text LIKE :q OR slug LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);

        return AdvancedCRUD::selectnew(self::TABLE, '*', $sqlWhere, 'is_quick DESC, use_count DESC, title ASC', null, $params);
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

    public function incrementUseCount(string $randomnId): void
    {
        if (!self::tableExists()) {
            return;
        }

        $pdo = Database::getDB();
        $stmt = $pdo->prepare('UPDATE ' . self::TABLE . ' SET use_count = use_count + 1 WHERE randomn_id = ?');
        $stmt->execute([$randomnId]);
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        if (!self::tableExists()) {
            return ['total' => 0, 'quick' => 0, 'uses' => 0];
        }

        $pdo = Database::getDB();
        $row = $pdo->query(
            'SELECT COUNT(*) AS total,
                    SUM(is_quick) AS quick,
                    COALESCE(SUM(use_count), 0) AS uses
             FROM ' . self::TABLE . ' WHERE status = 1'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'quick' => (int) ($row['quick'] ?? 0),
            'uses' => (int) ($row['uses'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function filter(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::ALLOWED));
    }
}
