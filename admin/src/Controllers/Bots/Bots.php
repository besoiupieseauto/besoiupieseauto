<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Bots;

use Evasystem\Exceptions\ValidationException;

/**
 * Controller pentru roboți și token-uri.
 */
final class Bots
{
    private const FORBIDDEN_INPUT_KEYS = ['type_product', 'type', 'id', 'idusers', 'randomnid', 'usersveryfi', 'experiences'];
    private const ALLOWED_CHANNELS = ['manual', 'website', 'whatsapp', 'olx', 'pieseauto', 'dezro', 'facebook', 'email'];
    private const ALLOWED_TOKEN_STATUSES = ['active', 'expired', 'disabled'];
    private const ALLOWED_TOKEN_PLANS = ['free', 'paid'];
    private const ALLOWED_BOT_TYPES = ['message_sender', 'scraper', 'sync', 'ai_reply', 'notification'];

    private BotsService $botsService;

    public function __construct(BotsService $botsService)
    {
        $this->botsService = $botsService;
    }

    /** @param array<string, mixed> $rawInput */
    public function add(array $rawInput): array
    {
        return $this->botsService->createBot($this->buildBotPayload($rawInput, false));
    }

    /** @param array<string, mixed> $rawInput */
    public function update(array $rawInput): array
    {
        return $this->botsService->updateBot($this->requireRandomId($rawInput), $this->buildBotPayload($rawInput, true));
    }

    /** @param array<string, mixed> $rawInput */
    public function changeStatus(array $rawInput): void
    {
        $this->botsService->changeBotStatus(
            $this->requireRandomId($rawInput),
            $this->normalizeChoice($rawInput['token_status'] ?? null, self::ALLOWED_TOKEN_STATUSES, 'Statusul tokenului nu este valid.')
        );
    }

    /** @param array<string, mixed> $rawInput */
    public function test(array $rawInput): array
    {
        return $this->botsService->testBot($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput */
    public function delete(array $rawInput): void
    {
        $this->botsService->deleteBot($this->requireRandomId($rawInput));
    }

    /** @return array<int, array<string, mixed>> */
    public function list(array $rawInput = []): array
    {
        return $this->botsService->listBots($rawInput);
    }

    /** @param array<string, mixed> $rawInput @return array<string, mixed> */
    public function find(array $rawInput): array
    {
        return $this->botsService->findBot($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput @return array<string, string|int|null> */
    private function buildBotPayload(array $rawInput, bool $isUpdate): array
    {
        $payload = $this->sanitizePayload($rawInput);
        if (!$isUpdate || isset($payload['name'])) {
            $this->validateText($payload['name'] ?? null, 'Numele botului este obligatoriu.', 255);
        }
        foreach (['bot_type' => self::ALLOWED_BOT_TYPES, 'channel' => self::ALLOWED_CHANNELS, 'token_status' => self::ALLOWED_TOKEN_STATUSES, 'token_plan' => self::ALLOWED_TOKEN_PLANS] as $field => $allowed) {
            if (isset($payload[$field])) {
                $payload[$field] = $this->normalizeChoice($payload[$field], $allowed, "Câmpul {$field} nu este valid.");
            }
        }
        $payload['bot_type'] = $payload['bot_type'] ?? 'message_sender';
        $payload['channel'] = $payload['channel'] ?? 'manual';
        $payload['token_status'] = $payload['token_status'] ?? 'active';
        $payload['token_plan'] = $payload['token_plan'] ?? 'free';

        foreach (['webhook_url', 'test_url'] as $urlField) {
            if (isset($payload[$urlField]) && !filter_var($payload[$urlField], FILTER_VALIDATE_URL)) {
                throw new ValidationException("URL-ul {$urlField} nu este valid.");
            }
        }
        foreach (['requests_limit', 'requests_used'] as $intField) {
            if (isset($payload[$intField])) {
                if (!is_numeric($payload[$intField]) || (int) $payload[$intField] < 0) {
                    throw new ValidationException("Câmpul {$intField} trebuie să fie numeric pozitiv.");
                }
                $payload[$intField] = (int) $payload[$intField];
            }
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

    /** @param mixed $value @param array<int, string> $allowedValues */
    private function normalizeChoice($value, array $allowedValues, string $message): string
    {
        $choice = mb_strtolower(trim((string) $value));
        if (!in_array($choice, $allowedValues, true)) {
            throw new ValidationException($message);
        }
        return $choice;
    }

    /** @param array<string, mixed> $rawInput */
    private function requireRandomId(array $rawInput): int
    {
        $randomId = $rawInput['randomn_id'] ?? $rawInput['id'] ?? null;
        if (!is_numeric($randomId) || (int) $randomId <= 0) {
            throw new ValidationException('Lipsește identificatorul botului.');
        }
        return (int) $randomId;
    }
}
