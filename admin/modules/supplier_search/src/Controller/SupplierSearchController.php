<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Controller;

use Besoiu\Modules\SupplierSearch\Service\SupplierSearchService;

final class SupplierSearchController
{
    public function __construct(
        private readonly SupplierSearchService $service = new SupplierSearchService(),
    ) {
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function list(array $input = []): array
    {
        return $this->service->list($input);
    }

    /** @return array<string, mixed>|null */
    public function find(array $input): ?array
    {
        $id = (int) ($input['id'] ?? $input['randomn_id'] ?? 0);

        return $id > 0 ? $this->service->findById($id) : null;
    }
}
