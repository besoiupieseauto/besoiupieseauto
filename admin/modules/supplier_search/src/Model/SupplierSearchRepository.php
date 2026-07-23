<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Model;

use Besoiu\Core\AdvancedCRUD;

final class SupplierSearchRepository
{
    private const TABLE = 'supplier_search';

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function findPaginated(int $page = 1, int $perPage = 20): array
    {
        return AdvancedCRUD::selectPaginated(self::TABLE, '*', '', 'id DESC', $page, $perPage, []);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $rows = AdvancedCRUD::selectnew(self::TABLE, '*', 'WHERE id = :id', '', null, [':id' => $id]);

        return $rows[0] ?? null;
    }
}
