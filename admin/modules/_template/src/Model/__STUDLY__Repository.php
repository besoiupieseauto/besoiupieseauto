<?php
declare(strict_types=1);

namespace Besoiu\Modules\__STUDLY__\Model;

use Config\Database;
use PDO;

final class __STUDLY__Repository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getDB();
    }

    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function list(array $filters): array
    {
        // TODO: înlocuiește cu tabela reală după migrare
        return ['items' => [], 'total' => 0];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function insert(array $data): array
    {
        // TODO: INSERT în tabela modulului
        return ['id' => 0, 'name' => (string) ($data['name'] ?? '')];
    }
}
