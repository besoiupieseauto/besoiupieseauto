<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Cunoaștere progresivă — salut revenit, nume, ce caută (fără formular agresiv).
 */
final class ShopChatOnboardingService
{
    /**
     * Salut la deschidere chat.
     *
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $sessionState
     */
    public function buildInitGreeting(array $profile, array $sessionState, ?array $shopUser = null): string
    {
        $name = $this->resolveDisplayName($profile, $sessionState, $shopUser);

        if ($name !== '') {
            $first = trim(explode(' ', $name)[0]);

            return "Salut, {$first}! 👋 Cu ce te pot ajuta azi?";
        }

        return 'Salut! 👋 Cu ce te pot ajuta? Spune-mi ce piesă cauți — cod OEM, categorie sau nume.';
    }

    /** Chip-uri la deschidere — fără listă tehnică; apare după prima căutare. @return list<array{id:string,label:string}> */
    public function buildInitChips(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $sessionState
     * @return array{handled:bool,reply?:string,ui_action?:string,chips?:list<array{id:string,label:string}>}|null
     */
    public function tryHandleOnboardingMessage(
        string $message,
        array &$sessionState,
        array &$profile
    ): ?array {
        require_once dirname(__DIR__, 3) . '/robot/public_shop_chat.php';
        ShopChatSessionService::normalize($sessionState);
        $step = ShopChatSessionService::onboardingStep($sessionState);
        $name = $this->parseNameFromMessage($message);

        if ($name !== '' && $this->looksLikeNameOnlyMessage($message)) {
            ShopChatSessionService::setClientName($name, $sessionState);
            if (($profile['client_name'] ?? '') === '') {
                $profile['client_name'] = $name;
            }
            ShopChatSessionService::setOnboardingStep('ask_need', $sessionState);

            return [
                'handled' => true,
                'reply' => "Mulțumesc, {$name}! Cu ce te pot ajuta?",
                'ui_action' => 'none',
            ];
        }

        if ($step === 'welcome' && $this->isPureGreeting($message)) {
            ShopChatSessionService::setOnboardingStep('ask_name', $sessionState);
            $existing = $this->resolveDisplayName($profile, $sessionState, null);
            if ($existing !== '') {
                ShopChatSessionService::setOnboardingStep('done', $sessionState);

                return [
                    'handled' => true,
                    'reply' => "Salut, {$existing}! Cu ce te pot ajuta — ce piesă sau categorie cauți?",
                    'ui_action' => 'none',
                ];
            }

            return [
                'handled' => true,
                'reply' => "Salut! Cu ce te pot ajuta?",
                'ui_action' => 'none',
            ];
        }

        if ($step === 'ask_need' && $this->isShortNeedStatement($message)) {
            ShopChatSessionService::setOnboardingStep('done', $sessionState);

            return null;
        }

        if ($step !== 'done' && public_shop_chat_looks_like_product_query($message)) {
            ShopChatSessionService::setOnboardingStep('done', $sessionState);
        }

        return null;
    }

    /** @param array<string, mixed> $profile @param array<string, mixed> $sessionState */
    public function resolveDisplayName(array $profile, array $sessionState, ?array $shopUser): string
    {
        if ($shopUser !== null && ($shopUser['name'] ?? '') !== '') {
            return trim((string) $shopUser['name']);
        }
        $fromSession = ShopChatSessionService::clientName($sessionState);
        if ($fromSession !== '') {
            return $fromSession;
        }

        return trim((string) ($profile['client_name'] ?? ''));
    }

    public function parseNameFromMessage(string $message): string
    {
        $message = trim($message);
        if ($message === '' || mb_strlen($message) > 60) {
            return '';
        }

        if (preg_match('/\b(ma\s+numesc|mă\s+numesc|sunt|numele\s+(meu\s+)?(?:e|este))\s+(.+)/ui', $message, $m)) {
            return $this->cleanName($m[2] ?? $m[1] ?? '');
        }

        if (preg_match('/^([A-ZĂÂÎȘȚ][a-zăâîșț]+(?:\s+[A-ZĂÂÎȘȚ][a-zăâîșț]+){0,2})$/u', $message)) {
            return $this->cleanName($message);
        }

        return '';
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 40) {
            return '';
        }
        if (preg_match('/\b(filtru|frana|frână|ulei|rulment|comand|cod|oem|produse)\b/ui', $name)) {
            return '';
        }

        return $name;
    }

    private function looksLikeNameOnlyMessage(string $message): bool
    {
        if (public_shop_chat_looks_like_product_query($message)) {
            return false;
        }
        if (preg_match('/\d{5,}/', $message)) {
            return false;
        }

        return $this->parseNameFromMessage($message) !== '';
    }

    private function isPureGreeting(string $message): bool
    {
        return (bool) preg_match(
            '/^(salut|bun[aă]|hey|hello|hi|servus|noroc|buna\s+ziua)[\s!.,?]*$/ui',
            mb_strtolower(trim($message), 'UTF-8')
        );
    }

    private function isShortNeedStatement(string $message): bool
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');

        return (bool) preg_match('/\b(caut|vreau|am\s+nevoie|imi\s+trebuie|as\s+vrea)\b/u', $lower)
            && mb_strlen($message) < 80;
    }
}
