<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * Căutare produse relevante pentru agenți Ollama și chat public — sursă: MySQL + TecDoc.
 */
final class CatalogRagService
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot
            ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    private function tecdocStockPath(): ?string
    {
        $candidates = [
            $this->projectRoot . '/app/Legacy/tecdoc_stock.php',
            (defined('BESOIU_LEGACY') ? (string) BESOIU_LEGACY : '') . '/tecdoc_stock.php',
            $this->projectRoot . '/system/tecdoc_stock.php',
            $this->projectRoot . '/app/Import/Scraper/system/tecdoc_stock.php',
        ];
        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function isEnabled(): bool
    {
        $raw = strtolower(trim((string) ($_ENV['CATALOG_RAG_ENABLED'] ?? getenv('CATALOG_RAG_ENABLED') ?: '1')));

        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }

    /**
     * @return array{products: list<array<string, mixed>>, filters: array<string, mixed>, context: string, total: int}
     */
    public function searchByMessage(string $message, int $limit = 10, ?PDO $pdo = null): array
    {
        $limit = max(1, min(20, $limit));
        $message = trim($message);
        if ($message === '' || !self::isEnabled()) {
            return [
                'products' => [],
                'filters' => [],
                'context' => '',
                'total' => 0,
            ];
        }

        $pdo = $pdo ?? Database::getDB();
        $filters = $this->buildFiltersFromMessage($message);
        $products = $this->queryProducts($pdo, $filters, $limit);
        $vehicleContext = '';

        $tecdocBridge = new CatalogTecDocBridgeService($this->projectRoot);
        $tecdoc = $tecdocBridge->enrich($message, $filters, $limit, $pdo);
        if (($tecdoc['vehicle_context'] ?? '') !== '') {
            $vehicleContext = (string) $tecdoc['vehicle_context'];
        }
        if ($products === [] && !empty($tecdoc['products'])) {
            $products = $tecdoc['products'];
        } elseif (!empty($tecdoc['products'])) {
            $existingIds = array_column($products, 'randomn_id');
            foreach ($tecdoc['products'] as $tp) {
                $rid = (string) ($tp['randomn_id'] ?? '');
                if ($rid !== '' && !in_array($rid, $existingIds, true)) {
                    $products[] = $tp;
                }
            }
            $products = array_slice($products, 0, $limit);
        }

        if ($products === [] && !empty($filters['vin'])) {
            $products = $this->searchByVin($filters['vin'], $limit);
        }

        $context = $this->formatContextForLlm($products);
        if ($vehicleContext !== '') {
            $context = $vehicleContext . "\n\n" . $context;
        }

        return [
            'products' => $products,
            'filters' => $filters,
            'context' => $context,
            'total' => count($products),
            'vehicle_context' => $vehicleContext,
            'tecdoc_meta' => $tecdoc['meta'] ?? [],
        ];
    }

    /** Răspuns chat direct din stoc — fără halucinații LLM când există produse. */
    public function formatReplyForChat(array $searchResult): string
    {
        $products = is_array($searchResult['products'] ?? null) ? $searchResult['products'] : [];
        if ($products === []) {
            return '';
        }

        $lines = ['Am găsit în stoc:'];
        foreach ($products as $i => $p) {
            if (!is_array($p)) {
                continue;
            }
            $stock = (int) ($p['stock'] ?? 0);
            $lines[] = sprintf(
                '%d. %s — %s RON, stoc %d buc., cod %s (%s)',
                $i + 1,
                (string) ($p['name'] ?? ''),
                (string) ($p['price'] ?? '—'),
                $stock,
                (string) ($p['code'] ?? '—'),
                (string) ($p['brand'] ?? '—')
            );
            $rid = (string) ($p['randomn_id'] ?? '');
            if ($rid !== '') {
                $lines[] = '   Link: /product.php?id=' . $rid;
            }
        }

        $vehicle = trim((string) ($searchResult['vehicle_context'] ?? ''));
        if ($vehicle !== '') {
            $lines[] = '';
            $lines[] = $vehicle;
        }

        $lines[] = '';
        $lines[] = 'Răspuns informativ din catalog — prețurile sunt în RON. Pentru comandă: coșul de pe site.';

        return implode("\n", $lines);
    }

    /** @param list<array<string, mixed>> $products */
    public function formatContextForLlm(array $products): string
    {
        if ($products === []) {
            return "### Produse relevante (stoc real)\nNiciun produs găsit în catalog pentru această întrebare.";
        }

        $lines = ['### Produse relevante (stoc real — folosește DOAR aceste date)'];
        foreach ($products as $i => $p) {
            $n = $i + 1;
            $stock = (int) ($p['stock'] ?? 0);
            $lines[] = sprintf(
                '%d. %s | Cod: %s | OEM: %s | Brand: %s | Categorie: %s | Preț: %s RON | Stoc: %s | URL: /product.php?id=%s',
                $n,
                (string) ($p['name'] ?? ''),
                (string) ($p['code'] ?? '—'),
                (string) ($p['oem'] ?? '—'),
                (string) ($p['brand'] ?? '—'),
                trim((string) ($p['category'] ?? '') . ' / ' . (string) ($p['subcategory'] ?? ''), ' /'),
                (string) ($p['price'] ?? '—'),
                $stock > 0 ? (string) $stock . ' buc' : 'INDISPONIBIL',
                (string) ($p['randomn_id'] ?? '')
            );
        }

        return implode("\n", $lines);
    }

    /** @return array<string, mixed> */
    public function buildFiltersFromMessage(string $message): array
    {
        $filters = [
            'oem' => '',
            'name' => '',
            'vin' => '',
            'category' => '',
        ];

        if (preg_match('/\b([A-HJ-NPR-Z0-9]{17})\b/i', $message, $m)) {
            $filters['vin'] = strtoupper($m[1]);
        }

        if (preg_match('/\b(?:oem|cod|articol|referinta|referință)\s*[:#]?\s*([A-Za-z0-9][A-Za-z0-9\-_.\/]{3,})\b/iu', $message, $m)) {
            $filters['oem'] = trim($m[1]);
        } elseif (preg_match('/\b([0-9]{5,}[A-Za-z0-9\-_.\/]*|[A-Za-z]{2,}[0-9]{4,}[A-Za-z0-9\-_.\/]*)\b/u', $message, $m)) {
            $candidate = trim($m[1]);
            if (strlen($candidate) >= 5 && strlen($candidate) <= 32 && !preg_match('/^\d{1,2}$/', $candidate)) {
                $filters['oem'] = $candidate;
            }
        }

        $clean = preg_replace('/\b(?:pret|preț|stoc|livrare|comand|awb|retur|garan|salut|buna|bună|aveti|aveți|aveti|in|în|ce|care|sunt|este)\w*\b/iu', ' ', $message) ?? $message;
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');

        if (preg_match('/filtr[e]?\s+(?:de\s+)?ulei/iu', $message)) {
            $filters['name'] = 'filtru ulei';
        } elseif (preg_match('/disc\s+fran[aă]/iu', $message)) {
            $filters['name'] = 'disc frana';
        } elseif (preg_match('/pl[aă]cu[tț]e\s+fran[aă]/iu', $message)) {
            $filters['name'] = 'placute frana';
        } elseif (preg_match('/filtru\s+aer/iu', $message)) {
            $filters['name'] = 'filtru aer';
        } elseif ($filters['oem'] === '' && mb_strlen($clean, 'UTF-8') >= 3) {
            $filters['name'] = mb_substr($clean, 0, 120, 'UTF-8');
        } elseif ($filters['oem'] !== '' && mb_strlen($clean, 'UTF-8') >= 3) {
            $filters['name'] = mb_substr($clean, 0, 80, 'UTF-8');
        }

        foreach (['filtre', 'frane', 'frâne', 'ulei', 'ambreiaj', 'suspensie', 'motor', 'electrica', 'electrică', 'filtru', 'disc', 'placute', 'plăcuțe', 'bujie'] as $kw) {
            if (mb_stripos($message, $kw, 0, 'UTF-8') !== false && $filters['name'] === '') {
                $filters['name'] = $kw;
                break;
            }
        }

        return $filters;
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    private function queryProducts(PDO $pdo, array $filters, int $limit): array
    {
        $tecdoc = $this->tecdocStockPath();
        if ($tecdoc === null) {
            return $this->queryProductsFallback($pdo, $filters, $limit);
        }

        try {
            require_once $tecdoc;
            $queryFilters = [];
            if (($filters['oem'] ?? '') !== '') {
                $queryFilters['oem'] = (string) $filters['oem'];
            }
            if (($filters['name'] ?? '') !== '' && ($filters['oem'] ?? '') === '') {
                $queryFilters['name'] = (string) $filters['name'];
            }
            if ($queryFilters === []) {
                return [];
            }

            $rows = tecdoc_query_bd_products($pdo, $queryFilters, $limit);
            if ($rows === []) {
                return $this->queryProductsFallback($pdo, $filters, $limit);
            }

            return array_map([$this, 'normalizeProductRow'], $rows);
        } catch (Throwable) {
            return $this->queryProductsFallback($pdo, $filters, $limit);
        }
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    private function queryProductsFallback(PDO $pdo, array $filters, int $limit): array
    {
        $oem = trim((string) ($filters['oem'] ?? ''));
        $name = trim((string) ($filters['name'] ?? ''));
        if ($oem === '' && $name === '') {
            return [];
        }

        $sql = "SELECT randomn_id, pName, pCode, pBrand, pCategory, pSubcategory, pPrice, pStock, pOem
                FROM produse WHERE status <> '0'";
        $params = [];

        if ($oem !== '') {
            $like = '%' . $oem . '%';
            $sql .= ' AND (pCode LIKE ? OR pOem LIKE ? OR pName LIKE ?)';
            array_push($params, $like, $like, $like);
        } elseif ($name !== '') {
            $like = '%' . $name . '%';
            $sql .= ' AND (pName LIKE ? OR pCategory LIKE ? OR pSubcategory LIKE ? OR pNote LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = $this->normalizeProductRow([
                'randomn_id' => $row['randomn_id'] ?? '',
                'name' => $row['pName'] ?? '',
                'code' => $row['pCode'] ?? '',
                'brand' => $row['pBrand'] ?? '',
                'category' => $row['pCategory'] ?? '',
                'subcategory' => $row['pSubcategory'] ?? '',
                'price' => $row['pPrice'] ?? '',
                'stock' => $row['pStock'] ?? 0,
                'oem' => $row['pOem'] ?? '',
            ]);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function searchByVin(string $vin, int $limit): array
    {
        $tecdoc = $this->tecdocStockPath();
        if ($tecdoc === null) {
            return [];
        }

        try {
            require_once $tecdoc;
            $result = tecdoc_public_search(['vin' => $vin]);
            $articles = is_array($result['articles'] ?? null) ? $result['articles'] : [];
            $products = [];
            foreach ($articles as $article) {
                if (!is_array($article)) {
                    continue;
                }
                $products[] = $this->normalizeProductRow($article);
                if (count($products) >= $limit) {
                    break;
                }
            }

            return $products;
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeProductRow(array $row): array
    {
        return [
            'randomn_id' => (string) ($row['randomn_id'] ?? $row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? $row['pName'] ?? ''),
            'code' => (string) ($row['code'] ?? $row['pCode'] ?? ''),
            'oem' => (string) ($row['oem'] ?? $row['pOem'] ?? ''),
            'brand' => (string) ($row['brand'] ?? $row['pBrand'] ?? ''),
            'category' => (string) ($row['category'] ?? $row['pCategory'] ?? ''),
            'subcategory' => (string) ($row['subcategory'] ?? $row['pSubcategory'] ?? ''),
            'price' => (string) ($row['price'] ?? $row['pPrice'] ?? ''),
            'stock' => (int) ($row['stock'] ?? $row['pStock'] ?? 0),
            'in_stock' => (int) ($row['stock'] ?? $row['pStock'] ?? 0) > 0,
        ];
    }
}
