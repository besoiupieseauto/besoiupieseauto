<?php

declare(strict_types=1);

namespace Evasystem\Core\Marketplace;

use Evasystem\Core\AdvancedCRUD;

/**
 * Acces la tabela `marketplace`, fara modificari in AdvancedCRUD.
 */
final class MarketplaceModel
{
    private const TABLE = 'marketplace';
    private const PRIMARY_LOGICAL_KEY = 'randomn_id';

    private const ALLOWED_COLUMNS = [
        'randomn_id',
        'name',
        'platform',
        'account_name',
        'account_email',
        'api_token',
        'token_status',
        'token_plan',
        'starts_at',
        'ends_at',
        'api_base_url',
        'webhook_url',
        'bl_inventory_id',
        'field_mapping',
        'sync_mode',
        'products_synced',
        'requests_today',
        'offers_sent',
        'errors_count',
        'last_sync_at',
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
