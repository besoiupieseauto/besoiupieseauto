<?php
declare(strict_types=1);

namespace Besoiu\Modules\__STUDLY__\Service;

use Besoiu\Modules\__STUDLY__\Model\__STUDLY__Repository;

final class __STUDLY__Service
{
    public function __construct(
        private readonly __STUDLY__Repository $repository = new __STUDLY__Repository(),
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
