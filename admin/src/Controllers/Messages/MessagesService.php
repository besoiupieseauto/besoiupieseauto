<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Messages;

use Evasystem\Core\Messages\MessagesModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\PersistenceException;

/**
 * Logică de business pentru mesaje.
 */
final class MessagesService
{
    private MessagesModel $messagesModel;

    public function __construct(MessagesModel $messagesModel)
    {
        $this->messagesModel = $messagesModel;
    }

    /** @param array<string, string|int|null> $messagePayload */
    public function createMessage(array $messagePayload): array
    {
        $randomId = $this->generateUniqueRandomId();
        $messagePayload['randomn_id'] = $randomId;
        $messagePayload['conversation_id'] = $messagePayload['conversation_id'] ?? $randomId;

        if (!$this->messagesModel->insert($messagePayload)) {
            throw new PersistenceException('Mesajul nu a putut fi salvat.');
        }

        return ['randomn_id' => $randomId, 'conversation_id' => (int) $messagePayload['conversation_id']];
    }

    public function markAsRead(int $randomId): void
    {
        $this->ensureMessageExists($randomId);
        if (!$this->messagesModel->updateByRandomId($randomId, ['is_read' => 1, 'message_status' => 'read'])) {
            throw new PersistenceException('Mesajul nu a putut fi marcat ca citit.');
        }
    }

    public function deleteMessage(int $randomId): void
    {
        $this->ensureMessageExists($randomId);
        if (!$this->messagesModel->deleteByRandomId($randomId)) {
            throw new PersistenceException('Mesajul nu a putut fi șters.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listMessages(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 10)));
        return $this->messagesModel->findPaginated($page, $perPage, $params);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function listConversations(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 10)));

        return $this->messagesModel->findConversationsPaginated($page, $perPage, $params);
    }

    /** @return array<int, array<string, mixed>> */
    public function listConversation(int $conversationId): array
    {
        return $this->messagesModel->findByConversationId($conversationId);
    }

    private function ensureMessageExists(int $randomId): void
    {
        if (!$this->messagesModel->existsByRandomId($randomId)) {
            throw new NotFoundException('Mesajul cerut nu există.');
        }
    }

    private function generateUniqueRandomId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = random_int(500000, 999999);
            if (!$this->messagesModel->existsByRandomId($candidate)) {
                return $candidate;
            }
        }
        throw new PersistenceException('Nu am reușit să generez un randomn_id unic pentru mesaj.');
    }
}
