<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Comenzi;

use Config\Database;
use Evasystem\Core\Comenzi\ComenziModel;
use Evasystem\Core\Comenzi\OrderItemsModel;
use Evasystem\Core\Produse\ProduseModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\PersistenceException;
use Evasystem\Exceptions\ValidationException;
use PDO;
use PDOException;

/**
 * Logică de business pentru comenzi.
 */
final class ComenziService
{
    private ComenziModel $comenziModel;
    private OrderItemsModel $orderItemsModel;
    private ProduseModel $produseModel;

    public function __construct(
        ComenziModel $comenziModel,
        ?OrderItemsModel $orderItemsModel = null,
        ?ProduseModel $produseModel = null
    ) {
        $this->comenziModel = $comenziModel;
        $this->orderItemsModel = $orderItemsModel ?? new OrderItemsModel();
        $this->produseModel = $produseModel ?? new ProduseModel();
    }

    /**
     * Creează o comandă simplă (manual / legacy payload agregat).
     *
     * @param array<string, string|int|float|null> $orderPayload
     * @return array{randomn_id: int, order_number: string}
     */
    public function createOrder(array $orderPayload): array
    {
        $randomId = $this->generateUniqueRandomId();
        $orderNumber = $this->generateOrderNumber($randomId);

        $orderPayload['randomn_id'] = $randomId;
        $orderPayload['order_number'] = $orderPayload['order_number'] ?? $orderNumber;

        if (!$this->comenziModel->insert($orderPayload)) {
            throw new PersistenceException('Comanda nu a putut fi salvată.');
        }

        return ['randomn_id' => $randomId, 'order_number' => (string) $orderPayload['order_number']];
    }

