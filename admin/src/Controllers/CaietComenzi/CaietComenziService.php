<?php

declare(strict_types=1);

namespace Evasystem\Controllers\CaietComenzi;

use Evasystem\Core\CaietComenzi\CaietComenziModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\ValidationException;

final class CaietComenziService
{
    private CaietComenziModel $model;

    public function __construct(CaietComenziModel $model)
    {
        $this->model = $model;
    }

    /**
     * @return array<string, int|float|string>
     */
    public function getStats(): array
    {
        return $this->model->getStats();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listOrders(array $filters): array
    {
        return $this->model->findOrders($filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrderDetails(int $orderId, string $sourceType): array
    {
        $this->assertSourceType($sourceType);

        $lines = $this->model->getOrderLines($orderId, $sourceType);

        $calculatedTotal = 0.0;
        foreach ($lines as $line) {
            $calculatedTotal += ((float) ($line['cantitate'] ?? 0)) * ((float) ($line['pret'] ?? 0));
        }

        return [
            'order_id' => $orderId,
            'source_type' => $sourceType,
            'lines' => $lines,
            'calculated_total' => round($calculatedTotal, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateStatus(int $orderId, int $newStatus, string $sourceType, int $userId): array
    {
        $this->assertSourceType($sourceType);

        if ($newStatus < 0 || $newStatus > 20) {
            throw new ValidationException('Statusul nou este invalid.');
        }

        $updated = $this->model->updateOrderStatus($orderId, $newStatus, $sourceType, $userId);
        if ($updated === null) {
            throw new NotFoundException('Comanda selectata nu exista.');
        }

        return $updated;
    }

    private function assertSourceType(string $sourceType): void
    {
        if (!in_array($sourceType, ['interna', 'externa'], true)) {
            throw new ValidationException('Tipul comenzii trebuie sa fie interna sau externa.');
        }
    }
}

