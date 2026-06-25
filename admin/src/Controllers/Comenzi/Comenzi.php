<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Comenzi;

use Evasystem\Exceptions\ValidationException;

/**
 * Controller pentru entitatea „Comandă”.
 */
final class Comenzi
{
    private const FORBIDDEN_INPUT_KEYS = [
        'type_product',
        'type',
        'id',
        'idusers',
        'randomnid',
        'usersveryfi',
        'experiences',
    ];

    private const ALLOWED_ORDER_STATUSES = [
        'noua',
        'in_lucru',
        'platita',
        'expediata',
        'finalizata',
        'retur',
        'anulata',
    ];

    private const ALLOWED_CHANNELS = [
        'website',
        'whatsapp',
        'olx',
        'pieseauto',
        'facebook',
        'manual',
    ];

    private const ALLOWED_PAYMENT_STATUSES = [
        'ramburs',
        'card_online',
        'card_fizic',
        'numerar',
        'confirmata',
        'esuata',
    ];

    private ComenziService $comenziService;

    public function __construct(ComenziService $comenziService)
    {
        $this->comenziService = $comenziService;
    }

    /**
     * Validează și creează o comandă.
     *
     * @param array<string, mixed> $rawInput
     * @return array{randomn_id: int, order_number: string}
     */
    public function add(array $rawInput): array
    {
        $hasWebsiteItems = $this->hasWebsiteItems($rawInput);

        if ($hasWebsiteItems) {
            return $this->comenziService->createWebsiteOrder(
                $this->buildOrderPayload($rawInput, false, true),
                $rawInput['items']
            );
        }

        return $this->comenziService->createOrder($this->buildOrderPayload($rawInput, false, false));
    }

    /**
     * Validează și actualizează o comandă.
     *
     * @param array<string, mixed> $rawInput
     * @return array{randomn_id: int}
     */
    public function update(array $rawInput): array
    {
        $randomId = $this->requireRandomId($rawInput);
        return $this->comenziService->updateOrder($randomId, $this->buildOrderPayload($rawInput, true, false));
    }

    /**
     * Schimbă statusul comenzii.
     *
     * @param array<string, mixed> $rawInput
     */
    public function changeStatus(array $rawInput): void
    {
        $this->comenziService->changeOrderStatus(
            $this->requireRandomId($rawInput),
            $this->normalizeChoice($rawInput['order_status'] ?? null, self::ALLOWED_ORDER_STATUSES, 'Statusul comenzii nu este valid.')
        );
    }

    /**
     * Șterge o comandă.
     *
     * @param array<string, mixed> $rawInput
     */
    public function delete(array $rawInput): void
    {
        $this->comenziService->deleteOrder($this->requireRandomId($rawInput));
    }

    /**
     * Listează comenzile.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(array $rawInput = []): array
    {
        return $this->comenziService->listOrders($rawInput);
    }

    /**
     * @param array<string, mixed> $rawInput
     * @return array<string, mixed>
     */
    public function fulfillment(array $rawInput): array
    {
        return $this->comenziService->getOrderFulfillment($this->requireRandomId($rawInput));
    }

    /**
     * @param array<string, mixed> $rawInput
     * @return array<string, mixed>
     */
    public function createInvoice(array $rawInput): array
    {
        return $this->comenziService->createInvoiceForOrder($this->requireRandomId($rawInput));
    }

    /**
     * @param array<string, mixed> $rawInput
     * @return array<string, mixed>
     */
    public function createDelivery(array $rawInput): array
    {
        $options = [];
        if (!empty($rawInput['courier'])) {
            $options['courier'] = trim((string) $rawInput['courier']);
        }
        if (!empty($rawInput['address'])) {
            $options['address'] = trim((string) $rawInput['address']);
        }

        return $this->comenziService->createDeliveryForOrder($this->requireRandomId($rawInput), $options);
    }

