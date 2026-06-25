<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Livrare;

use Evasystem\Exceptions\ValidationException;

/**
 * Controller pentru entitatea „Livrare”.
 */
final class Livrare
{
    private const FORBIDDEN_INPUT_KEYS = ['type_product', 'type', 'id', 'idusers', 'randomnid', 'usersveryfi', 'experiences'];
    private const ALLOWED_DELIVERY_STATUSES = ['pregatire', 'awb_generat', 'in_tranzit', 'livrat', 'retur', 'anulat'];
    private const ALLOWED_COURIERS = ['Fan Courier', 'Cargus', 'Sameday', 'DHL', 'Ridicare personala'];

    private LivrareService $livrareService;

    public function __construct(LivrareService $livrareService)
    {
        $this->livrareService = $livrareService;
    }

    /** @param array<string, mixed> $rawInput */
    public function add(array $rawInput): array
    {
        return $this->livrareService->createDelivery($this->buildDeliveryPayload($rawInput, false));
    }

    /** @param array<string, mixed> $rawInput */
    public function update(array $rawInput): array
    {
        return $this->livrareService->updateDelivery(
            $this->requireRandomId($rawInput),
            $this->buildDeliveryPayload($rawInput, true)
        );
    }

    /** @param array<string, mixed> $rawInput */
    public function changeStatus(array $rawInput): void
    {
        $this->livrareService->changeDeliveryStatus(
            $this->requireRandomId($rawInput),
            $this->normalizeChoice($rawInput['delivery_status'] ?? null, self::ALLOWED_DELIVERY_STATUSES, 'Statusul livrării nu este valid.')
        );
    }

    /** @param array<string, mixed> $rawInput */
    public function delete(array $rawInput): void
    {
        $this->livrareService->deleteDelivery($this->requireRandomId($rawInput));
    }

    /** @return array<int, array<string, mixed>> */
    public function list(array $rawInput = []): array
    {
        return $this->livrareService->listDeliveries($rawInput);
    }

    /** @param array<string, mixed> $rawInput */
    private function buildDeliveryPayload(array $rawInput, bool $isUpdate): array
    {
        $payload = $this->sanitizePayload($rawInput);

        if (!$isUpdate || array_key_exists('delivery_title', $payload)) {
            $this->validateText($payload['delivery_title'] ?? null, 'Titlul livrării este obligatoriu.', 255);
        }

        if (isset($payload['courier'])) {
            $payload['courier'] = $this->normalizeCourier($payload['courier']);
        } elseif (!$isUpdate) {
            $payload['courier'] = 'Fan Courier';
        }

        if (isset($payload['delivery_status'])) {
            $payload['delivery_status'] = $this->normalizeChoice($payload['delivery_status'], self::ALLOWED_DELIVERY_STATUSES, 'Statusul livrării nu este valid.');
        } elseif (!$isUpdate) {
            $payload['delivery_status'] = 'pregatire';
        }

        if (isset($payload['email'])) {
            $payload['email'] = $this->normalizeEmail($payload['email']);
        }

        if (isset($payload['total_amount'])) {
            $payload['total_amount'] = $this->normalizeMoney($payload['total_amount']);
        } elseif (!$isUpdate) {
            $payload['total_amount'] = 0.00;
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
    private function validateText($value, string $message, int $maxLength): void
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > $maxLength) {
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

    /** @param mixed $value */
    private function normalizeCourier($value): string
    {
        $courier = trim((string) $value);
        if (!in_array($courier, self::ALLOWED_COURIERS, true)) {
            throw new ValidationException('Curierul nu este valid.');
        }
        return $courier;
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
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            throw new ValidationException('Totalul livrării trebuie să fie un număr pozitiv.');
        }
        return round((float) $normalized, 2);
    }

    /** @param array<string, mixed> $rawInput */
    private function requireRandomId(array $rawInput): int
    {
        $randomId = $rawInput['randomn_id'] ?? $rawInput['id'] ?? null;
        if (!is_numeric($randomId) || (int) $randomId <= 0) {
            throw new ValidationException('Lipsește identificatorul livrării.');
        }
        return (int) $randomId;
    }
}
