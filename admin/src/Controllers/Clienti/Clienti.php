<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Clienti;

use Evasystem\Exceptions\ValidationException;

/**
 * Controller pentru entitatea „Client”.
 */
final class Clienti
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

    private ClientiService $clientiService;

    public function __construct(ClientiService $clientiService)
    {
        $this->clientiService = $clientiService;
    }

    /**
     * Validează și creează un client.
     *
     * @param array<string, mixed> $rawInput
     * @return array{randomn_id: int}
     */
    public function add(array $rawInput): array
    {
        $clientPayload = $this->buildClientPayload($rawInput, false);

        return $this->clientiService->createClient($clientPayload);
    }

    /**
     * Validează și actualizează un client.
     *
     * @param array<string, mixed> $rawInput
     * @return array{randomn_id: int}
     */
    public function update(array $rawInput): array
    {
        $randomId = $this->requireRandomId($rawInput);
        $clientPayload = $this->buildClientPayload($rawInput, true);

        return $this->clientiService->updateClient($randomId, $clientPayload);
    }

    /**
     * Schimbă statusul unui client.
     *
     * @param array<string, mixed> $rawInput
     */
    public function changeStatus(array $rawInput): void
    {
        $randomId = $this->requireRandomId($rawInput);
        $status = $this->normalizeStatus($rawInput['status'] ?? null);

        $this->clientiService->changeClientStatus($randomId, $status);
    }

    /**
     * Șterge un client.
     *
     * @param array<string, mixed> $rawInput
     */
    public function delete(array $rawInput): void
    {
        $this->clientiService->deleteClient($this->requireRandomId($rawInput));
    }

    /**
     * @param array<string, mixed> $rawInput
     * @return array{items:array<int,array<string,string|null>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function list(array $rawInput = []): array
    {
        return $this->clientiService->listClients($rawInput);
    }

    /**
     * Pregătește payload-ul curat pentru Service.
     *
     * @param array<string, mixed> $rawInput
     * @return array<string, string|int|float|null>
     */
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

    /**
     * Elimină cheile de protocol și normalizează valorile scalare.
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
     * Validează numele clientului.
     *
     * @param mixed $clientName
     */
    private function validateClientName($clientName): void
    {
        $name = trim((string) $clientName);
        if ($name === '' || mb_strlen($name) > 160) {
            throw new ValidationException('Numele clientului este obligatoriu și trebuie să aibă maximum 160 de caractere.');
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

        if (mb_strlen($emailValue) > 190 || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Emailul clientului nu este valid.');
        }

        return mb_strtolower($emailValue);
    }

    /**
     * Normalizează statusul.
     *
     * @param mixed $status
     */
    private function normalizeStatus($status): string
    {
        $statusValue = mb_strtolower(trim((string) $status));
        if (!in_array($statusValue, self::ALLOWED_STATUSES, true)) {
            throw new ValidationException('Statusul clientului nu este valid.');
        }

        return $statusValue;
    }

    /**
     * Normalizează un număr întreg fără semn.
     *
     * @param mixed $value
     */
    private function normalizeUnsignedInteger($value, string $fieldName): int
    {
        if (!is_numeric($value) || (int) $value < 0) {
            throw new ValidationException("Câmpul {$fieldName} trebuie să fie un număr pozitiv.");
        }

        return (int) $value;
    }

    /**
     * Normalizează valorile monetare.
     *
     * @param mixed $value
     */
    private function normalizeMoney($value): float
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            throw new ValidationException('Valoarea total_paid trebuie să fie un număr pozitiv.');
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
            throw new ValidationException('Lipsește identificatorul clientului.');
        }

        return (int) $randomId;
    }
}
