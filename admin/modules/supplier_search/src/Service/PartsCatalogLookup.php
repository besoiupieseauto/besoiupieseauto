<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service;

use PDO;

final class PartsCatalogLookup
{
    public function __construct(private readonly ?PDO $pdo)
    {
    }

    /** @return object|null */
    public function getByCode(string $code): ?object
    {
        if ($this->pdo === null || $code === '') {
            return null;
        }

        $normalized = SupplierSearchConfig::normalizeQuery($code);
        $stmt = $this->pdo->prepare(
            'SELECT code_parts, code_parts_advanced, mainart_code_parts, mainart_brands, brands, brand_id
             FROM parts_catalog WHERE code_parts = ?
             UNION
             SELECT code_parts, code_parts_advanced, mainart_code_parts, mainart_brands, brands, brand_id
             FROM parts_catalog WHERE code_parts_advanced = ?
             UNION
             SELECT code_parts, code_parts_advanced, mainart_code_parts, mainart_brands, brands, brand_id
             FROM parts_catalog WHERE mainart_code_parts = ?
             LIMIT 1'
        );
        $stmt->execute([$normalized, $normalized, $normalized]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        if ($row) {
            return $row;
        }

        $prefix = substr($normalized, 0, min(6, strlen($normalized)));
        if ($prefix === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT code_parts, code_parts_advanced, mainart_code_parts, mainart_brands, brands, brand_id
             FROM parts_catalog
             WHERE code_parts LIKE ?
             LIMIT 120'
        );
        $like = $prefix . '%';
        $stmt->execute([$like]);

        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $candidate) {
            if ($this->normalizeField($candidate->code_parts ?? '') === $normalized
                || $this->normalizeField($candidate->code_parts_advanced ?? '') === $normalized
                || $this->normalizeField($candidate->mainart_code_parts ?? '') === $normalized) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array<int, object> */
    public function getAllRowsByCode(string $code): array
    {
        if ($this->pdo === null || $code === '') {
            return [];
        }

        $normalized = SupplierSearchConfig::normalizeQuery($code);
        $stmt = $this->pdo->prepare(
            'SELECT code_parts, code_parts_advanced, mainart_code_parts, mainart_brands, brands, brand_id
             FROM parts_catalog WHERE code_parts = ?
             UNION
             SELECT code_parts, code_parts_advanced, mainart_code_parts, mainart_brands, brands, brand_id
             FROM parts_catalog WHERE code_parts_advanced = ?
             UNION
             SELECT code_parts, code_parts_advanced, mainart_code_parts, mainart_brands, brands, brand_id
             FROM parts_catalog WHERE mainart_code_parts = ?
             LIMIT 200'
        );
        $stmt->execute([$normalized, $normalized, $normalized]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        if (is_array($rows) && $rows !== []) {
            return $rows;
        }

        $prefix = substr($normalized, 0, min(6, strlen($normalized)));
        if ($prefix === '') {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT code_parts, code_parts_advanced, mainart_code_parts, mainart_brands, brands, brand_id
             FROM parts_catalog
             WHERE code_parts LIKE ?
             LIMIT 200'
        );
        $like = $prefix . '%';
        $stmt->execute([$like]);

        $filtered = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $candidate) {
            if ($this->normalizeField($candidate->code_parts ?? '') === $normalized
                || $this->normalizeField($candidate->code_parts_advanced ?? '') === $normalized
                || $this->normalizeField($candidate->mainart_code_parts ?? '') === $normalized) {
                $filtered[] = $candidate;
            }
        }

        return $filtered;
    }

    /** @return array<int, object> */
    public function getRowsForAutonet(string $query, string $rawQuery): array
    {
        if ($this->pdo === null) {
            return [];
        }

        $needles = array_values(array_unique(array_filter(
            [$query, $rawQuery, preg_replace('/[.\s\-\/|\\\\]+/', '', $rawQuery) ?? ''],
            static fn (string $needle): bool => $needle !== ''
        )));
        $parts = [];
        $params = [];
        foreach ($needles as $needle) {
            foreach (['code_parts', 'code_parts_advanced', 'mainart_code_parts'] as $column) {
                $parts[] = 'SELECT code_parts, code_parts_advanced, mainart_code_parts, mainart_brands, brands, brand_id FROM parts_catalog WHERE ' . $column . ' = ?';
                $params[] = $needle;
            }
        }
        $stmt = $this->pdo->prepare(implode(' UNION ', $parts) . ' LIMIT 200');
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        return is_array($rows) ? $rows : [];
    }

    private function normalizeField(?string $value): string
    {
        return preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $value) ?? '';
    }
}
