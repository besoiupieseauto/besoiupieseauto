<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Coș furnizori B2B — persistență în BD legacy (tabel supplier_carts, compatibil Laravel)
 * cu fallback automat pe instanța MySQL writable (port 3308) și oglindă locală JSON.
 */
final class SupplierCartService
{
    private const ALLOWED_SUPPLIERS = ['materom', 'elit', 'autopartner', 'autonet', 'autototal', 'intercars', 'szakal', 'bardi', 'site_produse'];

    public function __construct(private ?PDO $pdo)
    {
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    public function getCart(int $userId): array
    {
        $this->assertUserId($userId);
        $row = $this->fetchRow($userId);
        if ($row === null) {
            return [];
        }

        $cart = json_decode((string) ($row['cart'] ?? ''), true);

        return is_array($cart) ? $this->normalizeCart($cart) : [];
    }

    /** @param array<string, array<string, array<string, mixed>>> $cart */
    public function saveCart(int $userId, array $cart): void
    {
        $this->assertUserId($userId);

        $json = json_encode($cart, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Nu s-a putut serializa coșul.');
        }

        $dbSynced = false;
        $lastDbException = null;

        $pdoCandidates = [];
        if ($this->pdo !== null) {
            $pdoCandidates[] = $this->pdo;
        }
        $fallbackPdo = $this->resolveFallbackWritablePdo();
        if ($fallbackPdo !== null && $fallbackPdo !== $this->pdo) {
            $pdoCandidates[] = $fallbackPdo;
        }

        foreach ($pdoCandidates as $candidatePdo) {
            try {
                $this->upsertRowOnPdo($candidatePdo, $userId, $json);
                $this->pdo = $candidatePdo;
                $dbSynced = true;
                break;
            } catch (Throwable $e) {
                $lastDbException = $e;
            }
        }

        $localSaved = $this->saveLocalFileCart($userId, $json, $dbSynced);

        if (!$dbSynced && !$localSaved) {
            if ($lastDbException instanceof Throwable) {
                throw new RuntimeException('Nu s-a putut salva coșul: ' . $lastDbException->getMessage(), 0, $lastDbException);
            }
            $this->assertPdo();
        }
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function addItem(int $userId, array $payload): array
    {
        $supplier = strtolower(trim((string) ($payload['supplier'] ?? '')));
        $productCode = trim((string) ($payload['product_code'] ?? ''));
        $variantCode = trim((string) ($payload['variant_code'] ?? ''));
        $qty = max(1, (int) ($payload['qty'] ?? 1));
        $price = (float) ($payload['price'] ?? 0);
        $currency = trim((string) ($payload['currency'] ?? 'RON'));

        if (!in_array($supplier, self::ALLOWED_SUPPLIERS, true)) {
            throw new InvalidArgumentException('Furnizor invalid.');
        }
        if ($productCode === '' || $variantCode === '') {
            throw new InvalidArgumentException('Cod produs și variantă sunt obligatorii.');
        }
        if ($price <= 0) {
            throw new InvalidArgumentException('Preț invalid.');
        }
        if ($currency === '') {
            throw new InvalidArgumentException('Moneda este obligatorie.');
        }

        $mfrpn = trim((string) ($payload['mfrpn'] ?? ''));
        $productName = trim((string) ($payload['product_name'] ?? ''));
        if ($productName === '') {
            $productName = $productCode;
        }

        $manufacturer = trim((string) ($payload['manufacturer'] ?? ''));
        if ($supplier === 'materom') {
            $manufacturer = $this->normalizeMateromManufacturer($manufacturer);
        }

        $apiLookupCode = trim((string) ($payload['api_lookup_code'] ?? ''));
        if ($apiLookupCode === '') {
            $apiLookupCode = $variantCode;
        }
        if ($supplier === 'materom' && $mfrpn !== '') {
            $apiLookupCode = $mfrpn;
        }

        $searchedCode = trim((string) ($payload['searched_code'] ?? ''));
        $rawPrice = isset($payload['raw_price']) ? (float) $payload['raw_price'] : null;
        $plantname = trim((string) ($payload['plantname'] ?? ''));
        $delivery = trim((string) ($payload['delivery'] ?? ''));
        $livrare = trim((string) ($payload['livrare'] ?? ''));
        if ($livrare === '') {
            $livrare = '-';
        }
        $depozit = trim((string) ($payload['depozit'] ?? ''));
        if ($depozit === '') {
            $depozit = '-';
        }
        $departamentCode = trim((string) ($payload['departamentcode'] ?? $payload['departamentCode'] ?? ''));
        $autonetPartNo = trim((string) ($payload['autonet_partno'] ?? ''));
        $szakalToken = trim((string) ($payload['szakal_token'] ?? ''));
        $szakalGlid = trim((string) ($payload['szakal_glid'] ?? ''));

        $cart = $this->getCart($userId);
        $cartKey = $productCode . '-' . md5($variantCode);

        if (!isset($cart[$supplier])) {
            $cart[$supplier] = [];
        }

        if (isset($cart[$supplier][$cartKey])) {
            $cart[$supplier][$cartKey]['qty'] += $qty;
            if ($apiLookupCode !== '') {
                $cart[$supplier][$cartKey]['api_lookup_code'] = $apiLookupCode;
            }
            if ($searchedCode !== '') {
                $cart[$supplier][$cartKey]['searched_code'] = $searchedCode;
            }
            if ($supplier === 'autonet' && $autonetPartNo !== '') {
                $cart[$supplier][$cartKey]['autonet_partno'] = $autonetPartNo;
            }
            if ($supplier === 'szakal') {
                if ($szakalToken !== '') {
                    $cart[$supplier][$cartKey]['szakal_token'] = $szakalToken;
                }
                if ($szakalGlid !== '') {
                    $cart[$supplier][$cartKey]['szakal_glid'] = $szakalGlid;
                }
            }
            $cart[$supplier][$cartKey]['price'] = $price;
            if ($rawPrice !== null) {
                $cart[$supplier][$cartKey]['raw_price'] = $rawPrice;
            }
            $cart[$supplier][$cartKey]['order_code'] = $this->updateOrderCodeQuantity(
                (string) ($cart[$supplier][$cartKey]['order_code'] ?? $variantCode),
                (int) $cart[$supplier][$cartKey]['qty']
            );
        } else {
            $cart[$supplier][$cartKey] = [
                'supplier' => $supplier,
                'product_code' => $productCode,
                'product_name' => $productName,
                'manufacturer' => $manufacturer,
                'variant_code' => $variantCode,
                'api_lookup_code' => $apiLookupCode,
                'searched_code' => $searchedCode,
                'qty' => $qty,
                'price' => $price,
                'raw_price' => $rawPrice,
                'currency' => $currency,
                'delivery' => $delivery,
                'plantraw' => $plantname,
                'plantname' => '',
                'livrare' => $livrare,
                'depozit' => $depozit,
                'departamentCode' => $departamentCode,
                'autonet_partno' => $supplier === 'autonet' ? $autonetPartNo : '',
                'szakal_token' => $supplier === 'szakal' ? $szakalToken : '',
                'szakal_glid' => $supplier === 'szakal' ? $szakalGlid : '',
                'order_code' => $this->updateOrderCodeQuantity($variantCode, $qty),
                'webshop_line_id' => '',
                'webshop_status' => '',
            ];
        }

        if (WebshopCartMirrorService::isWebshopSupplier($supplier)) {
            $cart[$supplier][$cartKey] = (new WebshopCartMirrorService())->attachOnAdd($cart[$supplier][$cartKey]);
        }

        $this->saveCart($userId, $cart);

        $message = 'Produs adăugat în coș.';
        if (WebshopCartMirrorService::isWebshopSupplier($supplier)) {
            $lineId = trim((string) ($cart[$supplier][$cartKey]['webshop_line_id'] ?? ''));
            $message = $lineId !== ''
                ? 'Produs adăugat în coșul intern. Linie webshop: ' . $lineId . '.'
                : 'Produs adăugat în coșul intern. Oglindirea webshop nu a putut captura ID-ul liniei.';
        }

        return [
            'message' => $message,
            'cart' => $cart,
            'summary' => $this->summarizeCart($cart),
            'webshop' => [
                'line_id' => (string) ($cart[$supplier][$cartKey]['webshop_line_id'] ?? ''),
                'status' => (string) ($cart[$supplier][$cartKey]['webshop_status'] ?? ''),
                'error' => (string) ($cart[$supplier][$cartKey]['webshop_error'] ?? ''),
            ],
        ];
    }

    /**
     * @param list<array{supplier?:string,key?:string}|string> $keys
     * @return array{success:bool,submitted:false,results:list<array<string,mixed>>}
     */
    public function rehearseWebshop(int $userId, array $keys): array
    {
        $cart = $this->getCart($userId);
        $mirror = new WebshopCartMirrorService();
        $results = [];

        foreach ($keys as $entry) {
            $supplier = '';
            $key = '';
            if (is_string($entry) && str_contains($entry, '|')) {
                [$supplier, $key] = explode('|', $entry, 2);
            } elseif (is_array($entry)) {
                $supplier = (string) ($entry['supplier'] ?? '');
                $key = (string) ($entry['key'] ?? '');
            }
            $supplier = strtolower(trim($supplier));
            $key = trim($key);
            if ($supplier === '' || $key === '' || !isset($cart[$supplier][$key]) || !is_array($cart[$supplier][$key])) {
                continue;
            }
            $results[] = $mirror->rehearse($cart[$supplier][$key]) + [
                'cart_key' => $key,
            ];
        }

        return [
            'success' => $results !== [],
            'submitted' => false,
            'results' => $results,
        ];
    }

    /** @return array{success:bool,summary:array<string,mixed>} */
    public function updateQty(int $userId, string $supplier, string $key, int $qty): array
    {
        $supplier = strtolower(trim($supplier));
        $key = trim($key);
        $qty = max(1, $qty);

        $cart = $this->getCart($userId);
        if (!isset($cart[$supplier][$key])) {
            throw new RuntimeException('Articol inexistent în coș.');
        }

        $cart[$supplier][$key]['qty'] = $qty;
        $cart[$supplier][$key]['order_code'] = $this->updateOrderCodeQuantity(
            (string) ($cart[$supplier][$key]['order_code'] ?? $cart[$supplier][$key]['variant_code'] ?? ''),
            $qty
        );

        $this->saveCart($userId, $cart);

        return ['success' => true, 'summary' => $this->summarizeCart($cart)];
    }

    /** @return array{success:bool,summary:array<string,mixed>} */
    public function removeItem(int $userId, string $supplier, string $key): array
    {
        $this->removeItems($userId, [['supplier' => $supplier, 'key' => $key]]);

        return ['success' => true, 'summary' => $this->summarizeCart($this->getCart($userId))];
    }

    /** @param list<array{supplier:string,key:string}> $keys */
    public function removeItems(int $userId, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $cart = $this->getCart($userId);
        foreach ($keys as $entry) {
            $supplier = strtolower(trim((string) ($entry['supplier'] ?? '')));
            $key = trim((string) ($entry['key'] ?? ''));
            if ($supplier === '' || $key === '') {
                continue;
            }
            unset($cart[$supplier][$key]);
            if (isset($cart[$supplier]) && $cart[$supplier] === []) {
                unset($cart[$supplier]);
            }
        }

        $this->saveCart($userId, $cart);
    }

    /** @return array<string, mixed> */
    public function show(int $userId): array
    {
        $cart = $this->getCart($userId);

        return [
            'cart' => $cart,
            'summary' => $this->summarizeCart($cart),
        ];
    }

    /** @param array<string, array<string, array<string, mixed>>> $cart @return array{items:int,lines:int,total:float} */
    public function summarizeCart(array $cart): array
    {
        $items = 0;
        $lines = 0;
        $total = 0.0;

        foreach ($cart as $supplierItems) {
            if (!is_array($supplierItems)) {
                continue;
            }
            foreach ($supplierItems as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $qty = max(1, (int) ($item['qty'] ?? 1));
                $price = (float) ($item['price'] ?? 0);
                $items += $qty;
                $lines++;
                $total += $price * $qty;
            }
        }

        return [
            'items' => $items,
            'lines' => $lines,
            'total' => round($total, 2),
        ];
    }

    /** @return array<string, mixed>|null */
    private function fetchRow(int $userId): ?array
    {
        $local = $this->readLocalFileCart($userId);
        if (is_array($local) && ($local['db_synced'] ?? true) === false) {
            return $local;
        }

        $pdoCandidates = [];
        $fallbackPdo = $this->resolveFallbackWritablePdo();
        if ($fallbackPdo !== null) {
            $pdoCandidates[] = $fallbackPdo;
        }
        if ($this->pdo !== null && $this->pdo !== $fallbackPdo) {
            $pdoCandidates[] = $this->pdo;
        }

        foreach ($pdoCandidates as $candidatePdo) {
            try {
                $stmt = $candidatePdo->prepare('SELECT id, user_id, cart FROM supplier_carts WHERE user_id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return $row;
                }
            } catch (Throwable) {
                continue;
            }
        }

        if (is_array($local)) {
            return $local;
        }

        return null;
    }

    private function upsertRowOnPdo(PDO $pdo, int $userId, string $json): void
    {
        $stmt = $pdo->prepare('SELECT id FROM supplier_carts WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $existingId = $stmt->fetchColumn();

        if ($existingId === false || $existingId === null) {
            $ins = $pdo->prepare('INSERT INTO supplier_carts (user_id, cart, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
            $ins->execute([$userId, $json]);
        } else {
            $upd = $pdo->prepare('UPDATE supplier_carts SET cart = ?, updated_at = NOW() WHERE user_id = ?');
            $upd->execute([$json, $userId]);
        }
    }

    private function resolveFallbackWritablePdo(): ?PDO
    {
        static $cachedFallback = null;
        static $attempted = false;

        if ($this->pdo !== null) {
            try {
                $ro = $this->pdo->query('SELECT (@@innodb_read_only + @@read_only)');
                if ($ro !== false && (int) $ro->fetchColumn() === 0) {
                    return $this->pdo;
                }
            } catch (Throwable) {
            }
        }

        if ($attempted) {
            return $cachedFallback;
        }
        $attempted = true;

        $port = (int) ($_ENV['WRITABLE_FALLBACK_DB_PORT'] ?? 3308);
        $dbName = trim((string) ($_ENV['LEGACY_DB_NAME'] ?? 'caietcom_comenzilv'));
        if ($dbName === '') {
            $dbName = 'caietcom_comenzilv';
        }
        $dsn = sprintf('mysql:host=127.0.0.1;port=%d;dbname=%s;charset=utf8mb4', $port, $dbName);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
            $options[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 3;
        }

        $creds = [
            [(string) ($_ENV['LEGACY_DB_USER'] ?? $_ENV['DB_USER'] ?? 'root'), (string) ($_ENV['LEGACY_DB_PASS'] ?? $_ENV['DB_PASS'] ?? '')],
            ['root', ''],
        ];

        foreach ($creds as [$u, $p]) {
            try {
                $fb = new PDO($dsn, $u, $p, $options);
                $ro = $fb->query('SELECT (@@innodb_read_only + @@read_only)');
                if ($ro !== false && (int) $ro->fetchColumn() === 0) {
                    $fb->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
                    $cachedFallback = $fb;
                    return $cachedFallback;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function readLocalFileCart(int $userId): ?array
    {
        $path = $this->localCartFilePath($userId);
        if ($path === null || !is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['cart']) || !is_string($decoded['cart'])) {
            return null;
        }

        return [
            'id' => (int) ($decoded['id'] ?? $userId),
            'user_id' => $userId,
            'cart' => $decoded['cart'],
            'db_synced' => (bool) ($decoded['db_synced'] ?? true),
        ];
    }

    private function saveLocalFileCart(int $userId, string $cartJson, bool $dbSynced): bool
    {
        $path = $this->localCartFilePath($userId);
        if ($path === null) {
            return false;
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $payload = json_encode([
            'id' => $userId,
            'user_id' => $userId,
            'cart' => $cartJson,
            'db_synced' => $dbSynced,
            'updated_at' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return false;
        }

        return @file_put_contents($path, $payload, LOCK_EX) !== false;
    }

    private function localCartFilePath(int $userId): ?string
    {
        for ($level = 3; $level <= 6; $level++) {
            $root = dirname(__DIR__, $level);
            if (is_dir($root . '/admin/storage')) {
                return $root . '/admin/storage/cache/supplier_carts/user_' . $userId . '.json';
            }
        }

        return null;
    }

    /** @param array<string, mixed> $cart @return array<string, array<string, array<string, mixed>>> */
    private function normalizeCart(array $cart): array
    {
        $normalized = [];
        foreach ($cart as $supplier => $items) {
            if (!is_string($supplier) || !is_array($items)) {
                continue;
            }
            $supplier = strtolower($supplier);
            $normalized[$supplier] = [];
            foreach ($items as $key => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $normalized[$supplier][(string) $key] = $item;
            }
        }

        return $normalized;
    }

    private function updateOrderCodeQuantity(string $orderCode, int $newQty): string
    {
        if (preg_match('/qty:\d+/', $orderCode)) {
            return (string) preg_replace('/qty:\d+/', 'qty:' . $newQty, $orderCode);
        }

        return $orderCode;
    }

    private function normalizeMateromManufacturer(string $manufacturer): string
    {
        if ($manufacturer === '') {
            return '';
        }
        $prev = null;
        while ($prev !== $manufacturer) {
            $prev = $manufacturer;
            $manufacturer = (string) preg_replace('/\s*[-–—]\s*(OEM|AM|OE)\s*$/iu', '', $manufacturer);
            $manufacturer = trim($manufacturer);
        }

        return $manufacturer;
    }

    private function assertUserId(int $userId): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Utilizator neautentificat.');
        }
    }

    private function assertPdo(): void
    {
        if ($this->pdo === null) {
            throw new RuntimeException('Conexiune legacy indisponibilă (LEGACY_DB_*). Coșul furnizori necesită BD caiet comenzi.');
        }
    }
}
