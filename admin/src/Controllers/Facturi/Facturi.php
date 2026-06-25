<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Facturi;

use Evasystem\Exceptions\ValidationException;

/**
 * Controller pentru entitatea „Factură”.
 */
final class Facturi
{
    private const FORBIDDEN_INPUT_KEYS = ['type_product', 'type', 'id', 'idusers', 'randomnid', 'usersveryfi', 'experiences'];
    private const ALLOWED_INVOICE_STATUSES = ['achitata', 'neachitata', 'anulata', 'storno'];
    private const ALLOWED_PAYMENT_METHODS = ['ramburs', 'card_online', 'transfer', 'cash'];

    private FacturiService $facturiService;

    public function __construct(FacturiService $facturiService)
    {
        $this->facturiService = $facturiService;
    }

    /** @param array<string, mixed> $rawInput */
    public function add(array $rawInput): array
    {
        return $this->facturiService->createInvoice($this->buildInvoicePayload($rawInput, false));
    }

    /** @param array<string, mixed> $rawInput */
    public function update(array $rawInput): array
    {
        return $this->facturiService->updateInvoice(
            $this->requireRandomId($rawInput),
            $this->buildInvoicePayload($rawInput, true)
        );
    }

    /** @param array<string, mixed> $rawInput */
    public function changeStatus(array $rawInput): void
    {
        $this->facturiService->changeInvoiceStatus(
            $this->requireRandomId($rawInput),
            $this->normalizeChoice($rawInput['invoice_status'] ?? null, self::ALLOWED_INVOICE_STATUSES, 'Statusul facturii nu este valid.')
        );
    }

    /** @param array<string, mixed> $rawInput */
    public function delete(array $rawInput): void
    {
        $this->facturiService->deleteInvoice($this->requireRandomId($rawInput));
    }

    /** @return array<int, array<string, mixed>> */
    public function list(array $rawInput = []): array
    {
        return $this->facturiService->listInvoices($rawInput);
    }

    /** @return array{all:int,achitata:int,neachitata:int,anulata:int,storno:int,total_amount:float} */
    public function stats(): array
    {
        return $this->facturiService->stats();
    }

    /** @param array<string, mixed> $rawInput */
    private function buildInvoicePayload(array $rawInput, bool $isUpdate): array
    {
        $payload = $this->sanitizePayload($rawInput);

        if (!$isUpdate || array_key_exists('invoice_title', $payload)) {
            $this->validateText($payload['invoice_title'] ?? null, 'Titlul facturii este obligatoriu.', 255);
        }

        if (isset($payload['client_name'])) {
            $this->validateText($payload['client_name'], 'Numele clientului este invalid.', 160, false);
        }

        if (isset($payload['email'])) {
            $payload['email'] = $this->normalizeEmail($payload['email']);
        }

        if (isset($payload['payment_method'])) {
            $payload['payment_method'] = $this->normalizeChoice($payload['payment_method'], self::ALLOWED_PAYMENT_METHODS, 'Metoda de plată nu este validă.');
        } elseif (!$isUpdate) {
            $payload['payment_method'] = 'ramburs';
        }

        if (isset($payload['invoice_status'])) {
            $payload['invoice_status'] = $this->normalizeChoice($payload['invoice_status'], self::ALLOWED_INVOICE_STATUSES, 'Statusul facturii nu este valid.');
        } elseif (!$isUpdate) {
            $payload['invoice_status'] = 'neachitata';
        }

        if (isset($payload['amount'])) {
            $payload['amount'] = $this->normalizeMoney($payload['amount']);
        } elseif (!$isUpdate) {
            $payload['amount'] = 0.00;
        }

        return $payload;
    }

    /** @param array<string, mixed> $rawInput */
    private function sanitizePayload(array $rawInput): array
    {
        $withoutForbidden = array_diff_key($rawInput, array_flip(self::FORBIDDEN_INPUT_KEYS));
        $cleanPayload = [];

        foreach ($withoutForbidden as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $stringValue = trim((string) $value);
            if ($stringValue !== '') {
                $cleanPayload[$key] = $stringValue;
            }
        }

        return $cleanPayload;
    }

    /** @param mixed $value */
    private function validateText($value, string $message, int $maxLength, bool $required = true): void
    {
        $text = trim((string) $value);
        if (($required && $text === '') || mb_strlen($text) > $maxLength) {
            throw new ValidationException($message);
        }
    }

    /** @param mixed $email */
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

    /** @param mixed $value @param array<int, string> $allowedValues */
    private function normalizeChoice($value, array $allowedValues, string $message): string
    {
        $choice = mb_strtolower(trim((string) $value));
        if (!in_array($choice, $allowedValues, true)) {
            throw new ValidationException($message);
        }
        return $choice;
    }

    /** @param mixed $value */
    private function normalizeMoney($value): float
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        if (!is_numeric($normalized)) {
            throw new ValidationException('Suma facturii trebuie să fie numerică.');
        }
        return round((float) $normalized, 2);
    }

    /** @param array<string, mixed> $rawInput */
    private function requireRandomId(array $rawInput): int
    {
        $randomId = $rawInput['randomn_id'] ?? $rawInput['id'] ?? null;
        if (!is_numeric($randomId) || (int) $randomId <= 0) {
            throw new ValidationException('Lipsește identificatorul facturii.');
        }
        return (int) $randomId;
    }
}
