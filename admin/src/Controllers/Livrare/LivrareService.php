<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Livrare;

use Evasystem\Core\Livrare\LivrareModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\PersistenceException;

/**
 * Logică de business pentru livrări.
 */
final class LivrareService
{
    private LivrareModel $livrareModel;

    public function __construct(LivrareModel $livrareModel)
    {
        $this->livrareModel = $livrareModel;
    }

    /** @param array<string, string|int|float|null> $deliveryPayload */
    public function createDelivery(array $deliveryPayload): array
    {
        $randomId = $this->generateUniqueRandomId();
        $awb = $deliveryPayload['awb'] ?? ('AWB-' . $randomId);
        $deliveryPayload['randomn_id'] = $randomId;
        $deliveryPayload['awb'] = $awb;

        if (!$this->livrareModel->insert($deliveryPayload)) {
            throw new PersistenceException('Livrarea nu a putut fi salvată.');
        }

        return ['randomn_id' => $randomId, 'awb' => (string) $awb];
    }

    /** @param array<string, string|int|float|null> $deliveryPayload */
    public function updateDelivery(int $randomId, array $deliveryPayload): array
    {
        $this->ensureDeliveryExists($randomId);
        if (!$this->livrareModel->updateByRandomId($randomId, $deliveryPayload)) {
            throw new PersistenceException('Livrarea nu a putut fi actualizată.');
        }
        return ['randomn_id' => $randomId];
    }

    public function changeDeliveryStatus(int $randomId, string $deliveryStatus): void
    {
        $this->ensureDeliveryExists($randomId);
        if (!$this->livrareModel->updateDeliveryStatusByRandomId($randomId, $deliveryStatus)) {
            throw new PersistenceException('Statusul livrării nu a putut fi actualizat.');
        }
    }

    public function deleteDelivery(int $randomId): void
    {
        $this->ensureDeliveryExists($randomId);
        if (!$this->livrareModel->deleteByRandomId($randomId)) {
            throw new PersistenceException('Livrarea nu a putut fi ștearsă.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listDeliveries(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 10)));
        return $this->livrareModel->findPaginated($page, $perPage, $params);
    }

    private function ensureDeliveryExists(int $randomId): void
    {
        if (!$this->livrareModel->existsByRandomId($randomId)) {
            throw new NotFoundException('Livrarea cerută nu există.');
        }
    }

    private function generateUniqueRandomId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = random_int(400000, 999999);
            if (!$this->livrareModel->existsByRandomId($candidate)) {
                return $candidate;
            }
        }
        throw new PersistenceException('Nu am reușit să generez un randomn_id unic pentru livrare.');
    }
}
