<?php

declare(strict_types=1);

namespace Evasystem\Services\Orders;

use PDO;
use RuntimeException;

/**
 * Coș temporar comenzi ERP (tabel legacy tmp).
 */
final class OrderTmpService
{
    public function __construct(private readonly ?PDO $pdo)
    {
    }

    /** @param array<string, mixed> $payload */
    public function addProduct(string $sessionId, array $payload): void
    {
        $this->assertPdo();
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            throw new RuntimeException('Sesiune invalidă.');
        }

        $productId = (int) ($payload['id_produs'] ?? 0);
        $quantity = max(1, (int) ($payload['cantitate'] ?? $payload['qty'] ?? 1));
        $price = max(0, (float) ($payload['pret'] ?? $payload['price'] ?? 0));
        $furnizor = trim((string) ($payload['furnizor'] ?? '__'));
        if ($furnizor === '') {
            $furnizor = '__';
        }
        $culoare = trim((string) ($payload['culoare'] ?? $payload['disponibilitate'] ?? ''));

        if ($productId <= 0) {
            throw new InvalidArgumentException('id_produs invalid.');
        }
        if (!$this->productExists($productId)) {
            throw new RuntimeException('Produs ERP inexistent.');
        }

        $existing = $this->fetchBySessionAndProduct($sessionId, $productId);
        if ($existing !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE tmp SET cantitate_tmp = ?, pret_tmp = ?, furnizor = ?, culoare = ? WHERE id_tmp = ? AND session_id = ?'
            );
            $stmt->execute([$quantity, $price, $furnizor, $culoare, (int) $existing['id_tmp'], $sessionId]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tmp (session_id, id_produs, cantitate_tmp, pret_tmp, furnizor, culoare) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$sessionId, $productId, $quantity, $price, $furnizor, $culoare]);
        }
    }

    /** @return array{products:array<int,array<string,mixed>>,total:float} */
    public function listProducts(string $sessionId): array
    {
        $this->assertPdo();
        $stmt = $this->pdo->prepare(
            'SELECT tmp.*, produse.denumire AS ProductName, produse.cod_produs, produse.TVA AS tva
             FROM tmp
             LEFT JOIN produse ON tmp.id_produs = produse.idprodus
             WHERE tmp.session_id = ? AND tmp.id_produs IS NOT NULL
             ORDER BY tmp.id_tmp ASC'
        );
        $stmt->execute([$sessionId]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $total = 0.0;
        foreach ($products as $product) {
            $total += ((float) ($product['cantitate_tmp'] ?? 0)) * ((float) ($product['pret_tmp'] ?? 0));
        }

        return ['products' => $products, 'total' => round($total, 2)];
    }

    /** @param array<string, mixed> $payload */
    public function updateProduct(string $sessionId, int $idTmp, array $payload): void
    {
        $this->assertPdo();
        $quantity = max(1, (int) ($payload['cantitate'] ?? $payload['qty'] ?? 1));
        $price = max(0, (float) ($payload['pret'] ?? $payload['price'] ?? 0));

        $stmt = $this->pdo->prepare(
            'UPDATE tmp SET cantitate_tmp = ?, pret_tmp = ? WHERE session_id = ? AND id_tmp = ?'
        );
        $stmt->execute([$quantity, $price, $sessionId, $idTmp]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Linie tmp inexistentă.');
        }
    }

    public function deleteProduct(string $sessionId, int $idTmp): void
    {
        $this->assertPdo();
        $stmt = $this->pdo->prepare('DELETE FROM tmp WHERE session_id = ? AND id_tmp = ?');
        $stmt->execute([$sessionId, $idTmp]);
    }

    public function clear(string $sessionId): void
    {
        $this->assertPdo();
        $stmt = $this->pdo->prepare('DELETE FROM tmp WHERE session_id = ?');
        $stmt->execute([$sessionId]);
    }

    public function deleteByProduct(string $sessionId, int $productId): void
    {
        $this->assertPdo();
        $stmt = $this->pdo->prepare('DELETE FROM tmp WHERE session_id = ? AND id_produs = ?');
        $stmt->execute([$sessionId, $productId]);
    }

    /** @return array<string, mixed>|null */
    private function fetchBySessionAndProduct(string $sessionId, int $productId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_tmp FROM tmp WHERE session_id = ? AND id_produs = ? LIMIT 1'
        );
        $stmt->execute([$sessionId, $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function productExists(int $productId): bool
    {
        $stmt = $this->pdo->prepare('SELECT idprodus FROM produse WHERE idprodus = ? LIMIT 1');
        $stmt->execute([$productId]);

        return (bool) $stmt->fetchColumn();
    }

    private function assertPdo(): void
    {
        if ($this->pdo === null) {
            throw new RuntimeException('Conexiune legacy indisponibilă (LEGACY_DB_*).');
        }
    }
}
