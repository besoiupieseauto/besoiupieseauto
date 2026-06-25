<?php

declare(strict_types=1);

namespace Evasystem\Core\Messages;

use Config\Database;
use Evasystem\Core\AdvancedCRUD;
use Evasystem\Core\Pagination;
use PDO;

/**
 * Acces la tabela `messages`, folosind AdvancedCRUD existent.
 */
final class MessagesModel
{
    private const TABLE = 'messages';
    private const PRIMARY_LOGICAL_KEY = 'randomn_id';

    private const ALLOWED_COLUMNS = [
        'randomn_id',
        'conversation_id',
        'name',
        'email',
        'phone',
        'subject',
        'message_body',
        'direction',
        'message_status',
        'channel',
        'external_conversation_id',
        'external_message_id',
        'delivery_status',
        'bot_status',
        'source_url',
        'assigned_bot',
        'is_read',
        'status',
    ];

    /** @return array<int, array<string, mixed>> */
    public function findAll(): array
    {
        return AdvancedCRUD::selectnew(self::TABLE, '*', '', 'id DESC');
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function findPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $whereParts = [];
        $params = [];
        if (!empty($filters['q'])) {
            $whereParts[] = '(name LIKE :q OR email LIKE :q OR phone LIKE :q OR subject LIKE :q OR message_body LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }
        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        return AdvancedCRUD::selectPaginated(self::TABLE, '*', $where, 'id DESC', $page, $perPage, $params);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function findConversationsPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $meta = Pagination::normalize($page, $perPage);
        $pdo = Database::getDB();
        $whereParts = [];
        $params = [];

        if (!empty($filters['q'])) {
            $whereParts[] = '(m.name LIKE :q OR m.email LIKE :q OR m.phone LIKE :q OR m.subject LIKE :q OR m.message_body LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $countSql = 'SELECT COUNT(*) FROM (
            SELECT COALESCE(conversation_id, randomn_id) AS conv_key
            FROM `' . self::TABLE . '` m
            ' . $where . '
            GROUP BY conv_key
        ) conv_count';
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT m.* FROM `' . self::TABLE . '` m
            INNER JOIN (
                SELECT MAX(id) AS max_id
                FROM `' . self::TABLE . '`
                GROUP BY COALESCE(conversation_id, randomn_id)
            ) latest ON m.id = latest.max_id
            ' . $where . '
            ORDER BY m.id DESC
            LIMIT ' . (int) $meta['offset'] . ', ' . (int) $meta['per_page'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
    }

    /** @return array<int, array<string, mixed>> */
    public function findRecent(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        return AdvancedCRUD::selectnew(self::TABLE, '*', '', 'id DESC', (string) $limit);
    }

    /** @return array<string, mixed>|null */
    public function findByRandomId(int $randomId): ?array
    {
        $rows = AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = :random_id',
            '',
            null,
            [':random_id' => $randomId]
        );
        return $rows[0] ?? null;
    }

    /** @return array<int, array<string, mixed>> */
    public function findByConversationId(int $conversationId): array
    {
        return AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE conversation_id = :conversation_id',
            'id ASC',
            null,
            [':conversation_id' => $conversationId]
        );
    }

    public function existsByRandomId(int $randomId): bool
    {
        return $this->findByRandomId($randomId) !== null;
    }

    /** @param array<string, string|int|null> $payload */
    public function insert(array $payload): bool
    {
        return AdvancedCRUD::create(self::TABLE, $this->filterAllowedColumns($payload));
    }

    /** @param array<string, string|int|null> $payload */
    public function updateByRandomId(int $randomId, array $payload): bool
    {
        return AdvancedCRUD::update(
            self::TABLE,
            $this->filterAllowedColumns($payload),
            'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = ' . (int) $randomId
        );
    }

    public function deleteByRandomId(int $randomId): bool
    {
        return AdvancedCRUD::delete(self::TABLE, 'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = ' . (int) $randomId);
    }

    /** @param array<string, string|int|null> $payload */
    private function filterAllowedColumns(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::ALLOWED_COLUMNS));
    }
}
