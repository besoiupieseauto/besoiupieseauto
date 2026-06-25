<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Bots;

use Evasystem\Core\Bots\BotsModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\PersistenceException;

/**
 * Logică de business pentru roboți.
 */
final class BotsService
{
    private BotsModel $botsModel;

    public function __construct(BotsModel $botsModel)
    {
        $this->botsModel = $botsModel;
    }

    /** @param array<string, string|int|null> $botPayload */
    public function createBot(array $botPayload): array
    {
        $randomId = $this->generateUniqueRandomId();
        $botPayload['randomn_id'] = $randomId;

        if (!$this->botsModel->insert($botPayload)) {
            throw new PersistenceException('Botul nu a putut fi salvat.');
        }

        return ['randomn_id' => $randomId];
    }

    /** @param array<string, string|int|null> $botPayload */
    public function updateBot(int $randomId, array $botPayload): array
    {
        $this->ensureBotExists($randomId);
        if (!$this->botsModel->updateByRandomId($randomId, $botPayload)) {
            throw new PersistenceException('Botul nu a putut fi actualizat.');
        }
        return ['randomn_id' => $randomId];
    }

    public function changeBotStatus(int $randomId, string $tokenStatus): void
    {
        $this->ensureBotExists($randomId);
        if (!$this->botsModel->updateByRandomId($randomId, ['token_status' => $tokenStatus])) {
            throw new PersistenceException('Statusul botului nu a putut fi actualizat.');
        }
    }

    public function deleteBot(int $randomId): void
    {
        $this->ensureBotExists($randomId);
        if (!$this->botsModel->deleteByRandomId($randomId)) {
            throw new PersistenceException('Botul nu a putut fi șters.');
        }
    }

    /** @return array<string, string|null> */
    public function testBot(int $randomId): array
    {
        $bot = $this->botsModel->findByRandomId($randomId);
        if ($bot === null) {
            throw new NotFoundException('Botul cerut nu există.');
        }

        $status = 'success';
        $message = $this->validateBotLocally($bot);

        if ($message === 'OK' && !empty($bot['test_url'])) {
            $message = $this->testUrl((string) $bot['test_url']);
        }

        if ($message !== 'OK') {
            $status = 'failed';
        }

        $this->botsModel->updateByRandomId($randomId, [
            'last_test_status' => $status,
            'last_test_message' => $message,
            'last_test_at' => date('Y-m-d H:i:s'),
        ]);

        return ['last_test_status' => $status, 'last_test_message' => $message];
    }

    /** @return array<int, array<string, mixed>> */
    public function listBots(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 10)));
        return $this->botsModel->findPaginated($page, $perPage, $params);
    }

    /** @return array<string, mixed> */
    public function findBot(int $randomId): array
    {
        $bot = $this->botsModel->findByRandomId($randomId);
        if ($bot === null) {
            throw new NotFoundException('Botul cerut nu exista.');
        }
        return $bot;
    }

    /** @param array<string, mixed> $bot */
    private function validateBotLocally(array $bot): string
    {
        if (($bot['token_status'] ?? '') !== 'active') {
            return 'Tokenul nu este activ.';
        }
        if (empty($bot['token_value'])) {
            return 'Tokenul lipsește.';
        }
        if (!empty($bot['starts_at']) && strtotime((string) $bot['starts_at']) > time()) {
            return 'Tokenul nu este încă în perioada de start.';
        }
        if (!empty($bot['ends_at']) && strtotime((string) $bot['ends_at']) < time()) {
            return 'Tokenul este expirat.';
        }
        if (!empty($bot['requests_limit']) && (int) ($bot['requests_used'] ?? 0) >= (int) $bot['requests_limit']) {
            return 'Limita de request-uri a fost atinsă.';
        }
        return 'OK';
    }

    private function testUrl(string $testUrl): string
    {
        if (!filter_var($testUrl, FILTER_VALIDATE_URL)) {
            return 'URL-ul de test nu este valid.';
        }

        $context = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 6],
        ]);
        $result = @file_get_contents($testUrl, false, $context);
        if ($result === false) {
            return 'Test HTTP eșuat sau blocat de server.';
        }
        return 'OK';
    }

    private function ensureBotExists(int $randomId): void
    {
        if (!$this->botsModel->existsByRandomId($randomId)) {
            throw new NotFoundException('Botul cerut nu există.');
        }
    }

    private function generateUniqueRandomId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = random_int(600000, 999999);
            if (!$this->botsModel->existsByRandomId($candidate)) {
                return $candidate;
            }
        }
        throw new PersistenceException('Nu am reușit să generez un randomn_id unic pentru bot.');
    }
}