    /**
     * Pregătește payload-ul curat pentru Service.
     *
     * @param array<string, mixed> $rawInput
     * @return array<string, string|int|float|null>
     */
    private function buildOrderPayload(array $rawInput, bool $isUpdate, bool $skipProductAggregate = false): array
    {
        $payload = $this->sanitizePayload($rawInput);

        if (!$skipProductAggregate && (!$isUpdate || array_key_exists('product_name', $payload))) {
            $this->validateText($payload['product_name'] ?? null, 'Produsul este obligatoriu.', 255);
        }

        if (isset($payload['client_name'])) {
            $this->validateText($payload['client_name'], 'Numele clientului este invalid.', 160, false);
        }

        if (isset($payload['product_image'])) {
            $this->validateText($payload['product_image'], 'Imaginea produsului este invalida.', 500, false);
        }

        if (isset($payload['email'])) {
            $payload['email'] = $this->normalizeEmail($payload['email']);
        }

        if (isset($payload['channel'])) {
            $payload['channel'] = $this->normalizeChoice($payload['channel'], self::ALLOWED_CHANNELS, 'Canalul nu este valid.');
        } elseif (!$isUpdate) {
            $payload['channel'] = 'manual';
        }

        if (isset($payload['payment_status'])) {
            $payload['payment_status'] = $this->normalizeChoice($payload['payment_status'], self::ALLOWED_PAYMENT_STATUSES, 'Statusul plății nu este valid.');
        } elseif (!$isUpdate) {
            $payload['payment_status'] = 'ramburs';
        }

        if (isset($payload['order_status'])) {
            $payload['order_status'] = $this->normalizeChoice($payload['order_status'], self::ALLOWED_ORDER_STATUSES, 'Statusul comenzii nu este valid.');
        } elseif (!$isUpdate) {
            $payload['order_status'] = 'noua';
        }

        if (!$skipProductAggregate && isset($payload['quantity'])) {
            $payload['quantity'] = $this->normalizeUnsignedInteger($payload['quantity'], 'quantity', 1);
        } elseif (!$isUpdate && !$skipProductAggregate) {
            $payload['quantity'] = 1;
        }

        if (!$skipProductAggregate && isset($payload['total_amount'])) {
            $payload['total_amount'] = $this->normalizeMoney($payload['total_amount']);
        } elseif (!$isUpdate && !$skipProductAggregate) {
            $payload['total_amount'] = 0.00;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $rawInput
     */
    private function hasWebsiteItems(array $rawInput): bool
    {
        if (empty($rawInput['items']) || !is_array($rawInput['items'])) {
            return false;
        }

        foreach ($rawInput['items'] as $item) {
            if (is_array($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Curăță input-ul de chei de protocol și valori nescalare.
     *
     * @param array<string, mixed> $rawInput
     * @return array<string, string>
     */
    private function sanitizePayload(array $rawInput): array
    {
        $withoutForbidden = array_diff_key($rawInput, array_flip(self::FORBIDDEN_INPUT_KEYS));
        $cleanPayload = [];

        foreach ($withoutForbidden as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $stringValue = trim((string) $value);
            if ($stringValue === '') {
                continue;
            }

            $cleanPayload[$key] = $stringValue;
        }

        return $cleanPayload;
    }

    /**
     * Validează text simplu.
     *
     * @param mixed $value
     */
    private function validateText($value, string $message, int $maxLength, bool $required = true): void
    {
        $text = trim((string) $value);
        if (($required && $text === '') || mb_strlen($text) > $maxLength) {
            throw new ValidationException($message);
        }
    }

    /**
     * Normalizează emailul.
     *
     * @param mixed $email
     */
    private function normalizeEmail($email): ?string
    {
        $emailValue = trim((string) $email);
        if ($emailValue === '') {
            return null;
        }

        if (mb_strlen($emailValue) > 255 || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Emailul nu este valid.');
        }

        return mb_strtolower($emailValue);
    }

    /**
     * Normalizează o valoare din whitelist.
     *
     * @param mixed $value
     * @param array<int, string> $allowedValues
     */
    private function normalizeChoice($value, array $allowedValues, string $message): string
    {
        $choice = mb_strtolower(trim((string) $value));
        if (!in_array($choice, $allowedValues, true)) {
            throw new ValidationException($message);
        }

        return $choice;
    }

    /**
     * Normalizează număr întreg pozitiv.
     *
     * @param mixed $value
     */
    private function normalizeUnsignedInteger($value, string $fieldName, int $minimum): int
    {
        if (!is_numeric($value) || (int) $value < $minimum) {
            throw new ValidationException("Câmpul {$fieldName} trebuie să fie un număr valid.");
        }

        return (int) $value;
    }

    /**
     * Normalizează suma comenzii.
     *
     * @param mixed $value
     */
    private function normalizeMoney($value): float
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            throw new ValidationException('Totalul comenzii trebuie să fie un număr pozitiv.');
        }

        return round((float) $normalized, 2);
    }

    /**
     * Citește randomn_id din payload.
     *
     * @param array<string, mixed> $rawInput
     */
    private function requireRandomId(array $rawInput): int
    {
        $randomId = $rawInput['randomn_id'] ?? $rawInput['id'] ?? null;
        if (!is_numeric($randomId) || (int) $randomId <= 0) {
            throw new ValidationException('Lipsește identificatorul comenzii.');
        }

        return (int) $randomId;
    }
}
