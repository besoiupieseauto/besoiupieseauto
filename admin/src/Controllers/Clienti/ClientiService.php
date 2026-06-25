<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Clienti;

use Evasystem\Core\Clienti\ClientiModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\PersistenceException;

/**
 * Logică de business pentru clienți.
 */
final class ClientiService
{
    private ClientiModel $clientiModel;

    public function __construct(ClientiModel $clientiModel)
    {
        $this->clientiModel = $clientiModel;
    }

    /**
     * Creează un client cu randomn_id unic.
     *
     * @param array<string, string|int|float|null> $clientPayload
     * @return array{randomn_id: int}
     */
    public function createClient(array $clientPayload): array
    {
        $randomId = $this->generateUniqueRandomId();
        $clientPayload['randomn_id'] = $randomId;

        if (!$this->clientiModel->insert($clientPayload)) {
            throw new PersistenceException('Clientul nu a putut fi salvat.');
        }

        return ['randomn_id' => $randomId];
    }

    /**
     * Actualizează un client existent.
     *
     * @param array<string, string|int|float|null> $clientPayload
     * @return array{randomn_id: int}
     */
    public function updateClient(int $randomId, array $clientPayload): array
    {
        $this->ensureClientExists($randomId);

        if (!$this->clientiModel->updateByRandomId($randomId, $clientPayload)) {
            throw new PersistenceException('Clientul nu a putut fi actualizat.');
        }

        return ['randomn_id' => $randomId];
    }

    /**
     * Schimbă statusul clientului.
     */
    public function changeClientStatus(int $randomId, string $status): void
    {
        $this->ensureClientExists($randomId);

        if (!$this->clientiModel->updateStatusByRandomId($randomId, $status)) {
            throw new PersistenceException('Statusul clientului nu a putut fi actualizat.');
        }
    }

    /**
     * Șterge un client.
     */
    public function deleteClient(int $randomId): void
    {
        $this->ensureClientExists($randomId);

        if (!$this->clientiModel->deleteByRandomId($randomId)) {
            throw new PersistenceException('Clientul nu a putut fi șters.');
        }
    }

    /**
     * Listează clienții.
     *
     * @return array<int, array<string, string|null>>
     */
    public function listClients(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 10)));

        return $this->clientiModel->findPaginated($page, $perPage, $params);
    }

    /**
     * Verifică existența înaintea operațiilor destructive.
     */
    private function ensureClientExists(int $randomId): void
    {
        if (!$this->clientiModel->existsByRandomId($randomId)) {
            throw new NotFoundException('Clientul cerut nu există.');
        }
    }

    /**
     * Generează un randomn_id unic.
     */
    private function generateUniqueRandomId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = random_int(100000, 999999);
            if (!$this->clientiModel->existsByRandomId($candidate)) {
                return $candidate;
            }
        }

        throw new PersistenceException('Nu am reușit să generez un randomn_id unic.');
    }
}
