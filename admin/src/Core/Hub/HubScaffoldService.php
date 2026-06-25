<?php

declare(strict_types=1);

namespace Evasystem\Core\Hub;

use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\PersistenceException;

final class HubScaffoldService
{
    private HubScaffoldModel $model;

    public function __construct(HubScaffoldModel $model)
    {
        $this->model = $model;
    }

    /** @param array<string, mixed> $payload @return array{id: int} */
    public function createRecord(array $payload): array
    {
        if (!$this->model->insert($payload)) {
            throw new PersistenceException('Înregistrarea nu a putut fi salvată.');
        }

        $pdo = \Config\Database::getDB();
        $id = (int) $pdo->lastInsertId();

        return ['id' => $id];
    }

    /** @param array<string, mixed> $payload @return array{id: int} */
    public function updateRecord(int $id, array $payload): array
    {
        $this->ensureExists($id);

        if (!$this->model->updateById($id, $payload)) {
            throw new PersistenceException('Înregistrarea nu a putut fi actualizată.');
        }

        return ['id' => $id];
    }

    public function changeStatus(int $id, int $status): void
    {
        $this->ensureExists($id);

        if (!$this->model->updateById($id, ['status' => $status])) {
            throw new PersistenceException('Statusul nu a putut fi actualizat.');
        }
    }

    public function deleteRecord(int $id): void
    {
        $this->ensureExists($id);

        if (!$this->model->deleteById($id)) {
            throw new PersistenceException('Înregistrarea nu a putut fi ștearsă.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listAll(): array
    {
        return $this->model->findAll();
    }

    /** @param array<string, mixed> $params @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function listPaginated(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 10)));

        return $this->model->findPaginated($page, $perPage, $params);
    }

    private function ensureExists(int $id): void
    {
        if (!$this->model->existsById($id)) {
            throw new NotFoundException('Înregistrarea cerută nu există.');
        }
    }
}
