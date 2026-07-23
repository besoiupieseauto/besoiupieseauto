<?php declare(strict_types=1);

namespace Besoiu\Repositories;

use Besoiu\DTOs\BaseDTO;
use Config\Database;
use PDO;
use PDOException;

/**
 * Repository de bază pentru toate operațiunile cu baza de date
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
abstract class BaseRepository
{
    protected PDO $pdo;
    protected string $table;
    protected string $primaryKey = 'id';
    protected ?array $fillable = null;
    
    public function __construct(string $connection = 'default')
    {
        $this->pdo = Database::getDB($connection);
    }

    abstract protected function getTableName(): string;
    abstract protected function getDTOClass(): string;

    public function find(int|string $id): ?BaseDTO
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->getTableName()} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->mapRowToDTO($row) : null;
    }

    public function findBy(array $criteria, ?int $limit = null): array
    {
        $conditions = [];
        $params = [];
        
        foreach ($criteria as $field => $value) {
            $conditions[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $sql = "SELECT * FROM {$this->getTableName()}";
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return array_map([$this, 'mapRowToDTO'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(BaseDTO $dto): BaseDTO
    {
        $data = $this->filterFillable($dto->toArray());
        unset($data[$this->primaryKey]); // Remove PK for insert
        
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO {$this->getTableName()} (" . implode(',', $fields) . ") VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        $id = $this->pdo->lastInsertId();
        return $this->find($id);
    }

    public function update(int|string $id, BaseDTO $dto): ?BaseDTO
    {
        $data = $this->filterFillable($dto->toArray());
        unset($data[$this->primaryKey]);
        
        $setParts = [];
        foreach (array_keys($data) as $field) {
            $setParts[] = "{$field} = ?";
        }
        
        $sql = "UPDATE {$this->getTableName()} SET " . implode(', ', $setParts) . " WHERE {$this->primaryKey} = ?";
        $params = array_merge(array_values($data), [$id]);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $this->find($id);
    }

    public function delete(int|string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->getTableName()} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }

    protected function mapRowToDTO(array $row): BaseDTO
    {
        $dtoClass = $this->getDTOClass();
        return new $dtoClass($row);
    }

    protected function filterFillable(array $data): array
    {
        if ($this->fillable === null) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
}