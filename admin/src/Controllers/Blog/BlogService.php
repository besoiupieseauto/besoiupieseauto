<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Blog;

use Evasystem\Core\Blog\BlogModel;

final class BlogService
{
    private BlogModel $model;

    public function __construct(?BlogModel $model = null)
    {
        $this->model = $model ?? new BlogModel();
    }

    public function getAll(): array
    {
        return $this->model->findAll();
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function getPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        return $this->model->findPaginated($page, $perPage, $filters);
    }

    public function getPublished(int $limit = 50): array
    {
        return $this->model->findPublished($limit);
    }

    public function getById(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function create(array $payload): bool
    {
        return $this->model->insert($payload);
    }

    public function update(int $id, array $payload): bool
    {
        return $this->model->update($id, $payload);
    }

    public function delete(int $id): bool
    {
        return $this->model->delete($id);
    }
}
