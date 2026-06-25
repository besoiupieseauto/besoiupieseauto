<?php

declare(strict_types=1);

namespace Evasystem\Controllers\CaietComenzi;

use Evasystem\Exceptions\ValidationException;

final class CaietComenzi
{
    private CaietComenziService $service;

    public function __construct(CaietComenziService $service)
    {
        $this->service = $service;
    }

    /**
     * @return array<string, int|float|string>
     */
    public function stats(): array
    {
        return $this->service->getStats();
    }

    /**
     * @param array<string, mixed> $rawInput
     * @return array<int, array<string, mixed>>
     */
    public function list(array $rawInput): array
    {
        return $this->service->listOrders([
            'search' => $this->sanitizeText($rawInput['search'] ?? ''),
            'source_type' => $this->sanitizeText($rawInput['source_type'] ?? ''),
            'status' => $rawInput['status'] ?? null,
            'order_date' => $this->sanitizeText($rawInput['order_date'] ?? ''),
            'date_from' => $this->sanitizeText($rawInput['date_from'] ?? ''),
            'date_to' => $this->sanitizeText($rawInput['date_to'] ?? ''),
            'location' => $this->sanitizeText($rawInput['location'] ?? ''),
            'limit' => $rawInput['limit'] ?? 120,
        ]);
    }

    /**
     * @param array<string, mixed> $rawInput
     * @return array<string, mixed>
     */
    public function details(array $rawInput): array
    {
        return $this->service->getOrderDetails(
            $this->requireOrderId($rawInput),
            $this->requireSourceType($rawInput)
        );
    }

    /**
     * @param array<string, mixed> $rawInput
     * @return array<string, mixed>
     */
    public function changeStatus(array $rawInput, int $userId): array
    {
        $newStatus = $rawInput['new_status'] ?? $rawInput['status'] ?? null;
        if (!is_numeric($newStatus)) {
            throw new ValidationException('Lipseste noul status.');
        }

        return $this->service->updateStatus(
            $this->requireOrderId($rawInput),
            (int) $newStatus,
            $this->requireSourceType($rawInput),
            $userId
        );
    }

    /**
     * @param array<string, mixed> $rawInput
     */
    private function requireOrderId(array $rawInput): int
    {
        $orderId = $rawInput['order_id'] ?? $rawInput['idcomanda'] ?? null;
        if (!is_numeric($orderId) || (int) $orderId <= 0) {
            throw new ValidationException('ID-ul comenzii este invalid.');
        }

        return (int) $orderId;
    }

    /**
     * @param array<string, mixed> $rawInput
     */
    private function requireSourceType(array $rawInput): string
    {
        $source = $this->sanitizeText($rawInput['source_type'] ?? '');
        if ($source === '') {
            throw new ValidationException('Lipseste tipul comenzii (interna/externa).');
        }

        return $source;
    }

    /**
     * @param mixed $value
     */
    private function sanitizeText($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}

