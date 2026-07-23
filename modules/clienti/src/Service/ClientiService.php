<?php
declare(strict_types=1);

namespace Besoiu\Modules\Clienti\Service;

use Besoiu\Exceptions\NotFoundException;
use Besoiu\Exceptions\PersistenceException;
use Besoiu\Modules\Clienti\Model\ClientiRepository;

final class ClientiService
{
    public function __construct(
        private readonly ClientiRepository $clientiRepository = new ClientiRepository(),
    ) {
    }

    /** @param array<string, string|int|float|null> $clientPayload */
    public function createClient(array $clientPayload): array
    {
        $randomId = $this->generateUniqueRandomId();
        $clientPayload['randomn_id'] = $randomId;

        if (!$this->clientiRepository->insert($clientPayload)) {
            throw new PersistenceException('Clientul nu a putut fi salvat.');
        }

        return ['randomn_id' => $randomId];
    }

    /** @param array<string, string|int|float|null> $clientPayload */
    public function updateClient(int $randomId, array $clientPayload): array
    {
        $this->ensureClientExists($randomId);

        if (!$this->clientiRepository->updateByRandomId($randomId, $clientPayload)) {
            throw new PersistenceException('Clientul nu a putut fi actualizat.');
        }

        return ['randomn_id' => $randomId];
    }

    public function changeClientStatus(int $randomId, string $status): void
    {
        $this->ensureClientExists($randomId);

        if (!$this->clientiRepository->updateStatusByRandomId($randomId, $status)) {
            throw new PersistenceException('Statusul clientului nu a putut fi actualizat.');
        }
    }

    public function deleteClient(int $randomId): void
    {
        $this->ensureClientExists($randomId);

        if (!$this->clientiRepository->deleteByRandomId($randomId)) {
            throw new PersistenceException('Clientul nu a putut fi șters.');
        }
    }

    /** @return array{items:array<int,array<string,string|null>>,total:int,page:int,per_page:int,total_pages:int} */
    public function listClients(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 10)));

        return $this->clientiRepository->findPaginated($page, $perPage, $params);
    }

    private function ensureClientExists(int $randomId): void
    {
        if (!$this->clientiRepository->existsByRandomId($randomId)) {
            throw new NotFoundException('Clientul cerut nu există.');
        }
    }

    private function generateUniqueRandomId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = random_int(100000, 999999);
            if (!$this->clientiRepository->existsByRandomId($candidate)) {
                return $candidate;
            }
        }

        throw new PersistenceException('Nu am reușit să generez un randomn_id unic.');
    }
}
