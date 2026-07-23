<?php

declare(strict_types=1);

namespace Besoiu\Services\Products;

use Config\Database;
use PDO;
use Throwable;

/**
 * Legătură read-only între catalog site (produse.randomn_id) și ERP legacy (produse.idprodus).
 * Match primar pe cod normalizat (pCodeNorm ↔ cod_produs).
 */
final class ProductLegacyBridgeService
{
    public function __construct(
        private ?PDO $sitePdo = null,
        private ?PDO $legacyPdo = null,
    ) {
        $this->sitePdo = $sitePdo ?? Database::getDB('default');
        if ($this->legacyPdo === null && Database::hasConnection('legacy')) {
            $this->legacyPdo = Database::getDB('legacy');
        }
    }

    public function isLegacyAvailable(): bool
    {
        return $this->legacyPdo instanceof PDO;
    }

    /** @param array<string, mixed> $siteProduct */
    public function findLegacyMatch(array $siteProduct): ?array
    {
        if (!$this->isLegacyAvailable()) {
            return null;
        }

        $bridge = $this->readBridgeFromRawJson($siteProduct);
        if ($bridge !== null && !empty($bridge['idprodus'])) {
            $row = $this->findLegacyByIdprodus((int) $bridge['idprodus']);
            if ($row !== null) {
                return $row;
            }
        }

        $codeNorm = $this->normalizeCode((string) ($siteProduct['pCodeNorm'] ?? $siteProduct['pCode'] ?? ''));
        if ($codeNorm === '') {
            return null;
        }

        return $this->findLegacyByCodeNorm($codeNorm);
    }

    public function findSiteByLegacyIdprodus(int $idprodus): ?array
    {
        if ($idprodus <= 0) {
            return null;
        }

        try {
            if ($this->siteColumnExists('raw_json')) {
                $stmt = $this->sitePdo->prepare(
                    "SELECT id, randomn_id, pName, pCode, pBrand, pPrice, pStock, raw_json
                     FROM produse
                     WHERE JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.legacy_bridge.idprodus')) = ?
                     ORDER BY id DESC
                     LIMIT 1"
                );
                $stmt->execute([(string) $idprodus]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return $row;
                }
            }
        } catch (Throwable) {
            // JSON_EXTRACT indisponibil sau raw_json invalid
        }

        return $this->findSiteByLegacyCodeFallback($idprodus);
    }

    /**
     * Persistă legătura în raw_json (fără migrare coloană nouă).
     *
     * @param array<string, mixed> $siteProduct
     * @return array<string, mixed>|null
     */
    public function linkSiteToLegacy(array $siteProduct, bool $persist = false): ?array
    {
        $legacy = $this->findLegacyMatch($siteProduct);
        if ($legacy === null) {
            return null;
        }

        $bridge = [
            'idprodus' => (int) ($legacy['idprodus'] ?? 0),
            'cod_produs' => (string) ($legacy['cod_produs'] ?? ''),
            'denumire' => (string) ($legacy['denumire'] ?? ''),
            'pret_legacy' => (float) ($legacy['pret'] ?? 0),
            'linked_at' => date('c'),
            'match_key' => 'code_norm',
        ];

        if ($persist && !empty($siteProduct['id']) && $this->siteColumnExists('raw_json')) {
            $this->persistBridge((int) $siteProduct['id'], $bridge, $siteProduct);
        }

        return $bridge;
    }

    /** @return array{idprodus:int,cod_produs:string,denumire:string,pret:float}|null */
    private function findLegacyByIdprodus(int $idprodus): ?array
    {
        if (!$this->isLegacyAvailable() || $idprodus <= 0) {
            return null;
        }

        try {
            $stmt = $this->legacyPdo->prepare(
                'SELECT idprodus, cod_produs, denumire, pret FROM produse WHERE idprodus = ? LIMIT 1'
            );
            $stmt->execute([$idprodus]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{idprodus:int,cod_produs:string,denumire:string,pret:float}|null */
    private function findLegacyByCodeNorm(string $codeNorm): ?array
    {
        if (!$this->isLegacyAvailable()) {
            return null;
        }

        try {
            $stmt = $this->legacyPdo->prepare(
                "SELECT idprodus, cod_produs, denumire, pret
                 FROM produse
                 WHERE REPLACE(REPLACE(REPLACE(UPPER(TRIM(cod_produs)), ' ', ''), '-', ''), '.', '') = ?
                 ORDER BY idprodus DESC
                 LIMIT 1"
            );
            $stmt->execute([$codeNorm]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function findSiteByLegacyCodeFallback(int $idprodus): ?array
    {
        $legacy = $this->findLegacyByIdprodus($idprodus);
        if ($legacy === null) {
            return null;
        }

        $codeNorm = $this->normalizeCode((string) ($legacy['cod_produs'] ?? ''));
        if ($codeNorm === '') {
            return null;
        }

        try {
            $stmt = $this->sitePdo->prepare(
                'SELECT id, randomn_id, pName, pCode, pBrand, pPrice, pStock, raw_json
                 FROM produse WHERE pCodeNorm = ? ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$codeNorm]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        } catch (Throwable) {
            // pCodeNorm lipsește — fallback SQL
        }

        try {
            $stmt = $this->sitePdo->prepare(
                "SELECT id, randomn_id, pName, pCode, pBrand, pPrice, pStock, raw_json
                 FROM produse
                 WHERE REPLACE(REPLACE(REPLACE(UPPER(TRIM(pCode)), ' ', ''), '-', ''), '.', '') = ?
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$codeNorm]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $siteProduct @return array<string, mixed>|null */
    private function readBridgeFromRawJson(array $siteProduct): ?array
    {
        $raw = $siteProduct['raw_json'] ?? '';
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        } else {
            return null;
        }

        $bridge = $decoded['legacy_bridge'] ?? null;

        return is_array($bridge) ? $bridge : null;
    }

    /** @param array<string, mixed> $bridge @param array<string, mixed> $siteProduct */
    private function persistBridge(int $siteId, array $bridge, array $siteProduct): void
    {
        $raw = $siteProduct['raw_json'] ?? '';
        $decoded = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        }
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $decoded['legacy_bridge'] = $bridge;

        $stmt = $this->sitePdo->prepare('UPDATE produse SET raw_json = ? WHERE id = ? LIMIT 1');
        $stmt->execute([json_encode($decoded, JSON_UNESCAPED_UNICODE), $siteId]);
    }

    private function normalizeCode(string $code): string
    {
        $code = mb_strtoupper(trim($code), 'UTF-8');

        return (string) preg_replace('/[^A-Z0-9]/', '', $code);
    }

    private function siteColumnExists(string $column): bool
    {
        static $cache = null;
        if (is_array($cache)) {
            return isset($cache[$column]);
        }
        $cache = [];
        try {
            foreach ($this->sitePdo->query('SHOW COLUMNS FROM produse')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
                $cache[(string) ($col['Field'] ?? '')] = true;
            }
        } catch (Throwable) {
            return false;
        }

        return isset($cache[$column]);
    }
}
