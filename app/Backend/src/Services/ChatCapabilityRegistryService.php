<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Registry capabilități chat — sursă unică module admin 1:1.
 */
final class ChatCapabilityRegistryService
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /** @return array<string, mixed> */
    public function registry(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = dirname(__DIR__, 2) . '/config/chat_capability_registry.php';
        if (!is_file($path)) {
            self::$cache = ['version' => '0', 'modules' => []];

            return self::$cache;
        }

        $data = require $path;
        self::$cache = is_array($data) ? $data : ['version' => '0', 'modules' => []];

        return self::$cache;
    }

    /** @return list<array<string, mixed>> */
    public function allCapabilities(): array
    {
        $out = [];
        foreach ($this->registry()['modules'] ?? [] as $moduleKey => $module) {
            if (!is_array($module)) {
                continue;
            }
            foreach ($module['capabilities'] ?? [] as $cap) {
                if (!is_array($cap)) {
                    continue;
                }
                $cap['module'] = (string) $moduleKey;
                $cap['module_label'] = (string) ($module['label'] ?? $moduleKey);
                $cap['module_url'] = (string) ($module['url'] ?? '/admin/product');
                $out[] = $cap;
            }
        }

        return $out;
    }

    /** @return array{live:int,navigate_only:int,planned:int,total:int,modules:int} */
    public function stats(): array
    {
        $live = 0;
        $nav = 0;
        $planned = 0;
        foreach ($this->allCapabilities() as $cap) {
            $status = (string) ($cap['status'] ?? 'live');
            if ($status === 'live') {
                ++$live;
            } elseif ($status === 'navigate_only') {
                ++$nav;
            } else {
                ++$planned;
            }
        }

        return [
            'live' => $live,
            'navigate_only' => $nav,
            'planned' => $planned,
            'total' => $live + $nav + $planned,
            'modules' => count($this->registry()['modules'] ?? []),
        ];
    }

    /**
     * @return array{capability:array<string,mixed>,score:float,mode:string}|null
     */
    public function match(string $message, ?string $section = null): ?array
    {
        $message = SectionAssistantQueryHelper::normalizeComposerTypos(trim($message));
        if ($message === '') {
            return null;
        }

        $lower = mb_strtolower($message, 'UTF-8');
        $productMode = SectionAssistantQueryHelper::resolveProductQueryMode($message);
        $best = null;
        $bestScore = 0.0;

        foreach ($this->allCapabilities() as $cap) {
            $score = $this->scoreCapability($lower, $message, $cap, $section, $productMode);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $cap;
            }
        }

        if ($best === null || $bestScore < 2.5) {
            return null;
        }

        return [
            'capability' => $best,
            'score' => $bestScore,
            'mode' => $this->capabilityMode($best, $productMode),
        ];
    }

    /**
     * @param array<string, mixed> $cap
     * @return array<string, mixed>
     */
    public function capabilityForUi(array $cap): array
    {
        return [
            'id' => (string) ($cap['id'] ?? ''),
            'module' => (string) ($cap['module'] ?? ''),
            'module_label' => (string) ($cap['module_label'] ?? ''),
            'label' => (string) ($cap['label'] ?? ''),
            'kind' => (string) ($cap['kind'] ?? ''),
            'status' => (string) ($cap['status'] ?? 'live'),
            'examples' => array_values(array_filter((array) ($cap['examples'] ?? []))),
            'url' => (string) ($cap['url'] ?? $cap['module_url'] ?? '/admin/product'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function modulesForUi(?string $section = null): array
    {
        $registry = $this->registry();
        $modules = $registry['modules'] ?? [];
        $out = [];

        foreach ($modules as $key => $module) {
            if (!is_array($module)) {
                continue;
            }
            if ($section !== null && $section !== '' && $section !== 'all') {
                $allowed = SectionAssistantAdminCatalog::forSection($section);
                if (!isset($allowed[$key]) && $key !== 'site_public') {
                    continue;
                }
            }

            $caps = [];
            foreach ($module['capabilities'] ?? [] as $cap) {
                if (is_array($cap)) {
                    $cap['module'] = (string) $key;
                    $caps[] = $this->capabilityForUi($cap);
                }
            }

            $out[] = [
                'key' => (string) $key,
                'label' => (string) ($module['label'] ?? $key),
                'url' => (string) ($module['url'] ?? '/admin/product'),
                'capabilities' => $caps,
                'counts' => [
                    'live' => count(array_filter($caps, static fn ($c) => ($c['status'] ?? '') === 'live')),
                    'total' => count($caps),
                ],
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $cap */
    private function scoreCapability(string $lower, string $rawMessage, array $cap, ?string $section, ?string $productMode): float
    {
        $score = 0.0;

        if ($section !== null && $section !== '' && $section !== 'all') {
            $moduleKey = (string) ($cap['module'] ?? '');
            $allowed = SectionAssistantAdminCatalog::forSection($section);
            if (isset($allowed[$moduleKey])) {
                $score += 0.5;
            } elseif ($moduleKey !== 'site_public') {
                $score -= 0.3;
            }
        }

        foreach ((array) ($cap['examples'] ?? []) as $example) {
            $ex = mb_strtolower(trim((string) $example), 'UTF-8');
            if ($ex !== '' && str_contains($lower, $ex)) {
                $score += 5.0;
            }
        }

        foreach ((array) ($cap['patterns'] ?? []) as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }
            $regex = '/' . str_replace('/', '\/', $pattern) . '/iu';
            if (@preg_match($regex, $lower)) {
                $score += 2.0;
            } elseif (str_contains($lower, mb_strtolower($pattern, 'UTF-8'))) {
                $score += 1.0;
            }
        }

        $handler = (string) ($cap['handler'] ?? '');
        if ($productMode === 'list' && $handler === 'product_list') {
            $score += 4.0;
        }
        if ($productMode === 'summary' && $handler === 'product_inventory') {
            $score += 4.0;
        }
        if ($productMode === 'filter' && $handler === 'product_filter') {
            $score += 4.0;
        }
        if ($productMode === 'code' && $handler === 'product_by_code') {
            $score += 4.0;
        }

        if (SectionAssistantActionService::messageLooksLikeAction($rawMessage) && ($cap['kind'] ?? '') === 'action') {
            $score += 2.5;
        }

        return $score;
    }

    /** @param array<string, mixed> $cap */
    private function capabilityMode(array $cap, ?string $productMode): string
    {
        return match ((string) ($cap['kind'] ?? '')) {
            'query' => 'read',
            'action' => 'write',
            'navigate' => 'navigate',
            default => 'unknown',
        };
    }
}
