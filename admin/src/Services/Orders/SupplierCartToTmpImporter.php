<?php

declare(strict_types=1);

namespace Evasystem\Services\Orders;

use PDO;
use RuntimeException;

/**
 * Import linii din coș furnizori → produse ERP + tmp (port addProductsToDbAndTemp).
 */
final class SupplierCartToTmpImporter
{
    public function __construct(
        private readonly ?PDO $pdo,
        private readonly OrderTmpService $tmpService,
        private readonly OrderDeliveryColorMapper $colorMapper = new OrderDeliveryColorMapper(),
    ) {
    }

    /** @param array<int, array<string, mixed>> $items @return array{imported:int,product_ids:array<int,int>} */
    public function importItems(string $sessionId, array $items, bool $clearTmpFirst = true): array
    {
        if ($this->pdo === null) {
            throw new RuntimeException('Conexiune legacy indisponibilă (LEGACY_DB_*).');
        }

        if ($clearTmpFirst) {
            $this->tmpService->clear($sessionId);
        }

        $imported = 0;
        $productIds = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productId = $this->upsertProduse($item);
            $this->tmpService->deleteByProduct($sessionId, $productId);

            foreach ($this->expandItemForTmpRows($item) as $row) {
                $this->tmpService->addProduct($sessionId, [
                    'id_produs' => $productId,
                    'cantitate' => $row['qty'],
                    'pret' => (float) ($item['price'] ?? 0),
                    'furnizor' => $row['furnizor'],
                    'culoare' => $row['culoare'],
                ]);
            }

            $imported++;
            $productIds[] = $productId;
        }

        return ['imported' => $imported, 'product_ids' => $productIds];
    }

    /** @param array<string, array<string, array<string, mixed>>> $cart @return array{imported:int,product_ids:array<int,int>} */
    public function importFromSupplierCart(string $sessionId, array $cart, bool $clearTmpFirst = true): array
    {
        $items = [];
        foreach ($cart as $supplier => $supplierItems) {
            if (!is_array($supplierItems)) {
                continue;
            }
            foreach ($supplierItems as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $item['supplier'] = (string) ($item['supplier'] ?? $supplier);
                $items[] = $item;
            }
        }

        return $this->importItems($sessionId, $items, $clearTmpFirst);
    }

    /** @param array<string, mixed> $item */
    private function upsertProduse(array $item): int
    {
        $code = trim((string) ($item['product_code'] ?? ''));
        if ($code === '') {
            throw new InvalidArgumentException('product_code lipsă la import.');
        }

        $name = trim((string) ($item['product_name'] ?? $code));
        $price = max(0, (float) ($item['price'] ?? 0));
        $createdAt = time() + 7200;

        $stmt = $this->pdo->prepare('SELECT idprodus FROM produse WHERE cod_produs = ? LIMIT 1');
        $stmt->execute([$code]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $update = $this->pdo->prepare('UPDATE produse SET denumire = ?, pret = ? WHERE idprodus = ?');
            $update->execute([$name, $price, (int) $existingId]);

            return (int) $existingId;
        }

        $nextIdStmt = $this->pdo->query('SELECT COALESCE(MAX(idprodus), 0) + 1 FROM produse');
        $nextId = (int) $nextIdStmt->fetchColumn();

        $insert = $this->pdo->prepare(
            'INSERT INTO produse (idprodus, denumire, cod_produs, pret, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([$nextId, $name, $code, $price, $createdAt]);

        return $nextId;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<int, array{qty:int,culoare:string,furnizor:string}>
     */
    private function expandItemForTmpRows(array $item): array
    {
        $supplier = strtolower(trim((string) ($item['supplier'] ?? '')));
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $baseFurnizor = trim((string) ($item['tmp_furnizor'] ?? ''));
        if ($baseFurnizor === '') {
            $baseFurnizor = $this->colorMapper->supplierShortCode($supplier);
        }

        $slots = $this->parseWarehouseSlots($item);
        if (!in_array($supplier, ['autonet', 'autototal'], true) || count($slots) <= 1) {
            $culoare = trim((string) ($item['tmp_culoare'] ?? ''));
            if ($culoare === '') {
                $culoare = $this->colorMapper->colorFromCartItem($item);
            }

            return [[
                'qty' => $qty,
                'culoare' => $culoare,
                'furnizor' => $baseFurnizor,
            ]];
        }

        $out = [];
        for ($i = 0; $i < $qty; $i++) {
            $slotIdx = min($i, count($slots) - 1);
            $itemForColor = $item;
            $itemForColor['depozit'] = $slots[$slotIdx];
            $itemForColor['qty'] = 1;
            $culoare = trim((string) ($item['tmp_culoare'] ?? ''));
            if ($culoare === '') {
                $culoare = $this->colorMapper->colorFromCartItem($itemForColor);
            }
            $out[] = [
                'qty' => 1,
                'culoare' => $culoare,
                'furnizor' => $baseFurnizor,
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $item @return array<int, string> */
    private function parseWarehouseSlots(array $item): array
    {
        $depozit = trim((string) ($item['depozit'] ?? ''));
        if ($depozit === '' || strcasecmp($depozit, '-') === 0) {
            return [];
        }

        $parts = preg_split('/\s*\+\s*/u', $depozit) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $p) => $p !== ''));
    }
}
