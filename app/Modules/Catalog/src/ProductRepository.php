<?php

declare(strict_types=1);

namespace Storefront\Modules\Catalog;

use Storefront\Core\Repository\AbstractRepository;

/** Repository produse live (status activ) — BD default. */
final class ProductRepository extends AbstractRepository
{
    public function countActive(): int
    {
        $stmt = $this->pdo()->query("SELECT COUNT(*) FROM produse WHERE status <> '0'");
        return (int) ($stmt ? $stmt->fetchColumn() : 0);
    }
}
