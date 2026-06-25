<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Comenzi;

use Config\Database;
use Evasystem\Controllers\Facturi\FacturiService;
use Evasystem\Controllers\Livrare\LivrareService;
use Evasystem\Core\Comenzi\ComenziModel;
use Evasystem\Core\Comenzi\OrderItemsModel;
use Evasystem\Core\Facturi\FacturiModel;
use Evasystem\Core\Livrare\LivrareModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\PersistenceException;
use Evasystem\Exceptions\ValidationException;
use Evasystem\Services\Fulfillment\CourierGateway;
use Evasystem\Services\Fulfillment\FulfillmentConfig;
use Evasystem\Services\Fulfillment\FulfillmentPayloadBuilder;
use Evasystem\Services\Fulfillment\FulfillmentTestSimulator;
use Evasystem\Services\Fulfillment\SmartBillClient;
use PDO;

/**
 * Legatura comanda site -> factura fiscala (modul facturi) + livrare AWB.
 */
final class OrderFulfillmentService
{
    /** @var array<string, bool> */
    private static array $columnExistsCache = [];

    private ComenziModel $comenziModel;
    private FacturiModel $facturiModel;
    private LivrareModel $livrareModel;
    private OrderItemsModel $orderItemsModel;
    private FacturiService $facturiService;
    private LivrareService $livrareService;
    private SmartBillClient $smartBillClient;
    private CourierGateway $courierGateway;
    private FulfillmentPayloadBuilder $payloadBuilder;
    private FulfillmentTestSimulator $testSimulator;

    public function __construct(
        ?ComenziModel $comenziModel = null,
        ?FacturiModel $facturiModel = null,
        ?LivrareModel $livrareModel = null,
        ?OrderItemsModel $orderItemsModel = null,
        ?FacturiService $facturiService = null,
        ?LivrareService $livrareService = null,
        ?SmartBillClient $smartBillClient = null,
        ?CourierGateway $courierGateway = null,
        ?FulfillmentPayloadBuilder $payloadBuilder = null,
        ?FulfillmentTestSimulator $testSimulator = null
    ) {
        $this->comenziModel = $comenziModel ?? new ComenziModel();
        $this->facturiModel = $facturiModel ?? new FacturiModel();
        $this->livrareModel = $livrareModel ?? new LivrareModel();
        $this->orderItemsModel = $orderItemsModel ?? new OrderItemsModel();
        $this->facturiService = $facturiService ?? new FacturiService($this->facturiModel);
        $this->livrareService = $livrareService ?? new LivrareService($this->livrareModel);
        $this->smartBillClient = $smartBillClient ?? new SmartBillClient();
        $this->courierGateway = $courierGateway ?? new CourierGateway();
        $this->payloadBuilder = $payloadBuilder ?? new FulfillmentPayloadBuilder();
        $this->testSimulator = $testSimulator ?? new FulfillmentTestSimulator();
    }

