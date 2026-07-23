<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Phase1;

use Besoiu\Services\AiActionEventService;

/**
 * Faza 1 — înregistrare bogată acțiuni admin (add/update/delete/import/eroare).
 */
final class AdminEventBridge
{
    /** @var list<string> */
    private const KNOWN_MODULES = [
        'produse', 'import', 'categorii', 'furnizori', 'export', 'scraper',
        'settings', 'bots', 'orders', 'comunicare', 'marketplace', 'backup',
        'crud', 'users', 'blog', 'website', 'scan', 'cron',
    ];

    public function __construct(
        private ?AiActionEventService $events = null,
    ) {
        $this->events = $events ?? new AiActionEventService();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $meta
     */
    public function recordSuccess(
        string $module,
        array $payload,
        int $httpStatus = 200,
        string $actionHint = '',
        array $meta = []
    ): void {
        if ($httpStatus < 200 || $httpStatus >= 300 || empty($payload['success'])) {
            return;
        }

        $this->emit('admin', $this->resolveAction($module, $payload, $actionHint), $module, $payload, $meta, false);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $meta
     */
    public function recordFailure(
        string $module,
        array $payload,
        int $httpStatus = 500,
        string $actionHint = 'admin_error',
        array $meta = []
    ): void {
        $action = $actionHint !== '' ? $actionHint : 'admin_error';
        $this->emit('admin', $action, $module, $payload, array_merge($meta, [
            'http_status' => $httpStatus,
            'error' => true,
        ]), true);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $meta
     */
    public function recordException(
        string $module,
        string $action,
        \Throwable $exception,
        array $meta = []
    ): void {
        $this->emit('admin', 'admin_error', $module, [
            'success' => false,
            'message' => $exception->getMessage(),
        ], array_merge($meta, [
            'action' => $action,
            'exception' => get_class($exception),
            'error' => true,
        ]), true);
    }

    public function inferModuleFromRequest(): string
    {
        $uri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));
        foreach (self::KNOWN_MODULES as $mod) {
            if (str_contains($uri, '/' . $mod) || str_contains($uri, $mod . '.php')) {
                return $mod;
            }
        }
        if (str_contains($uri, 'categorii')) {
            return 'categorii';
        }
        if (str_contains($uri, 'furnizor')) {
            return 'furnizori';
        }
        if (str_contains($uri, 'import')) {
            return 'import';
        }

        return 'admin';
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $meta
     */
    private function emit(
        string $actorType,
        string $action,
        string $module,
        array $payload,
        array $meta,
        bool $isError
    ): void {
        $subject = $this->buildSubject($module, $payload, $action);
        $actorId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;

        $eventMeta = array_merge([
            'module' => $module,
            'entity' => $this->inferEntity($module),
            'message' => (string) ($payload['message'] ?? ''),
            'route' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            'success' => !$isError && !empty($payload['success']),
        ], $this->extractIds($payload), $meta);

        if ($isError) {
            $eventMeta['severity'] = 'error';
        }

        $this->events->record($actorType, $action, $subject, $eventMeta, $actorId);

        $helper = dirname(__DIR__, 5) . '/system/ai_learning.php';
        if (is_file($helper)) {
            require_once $helper;
            if (function_exists('ai_learning_touch_admin')) {
                ai_learning_touch_admin($module, $action, $isError);
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function resolveAction(string $module, array $payload, string $hint): string
    {
        if ($hint !== '') {
            return $hint;
        }
        $helper = dirname(__DIR__, 5) . '/system/ai_learning.php';
        if (is_file($helper)) {
            require_once $helper;
            if (function_exists('ai_admin_infer_action')) {
                return ai_admin_infer_action($module, $payload);
            }
        }
        if (isset($payload['type_product'])) {
            return (string) $payload['type_product'];
        }

        return $module . '_action';
    }

    /** @param array<string, mixed> $payload */
    private function buildSubject(string $module, array $payload, string $action): string
    {
        $parts = [$module, $action];
        foreach (['id', 'randomn_id', 'pCode', 'name', 'count'] as $key) {
            if (isset($payload[$key]) && (string) $payload[$key] !== '') {
                $parts[] = $key . '=' . mb_substr((string) $payload[$key], 0, 80);
                break;
            }
        }
        $msg = trim((string) ($payload['message'] ?? ''));
        if ($msg !== '' && !isset($payload['id'])) {
            $parts[] = mb_substr($msg, 0, 100);
        }

        return implode(' · ', $parts);
    }

    private function inferEntity(string $module): string
    {
        return match ($module) {
            'produse', 'import', 'addproduse', 'editproduse' => 'product',
            'categorii' => 'category',
            'furnizori', 'supplier' => 'supplier',
            'export' => 'export',
            'scraper' => 'scraper',
            'orders' => 'order',
            'bots', 'comunicare' => 'bot',
            'settings' => 'settings',
            default => 'resource',
        };
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function extractIds(array $payload): array
    {
        $out = [];
        foreach (['id', 'randomn_id', 'pCode', 'count', 'imported', 'deleted', 'mode'] as $k) {
            if (array_key_exists($k, $payload) && $payload[$k] !== null && $payload[$k] !== '') {
                $out[$k] = $payload[$k];
            }
        }

        return $out;
    }
}
