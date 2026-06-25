<?php
declare(strict_types=1);

namespace Evasystem\Core\AdaosComercial;

use Config\Database;
use PDO;

final class AdaosComercialModel
{
    private const TABLE = 'adaos_comercial_rules';

    private PDO $pdo;

    private array $allowedColumns = [
        'name',
        'category_filter',
        'brand_filter',
        'price_min',
        'price_max',
        'adjustment_type',
        'adjustment_value',
        'round_to',
        'priority',
        'note',
        'is_active',
    ];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getDB();
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM ' . self::TABLE . ' ORDER BY priority ASC, id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insert(array $payload): int
    {
        $payload = $this->filterColumns($payload);
        $fields = array_keys($payload);

        $sql = 'INSERT INTO ' . self::TABLE .
            ' (`' . implode('`,`', $fields) . '`) VALUES (:' . implode(',:', $fields) . ')';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($payload);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): bool
    {
        $payload = $this->filterColumns($payload);
        if ($payload === []) {
            return true;
        }

        $sets = [];
        foreach (array_keys($payload) as $field) {
            $sets[] = "`{$field}` = :{$field}";
        }
        $payload['id'] = $id;

        $sql = 'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($payload);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');

        return $stmt->execute([':id' => $id]);
    }

    private function filterColumns(array $payload): array
    {
        return array_intersect_key($payload, array_flip($this->allowedColumns));
    }
}
