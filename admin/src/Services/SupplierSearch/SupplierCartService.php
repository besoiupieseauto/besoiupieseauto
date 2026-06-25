<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch;

use PDO;
use RuntimeException;

/**
 * Coș furnizori B2B — persistență în BD legacy (tabel supplier_carts, compatibil Laravel).
 */
final class SupplierCartService
{
    private const ALLOWED_SUPPLIERS = ['materom', 'elit', 'autopartner', 'autonet', 'autototal', 'site_produse'];

    public function __construct(private readonly ?PDO $pdo)
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
        $this->assertPdo();

        $json = json_encode($cart, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Nu s-a putut serializa coșul.');
        }

        $existing = $this->fetchRow($userId);
        if ($existing === null) {
            $stmt = $this->pdo->prepare('INSERT INTO supplier_carts (user_id, cart, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
            $stmt->execute([$userId, $json]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE supplier_carts SET cart = ?, updated_at = NOW() WHERE user_id = ?');
            $stmt->execute([$json, $userId]);
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
                'order_code' => $this->updateOrderCodeQuantity($variantCode, $qty),
            ];
        }

        $this->saveCart($userId, $cart);

        return [
            'message' => 'Produs adăugat în coș.',
            'cart' => $cart,
            'summary' => $this->summarizeCart($cart),
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
        $this->assertPdo();
        $stmt = $this->pdo->prepare('SELECT id, user_id, cart FROM supplier_carts WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
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
