<?php

declare(strict_types=1);

namespace Evasystem\Core\Blog;

use Evasystem\Core\AdvancedCRUD;

final class BlogModel
{
    private const TABLE = 'blog_posts';

    private const ALLOWED_COLUMNS = [
        'slug',
        'title',
        'tag',
        'excerpt',
        'body_html',
        'featured_image',
        'is_published',
        'published_at',
    ];

    public function findAll(): array
    {
        return AdvancedCRUD::selectnew(self::TABLE, '*', '', 'COALESCE(published_at, created_at) DESC, id DESC');
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function findPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $whereParts = [];
        $params = [];
        if (!empty($filters['q'])) {
            $whereParts[] = '(title LIKE :q OR tag LIKE :q OR slug LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }
        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        return AdvancedCRUD::selectPaginated(
            self::TABLE,
            '*',
            $where,
            'COALESCE(published_at, created_at) DESC, id DESC',
            $page,
            $perPage,
            $params
        );
    }

    public function findPublished(int $limit = 50): array
    {
        return AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE is_published = 1',
            'COALESCE(published_at, created_at) DESC, id DESC',
            (string) max(1, $limit)
        );
    }

    public function findById(int $id): ?array
    {
        $rows = AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE id = :id',
            '',
            null,
            [':id' => $id]
        );

        return $rows[0] ?? null;
    }

    public function findBySlug(string $slug): ?array
    {
        $rows = AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE slug = :slug',
            '',
            null,
            [':slug' => $slug]
        );

        return $rows[0] ?? null;
    }

    public function insert(array $payload): bool
    {
        return AdvancedCRUD::create(self::TABLE, $this->filterColumns($payload));
    }

    public function update(int $id, array $payload): bool
    {
        return AdvancedCRUD::update(
            self::TABLE,
            $this->filterColumns($payload),
            'WHERE id = ' . $id
        );
    }

    public function delete(int $id): bool
    {
        return AdvancedCRUD::delete(self::TABLE, 'WHERE id = ' . $id);
    }

    private function filterColumns(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::ALLOWED_COLUMNS));
    }
}