    /**
     * @return array<string, mixed>
     */
    public function getFulfillment(int $orderRandomId): array
    {
        $order = $this->requireOrder($orderRandomId);
        $orderDbId = (int) ($order['id'] ?? 0);

        return [
            'order_randomn_id' => $orderRandomId,
            'order_id' => $orderDbId,
            'invoice' => $this->resolveInvoiceForOrder($order),
            'delivery' => $this->resolveDeliveryForOrder($order),
            'fulfillment_mode' => FulfillmentConfig::publicStatus(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createInvoiceFromOrder(int $orderRandomId): array
    {
        if (!$this->columnExists('facturi', 'order_id')) {
            throw new PersistenceException('Ruleaza migrarea 033 pentru legatura comenzi-facturi.');
        }

        $order = $this->requireOrder($orderRandomId);
        $orderDbId = (int) ($order['id'] ?? 0);
        if ($orderDbId <= 0) {
            throw new ValidationException('Comanda nu are ID intern valid.');
        }

        $existing = $this->facturiModel->findByOrderId($orderDbId);
        if ($existing !== null) {
            $this->linkOrderToInvoice($orderRandomId, (int) ($existing['randomn_id'] ?? 0));

            return [
                'created' => false,
                'invoice' => $existing,
                'message' => 'Factura exista deja pentru aceasta comanda.',
            ];
        }

        $lines = $this->resolveOrderLines($order);
        $notes = $this->buildInvoiceNotes($order, $lines);
        $paymentStatus = mb_strtolower(trim((string) ($order['payment_status'] ?? 'ramburs')));
        $invoiceStatus = in_array($paymentStatus, ['confirmata', 'platita'], true) ? 'achitata' : 'neachitata';

        $invoicePayload = [
            'order_id' => $orderDbId,
            'order_number' => (string) ($order['order_number'] ?? ('ORD-' . $orderRandomId)),
            'invoice_title' => 'Factura comanda ' . ($order['order_number'] ?? $orderRandomId),
            'client_name' => (string) ($order['client_name'] ?? ''),
            'email' => (string) ($order['email'] ?? ''),
            'phone' => (string) ($order['phone'] ?? ''),
            'payment_method' => $paymentStatus !== '' ? $paymentStatus : 'ramburs',
            'invoice_status' => $invoiceStatus,
            'amount' => round((float) ($order['total_amount'] ?? 0), 2),
            'due_date' => date('Y-m-d', strtotime('+14 days')),
            'notes' => $notes,
            'status' => 'activ',
        ];

        $created = $this->facturiService->createInvoice($invoicePayload);
        $invoiceRandomId = (int) ($created['randomn_id'] ?? 0);
        $this->linkOrderToInvoice($orderRandomId, $invoiceRandomId);

        if (FulfillmentConfig::isTestMode() && $invoiceRandomId > 0) {
            $this->facturiService->updateInvoice($invoiceRandomId, [
                'invoice_number' => $this->testSimulator->localInvoiceNumber($invoiceRandomId),
            ]);
        }

        if ($invoiceStatus === 'achitata') {
            $this->comenziModel->updateByRandomId($orderRandomId, ['order_status' => 'platita']);
        }

        $smartbillMeta = $this->syncSmartBillInvoice($order, $lines, $invoiceRandomId);

        $invoice = $this->facturiModel->findByRandomId($invoiceRandomId);
        $message = FulfillmentConfig::isTestMode()
            ? 'Factura TEST a fost generata din comanda (simulare, fara SmartBill real).'
            : 'Factura a fost generata din comanda.';
        if (($smartbillMeta['attempted'] ?? false) && !($smartbillMeta['success'] ?? false)) {
            $message .= FulfillmentConfig::isTestMode()
                ? ' Simulare SmartBill: ' . (string) ($smartbillMeta['error'] ?? 'eroare necunoscuta')
                : ' SmartBill: ' . (string) ($smartbillMeta['error'] ?? 'eroare necunoscuta');
        } elseif ($smartbillMeta['success'] ?? false) {
            $label = ($smartbillMeta['test_mode'] ?? false) ? 'SmartBill TEST' : 'SmartBill';
            $message .= ' ' . $label . ': ' . (string) ($smartbillMeta['series'] ?? '') . ' #' . (string) ($smartbillMeta['number'] ?? '');
        }

        return [
            'created' => true,
            'invoice' => $invoice,
            'smartbill' => $smartbillMeta,
            'test_mode' => FulfillmentConfig::isTestMode(),
            'message' => $this->testSimulator->prefixMessage($message),
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createDeliveryFromOrder(int $orderRandomId, array $options = []): array
    {
        if (!$this->columnExists('livrare', 'order_id')) {
            throw new PersistenceException('Ruleaza migrarea 033 pentru legatura comenzi-livrare.');
        }

        $order = $this->requireOrder($orderRandomId);
        $orderDbId = (int) ($order['id'] ?? 0);
        if ($orderDbId <= 0) {
            throw new ValidationException('Comanda nu are ID intern valid.');
        }

        $existing = $this->livrareModel->findByOrderId($orderDbId);
        if ($existing !== null) {
            $this->linkOrderToDelivery($orderRandomId, (int) ($existing['randomn_id'] ?? 0));

            return [
                'created' => false,
                'delivery' => $existing,
                'message' => 'Livrarea exista deja pentru aceasta comanda.',
            ];
        }

        $deliveryMethod = mb_strtolower(trim((string) ($order['delivery_method'] ?? 'tarif_fix')));
        $courier = trim((string) ($options['courier'] ?? ''));
        if ($courier === '') {
            $courier = $deliveryMethod === 'ridicare_locala' ? 'Ridicare magazin' : 'Fan Courier';
        }

        $address = trim((string) ($options['address'] ?? $this->extractAddressFromNotes((string) ($order['notes'] ?? ''))));
        if ($address === '') {
            $address = $deliveryMethod === 'ridicare_locala'
                ? 'Ridicare locala Besoiu Piese Auto'
                : 'Adresa de livrare din comanda #' . ($order['order_number'] ?? $orderRandomId);
        }

        $deliveryPayload = [
            'order_id' => $orderDbId,
            'order_number' => (string) ($order['order_number'] ?? ('ORD-' . $orderRandomId)),
            'delivery_title' => 'Livrare comanda ' . ($order['order_number'] ?? $orderRandomId),
            'client_name' => (string) ($order['client_name'] ?? ''),
            'email' => (string) ($order['email'] ?? ''),
            'phone' => (string) ($order['phone'] ?? ''),
            'address' => $address,
            'courier' => $courier,
            'service_type' => $deliveryMethod,
            'delivery_status' => 'pregatire',
            'delivery_date' => date('Y-m-d', strtotime('+1 day')),
            'total_amount' => round((float) ($order['total_amount'] ?? 0), 2),
            'notes' => (string) ($order['notes'] ?? ''),
            'status' => 'activ',
        ];

        $created = $this->livrareService->createDelivery($deliveryPayload);
        $deliveryRandomId = (int) ($created['randomn_id'] ?? 0);
        $this->linkOrderToDelivery($orderRandomId, $deliveryRandomId);

        $this->comenziModel->updateByRandomId($orderRandomId, [
            'delivery_status' => 'pregatire',
            'order_status' => 'in_lucru',
        ]);

        $awbMeta = $this->syncCourierAwb($order, $orderRandomId, $deliveryRandomId, $options);

        $delivery = $this->livrareModel->findByRandomId($deliveryRandomId);
        $message = FulfillmentConfig::isTestMode()
            ? 'Livrare TEST creata din comanda (simulare AWB, fara curier real).'
            : 'Livrarea a fost creata din comanda.';
        if (($awbMeta['attempted'] ?? false) && !($awbMeta['success'] ?? false)) {
            $message .= FulfillmentConfig::isTestMode()
                ? ' Simulare AWB: ' . (string) ($awbMeta['error'] ?? 'eroare necunoscuta')
                : ' AWB: ' . (string) ($awbMeta['error'] ?? 'eroare necunoscuta');
        } elseif ($awbMeta['success'] ?? false) {
            $label = ($awbMeta['test_mode'] ?? false) ? 'AWB TEST' : 'AWB';
            $message .= ' ' . $label . ': ' . (string) ($awbMeta['awb'] ?? '');
        }

        return [
            'created' => true,
            'delivery' => $delivery,
            'awb' => $awbMeta,
            'test_mode' => FulfillmentConfig::isTestMode(),
            'message' => $this->testSimulator->prefixMessage($message),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     * @return array<int, array<string, mixed>>
     */
    public function attachFulfillmentToOrders(array $orders): array
    {
        if ($orders === [] || !$this->columnExists('facturi', 'order_id')) {
            return $orders;
        }
        foreach ($orders as $order) {
            $dbId = (int) ($order['id'] ?? 0);
            if ($dbId > 0) {
                $orderDbIds[] = $dbId;
            }
        }

        if ($orderDbIds === []) {
            return $orders;
        }

        $invoices = $this->facturiModel->findByOrderIds($orderDbIds);
        $deliveries = $this->livrareModel->findByOrderIds($orderDbIds);

        foreach ($orders as $index => $order) {
            $dbId = (int) ($order['id'] ?? 0);
            $orders[$index]['fulfillment'] = [
                'invoice' => $invoices[$dbId] ?? null,
                'delivery' => $deliveries[$dbId] ?? null,
            ];
        }

        return $orders;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireOrder(int $orderRandomId): array
    {
        $order = $this->comenziModel->findByRandomId($orderRandomId);
        if ($order === null) {
            throw new NotFoundException('Comanda ceruta nu exista.');
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>|null
     */
    private function resolveInvoiceForOrder(array $order): ?array
    {
        $invoiceRandomId = (int) ($order['invoice_randomn_id'] ?? 0);
        if ($invoiceRandomId > 0) {
            $invoice = $this->facturiModel->findByRandomId($invoiceRandomId);
            if ($invoice !== null) {
                return $invoice;
            }
        }

        $orderDbId = (int) ($order['id'] ?? 0);
        if ($orderDbId <= 0) {
            return null;
        }

        return $this->facturiModel->findByOrderId($orderDbId);
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>|null
     */
    private function resolveDeliveryForOrder(array $order): ?array
    {
        $deliveryRandomId = (int) ($order['livrare_randomn_id'] ?? 0);
        if ($deliveryRandomId > 0) {
            $delivery = $this->livrareModel->findByRandomId($deliveryRandomId);
            if ($delivery !== null) {
                return $delivery;
            }
        }

        $orderDbId = (int) ($order['id'] ?? 0);
        if ($orderDbId <= 0) {
            return null;
        }

        return $this->livrareModel->findByOrderId($orderDbId);
    }

    /**
     * @param array<string, mixed> $order
     * @return array<int, array<string, mixed>>
     */
    private function resolveOrderLines(array $order): array
    {
        $orderDbId = (int) ($order['id'] ?? 0);
        if ($orderDbId <= 0) {
            return OrderItemsModel::parseLegacyLines($order);
        }

        $grouped = $this->orderItemsModel->findGroupedByOrderIds([$orderDbId]);
        $lines = $grouped[$orderDbId] ?? [];
        if ($lines !== []) {
            return $lines;
        }

        return OrderItemsModel::parseLegacyLines($order);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    private function buildInvoiceNotes(array $order, array $lines): string
    {
        $parts = [];
        $parts[] = 'Factura fiscala generata automat din comanda ' . ($order['order_number'] ?? '');

        if ($lines !== []) {
            $parts[] = 'Linii:';
            foreach ($lines as $line) {
                $name = trim((string) ($line['product_name'] ?? $line['name'] ?? 'Produs'));
                $qty = (int) ($line['quantity'] ?? 1);
                $total = number_format((float) ($line['line_total'] ?? $line['unit_price'] ?? 0), 2, '.', '');
                $parts[] = sprintf('- %s x%d = %s RON', $name, $qty, $total);
            }
        }

        $notes = trim((string) ($order['notes'] ?? ''));
        if ($notes !== '') {
            $parts[] = 'Detalii comanda: ' . $notes;
        }

        return implode("\n", $parts);
    }

    private function extractAddressFromNotes(string $notes): string
    {
        if (preg_match('/(?:adresa|address|livrare)\s*[:\-]\s*(.+)$/im', $notes, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    private function linkOrderToInvoice(int $orderRandomId, int $invoiceRandomId): void
    {
        if ($invoiceRandomId <= 0) {
            return;
        }

        if ($this->columnExists('comenzi', 'invoice_randomn_id')) {
            $this->comenziModel->updateByRandomId($orderRandomId, [
                'invoice_randomn_id' => $invoiceRandomId,
            ]);
        }
    }

    private function linkOrderToDelivery(int $orderRandomId, int $deliveryRandomId): void
    {
        if ($deliveryRandomId <= 0) {
            return;
        }

        if ($this->columnExists('comenzi', 'livrare_randomn_id')) {
            $this->comenziModel->updateByRandomId($orderRandomId, [
                'livrare_randomn_id' => $deliveryRandomId,
            ]);
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, self::$columnExistsCache)) {
            return self::$columnExistsCache[$cacheKey];
        }

        try {
            $pdo = Database::getDB();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            $exists = (int) $stmt->fetchColumn() > 0;
            self::$columnExistsCache[$cacheKey] = $exists;

            return $exists;
        } catch (\Throwable $exception) {
            self::$columnExistsCache[$cacheKey] = false;

            return false;
        }
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $lines
     * @return array<string, mixed>
     */
    private function syncSmartBillInvoice(array $order, array $lines, int $invoiceRandomId): array
    {
        $meta = [
            'attempted' => false,
            'success' => false,
            'test_mode' => FulfillmentConfig::isTestMode(),
            'series' => '',
            'number' => '',
            'error' => '',
        ];

        if (!FulfillmentConfig::smartbillEnabled()) {
            return $meta;
        }

        if ($invoiceRandomId <= 0) {
            return $meta;
        }

        $meta['attempted'] = true;
        $payload = $this->payloadBuilder->buildSmartBillInvoice($order, $lines);

        if (FulfillmentConfig::isTestMode()) {
            $result = $this->testSimulator->simulateSmartBill($order, $lines, $payload, $invoiceRandomId);
            $meta['success'] = $result['success'];
            $meta['series'] = $result['series'];
            $meta['number'] = $result['number'];
            $meta['error'] = $result['error'];

            if (!$meta['success']) {
                return $meta;
            }

            $series = $meta['series'];
            $number = $meta['number'];
            $update = [
                'invoice_number' => $series . '-' . $number,
            ];
            $existingInvoice = $this->facturiModel->findByRandomId($invoiceRandomId);
            $existingNotes = trim((string) ($existingInvoice['notes'] ?? ''));
            $update['notes'] = $existingNotes !== ''
                ? $existingNotes . "\n[TEST] SmartBill simulat: {$series} #{$number}"
                : "[TEST] SmartBill simulat: {$series} #{$number}";

            if ($this->columnExists('facturi', 'smartbill_series')) {
                $update['smartbill_series'] = $series;
                $update['smartbill_number'] = $number;
                $update['smartbill_invoice_id'] = 'TEST-' . $series . $number;
            }

            $this->facturiService->updateInvoice($invoiceRandomId, $update);

            return $meta;
        }

        if (!$this->smartBillClient->isConfigured()) {
            $meta['error'] = 'SmartBill nu este configurat in .env.';

            return $meta;
        }

        $result = $this->smartBillClient->createInvoice($payload);

        if (!$result['success'] || !is_array($result['data'])) {
            $meta['error'] = (string) ($result['error'] ?? 'SmartBill esuat');

            return $meta;
        }

        $series = (string) ($result['data']['series'] ?? FulfillmentConfig::smartbill()['series_name'] ?? 'BPA_CAI');
        $number = (string) ($result['data']['number'] ?? '');
        $meta['success'] = $number !== '';
        $meta['series'] = $series;
        $meta['number'] = $number;

        if (!$meta['success']) {
            $meta['error'] = 'SmartBill nu a returnat numar.';

            return $meta;
        }

        $update = [
            'invoice_number' => $series . '-' . $number,
        ];

        $existingInvoice = $this->facturiModel->findByRandomId($invoiceRandomId);
        $existingNotes = trim((string) ($existingInvoice['notes'] ?? ''));
        $update['notes'] = $existingNotes !== ''
            ? $existingNotes . "\nSmartBill: {$series} #{$number}"
            : "SmartBill: {$series} #{$number}";

        if ($this->columnExists('facturi', 'smartbill_series')) {
            $update['smartbill_series'] = $series;
            $update['smartbill_number'] = $number;
            $update['smartbill_invoice_id'] = $series . $number;
        }

        $this->facturiService->updateInvoice($invoiceRandomId, $update);

        return $meta;
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function syncCourierAwb(array $order, int $orderRandomId, int $deliveryRandomId, array $options): array
    {
        $meta = [
            'attempted' => false,
            'success' => false,
            'test_mode' => FulfillmentConfig::isTestMode(),
            'awb' => '',
            'courier' => '',
            'error' => '',
        ];

        if (!FulfillmentConfig::awbEnabled()) {
            return $meta;
        }

        $deliveryMethod = mb_strtolower(trim((string) ($order['delivery_method'] ?? '')));
        if ($deliveryMethod === 'ridicare_locala') {
            return $meta;
        }

        if ($deliveryRandomId <= 0) {
            return $meta;
        }

        $meta['attempted'] = true;
        $options['delivery_randomn_id'] = $deliveryRandomId;
        $result = $this->courierGateway->createAwbForOrder($order, $options);
        $meta['courier'] = (string) ($result['courier'] ?? '');

        if (!$result['success']) {
            $meta['error'] = (string) ($result['error'] ?? 'AWB esuat');
            if ($this->columnExists('livrare', 'courier_response') && !empty($result['data'])) {
                $this->livrareService->updateDelivery($deliveryRandomId, [
                    'courier_response' => json_encode($result['data'], JSON_UNESCAPED_UNICODE),
                ]);
            }

            return $meta;
        }

        $meta['success'] = true;
        $meta['awb'] = (string) ($result['awb'] ?? '');

        $update = [
            'awb' => $meta['awb'],
            'courier' => $meta['courier'] !== '' ? $meta['courier'] : (string) ($options['courier'] ?? 'Fan Courier'),
            'delivery_status' => FulfillmentConfig::isTestMode() ? 'pregatire' : 'expediata',
        ];

        if ($this->columnExists('livrare', 'courier_provider')) {
            $provider = mb_strtolower(str_replace(' ', '', $meta['courier']));
            $update['courier_provider'] = FulfillmentConfig::isTestMode() ? 'test_' . $provider : $provider;
        }
        if ($this->columnExists('livrare', 'courier_response') && !empty($result['data'])) {
            $update['courier_response'] = json_encode($result['data'], JSON_UNESCAPED_UNICODE);
        }

        $this->livrareService->updateDelivery($deliveryRandomId, $update);
        if (!FulfillmentConfig::isTestMode()) {
            $this->comenziModel->updateByRandomId($orderRandomId, [
                'delivery_status' => 'expediata',
            ]);
        }

        return $meta;
    }
}
