<?php

declare(strict_types=1);

namespace Evasystem\Core;

/**
 * Paginare standard admin — 10 înregistrări / pagină.
 */
final class Pagination
{
    public const DEFAULT_PER_PAGE = 10;
    public const MAX_PER_PAGE = 100;

    /** @return array{page:int,per_page:int,offset:int,limit:int} */
    public static function normalize(?int $page, ?int $perPage = null): array
    {
        $page = max(1, (int) ($page ?? 1));
        $perPage = max(1, min(self::MAX_PER_PAGE, (int) ($perPage ?? self::DEFAULT_PER_PAGE)));

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'limit' => $perPage,
        ];
    }

    /** @param array<int, mixed> $items */
    public static function envelope(array $items, int $total, int $page, int $perPage): array
    {
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));

        return [
            'items' => $items,
            'total' => $total,
            'page' => min($page, $totalPages),
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }
}
