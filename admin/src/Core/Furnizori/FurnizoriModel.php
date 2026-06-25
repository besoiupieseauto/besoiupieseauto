<?php

declare(strict_types=1);

namespace Evasystem\Core\Furnizori;

use Evasystem\Core\AdvancedCRUD;

/**
 * Acces la tabela `furnizori`.
 */
final class FurnizoriModel
{
    private const TABLE = 'furnizori';
    private const PRIMARY_LOGICAL_KEY = 'randomn_id';

    private const ALLOWED_COLUMNS = [
        'randomn_id',
        'name',
        'code',
        'status',
        'price_markup_type',
        'price_markup_value',
        'price_round_to',
        'price_min_margin',
        'adaos_template_rule_id',
        'stock_zero_mode',
        'scan_include_zero_stock',
        'scan_skip_unavailable',
        'connection_type',
        'scan_interval_minutes',
        'scan_schedule_mode',
        'scan_schedule_time',
        'scan_window_start',
        'scan_window_end',
        'scan_auto_enabled',
        'conn_host',
        'conn_port',
        'conn_username',
        'conn_password',
        'conn_remote_path',
        'conn_passive',
        'conn_email',
        'conn_email_inbox',
        'conn_imap_host',
        'conn_imap_port',
        'conn_email_password',
        'api_base_url',
        'api_token',
        'last_scan_at',
        'last_scan_status',
        'last_scan_message',
        'last_test_status',
        'last_test_message',
        'last_test_at',
        'products_count',
        'notes',
    ];

    /** @return array<int, array<string, mixed>> */
    public function findAll(): array
    {
        $rows = AdvancedCRUD::selectnew(self::TABLE, '*', '', 'id DESC');

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function findPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $whereParts = [];
        $params = [];

        if (!empty($filters['q'])) {
            $whereParts[] = '(name LIKE :q OR code LIKE :q OR connection_type LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }
        if (!empty($filters['status'])) {
            $whereParts[] = 'status = :status';
            $params[':status'] = (string) $filters['status'];
        }

        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        $result = AdvancedCRUD::selectPaginated(self::TABLE, '*', $where, 'id DESC', $page, $perPage, $params);
        $result['items'] = array_map(fn (array $row): array => $this->normalizeRow($row), $result['items']);

        return $result;
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $rows = AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE UPPER(TRIM(code)) = :code',
            '',
            null,
            [':code' => function_exists('mb_strtoupper') ? mb_strtoupper($code, 'UTF-8') : strtoupper($code)]
        );

        return isset($rows[0]) ? $this->normalizeRow($rows[0]) : null;
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

        if (!empty($rows[0])) {
            return $this->normalizeRow($rows[0]);
        }

        // Fallback: inregistrari vechi fara randomn_id (doar coloana id)
        $legacyRows = AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE id = :legacy_id',
            '',
            null,
            [':legacy_id' => $randomId]
        );

        return isset($legacyRows[0]) ? $this->normalizeRow($legacyRows[0]) : null;
    }

    public function existsByRandomId(int $randomId): bool
    {
        return $this->findByRandomId($randomId) !== null;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    public function normalizeRow(array $row): array
    {
        if (empty($row['randomn_id']) && !empty($row['id'])) {
            $row['randomn_id'] = 600000 + (int) $row['id'];
        }

        if (empty($row['status'])) {
            $row['status'] = 'active';
        }

        if (empty($row['connection_type'])) {
            $row['connection_type'] = 'ftp';
        }

        if (empty($row['stock_zero_mode'])) {
            $row['stock_zero_mode'] = 'full';
        }

        if (!isset($row['scan_interval_minutes']) || $row['scan_interval_minutes'] === '') {
            $row['scan_interval_minutes'] = 60;
        }

        if (empty($row['scan_schedule_mode'])) {
            $row['scan_schedule_mode'] = 'interval';
        }

        if (empty($row['scan_schedule_time'])) {
            $row['scan_schedule_time'] = '06:00';
        }

        if (empty($row['scan_window_start'])) {
            $row['scan_window_start'] = '08:00';
        }

        if (empty($row['scan_window_end'])) {
            $row['scan_window_end'] = '18:00';
        }

        if (!isset($row['scan_auto_enabled']) || $row['scan_auto_enabled'] === '') {
            $row['scan_auto_enabled'] = 1;
        }

        return $row;
    }

    /** @param array<string, string|int|float|null> $payload */
    public function insert(array $payload): bool
    {
        return AdvancedCRUD::create(self::TABLE, $this->filterAllowedColumns($payload));
    }

    /** @param array<string, string|int|float|null> $payload */
    public function updateByRandomId(int $randomId, array $payload): bool
    {
        $where = $this->buildWhereForIdentifier($randomId);

        return AdvancedCRUD::update(
            self::TABLE,
            $this->filterAllowedColumns($payload),
            $where
        );
    }

    public function deleteByRandomId(int $randomId): bool
    {
        return AdvancedCRUD::delete(self::TABLE, $this->buildWhereForIdentifier($randomId));
    }

    /** @param array<int, string> $allowedCodes */
    public function deleteNotInCodes(array $allowedCodes): int
    {
        $allowedCodes = array_values(array_filter(array_map(
            static fn ($code): string => function_exists('mb_strtoupper')
                ? mb_strtoupper(trim((string) $code), 'UTF-8')
                : strtoupper(trim((string) $code)),
            $allowedCodes
        )));

        if ($allowedCodes === []) {
            return 0;
        }

        $pdo = \Config\Database::getDB();
        $placeholders = implode(',', array_fill(0, count($allowedCodes), '?'));
        $stmt = $pdo->prepare(
            'DELETE FROM `' . self::TABLE . '`
             WHERE UPPER(TRIM(COALESCE(code, \'\'))) NOT IN (' . $placeholders . ')'
        );
        $stmt->execute($allowedCodes);

        return $stmt->rowCount();
    }

    /** @param array<string, string|int|float|null> $payload */
    public function upsertByCode(string $code, array $payload): bool
    {
        $existing = $this->findByCode($code);
        if ($existing !== null) {
            $randomId = (int) ($existing['randomn_id'] ?? 0);

            return $randomId > 0 && $this->updateByRandomId($randomId, $payload);
        }

        if (empty($payload['randomn_id'])) {
            return false;
        }

        return $this->insert($payload);
    }

    private function buildWhereForIdentifier(int $identifier): string
    {
        $existing = $this->findByRandomId($identifier);
        if ($existing !== null && !empty($existing['randomn_id'])) {
            return 'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = ' . (int) $existing['randomn_id'];
        }

        return 'WHERE id = ' . (int) $identifier;
    }

    /** @param array<string, string|int|float|null> $payload */
    private function filterAllowedColumns(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::ALLOWED_COLUMNS));
    }
}
