<?php

declare(strict_types=1);

namespace Storefront\Modules\Product;

use Storefront\Core\Repository\AbstractRepository;

final class ProductRepository extends AbstractRepository
{
    public function findByPublicId(string $randomnId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT * FROM produse WHERE randomn_id = :id AND status <> '0' LIMIT 1"
        );
        $stmt->execute([':id' => $randomnId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}
