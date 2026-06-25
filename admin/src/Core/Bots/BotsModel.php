<?php

declare(strict_types=1);

namespace Evasystem\Core\Bots;

use Evasystem\Core\AdvancedCRUD;

/**
 * Acces la tabela `bots`, fără modificări în AdvancedCRUD.
 */
final class BotsModel
{
    private const TABLE = 'bots';
    private const PRIMARY_LOGICAL_KEY = 'randomn_id';

    private const ALLOWED_COLUMNS = [
        'randomn_id',
        'name',
        'email',
        'phone',
        'bot_type',
        'channel',
        'token_value',
        'token_status',
        'token_plan',
        'starts_at',
        'ends_at',
        'requests_limit',
        'requests_used',
        'webhook_url',
        'test_url',
        'last_test_status',
        'last_test_message',
        'last_test_at',
        'notes',
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
            $whereParts[] = '(name LIKE :q OR channel LIKE :q OR bot_type LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }
        if (!empty($filters['channel'])) {
            $whereParts[] = 'channel = :channel';
            $params[':channel'] = (string) $filters['channel'];
        }
        if (!empty($filters['token_status'])) {
            $whereParts[] = 'token_status = :token_status';
            $params[':token_status'] = (string) $filters['token_status'];
        }
        if (!empty($filters['token_plan'])) {
            $whereParts[] = 'token_plan = :token_plan';
            $params[':token_plan'] = (string) $filters['token_plan'];
        }
        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        return AdvancedCRUD::selectPaginated(self::TABLE, '*', $where, 'id DESC', $page, $perPage, $params);
    }

    /** @return array<string, mixed>|null */
    public function findByRandomId(int $randomId): ?array
    {
        $rows = AdvancedCRUD::selectnew(self::TABLE, '*', 'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = :random_id', '', null, [':random_id' => $randomId]);
        return $rows[0] ?? null;
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
        return AdvancedCRUD::update(self::TABLE, $this->filterAllowedColumns($payload), 'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = ' . (int) $randomId);
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
