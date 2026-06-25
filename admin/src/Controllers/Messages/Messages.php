<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Messages;

use Evasystem\Exceptions\ValidationException;

/**
 * Controller pentru mesaje.
 */
final class Messages
{
    private const FORBIDDEN_INPUT_KEYS = ['type_product', 'type', 'id', 'idusers', 'randomnid', 'usersveryfi', 'experiences'];
    private const ALLOWED_DIRECTIONS = ['inbound', 'outbound'];
    private const ALLOWED_CHANNELS = ['manual', 'website', 'whatsapp', 'olx', 'pieseauto', 'dezro', 'facebook', 'email'];
    private const ALLOWED_DELIVERY_STATUSES = ['received', 'draft', 'queued', 'sent', 'delivered', 'failed'];
    private const ALLOWED_BOT_STATUSES = ['none', 'pending', 'handled', 'needs_human'];

    private MessagesService $messagesService;

    public function __construct(MessagesService $messagesService)
    {
        $this->messagesService = $messagesService;
    }

    /** @param array<string, mixed> $rawInput */
    public function add(array $rawInput): array
    {
        return $this->messagesService->createMessage($this->buildMessagePayload($rawInput));
    }

    /** @param array<string, mixed> $rawInput */
    public function markAsRead(array $rawInput): void
    {
        $this->messagesService->markAsRead($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput */
    public function delete(array $rawInput): void
    {
        $this->messagesService->deleteMessage($this->requireRandomId($rawInput));
    }

    /** @return array<int, array<string, mixed>> */
    public function list(array $rawInput = []): array
    {
        return $this->messagesService->listMessages($rawInput);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function conversations(array $rawInput = []): array
    {
        return $this->messagesService->listConversations($rawInput);
    }

    /** @param array<string, mixed> $rawInput @return array<int, array<string, mixed>> */
    public function conversation(array $rawInput): array
    {
        $conversationId = $rawInput['conversation_id'] ?? null;
        if (!is_numeric($conversationId) || (int) $conversationId <= 0) {
            throw new ValidationException('Lipsește identificatorul conversației.');
        }
        return $this->messagesService->listConversation((int) $conversationId);
    }

    /** @param array<string, mixed> $rawInput @return array<string, string|int|null> */
    private function buildMessagePayload(array $rawInput): array
    {
        $payload = $this->sanitizePayload($rawInput);
        $this->validateText($payload['name'] ?? null, 'Numele clientului este obligatoriu.', 255);
        $this->validateText($payload['message_body'] ?? null, 'Mesajul este obligatoriu.', 5000);

        if (isset($payload['direction'])) {
            $payload['direction'] = $this->normalizeChoice($payload['direction'], self::ALLOWED_DIRECTIONS, 'Direcția mesajului nu este validă.');
        } else {
            $payload['direction'] = 'outbound';
        }

        if (isset($payload['channel'])) {
            $payload['channel'] = $this->normalizeChoice($payload['channel'], self::ALLOWED_CHANNELS, 'Canalul mesajului nu este valid.');
        } else {
            $payload['channel'] = 'manual';
        }

        if (isset($payload['delivery_status'])) {
            $payload['delivery_status'] = $this->normalizeChoice($payload['delivery_status'], self::ALLOWED_DELIVERY_STATUSES, 'Statusul livrării mesajului nu este valid.');
        } else {
            $payload['delivery_status'] = $payload['direction'] === 'outbound' ? 'queued' : 'received';
        }

        if (isset($payload['bot_status'])) {
            $payload['bot_status'] = $this->normalizeChoice($payload['bot_status'], self::ALLOWED_BOT_STATUSES, 'Statusul robotului nu este valid.');
        } else {
            $payload['bot_status'] = $payload['direction'] === 'outbound' ? 'pending' : 'none';
        }

        if (isset($payload['email'])) {
            $payload['email'] = $this->normalizeEmail($payload['email']);
        }

        if (isset($payload['conversation_id'])) {
            if (!is_numeric($payload['conversation_id']) || (int) $payload['conversation_id'] <= 0) {
                throw new ValidationException('conversation_id nu este valid.');
            }
            $payload['conversation_id'] = (int) $payload['conversation_id'];
        }

        $payload['message_status'] = $payload['message_status'] ?? 'new';
        $payload['is_read'] = isset($payload['is_read']) ? (int) ((bool) $payload['is_read']) : 0;

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

    /** @param mixed $value @param array<int, string> $allowedValues */
    private function normalizeChoice($value, array $allowedValues, string $message): string
    {
        $choice = mb_strtolower(trim((string) $value));
        if (!in_array($choice, $allowedValues, true)) {
            throw new ValidationException($message);
        }
        return $choice;
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

    /** @param array<string, mixed> $rawInput */
    private function requireRandomId(array $rawInput): int
    {
        $randomId = $rawInput['randomn_id'] ?? $rawInput['id'] ?? null;
        if (!is_numeric($randomId) || (int) $randomId <= 0) {
            throw new ValidationException('Lipsește identificatorul mesajului.');
        }
        return (int) $randomId;
    }
}