    /**
     * Checkout site: validează preț/stoc din BD, salvează linii + decrementează stocul.
     *
     * @param array<string, string|int|float|null> $orderPayload
     * @param array<int, array<string, mixed>> $rawItems
     * @return array{randomn_id: int, order_number: string, total_amount: float, quantity: int}
     */
    public function createWebsiteOrder(array $orderPayload, array $rawItems): array
    {
        if (!$this->orderItemsModel->tableExists()) {
            throw new PersistenceException('Tabela order_items lipsește. Rulează migrarea 029.');
        }

        $resolvedLines = $this->resolveWebsiteLines($rawItems);
        if ($resolvedLines === []) {
            throw new ValidationException('Cosul nu conține produse valide.');
        }

        $totalAmount = 0.0;
        $totalQuantity = 0;
        foreach ($resolvedLines as $line) {
            $totalAmount += (float) $line['line_total'];
            $totalQuantity += (int) $line['quantity'];
        }
        $totalAmount = round($totalAmount, 2);

        $couponCode = trim((string) ($orderPayload['coupon_code'] ?? ''));
        $discountAmount = 0.0;
        if ($couponCode !== '') {
            require_once dirname(__DIR__, 4) . '/system/shop-coupon.php';
            $pdoPreview = Database::getDB();
            $couponResult = shop_coupon_validate($pdoPreview, $couponCode, $totalAmount);
            if (!$couponResult['valid']) {
                throw new ValidationException((string) $couponResult['message']);
            }
            $discountAmount = round((float) ($couponResult['discount'] ?? 0), 2);
            $totalAmount = round((float) ($couponResult['total_after'] ?? $totalAmount), 2);
            $orderPayload['coupon_code'] = shop_coupon_normalize_code($couponCode);
            $orderPayload['discount_amount'] = $discountAmount;
        } else {
            unset($orderPayload['coupon_code'], $orderPayload['discount_amount']);
        }

        $randomId = $this->generateUniqueRandomId();
        $orderNumber = $this->generateOrderNumber($randomId);

        $orderPayload['randomn_id'] = $randomId;
        $orderPayload['order_number'] = $orderNumber;
        $orderPayload['quantity'] = $totalQuantity;
        $orderPayload['total_amount'] = $totalAmount;
        $orderPayload['product_name'] = $this->buildAggregatedProductName($resolvedLines);
        $orderPayload['product_image'] = (string) ($resolvedLines[0]['product_image'] ?? '');

        $pdo = Database::getDB();

        try {
            $pdo->beginTransaction();

            $orderId = $this->comenziModel->insertAndGetId($orderPayload);
            if ($orderId <= 0) {
                throw new PersistenceException('Comanda nu a putut fi salvată.');
            }

            $this->orderItemsModel->insertLines($orderId, $this->stripInternalLineKeys($resolvedLines));
            $this->decrementStockForLines($pdo, $resolvedLines);

            if ($couponCode !== '') {
                shop_coupon_increment_usage($pdo, $couponCode);
            }

            $pdo->commit();

            $this->enqueuePostOrderJobs($pdo, $randomId, $resolvedLines);
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new PersistenceException('Comanda nu a putut fi salvată.', 0, $exception);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'randomn_id' => $randomId,
            'order_number' => $orderNumber,
            'total_amount' => $totalAmount,
            'quantity' => $totalQuantity,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rawItems
     * @return array<int, array<string, mixed>>
     */
    private function resolveWebsiteLines(array $rawItems): array
    {
        $lines = [];

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $randomId = trim((string) ($rawItem['randomn_id'] ?? $rawItem['product_id'] ?? ''));
            $quantity = max(1, (int) ($rawItem['quantity'] ?? 1));

            if ($randomId === '') {
                throw new ValidationException('Un produs din cos nu are identificator valid.');
            }

            $product = $this->produseModel->find($randomId);
            if ($product === null || (string) ($product['status'] ?? '') === '0') {
                throw new ValidationException('Produsul selectat nu mai este disponibil.');
            }

            $unitPrice = $this->parseProductPrice($product);
            if ($unitPrice <= 0) {
                throw new ValidationException('Produsul "' . trim((string) ($product['pName'] ?? '')) . '" nu are preț valid.');
            }

            $stock = $this->parseProductStock($product);
            if ($stock !== null && $stock < $quantity) {
                throw new ValidationException(
                    'Stoc insuficient pentru "' . trim((string) ($product['pName'] ?? '')) . '". Disponibil: ' . $stock . '.'
                );
            }

            $lines[] = [
                'product_id' => (int) ($product['id'] ?? 0),
                'randomn_id' => (string) ($product['randomn_id'] ?? $randomId),
                'product_name' => trim((string) ($product['pName'] ?? 'Produs')),
                'product_image' => $this->productImageUrl($product),
                'oem_code' => trim((string) ($product['pCode'] ?? '')),
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => round($unitPrice * $quantity, 2),
                '_track_stock' => $stock !== null,
            ];
        }

        return $lines;
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    private function stripInternalLineKeys(array $lines): array
    {
        return array_map(static function (array $line): array {
            unset($line['_track_stock']);

            return $line;
        }, $lines);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    private function decrementStockForLines(PDO $pdo, array $lines): void
    {
        $stmt = $pdo->prepare(
            'UPDATE produse
             SET pStock = CAST(GREATEST(COALESCE(CAST(NULLIF(TRIM(pStock), \'\') AS SIGNED), 0) - :qty, 0) AS CHAR)
             WHERE id = :id
               AND TRIM(COALESCE(pStock, \'\')) REGEXP \'^[0-9]+$\''
        );

        foreach ($lines as $line) {
            if (empty($line['_track_stock'])) {
                continue;
            }

            $stmt->execute([
                ':qty' => (int) ($line['quantity'] ?? 1),
                ':id' => (int) ($line['product_id'] ?? 0),
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    private function buildAggregatedProductName(array $lines): string
    {
        $parts = [];
        foreach ($lines as $line) {
            $parts[] = ((int) ($line['quantity'] ?? 1)) . ' x ' . (string) ($line['product_name'] ?? 'Produs');
        }

        return mb_substr(implode('; ', $parts), 0, 255);
    }

    private function parseProductPrice(array $product): float
    {
        $raw = str_replace(',', '.', trim((string) ($product['pPrice'] ?? '0')));
        if (!is_numeric($raw)) {
            return 0.0;
        }

        return round(max(0, (float) $raw), 2);
    }

    private function parseProductStock(array $product): ?int
    {
        $raw = trim((string) ($product['pStock'] ?? ''));
        if ($raw === '' || !preg_match('/^\d+$/', $raw)) {
            return null;
        }

        return max(0, (int) $raw);
    }

    private function productImageUrl(array $product): string
    {
        $decoded = json_decode((string) ($product['pImages'] ?? '[]'), true);
        if (is_array($decoded)) {
            foreach ($decoded as $image) {
                $url = trim((string) $image);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return '';
    }

    /**
     * Actualizează o comandă existentă.
     *
     * @param array<string, string|int|float|null> $orderPayload
     * @return array{randomn_id: int}
     */
    public function updateOrder(int $randomId, array $orderPayload): array
    {
        $this->ensureOrderExists($randomId);

        if (!$this->comenziModel->updateByRandomId($randomId, $orderPayload)) {
            throw new PersistenceException('Comanda nu a putut fi actualizată.');
        }

        return ['randomn_id' => $randomId];
    }

    /**
     * Schimbă statusul comenzii.
     */
    public function changeOrderStatus(int $randomId, string $orderStatus): void
    {
        $this->ensureOrderExists($randomId);

        if (!$this->comenziModel->updateOrderStatusByRandomId($randomId, $orderStatus)) {
            throw new PersistenceException('Statusul comenzii nu a putut fi actualizat.');
        }
    }

    /**
     * Șterge o comandă.
     */
    public function deleteOrder(int $randomId): void
    {
        $this->ensureOrderExists($randomId);

        if (!$this->comenziModel->deleteByRandomId($randomId)) {
            throw new PersistenceException('Comanda nu a putut fi ștearsă.');
        }
    }

    /**
     * Listează comenzile cu linii atașate (order_items sau fallback legacy).
     *
     * @return array<string, mixed>
     */
    public function listOrders(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 10)));

        $result = $this->comenziModel->findPaginated($page, $perPage, $params);
        $items = $result['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return $result;
        }

        $orderDbIds = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $dbId = (int) ($item['id'] ?? 0);
            if ($dbId > 0) {
                $orderDbIds[] = $dbId;
            }
        }

        $groupedLines = $this->orderItemsModel->findGroupedByOrderIds($orderDbIds);

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $dbId = (int) ($item['id'] ?? 0);
            $lines = $groupedLines[$dbId] ?? [];
            if ($lines === []) {
                $lines = OrderItemsModel::parseLegacyLines($item);
            }

            $items[$index]['order_items'] = $lines;
            $items[$index]['items_count'] = count($lines);
        }

        $result['items'] = $items;

        $fulfillmentService = new OrderFulfillmentService(
            $this->comenziModel,
            null,
            null,
            $this->orderItemsModel
        );
        $result['items'] = $fulfillmentService->attachFulfillmentToOrders($result['items']);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrderFulfillment(int $randomId): array
    {
        return (new OrderFulfillmentService($this->comenziModel, null, null, $this->orderItemsModel))
            ->getFulfillment($randomId);
    }

    /**
     * @return array<string, mixed>
     */
    public function createInvoiceForOrder(int $randomId): array
    {
        return (new OrderFulfillmentService($this->comenziModel, null, null, $this->orderItemsModel))
            ->createInvoiceFromOrder($randomId);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createDeliveryForOrder(int $randomId, array $options = []): array
    {
        return (new OrderFulfillmentService($this->comenziModel, null, null, $this->orderItemsModel))
            ->createDeliveryFromOrder($randomId, $options);
    }

    /**
     * @param array<int, array<string, mixed>> $resolvedLines
     */
    private function enqueuePostOrderJobs(PDO $pdo, int $orderRandomId, array $resolvedLines): void
    {
        $jobQueuePath = dirname(__DIR__, 4) . '/system/JobQueue.php';
        if (!is_file($jobQueuePath)) {
            return;
        }

        require_once $jobQueuePath;

        try {
            $queue = new \JobQueue($pdo, 'default');
            $queue->push('baselinker_sync_order', [
                'order_randomn_id' => $orderRandomId,
                'items_count' => count($resolvedLines),
            ]);
        } catch (\Throwable $exception) {
            error_log('[ComenziService] queue push failed: ' . $exception->getMessage());
        }
    }

    private function ensureOrderExists(int $randomId): void
    {
        if (!$this->comenziModel->existsByRandomId($randomId)) {
            throw new NotFoundException('Comanda cerută nu există.');
        }
    }

    private function generateUniqueRandomId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = random_int(200000, 999999);
            if (!$this->comenziModel->existsByRandomId($candidate)) {
                return $candidate;
            }
        }

        throw new PersistenceException('Nu am reușit să generez un randomn_id unic pentru comandă.');
    }

    private function generateOrderNumber(int $randomId): string
    {
        return 'ORD-' . $randomId;
    }
}
