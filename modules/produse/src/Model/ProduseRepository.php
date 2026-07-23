<?php
declare(strict_types=1);

namespace Besoiu\Modules\Produse\Model;

use Besoiu\Services\Products\ProduseService as CoreProduseService;

final class ProduseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function list(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 10)));
        $listFilters = [];
        foreach (['q', 'category', 'subcategory', 'marca', 'brand', 'supplier', 'image', 'markup', 'status', 'origin', 'sort'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $listFilters[$key] = $value;
            }
        }

        $result = (new CoreProduseService())->getProdusesPaginated($page, $perPage, $listFilters);

        return [
            'items' => $result['items'] ?? [],
            'total' => (int) ($result['total'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function insert(array $data): array
    {
        throw new \Besoiu\Exceptions\ValidationException(
            'Adaugarea produselor se face prin /admin/crudproduse sau formularul /admin/addproduse.'
        );
    }
}
