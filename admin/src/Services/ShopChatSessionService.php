<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Stare sesiune chat magazin — onboarding, căutare, vehicul, quiz comandă.
 */
final class ShopChatSessionService
{
    /** @return array<string, mixed> */
    public static function defaultState(): array
    {
        return [
            'last_products' => [],
            'last_plan' => null,
            'cart' => [],
            'onboarding_step' => 'welcome',
            'client_name' => '',
            'active_filters' => [],
            'last_search_label' => '',
            'vehicle' => null,
            'quiz' => null,
        ];
    }

    /** @param array<string, mixed> $state */
    public static function normalize(array &$state): void
    {
        $state = array_merge(self::defaultState(), $state);
        if (!is_array($state['last_products'])) {
            $state['last_products'] = [];
        }
        if (!is_array($state['active_filters'])) {
            $state['active_filters'] = [];
        }
        if (!is_array($state['cart'])) {
            $state['cart'] = [];
        }
    }

    /** @param list<array<string, mixed>> $products @param array<string, mixed> $state */
    public static function storeSearchResults(array $products, string $label, array &$state, bool $resetFilters = true): void
    {
        self::normalize($state);
        $state['last_products'] = $products;
        $state['last_search_label'] = $label;
        if ($resetFilters) {
            $state['active_filters'] = [];
        }
    }

    /** @param array<string, mixed> $filter @param array<string, mixed> $state */
    public static function pushFilter(array $filter, array &$state): void
    {
        self::normalize($state);
        $state['active_filters'][] = $filter;
    }

    /** @param array<string, mixed>|null $vehicle @param array<string, mixed> $state */
    public static function setVehicle(?array $vehicle, array &$state): void
    {
        self::normalize($state);
        $state['vehicle'] = $vehicle;
    }

    /** @param array<string, mixed> $state @return array<string, mixed>|null */
    public static function getVehicle(array $state): ?array
    {
        $v = $state['vehicle'] ?? null;

        return is_array($v) && ($v['label'] ?? '') !== '' ? $v : null;
    }

    /** @param array<string, mixed> $state */
    public static function onboardingStep(array $state): string
    {
        return (string) ($state['onboarding_step'] ?? 'welcome');
    }

    /** @param array<string, mixed> $state */
    public static function setOnboardingStep(string $step, array &$state): void
    {
        self::normalize($state);
        $state['onboarding_step'] = $step;
    }

    /** @param array<string, mixed> $state */
    public static function clientName(array $state): string
    {
        return trim((string) ($state['client_name'] ?? ''));
    }

    /** @param array<string, mixed> $state */
    public static function setClientName(string $name, array &$state): void
    {
        self::normalize($state);
        $state['client_name'] = trim($name);
    }

    /** @param array<string, mixed> $state @return array<string, mixed>|null */
    public static function getQuiz(array $state): ?array
    {
        $q = $state['quiz'] ?? null;

        return is_array($q) && ($q['step'] ?? '') !== '' ? $q : null;
    }

    /** @param array<string, mixed>|null $quiz @param array<string, mixed> $state */
    public static function setQuiz(?array $quiz, array &$state): void
    {
        self::normalize($state);
        $state['quiz'] = $quiz;
    }
}
