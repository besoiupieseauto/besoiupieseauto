<?php

declare(strict_types=1);

namespace Evasystem\Core\Website;

use Evasystem\Core\AdvancedCRUD;

final class SitePagesModel
{
    private const TABLE = 'site_pages';

    private const ALLOWED_COLUMNS = [
        'slug',
        'label',
        'title',
        'meta_description',
        'hero_label',
        'hero_title',
        'hero_subtitle',
        'body_html',
        'sections_json',
        'faq_json',
        'cta_json',
        'is_active',
        'sort_order',
    ];

    public function findAll(): array
    {
        return AdvancedCRUD::selectnew(self::TABLE, '*', '', 'sort_order ASC, id ASC');
    }

    public function findAllIncludingInactive(): array
    {
        return $this->findAll();
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        $where = 'WHERE slug = :slug';
        $params = [':slug' => $slug];
        if ($excludeId !== null && $excludeId > 0) {
            $where .= ' AND id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $rows = AdvancedCRUD::selectnew(self::TABLE, 'id', $where, 'LIMIT 1', null, $params);

        return $rows !== [];
    }

    public function create(array $payload): int
    {
        $data = $this->filterColumns($payload);
        if ($data === []) {
            throw new \InvalidArgumentException('Date pagină lipsă.');
        }

        $ok = AdvancedCRUD::create(self::TABLE, $data);
        if (!$ok) {
            return 0;
        }

        $pdo = \Config\Database::getDB();
        $id = (int) $pdo->lastInsertId();

        return $id > 0 ? $id : 0;
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        return AdvancedCRUD::delete(self::TABLE, 'WHERE id = ' . $id);
    }

    public function setActive(int $id, bool $active): bool
    {
        return $this->update($id, ['is_active' => $active ? 1 : 0]);
    }

    public function nextSortOrder(): int
    {
        $rows = AdvancedCRUD::selectnew(self::TABLE, 'MAX(sort_order) AS max_sort', '', '');
        $max = (int) ($rows[0]['max_sort'] ?? 0);

        return max(100, $max + 1);
    }

    public function findBySlug(string $slug): ?array
    {
        $rows = AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE slug = :slug AND is_active = 1',
            '',
            null,
            [':slug' => $slug]
        );

        return $rows[0] ?? null;
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

    public function update(int $id, array $payload): bool
    {
        return AdvancedCRUD::update(
            self::TABLE,
            $this->filterColumns($payload),
            'WHERE id = ' . $id
        );
    }

    private function filterColumns(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::ALLOWED_COLUMNS));
    }
}
