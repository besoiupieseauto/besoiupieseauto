<?php

declare(strict_types=1);

namespace Evasystem\Services\Orders;

use Evasystem\Services\SupplierSearch\SupplierCartService;
use RuntimeException;

/**
 * placeOrder one-click din coș furnizori (port SearchingController::placeOrder, fără API furnizori).
 */
final class SupplierPlaceOrderService
{
    public function __construct(
        private readonly OrderTmpService $tmpService,
        private readonly LegacyOrderService $orderService,
        private readonly SupplierCartService $cartService,
        private readonly SupplierCartToTmpImporter $importer,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function placeFromCart(string $sessionId, int $userId, array $payload): array
    {
        $importFrom = strtoupper(trim((string) ($payload['import_from'] ?? 'TIMISOARA')));
        $skipImport = $importFrom === 'NUIMPORTA';
        $clientId = (int) ($payload['id_client'] ?? $payload['idclient'] ?? 0);

        $cart = $this->cartService->getCart($userId);
        if ($cart === []) {
            throw new RuntimeException('Coșul furnizori este gol.');
        }

        $filteredCart = $this->filterCart($cart, $payload['order_item_keys'] ?? null);
        if ($filteredCart === []) {
            throw new RuntimeException('Niciun articol selectat în coș.');
        }

        $removedKeys = $this->collectCartKeys($filteredCart);

        if ($skipImport) {
            $this->cartService->removeItems($userId, $removedKeys);

            return [
                'mode' => 'nu_importa',
                'message' => 'Articolele selectate au fost eliminate din coș (fără comandă ERP).',
                'removed' => count($removedKeys),
            ];
        }

        if ($clientId <= 0) {
            throw new \InvalidArgumentException('Clientul ERP este obligatoriu.');
        }

        $this->tmpService->clear($sessionId);
        $importResult = $this->importer->importFromSupplierCart($sessionId, $filteredCart, false);

        $orderPayload = [
            'id_client' => $clientId,
            'idstare' => (int) ($payload['idstare'] ?? 1),
            'data' => (string) ($payload['data'] ?? date('Y-m-d')),
            'marca' => (string) ($payload['marca'] ?? ''),
            'observations' => (string) ($payload['observations'] ?? ''),
            'cont_awb' => (string) ($payload['cont_awb'] ?? 'Utvin'),
            'idmasina_cmd' => (int) ($payload['idmasina_cmd'] ?? 0),
        ];

        if ($importFrom === 'EXTERNE') {
            $order = $this->orderService->createExternalFromTmp($sessionId, $userId, $orderPayload);
        } else {
            $orderPayload['locatie_mgz'] = $importFrom === 'UTVIN' ? 2 : 1;
            $order = $this->orderService->createInternalFromTmp($sessionId, $userId, $orderPayload);
        }

        $this->cartService->removeItems($userId, $removedKeys);

        return [
            'mode' => 'order_created',
            'message' => 'Comandă creată direct din coș furnizori.',
            'imported' => $importResult['imported'],
            'order' => $order,
        ];
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $cart
     * @param array<int, mixed>|null $keysFilter
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function filterCart(array $cart, ?array $keysFilter): array
    {
        if ($keysFilter === null || $keysFilter === []) {
            return $cart;
        }

        $allowed = [];
        foreach ($keysFilter as $entry) {
            if (is_string($entry) && str_contains($entry, '|')) {
                [$supplier, $key] = explode('|', $entry, 2);
                $allowed[strtolower(trim($supplier)) . '|' . trim($key)] = true;
            } elseif (is_array($entry)) {
                $supplier = strtolower(trim((string) ($entry['supplier'] ?? '')));
                $key = trim((string) ($entry['key'] ?? ''));
                if ($supplier !== '' && $key !== '') {
                    $allowed[$supplier . '|' . $key] = true;
                }
            }
        }

        if ($allowed === []) {
            return $cart;
        }

        $filtered = [];
        foreach ($cart as $supplier => $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $key => $item) {
                $lookup = strtolower((string) $supplier) . '|' . (string) $key;
                if (isset($allowed[$lookup])) {
                    if (!isset($filtered[$supplier])) {
                        $filtered[$supplier] = [];
                    }
                    $filtered[$supplier][$key] = $item;
                }
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $cart
     * @return list<array{supplier:string,key:string}>
     */
    private function collectCartKeys(array $cart): array
    {
        $keys = [];
        foreach ($cart as $supplier => $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $key => $item) {
                if (is_array($item)) {
                    $keys[] = ['supplier' => (string) $supplier, 'key' => (string) $key];
                }
            }
        }

        return $keys;
    }
}
