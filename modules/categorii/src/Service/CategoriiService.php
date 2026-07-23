<?php
declare(strict_types=1);

namespace Besoiu\Modules\Categorii\Service;

use Besoiu\Modules\Categorii\Model\CategoriiRepository;

final class CategoriiService
{
    public function __construct(
        private readonly CategoriiRepository $repository = new CategoriiRepository(),
    ) {
    }

    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function list(array $filters): array
    {
        return $this->repository->list($filters);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function add(array $data): array
    {
        return $this->repository->insert($data);
    }
}
