<?php declare(strict_types=1);

namespace Besoiu\Repositories;

use Besoiu\DTOs\ProductDTO;

class ProductRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'produse';
    }

    protected function getDTOClass(): string
    {
        return ProductDTO::class;
    }

    public function findActiveProducts(?int $limit = null): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE status != '0'";
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return array_map([$this, 'mapRowToDTO'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByCode(string $code): ?ProductDTO
    {
        $result = $this->findBy(['pCode' => $code], 1);
        return $result[0] ?? null;
    }

    public function searchProducts(string $query, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE (pName LIKE ? OR pCode LIKE ? OR pOem LIKE ?) 
                AND status != '0' 
                ORDER BY pName 
                LIMIT ?";
        
        $searchTerm = "%{$query}%";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        
        return array_map([$this, 'mapRowToDTO'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}