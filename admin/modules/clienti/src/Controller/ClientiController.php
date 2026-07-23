<?php
declare(strict_types=1);

namespace Besoiu\Modules\Clienti\Controller;

use Besoiu\Exceptions\ValidationException;
use Besoiu\Modules\Clienti\Service\ClientiService;

final class ClientiController
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

    private const ALLOWED_STATUSES = [
        'nou',
        'activ',
        'vip',
        'inactiv',
        'blocat',
    ];

    public function __construct(
        private readonly ClientiService $clientiService = new ClientiService(),
    ) {
    }

    /** @param array<string, mixed> $rawInput */
    public function add(array $rawInput): array
    {
        return $this->clientiService->createClient($this->buildClientPayload($rawInput, false));
    }

    /** @param array<string, mixed> $rawInput */
    public function update(array $rawInput): array
    {
        $randomId = $this->requireRandomId($rawInput);

        return $this->clientiService->updateClient($randomId, $this->buildClientPayload($rawInput, true));
    }

    /** @param array<string, mixed> $rawInput */
    public function changeStatus(array $rawInput): void
    {
        $this->clientiService->changeClientStatus(
            $this->requireRandomId($rawInput),
            $this->normalizeStatus($rawInput['status'] ?? null)
        );
    }

    /** @param array<string, mixed> $rawInput */
    public function delete(array $rawInput): void
    {
        $this->clientiService->deleteClient($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput */
    public function list(array $rawInput = []): array
    {
        return $this->clientiService->listClients($rawInput);
    }

    /** @param array<string, mixed> $rawInput */
    private function buildClientPayload(array $rawInput, bool $isUpdate): array
    {
        $payload = $this->sanitizePayload($rawInput);

        if (!$isUpdate || array_key_exists('client_name', $payload)) {
            $this->validateClientName($payload['client_name'] ?? null);
        }

        if (isset($payload['email'])) {
            $payload['email'] = $this->normalizeEmail($payload['email']);
        }

        if (isset($payload['status'])) {
            $payload['status'] = $this->normalizeStatus($payload['status']);
        } elseif (!$isUpdate) {
            $payload['status'] = 'nou';
        }

        if (isset($payload['total_orders'])) {
            $payload['total_orders'] = $this->normalizeUnsignedInteger($payload['total_orders'], 'total_orders');
        }

        if (isset($payload['total_paid'])) {
            $payload['total_paid'] = $this->normalizeMoney($payload['total_paid']);
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
            if ($stringValue === '') {
                continue;
            }

            $cleanPayload[$key] = $stringValue;
        }

        return $cleanPayload;
    }

    /** @param mixed $clientName */
    private function validateClientName($clientName): void
    {
        $name = trim((string) $clientName);
        if ($name === '' || mb_strlen($name) > 160) {
            throw new ValidationException('Numele clientului este obligatoriu și trebuie să aibă maximum 160 de caractere.');
        }
    }

    /** @param mixed $email */
    private function normalizeEmail($email): ?string
    {
        $emailValue = trim((string) $email);
        if ($emailValue === '') {
            return null;
        }

        if (mb_strlen($emailValue) > 190 || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Emailul clientului nu este valid.');
        }

        return mb_strtolower($emailValue);
    }

    /** @param mixed $status */
    private function normalizeStatus($status): string
    {
        $statusValue = mb_strtolower(trim((string) $status));
        if (!in_array($statusValue, self::ALLOWED_STATUSES, true)) {
            throw new ValidationException('Statusul clientului nu este valid.');
        }

        return $statusValue;
    }

    /** @param mixed $value */
    private function normalizeUnsignedInteger($value, string $fieldName): int
    {
        if (!is_numeric($value) || (int) $value < 0) {
            throw new ValidationException("Câmpul {$fieldName} trebuie să fie un număr pozitiv.");
        }

        return (int) $value;
    }

    /** @param mixed $value */
    private function normalizeMoney($value): float
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            throw new ValidationException('Valoarea total_paid trebuie să fie un număr pozitiv.');
        }

        return round((float) $normalized, 2);
    }

    /** @param array<string, mixed> $rawInput */
    private function requireRandomId(array $rawInput): int
    {
        $randomId = $rawInput['randomn_id'] ?? $rawInput['id'] ?? null;
        if (!is_numeric($randomId) || (int) $randomId <= 0) {
            throw new ValidationException('Lipsește identificatorul clientului.');
        }

        return (int) $randomId;
    }
}
