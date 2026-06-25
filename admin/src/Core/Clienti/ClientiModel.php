<?php

declare(strict_types=1);

namespace Evasystem\Core\Clienti;

use Evasystem\Core\AdvancedCRUD;

/**
 * Acces la tabela `clienti`.
 *
 * Modelul acceptă doar coloane cunoscute și folosește metodele existente din
 * AdvancedCRUD, fără modificări în wrapper-ul PDO al proiectului.
 */
final class ClientiModel
{
    private const TABLE = 'clienti';
    private const PRIMARY_LOGICAL_KEY = 'randomn_id';

    private const ALLOWED_COLUMNS = [
        'randomn_id',
        'name',
        'email',
        'phone',
        'city',
        'address',
        'status',
        'total_orders',
        'total_paid',
        'preferred_courier',
        'notes',
    ];

    /**
     * Returnează toți clienții.
     *
     * @return array<int, array<string, string|null>>
     */
    public function findAll(): array
    {
        $rows = AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            '',
            'id DESC'
        );

        return array_map([$this, 'normalizeDatabaseRow'], $rows);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function findPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $whereParts = [];
        $params = [];

        if (!empty($filters['q'])) {
            $whereParts[] = '(name LIKE :q OR email LIKE :q OR phone LIKE :q OR city LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        $result = AdvancedCRUD::selectPaginated(self::TABLE, '*', $where, 'id DESC', $page, $perPage, $params);
        $result['items'] = array_map([$this, 'normalizeDatabaseRow'], $result['items']);

        return $result;
    }

    /**
     * Caută un client după identificatorul logic.
     *
     * @return array<string, string|null>|null
     */
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

        return isset($rows[0]) ? $this->normalizeDatabaseRow($rows[0]) : null;
    }

    /**
     * Verifică existența unui client.
     */
    public function existsByRandomId(int $randomId): bool
    {
        return $this->findByRandomId($randomId) !== null;
    }

    /**
     * Inserează un client nou.
     *
     * @param array<string, string|int|float|null> $payload
     */
    public function insert(array $payload): bool
    {
        return AdvancedCRUD::create(self::TABLE, $this->filterAllowedColumns($this->mapPayloadToDatabase($payload)));
    }

    /**
     * Actualizează clientul după randomn_id.
     *
     * @param array<string, string|int|float|null> $payload
     */
    public function updateByRandomId(int $randomId, array $payload): bool
    {
        return AdvancedCRUD::update(
            self::TABLE,
            $this->filterAllowedColumns($this->mapPayloadToDatabase($payload)),
            'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = ' . (int) $randomId
        );
    }

    /**
     * Șterge clientul după randomn_id.
     */
    public function deleteByRandomId(int $randomId): bool
    {
        return AdvancedCRUD::delete(
            self::TABLE,
            'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = ' . (int) $randomId
        );
    }

    /**
     * Actualizează doar statusul clientului.
     */
    public function updateStatusByRandomId(int $randomId, string $status): bool
    {
        return $this->updateByRandomId($randomId, ['status' => $status]);
    }

    /**
     * Mapează payload-ul UI pe coloanele reale ale tabelei existente.
     *
     * @param array<string, string|int|float|null> $payload
     * @return array<string, string|int|float|null>
     */
    private function mapPayloadToDatabase(array $payload): array
    {
        if (isset($payload['client_name'])) {
            $payload['name'] = $payload['client_name'];
            unset($payload['client_name']);
        }

        if (isset($payload['status'])) {
            $payload['status'] = $this->mapStatusToDatabase((string) $payload['status']);
        }

        return $payload;
    }

    /**
     * Normalizează rândul DB pentru pagina HTML.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeDatabaseRow(array $row): array
    {
        $row['client_name'] = $row['name'] ?? '';
        $row['status'] = $this->mapStatusFromDatabase($row['status'] ?? null);

        return $row;
    }

    /**
     * Păstrează compatibilitatea cu statusul tinyint existent.
     */
    private function mapStatusToDatabase(string $status): int
    {
        return in_array($status, ['activ', 'vip', 'nou'], true) ? 1 : 0;
    }

    /**
     * Transformă statusul existent într-un text pentru UI.
     *
     * @param mixed $status
     */
    private function mapStatusFromDatabase($status): string
    {
        return ((int) $status) === 1 ? 'activ' : 'inactiv';
    }

    /**
     * Elimină orice coloană necunoscută înainte de persistență.
     *
     * @param array<string, string|int|float|null> $payload
     * @return array<string, string|int|float|null>
     */
    private function filterAllowedColumns(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::ALLOWED_COLUMNS));
    }
}
